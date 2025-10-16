<?php
/**
 * Integration tests for Pathfinder business logic that uses the pathfinder_esi library
 * Tests the actual Pathfinder controllers and models, not just the ESI library directly
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Model\Pathfinder;
use Exodus4D\Pathfinder\Model\Universe;
use Exodus4D\Pathfinder\Controller\Api\Rest\Route as RouteController;

class PathfinderIntegrationTest extends TestCase
{
    private static $f3;

    public static function setUpBeforeClass(): void
    {
        // Bootstrap Fat-Free Framework
        require_once __DIR__ . '/../../vendor/autoload.php';

        self::$f3 = \Base::instance();
        self::$f3->set('NAMESPACE', 'Exodus4D\\Pathfinder');
        self::$f3->config(__DIR__ . '/../../app/config.ini', true);

        // Initialize Config (sets up ccpClient and other API clients)
        \Exodus4D\Pathfinder\Lib\Config::instance(self::$f3);

        // Set test environment
        self::$f3->set('TESTING', true);
    }

    /**
     * Test Route controller's searchRoute method with ESI integration
     * This tests the full route search logic: data prep, ESI call, result processing
     */
    public function testRouteControllerSearchRoute(): void
    {
        $routeController = new RouteController();

        $systemFromId = 30000142;  // Jita
        $systemToId = 30002187;    // Amarr

        $filterData = [
            'flag' => 'Shorter',
            'stargates' => true,
            'jumpbridges' => false,
            'wormholes' => false,
            'wormholesReduced' => false,
            'wormholesCritical' => false,
            'wormholesEOL' => true,
            'wormholesThera' => false,
            'wormholesSizeMin' => 'M',
            'excludeTypes' => [],
            'endpointsBubble' => true
        ];

        // Test route search (this uses ESI internally)
        $routeData = $routeController->searchRoute($systemFromId, $systemToId, 50, [], $filterData);

        $this->assertIsArray($routeData);
        $this->assertArrayHasKey('routePossible', $routeData);
        $this->assertArrayHasKey('route', $routeData);
        $this->assertArrayHasKey('searchType', $routeData);

        if($routeData['routePossible']){
            $this->assertNotEmpty($routeData['route']);
            $this->assertGreaterThan(0, $routeData['routeJumps']);

            echo "\n✓ Route search: {$routeData['routeJumps']} jumps via {$routeData['searchType']}\n";
        }
    }

    /**
     * Test Route controller's searchRoute with Safer preference
     * Tests that the flag parameter is properly used
     */
    public function testRouteControllerSearchRouteSafer(): void
    {
        $routeController = new RouteController();

        $systemFromId = 30000142;  // Jita
        $systemToId = 30002187;    // Amarr

        $filterData = [
            'flag' => 'Safer',
            'stargates' => true,
            'jumpbridges' => false,
            'wormholes' => false,
            'wormholesReduced' => false,
            'wormholesCritical' => false,
            'wormholesEOL' => true,
            'wormholesThera' => false,
            'wormholesSizeMin' => 'M',
            'excludeTypes' => [],
            'endpointsBubble' => true
        ];

        $routeData = $routeController->searchRoute($systemFromId, $systemToId, 50, [], $filterData);

        $this->assertIsArray($routeData);
        if($routeData['routePossible']){
            echo "\n✓ Safer route: {$routeData['routeJumps']} jumps\n";
        }
    }

    /**
     * Test SystemModel loading data from ESI
     */
    public function testSystemModelLoadById(): void
    {
        /**
         * @var Universe\SystemModel $systemModel
         */
        $systemModel = Universe\AbstractUniverseModel::getNew('SystemModel');

        $systemId = 30000142; // Jita
        $systemModel->loadById($systemId);

        $this->assertTrue($systemModel->valid());
        $this->assertEquals($systemId, $systemModel->_id);
        $this->assertNotEmpty($systemModel->name);
        $this->assertEquals('Jita', $systemModel->name);

        echo "\n✓ SystemModel loaded: {$systemModel->name} (ID: {$systemModel->_id})\n";
    }

    /**
     * Test ConstellationModel loading data from ESI
     */
    public function testConstellationModelLoadById(): void
    {
        /**
         * @var Universe\ConstellationModel $constellationModel
         */
        $constellationModel = Universe\AbstractUniverseModel::getNew('ConstellationModel');

        $constellationId = 20000020; // Kimotoro
        $constellationModel->loadById($constellationId);

        $this->assertTrue($constellationModel->valid());
        $this->assertEquals($constellationId, $constellationModel->_id);
        $this->assertNotEmpty($constellationModel->name);

        echo "\n✓ ConstellationModel loaded: {$constellationModel->name}\n";
    }

    /**
     * Test RegionModel loading data from ESI
     */
    public function testRegionModelLoadById(): void
    {
        /**
         * @var Universe\RegionModel $regionModel
         */
        $regionModel = Universe\AbstractUniverseModel::getNew('RegionModel');

        $regionId = 10000002; // The Forge
        $regionModel->loadById($regionId);

        $this->assertTrue($regionModel->valid());
        $this->assertEquals($regionId, $regionModel->_id);
        $this->assertNotEmpty($regionModel->name);
        $this->assertEquals('The Forge', $regionModel->name);

        echo "\n✓ RegionModel loaded: {$regionModel->name}\n";
    }

    /**
     * Test StationModel loading data from ESI
     * This requires an access token, so we'll use our test credentials
     */
    public function testStationModelLoadById(): void
    {
        if(!ESI_CLIENT_ID || !ESI_CLIENT_SECRET){
            $this->markTestSkipped('ESI credentials not configured');
        }

        /**
         * @var Universe\StationModel $stationModel
         */
        $stationModel = Universe\AbstractUniverseModel::getNew('StationModel');

        $stationId = 60003760; // Jita 4-4
        $stationModel->loadById($stationId);

        $this->assertTrue($stationModel->valid());
        $this->assertEquals($stationId, $stationModel->_id);
        $this->assertNotEmpty($stationModel->name);

        echo "\n✓ StationModel loaded: {$stationModel->name}\n";
    }

    /**
     * Test TypeModel loading data from ESI
     */
    public function testTypeModelLoadById(): void
    {
        /**
         * @var Universe\TypeModel $typeModel
         */
        $typeModel = Universe\AbstractUniverseModel::getNew('TypeModel');

        $typeId = 34; // Tritanium
        $typeModel->loadById($typeId);

        $this->assertTrue($typeModel->valid());
        $this->assertEquals($typeId, $typeModel->_id);
        $this->assertNotEmpty($typeModel->name);
        $this->assertEquals('Tritanium', $typeModel->name);

        echo "\n✓ TypeModel loaded: {$typeModel->name}\n";
    }

    /**
     * Test AllianceModel loading data from ESI
     */
    public function testAllianceModelLoadById(): void
    {
        /**
         * @var Universe\AllianceModel $allianceModel
         */
        $allianceModel = Universe\AbstractUniverseModel::getNew('AllianceModel');

        $allianceId = 434243723; // C C P Alliance
        $allianceModel->loadById($allianceId);

        $this->assertTrue($allianceModel->valid());
        $this->assertEquals($allianceId, $allianceModel->_id);
        $this->assertNotEmpty($allianceModel->name);

        echo "\n✓ AllianceModel loaded: {$allianceModel->name}\n";
    }

    /**
     * Test CorporationModel loading data from ESI
     */
    public function testCorporationModelLoadById(): void
    {
        /**
         * @var Universe\CorporationModel $corporationModel
         */
        $corporationModel = Universe\AbstractUniverseModel::getNew('CorporationModel');

        $corporationId = 1000169; // NPC Corp
        $corporationModel->loadById($corporationId);

        $this->assertTrue($corporationModel->valid());
        $this->assertEquals($corporationId, $corporationModel->_id);
        $this->assertNotEmpty($corporationModel->name);

        echo "\n✓ CorporationModel loaded: {$corporationModel->name}\n";
    }

    /**
     * Test CorporationModel NPC check
     * This tests business logic that determines if a corporation is NPC
     */
    public function testCorporationModelIsNpc(): void
    {
        /**
         * @var Universe\CorporationModel $corporationModel
         */
        $corporationModel = Universe\AbstractUniverseModel::getNew('CorporationModel');

        $corporationId = 1000169; // Known NPC Corp
        $corporationModel->loadById($corporationId);

        $this->assertTrue($corporationModel->valid());
        $this->assertTrue((bool)$corporationModel->isNPC);

        echo "\n✓ Corporation NPC check: {$corporationModel->name} is " . ($corporationModel->isNPC ? 'NPC' : 'player') . "\n";
    }

    /**
     * Test SystemModel getData method
     * This tests that the model properly formats ESI data for Pathfinder use
     */
    public function testSystemModelGetData(): void
    {
        /**
         * @var Universe\SystemModel $systemModel
         */
        $systemModel = Universe\AbstractUniverseModel::getNew('SystemModel');

        $systemId = 30000142; // Jita
        $systemModel->loadById($systemId);

        $data = $systemModel->getData();

        $this->assertIsObject($data);
        $this->assertEquals($systemId, $data->id);
        $this->assertEquals('Jita', $data->name);
        $this->assertObjectHasProperty('constellation', $data);
        $this->assertObjectHasProperty('security', $data);
        $this->assertObjectHasProperty('trueSec', $data);

        echo "\n✓ SystemModel getData: {$data->name} in {$data->constellation->name} constellation\n";
    }
}
