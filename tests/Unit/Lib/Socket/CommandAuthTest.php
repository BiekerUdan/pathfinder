<?php
/**
 * Batch 4 unit tests: HMAC command-channel envelopes.
 */

namespace Tests\Unit\Lib\Socket;

use Exodus4D\Pathfinder\Lib\Socket\CommandAuth;
use PHPUnit\Framework\TestCase;

class CommandAuthTest extends TestCase
{
    private $prevErrorHandler;
    private $prevExceptionHandler;

    private const SECRET = 'test-command-secret-12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevErrorHandler = set_error_handler(function () { return false; });
        restore_error_handler();
        $this->prevExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
        \Base::instance()->set('ENVIRONMENT.WS_COMMAND_SECRET', self::SECRET);
        // fresh replay cache per test
        $prop = new \ReflectionProperty(CommandAuth::class, 'seenNonces');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    protected function tearDown(): void
    {
        \Base::instance()->clear('ENVIRONMENT.WS_COMMAND_SECRET');
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

    public function testSignAndVerifyRoundtrip(): void
    {
        $auth = CommandAuth::sign('characterLogout', [12, 34]);
        $this->assertArrayHasKey('sig', $auth);
        $this->assertTrue(CommandAuth::verify(self::SECRET, 'characterLogout', [12, 34], $auth));
    }

    public function testSignatureIsHexHmac(): void
    {
        $auth = CommandAuth::sign('mapUpdate', ['a' => 1]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $auth['sig']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $auth['nonce']);
    }

    public function testTamperedLoadRejected(): void
    {
        $auth = CommandAuth::sign('mapUpdate', [1]);
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [2], $auth));
    }

    public function testTamperedTaskRejected(): void
    {
        $auth = CommandAuth::sign('mapUpdate', [1]);
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapDeleted', [1], $auth));
    }

    public function testWrongSecretRejected(): void
    {
        $auth = CommandAuth::sign('mapUpdate', [1]);
        $this->assertFalse(CommandAuth::verify('other-secret', 'mapUpdate', [1], $auth));
    }

    public function testReplayRejected(): void
    {
        $auth = CommandAuth::sign('mapUpdate', [1]);
        $this->assertTrue(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], $auth));
        // same envelope a second time must fail
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], $auth));
    }

    public function testStaleTimestampRejected(): void
    {
        $auth = CommandAuth::sign('mapUpdate', [1], time() - 600);
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], $auth));
    }

    public function testFutureTimestampRejected(): void
    {
        $auth = CommandAuth::sign('mapUpdate', [1], time() + 600);
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], $auth));
    }

    public function testMissingAuthRejected(): void
    {
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], null));
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], 'sig'));
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'mapUpdate', [1], []));
    }

    public function testMalformedFieldsRejected(): void
    {
        $this->assertFalse(CommandAuth::verify(self::SECRET, 't', [1], ['ts' => 'x', 'nonce' => 'ab12cd34ef56ab78', 'sig' => 'x']));
        $this->assertFalse(CommandAuth::verify(self::SECRET, 't', [1], ['ts' => time(), 'nonce' => 'short', 'sig' => 'x']));
        $this->assertFalse(CommandAuth::verify(self::SECRET, 't', [1], ['ts' => time(), 'nonce' => 'ab12cd34ef56ab78', 'sig' => '']));
    }

    public function testEmptySecretFailsClosed(): void
    {
        $auth = CommandAuth::sign('t', [1]);
        $this->assertFalse(CommandAuth::verify('', 't', [1], $auth));
    }

    public function testGetSecretNullWhenUnset(): void
    {
        \Base::instance()->clear('ENVIRONMENT.WS_COMMAND_SECRET');
        $this->assertNull(CommandAuth::getSecret());
        $this->assertSame(self::SECRET, (function () {
            \Base::instance()->set('ENVIRONMENT.WS_COMMAND_SECRET', self::SECRET);
            return CommandAuth::getSecret();
        })());
    }

    /**
     * cross-repo interop vector: this exact envelope must verify in the
     * websocket repo's CommandAuth implementation (and vice versa).
     */
    public function testInteropVector(): void
    {
        $auth = CommandAuth::sign('characterLogout', [12, 34], 1720000000, 'ab12cd34ef56ab78');
        // pinned value: regenerating must be deterministic for fixed inputs
        $this->assertSame(
            hash_hmac('sha256', "characterLogout\n1720000000\nab12cd34ef56ab78\n[12,34]", self::SECRET),
            $auth['sig']
        );
        $this->assertSame(1720000000, $auth['ts']);
        // note: the fixed-ts envelope itself is outside the liveness window by
        // design (freshness is enforced); verify() of it must fail closed
        $this->assertFalse(CommandAuth::verify(self::SECRET, 'characterLogout', [12, 34], $auth));
    }
}
