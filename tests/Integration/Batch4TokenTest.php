<?php
/**
 * Batch 4 DB-backed tests: ESI token encryption at rest and refresh-token
 * persistence.
 *
 * Same disposable-DB contract as the other batch tests: needs
 * PATHFINDER_TEST_DB_*, skips without a DB, truncates only fixture tables.
 */

namespace Tests\Integration;

use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Lib\Crypto\TokenCrypto;
use Exodus4D\Pathfinder\Model\Pathfinder as PF;
use PHPUnit\Framework\TestCase;

class Batch4TokenTest extends TestCase
{
    private static $f3;
    private static $cryptoKey;

    private const PF_MODELS = [
        'RightModel', 'RoleModel',
        'UserModel', 'CharacterModel', 'UserCharacterModel',
        'CharacterLogModel', 'CharacterAuthenticationModel', 'ActivityLogModel',
    ];

    public static function setUpBeforeClass(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        require_once __DIR__ . '/../../vendor/autoload.php';

        self::$f3 = \Base::instance();
        self::$f3->set('DEBUG', 0);
        self::$f3->set('NAMESPACE', 'Exodus4D\\Pathfinder');
        self::$f3->config(__DIR__ . '/../../app/config.ini', true);
        Config::instance(self::$f3);
        self::$f3->set('TESTING', true);

        if (!self::$f3->DB->getDB('PF')) {
            self::$f3 = null;
            return;
        }

        $pf = self::$f3->DB->getDB('PF');
        foreach (self::PF_MODELS as $model) {
            $class = Config::withNamespace('Model\\Pathfinder\\' . $model);
            $class::setup($pf);
        }
        // upgrade path: pre-encryption schemas created esiRefreshToken as
        // VARCHAR256, which cannot hold ciphertext. Fresh installs get TEXT
        // from fieldConf; existing installs must widen (setup column fix or
        // manual ALTER) - apply the same widening here for a stale test DB.
        $pf->exec('ALTER TABLE `character` MODIFY `esiRefreshToken` TEXT');

        // per-test-run encryption key (mirrors the per-instance Secret)
        self::$cryptoKey = TokenCrypto::generateKey();
        self::$f3->set('ENVIRONMENT.ESI_CRYPTO_KEY', self::$cryptoKey);
        self::$f3->clear('ENVIRONMENT.ESI_CRYPTO_KEY_OLD');
    }

    protected function tearDown(): void
    {
        self::$f3->set('ENVIRONMENT.ESI_CRYPTO_KEY', self::$cryptoKey);
        self::$f3->clear('ENVIRONMENT.ESI_CRYPTO_KEY_OLD');
        parent::tearDown();
    }

    private static function dbAvailable(): bool
    {
        if (self::$f3 === null) {
            self::markTestSkipped('No test database reachable (set PATHFINDER_TEST_DB_*).');
        }
        return true;
    }

    private function newCharacter(string $name): object
    {
        // fresh row per test: wipe own fixtures only
        $db = self::$f3->DB->getDB('PF');
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['character', 'user_character', 'character_log', 'character_authentication', 'activity_log'] as $table) {
            try {
                $db->exec('TRUNCATE TABLE `' . $table . '`');
            } catch (\Throwable $e) {
            }
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $char = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $char->name = $name;
        $char->ownerHash = 'batch4-' . $name;
        $char->save();
        return $char;
    }

    private function rawRow(int $id): array
    {
        $rows = self::$f3->DB->getDB('PF')->exec(
            'SELECT esiAccessToken, esiRefreshToken FROM `character` WHERE id = ' . $id
        );
        return (array)reset($rows);
    }

    public function testTokensStoredEncrypted(): void
    {
        self::dbAvailable();
        $char = $this->newCharacter('enc-rest');
        $char->esiAccessToken = 'plain-access-123';
        $char->esiRefreshToken = 'plain-refresh-456';
        $char->save();

        $raw = $this->rawRow((int)$char->_id);
        $this->assertStringStartsWith('v1:', $raw['esiAccessToken']);
        $this->assertStringStartsWith('v1:', $raw['esiRefreshToken']);
        $this->assertStringNotContainsString('plain-access-123', $raw['esiAccessToken']);
        $this->assertStringNotContainsString('plain-refresh-456', $raw['esiRefreshToken']);
    }

