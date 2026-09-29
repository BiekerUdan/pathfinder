<?php
/**
 * Batch 1 DB-backed security regression tests.
 *
 * Needs a disposable MySQL/MariaDB (see tests/bootstrap.php PATHFINDER_TEST_DB_*
 * variables, mapped to PF-ENV-* overrides). Skips gracefully when no DB is
 * reachable. Never point it at a real database: fixtures truncate tables.
 *
 * Covers, end to end against the real query layer:
 * - cross-map connection isolation via MapModel::getConnectionById()
 *   (the primitive Connection::put() now relies on instead of global getById)
 * - Route excludeTypes injection neutralized through setDynamicJumpData()
 * - valid excludeTypes filtering still works (bound parameter is functional)
 */

namespace Tests\Integration;

use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Model\Pathfinder as PF;
use Exodus4D\Pathfinder\Model\Universe as UNI;
use Exodus4D\Pathfinder\Controller\Api\Rest\Route as RouteController;
use PHPUnit\Framework\TestCase;

class Batch1SecurityTest extends TestCase
{
    private static $f3;
    private static $char;
    private static $mapA;
    private static $mapB;
    private static $connBId = 0;

    /** PF models (installer order subset) needed for these fixtures */
    private const PF_MODELS = [
        'MapScopeModel', 'MapTypeModel', 'SystemTypeModel', 'SystemStatusModel',
        'RightModel', 'RoleModel', 'ConnectionScopeModel', 'CharacterStatusModel',
        'UserModel', 'MapModel', 'CharacterModel', 'UserCharacterModel',
        'CharacterLogModel', 'CharacterMapModel', 'ActivityLogModel',
        'SystemModel', 'ConnectionModel',
    ];

    private const UNI_MODELS = [
        'DogmaAttributeModel', 'TypeAttributeModel', 'TypeModel', 'GroupModel',
        'CategoryModel', 'FactionModel', 'AllianceModel', 'CorporationModel',
        'RaceModel', 'StationModel', 'StructureModel', 'StargateModel',
        'StarModel', 'PlanetModel', 'SystemModel', 'ConstellationModel',
        'RegionModel', 'SystemNeighbourModel', 'SystemStaticModel',
        'SovereigntyMapModel', 'FactionWarSystemModel',
    ];

    public static function setUpBeforeClass(): void
    {
        // PHP 8.4 deprecations are trapped fatally by F3's handler; the app has
        // pre-existing deprecation noise outside Batch 1 scope, so keep them
        // non-fatal for this DB test process.
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        require_once __DIR__ . '/../../vendor/autoload.php';

        self::$f3 = \Base::instance();
        self::$f3->set('DEBUG', 0);
        self::$f3->set('NAMESPACE', 'Exodus4D\\Pathfinder');
        self::$f3->config(__DIR__ . '/../../app/config.ini', true);
        Config::instance(self::$f3);
        self::$f3->set('TESTING', true);

        if (!self::$f3->DB->getDB('PF') || !self::$f3->DB->getDB('UNIVERSE')) {
            self::$f3 = null;
            return;
        }

        self::setupSchema();
        self::buildFixtures();
    }

    private static function dbAvailable(): bool
    {
        if (self::$f3 === null) {
            self::markTestSkipped('No test database reachable (set PATHFINDER_TEST_DB_*).');
        }
        return true;
    }

    private static function setupSchema(): void
    {
        $pf = self::$f3->DB->getDB('PF');
        foreach (self::PF_MODELS as $model) {
            $class = Config::withNamespace('Model\\Pathfinder\\' . $model);
            $class::setup($pf);
        }
        $uni = self::$f3->DB->getDB('UNIVERSE');
        foreach (self::UNI_MODELS as $model) {
            $class = Config::withNamespace('Model\\Universe\\' . $model);
            $class::setup($uni);
        }
    }

