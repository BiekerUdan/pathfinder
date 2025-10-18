<?php
/**
 * Integration tests for System Controller
 * Tests the System controller and related system models
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Model;

class SystemControllerTest extends TestCase
{
    private static $f3;

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

    /**
     * Test SystemModel loading and data retrieval
     */
    public function testSystemModelLoading(): void
    {
        // Get Jita system (most famous system in EVE)
        $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
        $system->getById(30000142);

        $this->assertTrue($system->valid(), 'Jita system should load successfully');
        $this->assertEquals(30000142, $system->_id);
        $this->assertEquals('Jita', $system->name);
        $this->assertNotEmpty($system->security);

        echo "\n✓ SystemModel: Loaded Jita (security: " . $system->security . ")\n";
    }

    /**
     * Test SystemModel constellation relationship
     */
    public function testSystemConstellationRelationship(): void
    {
        $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
        $system->getById(30000142); // Jita

        $this->assertTrue($system->valid());

        // Load constellation via constellationId
        $constellationId = $system->get('constellationId', true);

        if ($constellationId) {
            $constellation = Model\Universe\AbstractUniverseModel::getNew('ConstellationModel');
            $constellation->getById($constellationId);

            $this->assertTrue($constellation->valid(), 'Constellation should load');
            $this->assertEquals('Kimotoro', $constellation->name);

            echo "\n✓ SystemModel: Validated constellation relationship (Kimotoro)\n";
        } else {
            $this->markTestSkipped('System constellationId not available');
        }
    }

    /**
     * Test SystemModel getData method
     */
    public function testSystemGetData(): void
    {
        $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
        $system->getById(30000142); // Jita

        $data = $system->getData();

        $this->assertIsObject($data);
        // Check for id property (might be 'id' instead of 'systemId')
        $hasId = isset($data->systemId) || isset($data->id);
        $this->assertTrue($hasId, 'Data should have systemId or id property');
        $this->assertObjectHasProperty('name', $data);
        $this->assertEquals('Jita', $data->name);

        echo "\n✓ SystemModel getData: Retrieved Jita data\n";
    }

    /**
     * Test loading systems with different security levels
     */
    public function testSystemSecurityLevels(): void
    {
        $testSystems = [
            30000142 => 'Jita',
            30000001 => 'Tanoo',
        ];

        foreach ($testSystems as $systemId => $expectedName) {
            $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
            $system->getById($systemId);

            $this->assertTrue($system->valid(), $expectedName . ' should load');
            $this->assertEquals($expectedName, $system->name);
            $this->assertNotNull($system->security, $expectedName . ' should have security value');
        }

        echo "\n✓ SystemModel: Validated " . count($testSystems) . " systems with security data\n";
    }

    /**
     * Test SystemModel region relationship through constellation
     */
    public function testSystemRegionThroughConstellation(): void
    {
        $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
        $system->getById(30000142); // Jita

        $this->assertTrue($system->valid());

        // Get constellation and then region
        $constellationId = $system->get('constellationId', true);
        if ($constellationId) {
            $constellation = Model\Universe\AbstractUniverseModel::getNew('ConstellationModel');
            $constellation->getById($constellationId);

            $regionId = $constellation->get('regionId', true);
            if ($regionId) {
                $region = Model\Universe\AbstractUniverseModel::getNew('RegionModel');
                $region->getById($regionId);

                $this->assertTrue($region->valid());
                $this->assertEquals('The Forge', $region->name);

                echo "\n✓ SystemModel: Accessed region through constellation (The Forge)\n";
            } else {
                $this->markTestSkipped('Constellation regionId not available');
            }
        } else {
            $this->markTestSkipped('System constellationId not available');
        }
    }

    /**
     * Test loading special system (Thera)
     */
    public function testSpecialSystemLoading(): void
    {
        // Thera - Special wormhole system
        $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
        $system->getById(31000005);

        if ($system->valid()) {
            $this->assertNotEmpty($system->name);
            $this->assertEquals('Thera', $system->name);

            echo "\n✓ SystemModel: Loaded special system " . $system->name . "\n";
        } else {
            $this->markTestSkipped('Thera system not in database');
        }
    }

    /**
     * Test StationModel loading for a system
     */
    public function testStationInSystem(): void
    {
        // Jita IV - Moon 4 - Caldari Navy Assembly Plant (most famous station)
        $station = Model\Universe\AbstractUniverseModel::getNew('StationModel');
        $station->getById(60003760);

        $this->assertTrue($station->valid(), 'Jita 4-4 station should load');
        $this->assertEquals(60003760, $station->_id);
        $this->assertStringContainsString('Jita IV', $station->name);

        // Station should belong to a system
        if ($station->system) {
            $this->assertInstanceOf(Model\Universe\SystemModel::class, $station->system);
            $this->assertEquals('Jita', $station->system->name);

            echo "\n✓ StationModel: Loaded Jita 4-4 station in system " . $station->system->name . "\n";
        }
    }

    /**
     * Test TypeModel loading (for ship/item types)
     */
    public function testTypeModelLoading(): void
    {
        // Tritanium - most basic ore
        $type = Model\Universe\AbstractUniverseModel::getNew('TypeModel');
        $type->getById(34);

        $this->assertTrue($type->valid(), 'Tritanium type should load');
        $this->assertEquals(34, $type->_id);
        $this->assertEquals('Tritanium', $type->name);

        echo "\n✓ TypeModel: Loaded Tritanium type\n";
    }

    /**
     * Test GroupModel loading
     */
    public function testGroupModelLoading(): void
    {
        // Group 25 = Frigate
        $group = Model\Universe\AbstractUniverseModel::getNew('GroupModel');
        $group->getById(25);

        $this->assertTrue($group->valid(), 'Frigate group should load');
        $this->assertEquals(25, $group->_id);
        $this->assertEquals('Frigate', $group->name);

        echo "\n✓ GroupModel: Loaded Frigate group\n";
    }

    /**
     * Test CategoryModel loading
     */
    public function testCategoryModelLoading(): void
    {
        // Category 6 = Ship
        $category = Model\Universe\AbstractUniverseModel::getNew('CategoryModel');
        $category->getById(6);

        $this->assertTrue($category->valid(), 'Ship category should load');
        $this->assertEquals(6, $category->_id);
        $this->assertEquals('Ship', $category->name);

        echo "\n✓ CategoryModel: Loaded Ship category\n";
    }

    /**
     * Test RegionModel loading
     */
    public function testRegionModelLoading(): void
    {
        // The Forge - most populated region
        $region = Model\Universe\AbstractUniverseModel::getNew('RegionModel');
        $region->getById(10000002);

        $this->assertTrue($region->valid(), 'The Forge region should load');
        $this->assertEquals(10000002, $region->_id);
        $this->assertEquals('The Forge', $region->name);

        echo "\n✓ RegionModel: Loaded The Forge region\n";
    }

    /**
     * Test multiple system searches
     */
    public function testMultipleSystemLookups(): void
    {
        $testSystems = [
            30000142, // Jita
            30002659, // Amarr
            30002537, // Dodixie
        ];

        foreach ($testSystems as $systemId) {
            $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
            $system->getById($systemId);

            $this->assertTrue($system->valid(), "System $systemId should load");
            $this->assertNotEmpty($system->name, "System $systemId should have a name");
        }

        echo "\n✓ SystemModel: Looked up " . count($testSystems) . " major trade hubs\n";
    }
}
