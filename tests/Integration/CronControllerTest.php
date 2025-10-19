<?php
/**
 * Integration tests for CronController
 * Tests the remote cron triggering functionality with token authentication
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Controller\CronController;
use Exodus4D\Pathfinder\Lib\Config;

class CronControllerTest extends TestCase
{
    private static $f3;
    private $cronController;
    private $originalTokenValue;

    public static function setUpBeforeClass(): void
    {
        // Bootstrap Fat-Free Framework
        require_once __DIR__ . '/../../vendor/autoload.php';

        self::$f3 = \Base::instance();
        self::$f3->set('NAMESPACE', 'Exodus4D\\Pathfinder');
        self::$f3->config(__DIR__ . '/../../app/config.ini', true);

        // Initialize Config (sets up database, clients, etc.)
        Config::instance(self::$f3);

        // Set test environment
        self::$f3->set('TESTING', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh CronController instance for each test
        $this->cronController = new CronController();

        // Store original token value to restore after tests
        $this->originalTokenValue = Config::getEnvironmentData('CRON_TOKEN');
    }

    protected function tearDown(): void
    {
        // Restore original token value
        self::$f3->set('ENVIRONMENT.CRON_TOKEN', $this->originalTokenValue);

        parent::tearDown();
    }

    /**
     * Test trigger with no token configured
     * Should return 404 Not Found since endpoint is not enabled
     */
    public function testTriggerWithNoTokenConfigured(): void
    {
        // Clear the token configuration
        self::$f3->set('ENVIRONMENT.CRON_TOKEN', '');

        ob_start();
        $this->cronController->trigger(self::$f3);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Not Found', $response['error']);
        $this->assertArrayHasKey('message', $response);
        $this->assertStringContainsString('not enabled', $response['message']);
        $this->assertStringContainsString('CRON_TOKEN', $response['message']);

        echo "\n✓ CronController: Returns 404 Not Found when token not configured\n";
    }

    /**
     * Test trigger with missing token in request
     * Should return forbidden error
     */
    public function testTriggerWithMissingToken(): void
    {
        // Set a token in configuration
        self::$f3->set('ENVIRONMENT.CRON_TOKEN', 'test-secret-token-123');

        // Don't provide any token in the request
        self::$f3->set('GET.token', null);
        self::$f3->set('POST.token', null);

        ob_start();
        $this->cronController->trigger(self::$f3);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Forbidden', $response['error']);
        $this->assertArrayHasKey('message', $response);
        $this->assertStringContainsString('Invalid or missing token', $response['message']);

        echo "\n✓ CronController: Returns forbidden when token is missing\n";
    }

    /**
     * Test trigger with invalid token in request
     * Should return forbidden error
     */
    public function testTriggerWithInvalidToken(): void
    {
        // Set a token in configuration
        self::$f3->set('ENVIRONMENT.CRON_TOKEN', 'test-secret-token-123');

        // Provide wrong token in the request
        self::$f3->set('GET.token', 'wrong-token');

        ob_start();
        $this->cronController->trigger(self::$f3);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Forbidden', $response['error']);

        echo "\n✓ CronController: Returns forbidden when token is invalid\n";
    }

    /**
     * Test trigger with valid token via GET parameter
     * Should execute cron and return success with job info
     *
     * @group cron-execution
     */
    public function testTriggerWithValidTokenViaGet(): void
    {
        $this->markTestSkipped(
            'This test requires actual cron execution which needs a fully configured database. ' .
            'Token authentication is tested in other tests.'
        );
    }

    /**
     * Test trigger with valid token via POST parameter
     * Should execute cron and return success with job info
     *
     * @group cron-execution
     */
    public function testTriggerWithValidTokenViaPost(): void
    {
        $this->markTestSkipped(
            'This test requires actual cron execution which needs a fully configured database. ' .
            'Token authentication is tested in other tests.'
        );
    }

    /**
     * Test that response includes job execution details
     *
     * @group cron-execution
     */
    public function testTriggerResponseIncludesJobDetails(): void
    {
        $this->markTestSkipped(
            'This test requires actual cron execution which needs a fully configured database. ' .
            'Response format is validated in other integration tests.'
        );
    }

    /**
     * Test GET token takes precedence over POST token
     *
     * @group cron-execution
     */
    public function testGetTokenPrecedenceOverPost(): void
    {
        $this->markTestSkipped(
            'This test requires actual cron execution which needs a fully configured database. ' .
            'Token precedence logic is part of the authentication which is tested separately.'
        );
    }

    /**
     * Test that empty string token in config is treated as not configured
     */
    public function testEmptyStringTokenTreatedAsNotConfigured(): void
    {
        // Set empty string as token
        self::$f3->set('ENVIRONMENT.CRON_TOKEN', '');
        self::$f3->set('GET.token', 'some-token');

        ob_start();
        $this->cronController->trigger(self::$f3);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Should return 404 (not configured), not 403 (forbidden)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Not Found', $response['error']);

        echo "\n✓ CronController: Empty string token treated as not configured (404)\n";
    }

    /**
     * Test response is valid JSON for error cases
     */
    public function testResponseIsValidJson(): void
    {
        // Test with an error case (no token configured) to verify JSON response format
        self::$f3->set('ENVIRONMENT.CRON_TOKEN', '');

        ob_start();
        $this->cronController->trigger(self::$f3);
        $output = ob_get_clean();

        // Should be valid JSON
        $response = json_decode($output, true);
        $this->assertNotNull($response, 'Response should be valid JSON');
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertIsArray($response);

        echo "\n✓ CronController: Response is valid JSON\n";
    }
}
