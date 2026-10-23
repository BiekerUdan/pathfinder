<?php
/**
 * Batch 4 unit tests: versioned ESI token encryption.
 * No database required (keys come from the F3 hive).
 */

namespace Tests\Unit\Lib\Crypto;

use Exodus4D\Pathfinder\Lib\Crypto\TokenCrypto;
use PHPUnit\Framework\TestCase;

class TokenCryptoTest extends TestCase
{
    private $prevErrorHandler;
    private $prevExceptionHandler;

    private const KEY_A = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='; // 32 zero bytes
    private const KEY_B = 'AQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE='; // 32 0x01 bytes

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevErrorHandler = set_error_handler(function () { return false; });
        restore_error_handler();
        $this->prevExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
        \Base::instance()->set('ENVIRONMENT.ESI_CRYPTO_KEY', self::KEY_A);
        \Base::instance()->clear('ENVIRONMENT.ESI_CRYPTO_KEY_OLD');
        // reset the one-time no-key warning flag via reflection between tests
        $prop = new \ReflectionProperty(TokenCrypto::class, 'warnedNoKey');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    protected function tearDown(): void
    {
        \Base::instance()->clear('ENVIRONMENT.ESI_CRYPTO_KEY');
        \Base::instance()->clear('ENVIRONMENT.ESI_CRYPTO_KEY_OLD');
        for ($i = 0; $i < 10; $i++) {
            $top = set_error_handler(function () { return false; });
            restore_error_handler();
            if ($top === $this->prevErrorHandler) {
                break;
            }
            restore_error_handler();
        }
        for ($i = 0; $i < 10; $i++) {
            $top = set_exception_handler(function () {});
            restore_exception_handler();
            if ($top === $this->prevExceptionHandler) {
                break;
            }
            restore_exception_handler();
        }
        parent::tearDown();
    }

    public function testRoundtrip(): void
    {
        $cipher = TokenCrypto::encrypt('secret-token-value');
        $this->assertTrue(TokenCrypto::isEncrypted($cipher));
        $this->assertMatchesRegularExpression('/^v1:[A-Za-z0-9\\-_]{12}:/', $cipher);
        $this->assertSame('secret-token-value', TokenCrypto::decrypt($cipher));
    }

    public function testCiphertextDiffersFromPlaintext(): void
    {
        $cipher = TokenCrypto::encrypt('my-refresh-token');
        $this->assertStringNotContainsString('my-refresh-token', $cipher);
    }

    public function testNonceRandomness(): void
    {
        // same input must produce different ciphertext (random nonce)
        $this->assertNotSame(
            TokenCrypto::encrypt('same-value'),
            TokenCrypto::encrypt('same-value')
        );
    }

    public function testEmptyPassthrough(): void
    {
        $this->assertSame('', TokenCrypto::encrypt(''));
        $this->assertSame('', TokenCrypto::decrypt(''));
        $this->assertSame('', TokenCrypto::decrypt(null));
    }

    public function testLegacyPlaintextReadsThrough(): void
    {
        // pre-encryption rows keep working until next token write
        $this->assertSame('legacy-plain', TokenCrypto::decrypt('legacy-plain'));
        $this->assertFalse(TokenCrypto::isEncrypted('legacy-plain'));
    }

    public function testAlreadyEncryptedNotDoubleWrapped(): void
    {
        $once = TokenCrypto::encrypt('value');
        $this->assertSame($once, TokenCrypto::encrypt($once));
    }

    public function testFingerprintIsStable(): void
    {
        $this->assertSame(
            TokenCrypto::keyFingerprint(base64_decode(self::KEY_A)),
            TokenCrypto::keyFingerprint(base64_decode(self::KEY_A))
        );
        $this->assertNotSame(
            TokenCrypto::keyFingerprint(base64_decode(self::KEY_A)),
            TokenCrypto::keyFingerprint(base64_decode(self::KEY_B))
        );
    }

    public function testWrongKeyFailsClosed(): void
    {
        $cipher = TokenCrypto::encrypt('value');
        \Base::instance()->set('ENVIRONMENT.ESI_CRYPTO_KEY', self::KEY_B);
        $this->assertNull(TokenCrypto::decrypt($cipher));
    }

    public function testOldKeyDecryptsAfterRotation(): void
    {
        $cipher = TokenCrypto::encrypt('value');
        // rotate: B becomes current, A moves to OLD
        \Base::instance()->set('ENVIRONMENT.ESI_CRYPTO_KEY', self::KEY_B);
        \Base::instance()->set('ENVIRONMENT.ESI_CRYPTO_KEY_OLD', self::KEY_A);
        $this->assertSame('value', TokenCrypto::decrypt($cipher));
        // new writes use the new key (fingerprint differs from the old key)
        $newCipher = TokenCrypto::encrypt('new');
        $newFp = explode(':', $newCipher)[1];
        $this->assertSame(TokenCrypto::keyFingerprint(base64_decode(self::KEY_B)), $newFp);
        $this->assertNotSame(TokenCrypto::keyFingerprint(base64_decode(self::KEY_A)), $newFp);
        $this->assertSame('new', TokenCrypto::decrypt(TokenCrypto::encrypt('new')));
    }

    public function testMalformedValuesReturnNull(): void
    {
        $this->assertNull(TokenCrypto::decrypt('v1:onlytwo'));
        $this->assertNull(TokenCrypto::decrypt('v1:abcdefghijkl:not-base64!!!'));
        $this->assertNull(TokenCrypto::decrypt('v1:abcdefghijkl:eA==')); // too short
    }

    public function testNoKeyStoresPlaintextAndReportsNoKeys(): void
    {
        \Base::instance()->clear('ENVIRONMENT.ESI_CRYPTO_KEY');
        $this->assertFalse(TokenCrypto::hasKeys());
        // fail-open for upgrades without the Secret deployed yet
        $this->assertSame('plain', TokenCrypto::encrypt('plain'));
        $this->assertSame('plain', TokenCrypto::decrypt('plain'));
    }

    public function testHasKeys(): void
    {
        $this->assertTrue(TokenCrypto::hasKeys());
    }

    public function testGenerateKeyFormat(): void
    {
        $key = TokenCrypto::generateKey();
        $raw = base64_decode($key, true);
        $this->assertNotFalse($raw);
        $this->assertSame(32, strlen($raw));
        $this->assertNotSame($key, TokenCrypto::generateKey());
    }

    public function testInvalidKeyIgnored(): void
    {
        \Base::instance()->set('ENVIRONMENT.ESI_CRYPTO_KEY', 'not-valid-base64!!!');
        $this->assertFalse(TokenCrypto::hasKeys());
        // degrades to plaintext rather than breaking logins
        $this->assertSame('x', TokenCrypto::encrypt('x'));
    }
}
