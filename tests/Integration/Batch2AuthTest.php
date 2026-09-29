<?php
/**
 * Batch 2 DB-backed auth tests: remember-me rotation and logout teardown.
 *
 * Same disposable-DB contract as Batch1SecurityTest: needs
 * PATHFINDER_TEST_DB_* (see tests/bootstrap.php), skips without a DB,
 * truncates only its own fixture tables.
 */

namespace Tests\Integration;

use Exodus4D\Pathfinder\Controller\Controller;
use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Model\Pathfinder as PF;
use PHPUnit\Framework\TestCase;

class Batch2AuthTest extends TestCase
{
    private static $f3;
    private static $char;

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

        self::buildFixtures();
    }

    private static function dbAvailable(): bool
    {
        if (self::$f3 === null) {
            self::markTestSkipped('No test database reachable (set PATHFINDER_TEST_DB_*).');
        }
        return true;
    }

    private static function buildFixtures(): void
    {
        $db = self::$f3->DB->getDB('PF');
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['character_authentication', 'character_log', 'user_character', 'character', 'activity_log'] as $table) {
            try {
                $db->exec('TRUNCATE TABLE `' . $table . '`');
            } catch (\Throwable $e) {
            }
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');

        self::$char = PF\AbstractPathfinderModel::getNew('CharacterModel');
        self::$char->name = 'Batch2 Char';
        self::$char->ownerHash = 'batch2-owner';
        self::$char->save();
    }

    private function newAuthRow(string $selector, string $validator, string $expires): object
    {
        $auth = PF\AbstractPathfinderModel::getNew('CharacterAuthenticationModel');
        $auth->characterId = self::$char;
        $auth->selector = $selector;
        $auth->token = hash('sha256', $validator);
        $auth->expires = $expires;
        $auth->setActive(true);
        $auth->save();
        return $auth;
    }

    private function authSelectorsForChar(): array
    {
        $rows = self::$f3->DB->getDB('PF')->exec(
            'SELECT selector FROM character_authentication WHERE characterId = ' . (int)self::$char->_id
        );
        return array_column((array)$rows, 'selector');
    }

    private function rotateViaController(object $authRow): void
    {
        // consent cookie on: remember-me issuance is enabled
        self::$f3->set('COOKIE.cookie', '1');
        $controller = new Controller();
        $method = new \ReflectionMethod($controller, 'rotateLoginCookie');
        $method->setAccessible(true);
        $method->invoke($controller, $authRow);
    }

    public function testRotationConsumesPresentedCredential(): void
    {
        self::dbAvailable();
        $auth = $this->newAuthRow('batch2-sel-old', 'batch2-val-old', date('Y-m-d H:i:s', time() + 3600));

        $this->rotateViaController($auth);

        $selectors = $this->authSelectorsForChar();
        $this->assertNotContains('batch2-sel-old', $selectors, 'presented selector must be single-use');
        $this->assertCount(1, $selectors, 'exactly one replacement credential must exist');
        $this->assertNotSame('batch2-sel-old', $selectors[0]);

        // replacement cookie issued to the client jar
        $cookieName = 'char_' . md5('Batch2 Char');
        $this->assertNotEmpty(
            self::$f3->get('COOKIE.' . $cookieName),
            'rotated validator must be delivered as a new cookie'
        );
    }

    public function testRotationPrunesExpiredCredentials(): void
    {
        self::dbAvailable();
        $this->newAuthRow('batch2-sel-dead', 'batch2-val-dead', date('Y-m-d H:i:s', time() - 3600));
        $live = $this->newAuthRow('batch2-sel-live', 'batch2-val-live', date('Y-m-d H:i:s', time() + 3600));

        $this->rotateViaController($live);

        $selectors = $this->authSelectorsForChar();
        $this->assertNotContains('batch2-sel-dead', $selectors, 'expired credentials must be pruned');
        $this->assertNotContains('batch2-sel-live', $selectors, 'presented credential must be consumed');
        $this->assertCount(1, $selectors, 'only the replacement remains');
    }

    public function testLogoutDestroysServerSession(): void
    {
        self::dbAvailable();
        // simulate a logged-in session with only this character active
        self::$f3->set('SESSION.USER', ['ID' => 1, 'NAME' => 'batch2']);
        self::$f3->set('SESSION.CHARACTERS', [[
            'ID' => (int)self::$char->_id,
            'NAME' => self::$char->name,
            'TIME' => time(),
        ]]);

        // silence the websocket broadcast path used by web logout flows;
        // CharacterModel::logout itself is what we verify here
        self::$char->logout();

        $this->assertFalse(self::$f3->exists('SESSION.USER'));
        $this->assertFalse(self::$f3->exists('SESSION.CHARACTERS'));
        $this->assertEmpty(self::$f3->get('SESSION'), 'server-side session must be destroyed on final logout');
    }
}