    public function testTokensReadBackDecrypted(): void
    {
        self::dbAvailable();
        $char = $this->newCharacter('enc-read');
        $char->esiAccessToken = 'access-abc';
        $char->esiRefreshToken = 'refresh-def';
        $char->save();
        $id = (int)$char->_id;

        $reloaded = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $reloaded->getById($id, 0);
        $this->assertSame('access-abc', $reloaded->esiAccessToken);
        $this->assertSame('refresh-def', $reloaded->esiRefreshToken);
    }

    public function testRefreshTokenColumnFitsCiphertext(): void
    {
        self::dbAvailable();
        // a long refresh token must survive the encrypt round-trip in storage
        $long = str_repeat('r', 400);
        $char = $this->newCharacter('enc-long');
        $char->esiRefreshToken = $long;
        $char->save();

        $reloaded = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $reloaded->getById((int)$char->_id, 0);
        $this->assertSame($long, $reloaded->esiRefreshToken);
    }

    public function testLegacyPlaintextRowMigratesOnWrite(): void
    {
        self::dbAvailable();
        $char = $this->newCharacter('enc-legacy');
        $id = (int)$char->_id;
        // simulate a pre-encryption row written directly
        self::$f3->DB->getDB('PF')->exec(
            "UPDATE `character` SET esiAccessToken = 'legacy-access', esiRefreshToken = 'legacy-refresh' WHERE id = " . $id
        );

        $reloaded = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $reloaded->getById($id, 0);
        // reads through unchanged (no lockout on upgrade)
        $this->assertSame('legacy-access', $reloaded->esiAccessToken);
        $this->assertSame('legacy-refresh', $reloaded->esiRefreshToken);

        // next token write encrypts the row
        $reloaded->applyRefreshedTokens('new-access', date('Y-m-d H:i:s', time() + 1200), null);
        $reloaded->save();
        $raw = $this->rawRow($id);
        $this->assertStringStartsWith('v1:', $raw['esiAccessToken']);
        $this->assertStringStartsWith('v1:', $raw['esiRefreshToken']);
        $this->assertSame('legacy-refresh', $reloaded->esiRefreshToken);
    }

    public function testRotatingRefreshTokenPersisted(): void
    {
        self::dbAvailable();
        $char = $this->newCharacter('enc-rotate');
        $char->esiAccessToken = 'old-access';
        $char->esiRefreshToken = 'old-refresh';
        $char->save();

        $char->applyRefreshedTokens('new-access', date('Y-m-d H:i:s', time() + 1200), 'new-refresh');
        $char->save();

        $reloaded = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $reloaded->getById((int)$char->_id, 0);
        $this->assertSame('new-access', $reloaded->esiAccessToken);
        $this->assertSame('new-refresh', $reloaded->esiRefreshToken);

        $raw = $this->rawRow((int)$char->_id);
        $this->assertStringNotContainsString('new-refresh', $raw['esiRefreshToken']);
    }

    public function testNonRotatingResponsePreservesRefreshToken(): void
    {
        self::dbAvailable();
        $char = $this->newCharacter('enc-norotate');
        $char->esiAccessToken = 'old-access';
        $char->esiRefreshToken = 'keep-refresh';
        $char->save();

        // provider omitted refreshToken (no rotation)
        $char->applyRefreshedTokens('new-access', date('Y-m-d H:i:s', time() + 1200), null);
        $char->save();

        $reloaded = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $reloaded->getById((int)$char->_id, 0);
        $this->assertSame('new-access', $reloaded->esiAccessToken);
        $this->assertSame('keep-refresh', $reloaded->esiRefreshToken);
    }

    public function testUndecryptableValuesHealToEmpty(): void
    {
        self::dbAvailable();
        $char = $this->newCharacter('enc-badkey');
        $char->esiAccessToken = 'good-access';
        $char->esiRefreshToken = 'good-refresh';
        $char->save();
        $id = (int)$char->_id;

        // simulate key loss: values can no longer decrypt -> '' (refresh path heals)
        self::$f3->set('ENVIRONMENT.ESI_CRYPTO_KEY', TokenCrypto::generateKey());
        $reloaded = PF\AbstractPathfinderModel::getNew('CharacterModel');
        $reloaded->getById($id, 0);
        $this->assertSame('', $reloaded->esiAccessToken);
        $this->assertSame('', $reloaded->esiRefreshToken);
    }
}
