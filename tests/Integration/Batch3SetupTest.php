<?php
/**
 * Batch 3 setup-gate wiring tests: beforeroute() enforcement on both
 * setup surfaces (/setup HTML + /api/Setup/*), incl. HTTP status mapping
 * and POST-only API mutations.
 *
 * No database required. F3's error() would halt the runner, so HALT/QUIET/
 * ONERROR are neutralized per test (restored afterwards).
 */

namespace Tests\Integration;

use Exodus4D\Pathfinder\Controller\Api\Setup as SetupApi;
use Exodus4D\Pathfinder\Controller\Setup as SetupHtml;
use Exodus4D\Pathfinder\Lib\Config;
use PHPUnit\Framework\TestCase;

class Batch3SetupTest extends TestCase
{
    private static $f3;
    private $savedHalt;
    private $savedQuiet;
    private $savedOnError;
    private static $savedLoggable;

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
        // keep F3's error() from writing to error_log during gate tests
        // (output would mark headers as sent for later tests in the process)
        self::$savedLoggable = self::$f3->get('LOGGABLE');
        self::$f3->set('LOGGABLE', 'none');
    }

    public static function tearDownAfterClass(): void
    {
        if(self::$f3){
            self::$f3->set('LOGGABLE', self::$savedLoggable);
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedHalt = self::$f3->get('HALT');
        $this->savedQuiet = self::$f3->get('QUIET');
        $this->savedOnError = self::$f3->get('ONERROR');
        self::$f3->set('HALT', false);
        self::$f3->set('QUIET', true);
        self::$f3->set('ONERROR', null);
        self::$f3->clear('ENVIRONMENT.SETUP_ENABLED');
        self::$f3->clear('ENVIRONMENT.SETUP_TOKEN');
        self::$f3->clear('POST.setup_token');
        self::$f3->clear('GET.setup_token');
        unset($_SERVER['HTTP_X_SETUP_TOKEN']);
    }

    protected function tearDown(): void
    {
        self::$f3->set('HALT', $this->savedHalt);
        self::$f3->set('QUIET', $this->savedQuiet);
        self::$f3->set('ONERROR', $this->savedOnError);
        self::$f3->clear('ENVIRONMENT.SETUP_ENABLED');
        self::$f3->clear('ENVIRONMENT.SETUP_TOKEN');
        self::$f3->clear('POST.setup_token');
        self::$f3->clear('GET.setup_token');
        unset($_SERVER['HTTP_X_SETUP_TOKEN']);
        // beforeroute() and error() leave hive residue (template vars, ERROR)
        // that would pollute later tests sharing this process
        foreach (['tplResource', 'tplPageTitle', 'tplPageContent', 'tplBodyClass', 'tplNavigation', 'ERROR'] as $key) {
            self::$f3->clear($key);
        }
        parent::tearDown();
    }

    private function enable(string $token): void
    {
        self::$f3->set('ENVIRONMENT.SETUP_ENABLED', '1');
        self::$f3->set('ENVIRONMENT.SETUP_TOKEN', $token);
    }

    public function testHtmlSetupHiddenWhenDisabled(): void
    {
        $controller = new SetupHtml();
        $this->assertFalse($controller->beforeroute(self::$f3, []));
        $this->assertSame(404, self::$f3->get('ERROR.code'));
    }

    public function testHtmlSetupRejectsBadToken(): void
    {
        $this->enable('correct-token');
        self::$f3->set('POST.setup_token', 'wrong-token');
        $controller = new SetupHtml();
        $this->assertFalse($controller->beforeroute(self::$f3, []));
        $this->assertSame(403, self::$f3->get('ERROR.code'));
    }

    public function testHtmlSetupAllowsValidToken(): void
    {
        $this->enable('correct-token');
        self::$f3->set('POST.setup_token', 'correct-token');
        $controller = new SetupHtml();
        $this->assertTrue($controller->beforeroute(self::$f3, []));
    }

    public function testApiSetupHiddenWhenDisabled(): void
    {
        self::$f3->set('VERB', 'POST');
        $controller = new SetupApi();
        $this->assertFalse($controller->beforeroute(self::$f3, []));
        $this->assertSame(404, self::$f3->get('ERROR.code'));
    }

    public function testApiSetupRejectsBadToken(): void
    {
        $this->enable('correct-token');
        self::$f3->set('VERB', 'POST');
        self::$f3->set('POST.setup_token', 'wrong-token');
        $controller = new SetupApi();
        $this->assertFalse($controller->beforeroute(self::$f3, []));
        $this->assertSame(403, self::$f3->get('ERROR.code'));
    }

    public function testApiSetupRejectsGetEvenWithValidToken(): void
    {
        $this->enable('correct-token');
        self::$f3->set('VERB', 'GET');
        self::$f3->set('GET.setup_token', 'correct-token');
        $controller = new SetupApi();
        $this->assertFalse($controller->beforeroute(self::$f3, []));
        $this->assertSame(405, self::$f3->get('ERROR.code'));
    }

    public function testApiSetupAllowsPostWithValidToken(): void
    {
        $this->enable('correct-token');
        self::$f3->set('VERB', 'POST');
        $_SERVER['HTTP_X_SETUP_TOKEN'] = 'correct-token';
        $controller = new SetupApi();
        $this->assertTrue($controller->beforeroute(self::$f3, []));
    }
}
