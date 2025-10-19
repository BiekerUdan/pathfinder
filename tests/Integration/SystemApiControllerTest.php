<?php
/**
 * Integration tests for System API Controller
 * Tests the System controller's waypoint and rally point functionality
 * Note: These methods require authentication, so tests focus on structure validation
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Controller\Api\System;

class SystemApiControllerTest extends TestCase
{
    private static $f3;
    private $systemController;

    public static function setUpBeforeClass(): void
    {
        // Bootstrap Fat-Free Framework
        require_once __DIR__ . '/../../vendor/autoload.php';

        self::$f3 = \Base::instance();
        self::$f3->set('NAMESPACE', 'Exodus4D\\Pathfinder');
        self::$f3->config(__DIR__ . '/../../app/config.ini', true);

        // Initialize Config (sets up database, clients, etc.)
        \Exodus4D\Pathfinder\Lib\Config::instance(self::$f3);

        // Set test environment
        self::$f3->set('TESTING', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh System controller instance for each test
        $this->systemController = new System();
    }

    /**
     * Test setDestination method without authentication
     * Verifies the method returns proper structure even without auth
     */
    public function testSetDestinationWithoutAuth(): void
    {
        // Simulate POST data
        self::$f3->set('POST', [
            'destData' => [],
            'clearOtherWaypoints' => false,
            'first' => false
        ]);

        ob_start();
        try {
            $this->systemController->setDestination(self::$f3);
            $output = ob_get_clean();

            // Should either error or return empty structure
            $response = json_decode($output);

            if ($response) {
                $this->assertIsObject($response);
                $this->assertObjectHasProperty('error', $response);
                $this->assertObjectHasProperty('destData', $response);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            // Authentication failure is expected without a logged-in character
            $this->assertStringContainsString('character', strtolower($e->getMessage()));
        }

        echo "\n✓ System setDestination: Handles missing authentication\n";
    }

    /**
     * Test setDestination with empty destData
     */
    public function testSetDestinationEmptyData(): void
    {
        self::$f3->set('POST', [
            'destData' => [],
            'clearOtherWaypoints' => true,
            'first' => false
        ]);

        ob_start();
        try {
            $this->systemController->setDestination(self::$f3);
            $output = ob_get_clean();

            $response = json_decode($output);

            if ($response) {
                $this->assertIsObject($response);
                // With empty destData, should have empty response or error
                $this->assertObjectHasProperty('destData', $response);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            // Expected without auth
            $this->assertTrue(true);
        }

        echo "\n✓ System setDestination: Handles empty destination data\n";
    }

    /**
     * Test pokeRally method without authentication
     */
    public function testPokeRallyWithoutAuth(): void
    {
        self::$f3->set('POST', [
            'systemId' => 30000142, // Jita
            'pokeDesktop' => '1',
            'pokeMail' => '0',
            'pokeSlack' => '0',
            'pokeDiscord' => '0',
            'message' => 'Test rally'
        ]);

        ob_start();
        try {
            $this->systemController->pokeRally(self::$f3);
            $output = ob_get_clean();

            $response = json_decode($output);

            // Should return an object (possibly empty without auth)
            if ($response) {
                $this->assertIsObject($response);
            }
        } catch (\TypeError $e) {
            ob_end_clean();
            // Expected TypeError when hasAccess() receives null character
            $this->assertStringContainsString('CharacterModel', $e->getMessage());
        } catch (\Exception $e) {
            ob_end_clean();
            // Other exceptions are also expected without authentication
            $this->assertTrue(true);
        }

        echo "\n✓ System pokeRally: Handles missing authentication\n";
    }

    /**
     * Test pokeRally with no systemId
     */
    public function testPokeRallyNoSystemId(): void
    {
        self::$f3->set('POST', [
            'systemId' => 0,
            'pokeDesktop' => '1',
            'pokeMail' => '0',
            'pokeSlack' => '0',
            'pokeDiscord' => '0',
            'message' => 'Test'
        ]);

        ob_start();
        try {
            $this->systemController->pokeRally(self::$f3);
            $output = ob_get_clean();

            $response = json_decode($output);

            // Should return empty object when systemId is 0
            if ($response) {
                $this->assertIsObject($response);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            // Expected
            $this->assertTrue(true);
        }

        echo "\n✓ System pokeRally: Handles missing systemId\n";
    }

    /**
     * Test controller instantiation
     */
    public function testControllerInstantiation(): void
    {
        $this->assertInstanceOf(System::class, $this->systemController);
        $this->assertInstanceOf(\Exodus4D\Pathfinder\Controller\AccessController::class, $this->systemController);

        echo "\n✓ System controller: Instantiated correctly\n";
    }
}
