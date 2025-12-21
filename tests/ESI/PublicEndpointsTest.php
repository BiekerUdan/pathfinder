<?php
/**
 * Integration tests for public ESI endpoints (no authentication required)
 */

namespace Tests\ESI;

class PublicEndpointsTest extends BaseIntegrationTest
{
    /**
     * Test getting server status
     */
    public function testGetServerStatus(): void
    {
        $response = $this->esiClient->send('getServerStatus');

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Server status should not return an error');
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('playerCount', $response['status']);
        $this->assertArrayHasKey('startTime', $response['status']);

        echo "Server Status: {$response['status']['playerCount']} players online\n";
    }

    /**
     * Test getting all universe regions
     */
    public function testGetUniverseRegions(): void
    {
        $response = $this->esiClient->send('getUniverseRegions');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should return array of region IDs');
        $this->assertGreaterThan(50, count($response), 'Should have more than 50 regions');

        echo "Found " . count($response) . " regions\n";
    }

    /**
     * Test getting a specific region
     */
    public function testGetUniverseRegion(): void
    {
        $regionId = 10000002; // The Forge

        $response = $this->esiClient->send('getUniverseRegion', $regionId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('constellations', $response);
        $this->assertEquals($regionId, $response['id']);
        $this->assertEquals('The Forge', $response['name']);

        echo "Region: {$response['name']} with " . count($response['constellations']) . " constellations\n";
    }

    /**
     * Test getting all universe systems
     */
    public function testGetUniverseSystems(): void
    {
        $response = $this->esiClient->send('getUniverseSystems');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertGreaterThan(5000, count($response), 'Should have more than 5000 systems');

        echo "Found " . count($response) . " systems\n";
    }

    /**
     * Test getting a specific system
     */
    public function testGetUniverseSystem(): void
    {
        $systemId = 30000142; // Jita

        $response = $this->esiClient->send('getUniverseSystem', $systemId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('constellationId', $response);
        $this->assertArrayHasKey('securityStatus', $response);
        $this->assertEquals($systemId, $response['id']);
        $this->assertEquals('Jita', $response['name']);

        echo "System: {$response['name']} (security: {$response['securityStatus']})\n";
    }

    /**
     * Test getting system jumps
     */
    public function testGetUniverseJumps(): void
    {
        $response = $this->esiClient->send('getUniverseJumps');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should have jump data for active systems');

        echo "Jump data available for " . count($response) . " systems\n";
    }

    /**
     * Test getting system kills
     */
    public function testGetUniverseKills(): void
    {
        $response = $this->esiClient->send('getUniverseKills');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should have kill data for active systems');

        echo "Kill data available for " . count($response) . " systems\n";
    }

    /**
     * Test getting a character by ID
     */
    public function testGetCharacter(): void
    {
        $characterId = 2117258361; // Curbside Pickup

        $response = $this->esiClient->send('getCharacter', $characterId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertEquals($characterId, $response['id']);

        echo "Character: {$response['name']}\n";
    }

    /**
     * Test getting corporation info
     */
    public function testGetCorporation(): void
    {
        $corporationId = 1000169; // CCP corporation

        $response = $this->esiClient->send('getCorporation', $corporationId);

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('ticker', $response);
        $this->assertEquals($corporationId, $response['id']);

        echo "Corporation: {$response['name']} [{$response['ticker']}]\n";
    }

    /**
     * Test getting alliance info
     */
    public function testGetAlliance(): void
    {
        $allianceId = 434243723; // C C P Alliance

        $response = $this->esiClient->send('getAlliance', $allianceId);

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('ticker', $response);
        $this->assertEquals($allianceId, $response['id']);

        echo "Alliance: {$response['name']} <{$response['ticker']}>\n";
    }

    /**
     * Test getting universe names for multiple IDs
     */
    public function testGetUniverseNames(): void
    {
        $universeIds = [
            30000142,   // Jita system
            60003760,   // Jita 4-4 station
            1000169     // CCP Corporation
        ];

        $response = $this->esiClient->send('getUniverseNames', $universeIds);

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertNotEmpty($response);

        // Check that we got results for different categories
        $this->assertArrayHasKey('system', $response);
        $this->assertArrayHasKey('station', $response);
        $this->assertArrayHasKey('corporation', $response);

        echo "Resolved " . count($response) . " entity types\n";
    }

    /**
     * Test getting sovereignty map
     */
    public function testGetSovereigntyMap(): void
    {
        $response = $this->esiClient->send('getSovereigntyMap');

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertArrayHasKey('map', $response);
        $this->assertNotEmpty($response['map'], 'Should have sovereignty data');

        echo "Sovereignty data for " . count($response['map']) . " systems\n";
    }

    /**
     * Test getting universe type
     */
    public function testGetUniverseType(): void
    {
        $typeId = 34; // Tritanium

        $response = $this->esiClient->send('getUniverseType', $typeId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('groupId', $response);
        $this->assertEquals($typeId, $response['id']);
        $this->assertEquals('Tritanium', $response['name']);

        echo "Type: {$response['name']}\n";
    }

    /**
     * Test calculating route between systems
     */
    public function testGetRoute(): void
    {
        $sourceId = 30000142;  // Jita
        $targetId = 30002187;  // Amarr

        $response = $this->esiClient->send('getRoute', $sourceId, $targetId);

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertArrayHasKey('route', $response);
        $this->assertNotEmpty($response['route']);
        $this->assertGreaterThan(1, count($response['route']), 'Route should have multiple systems');

        echo "Route from Jita to Amarr: " . count($response['route']) . " jumps\n";
    }

    /**
     * Test getting all constellations
     */
    public function testGetUniverseConstellations(): void
    {
        $response = $this->esiClient->send('getUniverseConstellations');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should return array of constellation IDs');
        $this->assertGreaterThan(500, count($response), 'Should have more than 500 constellations');

        echo "Found " . count($response) . " constellations\n";
    }

    /**
     * Test getting a specific constellation
     */
    public function testGetUniverseConstellation(): void
    {
        $constellationId = 20000020; // Kimotoro (Jita's constellation)

        $response = $this->esiClient->send('getUniverseConstellation', $constellationId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('systems', $response);
        $this->assertEquals($constellationId, $response['id']);

        echo "Constellation: {$response['name']} with " . count($response['systems']) . " systems\n";
    }

    /**
     * Test getting star info
     */
    public function testGetUniverseStar(): void
    {
        $starId = 40009076; // Jita's star (correct ID from system data)

        $response = $this->esiClient->send('getUniverseStar', $starId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('typeId', $response);
        $this->assertEquals($starId, $response['id']);

        echo "Star ID {$response['id']} (Type: {$response['typeId']})\n";
    }

    /**
     * Test getting planet info
     */
    public function testGetUniversePlanet(): void
    {
        $planetId = 40009077; // Jita I

        $response = $this->esiClient->send('getUniversePlanet', $planetId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('typeId', $response);
        $this->assertEquals($planetId, $response['id']);

        echo "Planet ID {$response['id']} (Type: {$response['typeId']})\n";
    }

    /**
     * Test getting stargate info
     */
    public function testGetUniverseStargate(): void
    {
        $stargateId = 50000342; // Jita stargate

        $response = $this->esiClient->send('getUniverseStargate', $stargateId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('typeId', $response);
        $this->assertArrayHasKey('systemId', $response);
        $this->assertEquals($stargateId, $response['id']);

        echo "Stargate: {$response['name']} in system {$response['systemId']}\n";
    }

    /**
     * Test getting station info
     */
    public function testGetUniverseStation(): void
    {
        $stationId = 60003760; // Jita 4-4

        $response = $this->esiClient->send('getUniverseStation', $stationId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('typeId', $response);
        $this->assertEquals($stationId, $response['id']);

        echo "Station: {$response['name']}\n";
    }

    /**
     * Test getting all categories
     */
    public function testGetUniverseCategories(): void
    {
        $response = $this->esiClient->send('getUniverseCategories');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should return array of category IDs');
        $this->assertGreaterThan(10, count($response), 'Should have more than 10 categories');

        echo "Found " . count($response) . " categories\n";
    }

    /**
     * Test getting a specific category
     */
    public function testGetUniverseCategory(): void
    {
        $categoryId = 6; // Ship category

        $response = $this->esiClient->send('getUniverseCategory', $categoryId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('groups', $response);
        $this->assertEquals($categoryId, $response['id']);

        echo "Category: {$response['name']} with " . count($response['groups']) . " groups\n";
    }

    /**
     * Test getting all groups
     */
    public function testGetUniverseGroups(): void
    {
        $response = $this->esiClient->send('getUniverseGroups');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should return array of group IDs');
        $this->assertGreaterThan(100, count($response), 'Should have more than 100 groups');

        echo "Found " . count($response) . " groups\n";
    }

    /**
     * Test getting a specific group
     */
    public function testGetUniverseGroup(): void
    {
        $groupId = 25; // Frigate group

        $response = $this->esiClient->send('getUniverseGroup', $groupId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('categoryId', $response);
        $this->assertEquals($groupId, $response['id']);

        echo "Group: {$response['name']} (Category: {$response['categoryId']})\n";
    }

    /**
     * Test getting dogma attribute
     */
    public function testGetDogmaAttribute(): void
    {
        $attributeId = 9; // Capacitor capacity

        $response = $this->esiClient->send('getDogmaAttribute', $attributeId);

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertEquals($attributeId, $response['id']);

        echo "Dogma Attribute: {$response['name']}\n";
    }

    /**
     * Test getting faction warfare systems
     */
    public function testGetFactionWarSystems(): void
    {
        $response = $this->esiClient->send('getFactionWarSystems');

        $this->assertIsArray($response);
        $this->assertNoError($response);
        $this->assertArrayHasKey('systems', $response);
        $this->assertNotEmpty($response['systems'], 'Should have FW system data');

        echo "FW data for " . count($response['systems']) . " systems\n";
    }

    /**
     * Test getting universe faction
     */
    public function testGetUniverseFaction(): void
    {
        $factionId = 500001; // Caldari State

        $response = $this->esiClient->send('getUniverseFaction', $factionId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertEquals($factionId, $response['id']);

        echo "Faction: {$response['name']}\n";
    }

    /**
     * Test getting universe race
     */
    public function testGetUniverseRace(): void
    {
        $raceId = 1; // Caldari

        $response = $this->esiClient->send('getUniverseRace', $raceId);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertEquals($raceId, $response['id']);

        echo "Race: {$response['name']}\n";
    }

    /**
     * Test getting NPC corporations
     */
    public function testGetNpcCorporations(): void
    {
        $response = $this->esiClient->send('getNpcCorporations');

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should return array of NPC corporation IDs');
        $this->assertGreaterThan(100, count($response), 'Should have more than 100 NPC corps');

        echo "Found " . count($response) . " NPC corporations\n";
    }
}
