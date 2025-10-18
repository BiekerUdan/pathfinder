<?php
/**
 * Integration tests for Universe Controller
 * Tests the Universe controller which handles universe searches and constellation data
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Controller\Api\Universe;
use Exodus4D\Pathfinder\Model;

class UniverseControllerTest extends TestCase
{
    private static $f3;
    private $universeController;

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

        // Create a fresh Universe controller instance for each test
        $this->universeController = new Universe();
    }

    /**
     * Test constellationData method with valid constellation ID
     * This tests:
     * - ConstellationModel loading
     * - System relationships
     * - System data retrieval from index
     * - JSON response structure
     */
    public function testConstellationDataValidId(): void
    {
        // Use Kimotoro constellation ID (known to exist in EVE database)
        $constellationId = 20000020; // Kimotoro in The Forge

        $params = ['arg1' => $constellationId];

        // Capture output since controller echoes JSON
        ob_start();
        $this->universeController->constellationData(self::$f3, $params);
        $output = ob_get_clean();

        // Extract JSON from output (there may be PHP warnings before it)
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
        $this->assertObjectHasProperty('systemsData', $response);
        $this->assertIsArray($response->systemsData);

        // Kimotoro should have systems
        $this->assertNotEmpty($response->systemsData, 'Kimotoro constellation should have systems');

        // Check first system has expected structure
        if (!empty($response->systemsData)) {
            $firstSystem = $response->systemsData[0];
            $this->assertIsObject($firstSystem);
        }

        echo "\n✓ Universe constellationData: Loaded " . count($response->systemsData) . " systems from Kimotoro\n";
    }

    /**
     * Test constellationData method with invalid constellation ID
     */
    public function testConstellationDataInvalidId(): void
    {
        $params = ['arg1' => 99999999]; // Non-existent ID

        ob_start();
        $this->universeController->constellationData(self::$f3, $params);
        $output = ob_get_clean();

        $response = json_decode($output);

        $this->assertIsObject($response);
        $this->assertObjectHasProperty('error', $response);
        $this->assertObjectHasProperty('systemsData', $response);

        // Should have empty systems array for invalid ID
        $this->assertEmpty($response->systemsData);

        echo "\n✓ Universe constellationData: Handled invalid constellation ID correctly\n";
    }

    /**
     * Test constellationData method with no ID parameter
     */
    public function testConstellationDataNoId(): void
    {
        $params = []; // No arg1

        ob_start();
        $this->universeController->constellationData(self::$f3, $params);
        $output = ob_get_clean();

        $response = json_decode($output);

        $this->assertIsObject($response);
        $this->assertObjectHasProperty('systemsData', $response);

        // Should have empty systems array when no ID provided
        $this->assertEmpty($response->systemsData);

        echo "\n✓ Universe constellationData: Handled missing ID parameter correctly\n";
    }

    /**
     * Test that ConstellationModel can be loaded and has expected structure
     */
    public function testConstellationModelLoading(): void
    {
        $constellation = Model\Universe\AbstractUniverseModel::getNew('ConstellationModel');
        $constellation->getById(20000020); // Kimotoro

        $this->assertTrue($constellation->valid(), 'Constellation should be valid after loading');
        $this->assertEquals(20000020, $constellation->_id);
        $this->assertNotEmpty($constellation->name);

        // Check relationship to systems
        if ($constellation->systems) {
            $this->assertNotEmpty($constellation->systems, 'Kimotoro should have systems');

            $count = 0;
            foreach ($constellation->systems as $system) {
                $this->assertInstanceOf(Model\Universe\SystemModel::class, $system);
                $count++;
            }
            $this->assertGreaterThan(0, $count, 'Should have at least one system');

            echo "\n✓ ConstellationModel: Loaded " . $constellation->name . " with $count systems\n";
        }
    }

    /**
     * Test that SystemModel can retrieve data from index
     */
    public function testSystemModelFromIndex(): void
    {
        // Get Jita system
        $system = Model\Universe\AbstractUniverseModel::getNew('SystemModel');
        $system->getById(30000142); // Jita

        $this->assertTrue($system->valid());

        $indexData = $system->fromIndex();

        if ($indexData) {
            $this->assertIsObject($indexData);
            // Check that it has some properties (property names may vary)
            $props = get_object_vars($indexData);
            $this->assertNotEmpty($props, 'Index data should have properties');

            echo "\n✓ SystemModel fromIndex: Retrieved Jita index data with " . count($props) . " properties\n";
        } else {
            // fromIndex() might return null if index not built
            $this->markTestSkipped('System index not available - run /setup build index');
        }
    }

    /**
     * Test loading multiple constellations to validate model queries
     */
    public function testMultipleConstellationLoading(): void
    {
        $constellationIds = [
            20000020, // Kimotoro (The Forge)
            20000069, // Genesis (Genesis)
            20000302, // Sinq Laison (Sinq Laison)
        ];

        foreach ($constellationIds as $constellationId) {
            $constellation = Model\Universe\AbstractUniverseModel::getNew('ConstellationModel');
            $constellation->getById($constellationId);

            $this->assertTrue($constellation->valid(), "Constellation $constellationId should be valid");
            $this->assertNotEmpty($constellation->name, "Constellation $constellationId should have a name");
        }

        echo "\n✓ ConstellationModel: Loaded " . count($constellationIds) . " different constellations\n";
    }

    /**
     * Test constellation belongs to region relationship
     */
    public function testConstellationRegionRelationship(): void
    {
        $constellation = Model\Universe\AbstractUniverseModel::getNew('ConstellationModel');
        $constellation->getById(20000020); // Kimotoro

        $this->assertTrue($constellation->valid());

        // Load region via get() which should return the ID
        $regionId = $constellation->get('regionId', true);

        if ($regionId) {
            $region = Model\Universe\AbstractUniverseModel::getNew('RegionModel');
            $region->getById($regionId);

            $this->assertTrue($region->valid(), 'Region should load from constellation regionId');
            $this->assertNotEmpty($region->name);
            $this->assertEquals('The Forge', $region->name); // Kimotoro is in The Forge

            echo "\n✓ ConstellationModel: Validated region relationship (" . $region->name . ")\n";
        } else {
            $this->markTestSkipped('Constellation regionId not available');
        }
    }
}
