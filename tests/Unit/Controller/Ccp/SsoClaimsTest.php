<?php
/**
 * Batch 2 unit tests: JWT claim enforcement and SSO log redaction.
 * No database required.
 */

namespace Tests\Unit\Controller\Ccp;

use Exodus4D\Pathfinder\Controller\Ccp\Sso;
use Exodus4D\Pathfinder\Exception\ValidationException;
use Exodus4D\Pathfinder\Lib\Config;
use PHPUnit\Framework\TestCase;

class SsoClaimsTest extends TestCase
{
    private $prevErrorHandler;
    private $prevExceptionHandler;

    private const ISSUERS = ['login.eveonline.com', 'https://login.eveonline.com'];
    private const CLIENT_ID = 'abc123client';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevErrorHandler = set_error_handler(function () { return false; });
        restore_error_handler();
        $this->prevExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
        // keep SSO logger output out of the repo checkout
        \Base::instance()->set('LOGS', sys_get_temp_dir() . '/pf-test-logs/');
        \Base::instance()->set('ENVIRONMENT.CCP_SSO_JWK_CLAIM', 'login.eveonline.com');
        \Base::instance()->set('ENVIRONMENT.CCP_SSO_CLIENT_ID', self::CLIENT_ID);
    }

    protected function tearDown(): void
    {
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

    private function validJwt(array $overrides = []): object
    {
        return (object)array_merge([
            'iss' => 'https://login.eveonline.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'CHARACTER:EVE:12345678',
            'exp' => time() + 600,
            'scp' => ['esi-location.read_location.v1'],
        ], $overrides);
    }

    public function testValidTokenPasses(): void
    {
        Sso::assertJwtClaimsValid($this->validJwt(), self::CLIENT_ID, self::ISSUERS);
        $this->assertTrue(true);
    }

    public function testBareIssuerFormPasses(): void
    {
        Sso::assertJwtClaimsValid($this->validJwt(['iss' => 'login.eveonline.com']), self::CLIENT_ID, self::ISSUERS);
        $this->assertTrue(true);
    }

    public function testArrayAudienceContainingClientPasses(): void
    {
        $jwt = $this->validJwt(['aud' => ['someone-else', self::CLIENT_ID]]);
        Sso::assertJwtClaimsValid($jwt, self::CLIENT_ID, self::ISSUERS);
        $this->assertTrue(true);
    }

    public function testWrongIssuerRejected(): void
    {
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($this->validJwt(['iss' => 'https://evil.example.com']), self::CLIENT_ID, self::ISSUERS);
    }

    public function testIssuerPrefixAttackRejected(): void
    {
        // old strpos() logic accepted any string CONTAINING the claim
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid(
            $this->validJwt(['iss' => 'https://login.eveonline.com.evil.example.com']),
            self::CLIENT_ID,
            self::ISSUERS
        );
    }

    public function testMissingIssuerRejected(): void
    {
        $jwt = $this->validJwt();
        unset($jwt->iss);
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($jwt, self::CLIENT_ID, self::ISSUERS);
    }

    public function testWrongAudienceRejected(): void
    {
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($this->validJwt(['aud' => 'other-client']), self::CLIENT_ID, self::ISSUERS);
    }

    public function testMissingAudienceRejected(): void
    {
        $jwt = $this->validJwt();
        unset($jwt->aud);
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($jwt, self::CLIENT_ID, self::ISSUERS);
    }

    public function testArrayAudienceWithoutClientRejected(): void
    {
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($this->validJwt(['aud' => ['a', 'b']]), self::CLIENT_ID, self::ISSUERS);
    }

    public function testMalformedSubjectRejected(): void
    {
        foreach (['12345678', 'CHARACTER:EVE:', 'CHARACTER:CORP:123', '', 'character:eve:123'] as $sub) {
            try {
                Sso::assertJwtClaimsValid($this->validJwt(['sub' => $sub]), self::CLIENT_ID, self::ISSUERS);
                $this->fail('subject accepted but must be rejected: ' . $sub);
            } catch (ValidationException $e) {
                // expected
            }
        }
        $this->assertTrue(true);
    }

    public function testMissingSubjectRejected(): void
    {
        $jwt = $this->validJwt();
        unset($jwt->sub);
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($jwt, self::CLIENT_ID, self::ISSUERS);
    }

    public function testMissingExpiryRejected(): void
    {
        $jwt = $this->validJwt();
        unset($jwt->exp);
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($jwt, self::CLIENT_ID, self::ISSUERS);
    }

    public function testEmptyIssuersFailClosed(): void
    {
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($this->validJwt(), self::CLIENT_ID, []);
    }

    public function testEmptyClientIdFailClosed(): void
    {
        $this->expectException(ValidationException::class);
        Sso::assertJwtClaimsValid($this->validJwt(), '', self::ISSUERS);
    }

    public function testAcceptedIssuersDerivedFromClaim(): void
    {
        $issuers = Sso::getSsoAcceptedIssuers();
        $this->assertContains('login.eveonline.com', $issuers);
        $this->assertContains('https://login.eveonline.com', $issuers);
    }

    public function testRedactSsoParams(): void
    {
        $redacted = Sso::redactSsoParams([
            'grant_type' => 'authorization_code',
            'code' => 'secret-auth-code',
            'refresh_token' => 'secret-refresh',
        ]);
        $this->assertSame('authorization_code', $redacted['grant_type']);
        $this->assertSame('[redacted]', $redacted['code']);
        $this->assertSame('[redacted]', $redacted['refresh_token']);
    }

    public function testRedactSsoParamsKeepsNonSecrets(): void
    {
        $redacted = Sso::redactSsoParams(['grant_type' => 'refresh_token']);
        $this->assertSame(['grant_type' => 'refresh_token'], $redacted);
    }

    public function testSessionCookiePolicyPinsFlags(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);
        $_SERVER['SERVER_PORT'] = 80;
        Config::hardenSessionCookies(\Base::instance());
        $f3 = \Base::instance();
        $this->assertTrue($f3->get('JAR.httponly'));
        $this->assertSame('Lax', $f3->get('JAR.samesite'));
        $this->assertSame('/', $f3->get('JAR.path'));
    }

    public function testSessionCookiePolicySecureBehindTlsProxy(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        Config::hardenSessionCookies(\Base::instance());
        $this->assertTrue(\Base::instance()->get('JAR.secure'));
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
    }
}