    private static function truncateFixtures(): void
    {
        $wipe = [
            'PF' => ['connection', 'system', 'character_map', 'character_log',
                     'user_character', 'character', 'map', 'activity_log'],
            'UNIVERSE' => ['system', 'constellation', 'region'],
        ];
        foreach ($wipe as $alias => $tables) {
            $db = self::$f3->DB->getDB($alias);
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                try {
                    $db->exec('TRUNCATE TABLE `' . $table . '`');
                } catch (\Throwable $e) {
                    // table may not exist in a partial schema; fixtures will fail loudly below
                }
            }
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private static function buildFixtures(): void
    {
        self::truncateFixtures();

        // universe: region -> constellation -> systems (fake EVE ids)
        $region = UNI\AbstractUniverseModel::getNew('RegionModel');
        $region->name = 'Batch1 Region';
        $region->save();
        $const = UNI\AbstractUniverseModel::getNew('ConstellationModel');
        $const->name = 'Batch1 Constellation';
        $const->regionId = $region;
        $const->save();
        foreach ([31000001, 31000002, 31000003, 31000004] as $eveId) {
            $sys = UNI\AbstractUniverseModel::getNew('SystemModel');
            $sys->set('id', $eveId);
            $sys->name = 'Batch1-' . $eveId;
            $sys->constellationId = $const;
            $sys->security = 'C-R';
            $sys->save();
        }

        // character (map saves require it for tracking)
        self::$char = PF\AbstractPathfinderModel::getNew('CharacterModel');
        self::$char->name = 'Batch1 Char';
        self::$char->ownerHash = 'batch1-owner';
        self::$char->save();

        // two private maps (type=2/filtered static data, scope wh=1)
        self::$mapA = PF\AbstractPathfinderModel::getNew('MapModel');
        self::$mapA->name = 'batch1-A';
        self::$mapA->scopeId = 1;
        self::$mapA->typeId = 2;
        self::$mapA->save(self::$char);

        self::$mapB = PF\AbstractPathfinderModel::getNew('MapModel');
        self::$mapB->name = 'batch1-B';
        self::$mapB->scopeId = 1;
        self::$mapB->typeId = 2;
        self::$mapB->save(self::$char);

        // access grant to map A only
        $link = PF\AbstractPathfinderModel::getNew('CharacterMapModel');
        $link->characterId = self::$char;
        $link->mapId = self::$mapA;
        $link->setActive(true);
        $link->save();

        // systems per map (w-space type=1, unknown status=1)
        $mkSys = function ($map, int $eveId) {
            $sys = PF\AbstractPathfinderModel::getNew('SystemModel');
            $sys->mapId = $map;
            $sys->systemId = $eveId;
            $sys->typeId = 1;
            $sys->statusId = 1;
            $sys->setActive(true);
            $sys->save(self::$char);
            return $sys;
        };
        $mkSys(self::$mapA, 31000001);
        $mkSys(self::$mapA, 31000002);
        $sysB1 = $mkSys(self::$mapB, 31000003);
        $sysB2 = $mkSys(self::$mapB, 31000004);

        // connection lives in map B
        $connB = PF\AbstractPathfinderModel::getNew('ConnectionModel');
        $connB->mapId = self::$mapB;
        $connB->source = $sysB1;
        $connB->target = $sysB2;
        $connB->scope = 'wh';
        $connB->type = ['wh_fresh'];
        $connB->setActive(true);
        $connB->save(self::$char);
        self::$connBId = (int)$connB->_id;
    }

    public function testCharacterSeesOnlyGrantedMap(): void
    {
        self::dbAvailable();
        $this->assertTrue(self::$mapA->hasAccess(self::$char));
        $this->assertFalse(self::$mapB->hasAccess(self::$char));
    }

    public function testCrossMapConnectionInvisibleViaAuthorizedMap(): void
    {
        self::dbAvailable();
        // the exact primitive Connection::put() uses: unknown/foreign ids must
        // resolve to null so nothing can be moved or modified cross-map
        $this->assertNull(self::$mapA->getConnectionById(self::$connBId));
        $this->assertNotNull(self::$mapB->getConnectionById(self::$connBId));
        $this->assertNull(self::$mapA->getConnectionById(999999999));
    }

    /**
     * drive the real route query with hostile excludeTypes input
     */
    private function runDynamicJumpData(array $excludeTypes): array
    {
        $route = new RouteController();
        $reset = new \ReflectionMethod($route, 'resetJumpData');
        $reset->setAccessible(true);
        $method = new \ReflectionMethod($route, 'setDynamicJumpData');
        $method->setAccessible(true);
        $prop = new \ReflectionProperty($route, 'jumpArray');
        $prop->setAccessible(true);

        $filter = [
            'stargates' => false, 'jumpbridges' => false, 'wormholes' => true,
            'wormholesReduced' => true, 'wormholesCritical' => true,
            'wormholesEOL' => true, 'excludeTypes' => $excludeTypes,
            'endpointsBubble' => true, 'wormholesThera' => false,
        ];
        $reset->invoke($route);
        $method->invoke($route, [(int)self::$mapB->_id], $filter);
        return $prop->getValue($route);
    }

    public function testRouteExcludeTypesInjectionNeutralized(): void
    {
        self::dbAvailable();
        $clean = $this->runDynamicJumpData([]);
        $this->assertArrayHasKey(31000003, $clean, 'fixture link must be present without filters');

        // non-destructive hostile inputs: tautology, stacked-query syntax,
        // comment truncation, regexp metacharacters
        $hostile = [
            "wh_fresh' OR '1'='1",
            "wh_fresh' OR '1'='1' -- ",
            'wh_fresh"; DROP TABLE `connection',
            '.*',
            'wh_(fresh|reduced)',
        ];
        foreach ($hostile as $payload) {
            $result = $this->runDynamicJumpData([$payload]);
            $this->assertEquals(
                $clean, $result,
                'hostile excludeTypes must be neutralized, not executed: ' . $payload
            );
        }

        // mixed valid + hostile keeps only the valid entry
        $mixed = $this->runDynamicJumpData(['wh_jump_mass_s', "wh_fresh' OR 1=1 --"]);
        $this->assertEquals($clean, $mixed);
    }

    public function testRouteExcludeTypesValidExclusionStillWorks(): void
    {
        self::dbAvailable();
        // the connection type is wh_fresh: excluding it must remove the link,
        // proving the bound-parameter filter is functional, not just neutered
        $filtered = $this->runDynamicJumpData(['wh_fresh']);
        $this->assertArrayNotHasKey(31000003, $filtered);
        $this->assertArrayNotHasKey(31000004, $filtered);

        // unrelated valid type must not exclude anything
        $other = $this->runDynamicJumpData(['wh_jump_mass_xl']);
        $this->assertArrayHasKey(31000003, $other);
    }
}
