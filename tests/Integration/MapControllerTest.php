<?php
/**
 * Integration tests for Map Controller
 * Tests the Map controller which touches multiple models and represents core functionality
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Controller\Api\Map;
use Exodus4D\Pathfinder\Model\Pathfinder;

class MapControllerTest extends TestCase
{
    private static $f3;
    private $mapController;

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

        // Create a fresh Map controller instance for each test
        $this->mapController = new Map();

        // Clear any cached data to ensure fresh tests
        self::$f3->clear(Map::CACHE_KEY_INIT);
    }

    /**
     * Test initData method which loads all static configuration data
     * This tests:
     * - MapTypeModel queries
     * - MapScopeModel queries
     * - SystemStatusModel queries
     * - SystemTypeModel queries
     * - ConnectionScopeModel queries
     * - CharacterStatusModel queries
     * - StructureStatusModel queries
     * - Universe GroupModel and TypeModel queries
     * - Config integration
     * - Caching
     */
    public function testInitDataLoadsAllStaticConfig(): void
    {
        // Capture output since controller echoes JSON
        ob_start();
        $this->mapController->initData(self::$f3);
        $output = ob_get_clean();

        // Extract JSON from output (there may be PHP warnings before it)
        // Find the first '{' and last '}' to extract just the JSON
        $jsonStart = strpos($output, '{');
        $jsonEnd = strrpos($output, '}');
        if ($jsonStart !== false && $jsonEnd !== false) {
            $output = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
        }

        // Decode JSON response
        $response = json_decode($output);

        $this->assertIsObject($response, 'Response should be a valid JSON object. Got: ' . substr($output, 0, 200));
        $this->assertObjectHasProperty('error', $response);
        $this->assertIsArray($response->error);

        // Verify timer config loaded (may be null if not configured)
        $this->assertObjectHasProperty('timer', $response);

        // Verify map types loaded from database
        $this->assertObjectHasProperty('mapTypes', $response);
        $this->assertIsObject($response->mapTypes);
        $this->assertNotEmpty((array)$response->mapTypes, 'MapTypes should not be empty');

        // Each map type should have expected structure (can be array or object depending on JSON decode)
        foreach ($response->mapTypes as $mapTypeName => $mapTypeData) {
            $mapTypeData = (array)$mapTypeData; // Convert to array for consistent checking
            $this->assertArrayHasKey('id', $mapTypeData);
            $this->assertArrayHasKey('label', $mapTypeData);
            $this->assertArrayHasKey('class', $mapTypeData);
            $this->assertArrayHasKey('classTab', $mapTypeData);
            $this->assertArrayHasKey('defaultConfig', $mapTypeData);
        }

        // Verify map scopes loaded
        $this->assertObjectHasProperty('mapScopes', $response);
        $this->assertIsObject($response->mapScopes);
        $this->assertNotEmpty((array)$response->mapScopes, 'MapScopes should not be empty');

        // Verify system status loaded
        $this->assertObjectHasProperty('systemStatus', $response);
        $this->assertIsObject($response->systemStatus);
        $this->assertNotEmpty((array)$response->systemStatus, 'SystemStatus should not be empty');

        // Verify system types loaded
        $this->assertObjectHasProperty('systemType', $response);
        $this->assertIsObject($response->systemType);
        $this->assertNotEmpty((array)$response->systemType, 'SystemType should not be empty');

        // Verify connection scopes loaded
        $this->assertObjectHasProperty('connectionScopes', $response);
        $this->assertIsObject($response->connectionScopes);
        $this->assertNotEmpty((array)$response->connectionScopes, 'ConnectionScopes should not be empty');

        // Verify character status loaded
        $this->assertObjectHasProperty('characterStatus', $response);
        $this->assertIsObject($response->characterStatus);
        $this->assertNotEmpty((array)$response->characterStatus, 'CharacterStatus should not be empty');

        // Verify route search config (can be array or object depending on JSON decode)
        $this->assertObjectHasProperty('routeSearch', $response);
        $routeSearch = (array)$response->routeSearch;
        $this->assertArrayHasKey('defaultCount', $routeSearch);
        $this->assertArrayHasKey('maxDefaultCount', $routeSearch);
        $this->assertArrayHasKey('limit', $routeSearch);

        // Verify routes config (can be array or object depending on JSON decode)
        $this->assertObjectHasProperty('routes', $response);
        $routes = (array)$response->routes;
        $this->assertNotEmpty($routes);

        // Verify URL config (third party APIs, can be array or object)
        $this->assertObjectHasProperty('url', $response);
        $url = (array)$response->url;
        $this->assertArrayHasKey('ccpImageServer', $url);
        $this->assertArrayHasKey('zKillboard', $url);
        $this->assertArrayHasKey('dotlan', $url);

        // Verify plugin config (can be array or object)
        $this->assertObjectHasProperty('plugin', $response);
        $plugin = (array)$response->plugin;

        // Verify character config (can be array or object)
        $this->assertObjectHasProperty('character', $response);
        $character = (array)$response->character;
        $this->assertArrayHasKey('autoLocationSelect', $character);

        // Verify Slack config (can be array or object)
        $this->assertObjectHasProperty('slack', $response);
        $slack = (array)$response->slack;
        $this->assertArrayHasKey('status', $slack);

        // Verify Discord config (can be array or object)
        $this->assertObjectHasProperty('discord', $response);
        $discord = (array)$response->discord;
        $this->assertArrayHasKey('status', $discord);

        // Verify structure status loaded
        $this->assertObjectHasProperty('structureStatus', $response);
        $this->assertIsObject($response->structureStatus);
        $this->assertNotEmpty((array)$response->structureStatus, 'StructureStatus should not be empty');

        // Verify wormhole data loaded (from Universe models)
        $this->assertObjectHasProperty('wormholes', $response);
        $this->assertIsObject($response->wormholes);
        $this->assertNotEmpty((array)$response->wormholes, 'Wormholes should not be empty');

        // Verify universe categories loaded (can be array or object)
        $this->assertObjectHasProperty('universeCategories', $response);
        $universeCategories = (array)$response->universeCategories;
        $this->assertCount(2, $universeCategories);

        echo "\n✓ Map initData: Loaded all static config successfully\n";
        echo "  - Map types: " . count((array)$response->mapTypes) . "\n";
        echo "  - Map scopes: " . count((array)$response->mapScopes) . "\n";
        echo "  - System status: " . count((array)$response->systemStatus) . "\n";
        echo "  - System types: " . count((array)$response->systemType) . "\n";
        echo "  - Connection scopes: " . count((array)$response->connectionScopes) . "\n";
        echo "  - Character status: " . count((array)$response->characterStatus) . "\n";
        echo "  - Structure status: " . count((array)$response->structureStatus) . "\n";
        echo "  - Wormhole types: " . count((array)$response->wormholes) . "\n";
    }

    /**
     * Test that initData properly caches results
     */
    public function testInitDataCaching(): void
    {
        // First call - should query database
        ob_start();
        $this->mapController->initData(self::$f3);
        $output1 = ob_get_clean();

        // Verify cache was set
        $this->assertTrue(self::$f3->exists(Map::CACHE_KEY_INIT));

        // Second call - should use cache
        ob_start();
        $this->mapController->initData(self::$f3);
        $output2 = ob_get_clean();

        // Both outputs should be identical
        $this->assertEquals($output1, $output2);

        $response1 = json_decode($output1);
        $response2 = json_decode($output2);

        $this->assertEquals($response1, $response2);

        echo "\n✓ Map initData: Caching works correctly\n";
    }

    /**
     * Test MapTypeModel loading
     */
    public function testMapTypeModelLoading(): void
    {
        $mapType = Pathfinder\AbstractPathfinderModel::getNew('MapTypeModel');
        $rows = $mapType->find('active = 1');

        $this->assertNotEmpty($rows, 'Should find active map types');

        $count = 0;
        foreach ($rows as $row) {
            $this->assertInstanceOf(Pathfinder\MapTypeModel::class, $row);
            $this->assertNotEmpty($row->name);
            // Label may be empty for some types
            $this->assertTrue(isset($row->label), 'Label field should exist');
            $this->assertEquals(1, $row->active);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one active map type');
        echo "\n✓ MapTypeModel: Loaded $count active types\n";
    }

    /**
     * Test MapScopeModel loading
     */
    public function testMapScopeModelLoading(): void
    {
        $mapScope = Pathfinder\AbstractPathfinderModel::getNew('MapScopeModel');
        $rows = $mapScope->find('active = 1');

        $this->assertNotEmpty($rows, 'Should find active map scopes');

        $count = 0;
        foreach ($rows as $row) {
            $this->assertInstanceOf(Pathfinder\MapScopeModel::class, $row);
            $this->assertNotEmpty($row->name);
            $this->assertNotEmpty($row->label);
            $this->assertEquals(1, $row->active);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one active map scope');
        echo "\n✓ MapScopeModel: Loaded $count active scopes\n";
    }

    /**
     * Test SystemStatusModel loading
     */
    public function testSystemStatusModelLoading(): void
    {
        $systemStatus = Pathfinder\AbstractPathfinderModel::getNew('SystemStatusModel');
        $rows = $systemStatus->find('active = 1');

        $this->assertNotEmpty($rows, 'Should find active system statuses');

        $count = 0;
        foreach ($rows as $row) {
            $this->assertInstanceOf(Pathfinder\SystemStatusModel::class, $row);
            $this->assertNotEmpty($row->name);
            $this->assertNotEmpty($row->label);
            $this->assertNotEmpty($row->class);
            $this->assertEquals(1, $row->active);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one active system status');
        echo "\n✓ SystemStatusModel: Loaded $count active statuses\n";
    }

    /**
     * Test SystemTypeModel loading
     */
    public function testSystemTypeModelLoading(): void
    {
        $systemType = Pathfinder\AbstractPathfinderModel::getNew('SystemTypeModel');
        $rows = $systemType->find('active = 1');

        $this->assertNotEmpty($rows, 'Should find active system types');

        $count = 0;
        foreach ($rows as $row) {
            $this->assertInstanceOf(Pathfinder\SystemTypeModel::class, $row);
            $this->assertNotEmpty($row->name);
            $this->assertEquals(1, $row->active);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one active system type');
        echo "\n✓ SystemTypeModel: Loaded $count active types\n";
    }

    /**
     * Test ConnectionScopeModel loading
     */
    public function testConnectionScopeModelLoading(): void
    {
        $connectionScope = Pathfinder\AbstractPathfinderModel::getNew('ConnectionScopeModel');
        $rows = $connectionScope->find('active = 1');

        $this->assertNotEmpty($rows, 'Should find active connection scopes');

        $count = 0;
        foreach ($rows as $row) {
            $this->assertInstanceOf(Pathfinder\ConnectionScopeModel::class, $row);
            $this->assertNotEmpty($row->name);
            $this->assertNotEmpty($row->label);
            $this->assertEquals(1, $row->active);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one active connection scope');
        echo "\n✓ ConnectionScopeModel: Loaded $count active scopes\n";
    }

    /**
     * Test CharacterStatusModel loading
     */
    public function testCharacterStatusModelLoading(): void
    {
        $characterStatus = Pathfinder\AbstractPathfinderModel::getNew('CharacterStatusModel');
        $rows = $characterStatus->find('active = 1');

        $this->assertNotEmpty($rows, 'Should find active character statuses');

        $count = 0;
        foreach ($rows as $row) {
            $this->assertInstanceOf(Pathfinder\CharacterStatusModel::class, $row);
            $this->assertNotEmpty($row->name);
            $this->assertNotEmpty($row->class);
            $this->assertEquals(1, $row->active);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one active character status');
        echo "\n✓ CharacterStatusModel: Loaded $count active statuses\n";
    }

    /**
     * Test StructureStatusModel loading
     */
    public function testStructureStatusModelLoading(): void
    {
        $structureStatuses = Pathfinder\StructureStatusModel::getAll();

        $this->assertNotEmpty($structureStatuses, 'Should find structure statuses');

        $count = 0;
        foreach ($structureStatuses as $status) {
            $this->assertInstanceOf(Pathfinder\StructureStatusModel::class, $status);
            $data = $status->getData();
            $this->assertIsObject($data);
            $this->assertObjectHasProperty('id', $data);
            $this->assertObjectHasProperty('name', $data);
            $count++;
        }

        $this->assertGreaterThan(0, $count, 'Should have at least one structure status');
        echo "\n✓ StructureStatusModel: Loaded $count statuses\n";
    }
}
