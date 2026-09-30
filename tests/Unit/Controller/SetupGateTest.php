<?php
/**
 * Batch 3 unit tests: setup access gate decision matrix.
 * No database required.
 */

namespace Tests\Unit\Controller;

use Exodus4D\Pathfinder\Controller\Setup;
use PHPUnit\Framework\TestCase;

class SetupGateTest extends TestCase
{
    private $prevErrorHandler;
    private $prevExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevErrorHandler = set_error_handler(function () { return false; });
        restore_error_handler();
        $this->prevExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
        $f3 = \Base::instance();
        $f3->clear('ENVIRONMENT.SETUP_ENABLED');
        $f3->clear('ENVIRONMENT.SETUP_TOKEN');
        $f3->clear('POST.setup_token');
        $f3->clear('GET.setup_token');
        unset($_SERVER['HTTP_X_SETUP_TOKEN']);
    }

    protected function tearDown(): void
    {
        $f3 = \Base::instance();
        $f3->clear('ENVIRONMENT.SETUP_ENABLED');
        $f3->clear('ENVIRONMENT.SETUP_TOKEN');
        $f3->clear('POST.setup_token');
        $f3->clear('GET.setup_token');
        unset($_SERVER['HTTP_X_SETUP_TOKEN']);
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

    private function enable(string $token): void
    {
        $f3 = \Base::instance();
        $f3->set('ENVIRONMENT.SETUP_ENABLED', '1');
        $f3->set('ENVIRONMENT.SETUP_TOKEN', $token);
    }

    public function testDisabledByDefault(): void
    {
        $this->assertSame('disabled', Setup::checkSetupAccess(\Base::instance()));
        $this->assertFalse(Setup::isSetupEnabled());
    }

    public function testDisabledDeniesEvenWithToken(): void
    {
        $f3 = \Base::instance();
        $f3->set('ENVIRONMENT.SETUP_TOKEN', 'secret');
        $f3->set('POST.setup_token', 'secret');
        $this->assertSame('disabled', Setup::checkSetupAccess($f3));
    }

    public function testEnabledWithoutTokenDenies(): void
    {
        // fail closed: enabled but no deployment secret configured
        $f3 = \Base::instance();
        $f3->set('ENVIRONMENT.SETUP_ENABLED', '1');
        $f3->set('POST.setup_token', 'anything');
        $this->assertSame('denied', Setup::checkSetupAccess($f3));
    }

    public function testEnabledWithoutPresentedTokenDenies(): void
    {
        $this->enable('top-secret');
        $this->assertSame('denied', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testWrongTokenDenied(): void
    {
        $this->enable('top-secret');
        \Base::instance()->set('POST.setup_token', 'wrong');
        $this->assertSame('denied', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testCorrectTokenViaPostParam(): void
    {
        $this->enable('top-secret');
        \Base::instance()->set('POST.setup_token', 'top-secret');
        $this->assertSame('ok', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testCorrectTokenViaGetParam(): void
    {
        $this->enable('top-secret');
        \Base::instance()->set('GET.setup_token', 'top-secret');
        $this->assertSame('ok', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testCorrectTokenViaHeader(): void
    {
        $this->enable('top-secret');
        $_SERVER['HTTP_X_SETUP_TOKEN'] = 'top-secret';
        $this->assertSame('ok', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testHeaderTakesPrecedenceOverWrongParam(): void
    {
        $this->enable('top-secret');
        $_SERVER['HTTP_X_SETUP_TOKEN'] = 'top-secret';
        \Base::instance()->set('POST.setup_token', 'wrong');
        $this->assertSame('ok', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testEmptyProvidedTokenDenied(): void
    {
        $this->enable('top-secret');
        \Base::instance()->set('POST.setup_token', '');
        $this->assertSame('denied', Setup::checkSetupAccess(\Base::instance()));
    }

    public function testEnabledFlagParsing(): void
    {
        $f3 = \Base::instance();
        foreach (['1', 1, true, 'true', 'on'] as $on) {
            $f3->set('ENVIRONMENT.SETUP_ENABLED', $on);
            $this->assertTrue(Setup::isSetupEnabled(), 'flag should enable: ' . var_export($on, true));
        }
        foreach (['0', 0, false, 'false', '', null] as $off) {
            $f3->set('ENVIRONMENT.SETUP_ENABLED', $off);
            $this->assertFalse(Setup::isSetupEnabled(), 'flag should disable: ' . var_export($off, true));
        }
    }
}
