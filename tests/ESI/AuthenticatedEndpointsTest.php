<?php
/**
 * Integration tests for authenticated ESI endpoints (require access token)
 */

namespace Tests\ESI;

class AuthenticatedEndpointsTest extends BaseIntegrationTest
{
    /**
     * Test getting character location
     */
    public function testGetCharacterLocation(): void
    {
        $this->skipIfNoCharacterId();

        $response = $this->esiClient->send(
            'getCharacterLocation',
            $this->getCharacterId(),
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Character location should not return an error');

        // Response should have system information
        if (isset($response['system'])) {
            $this->assertArrayHasKey('id', $response['system']);
            echo "Character is in system ID: {$response['system']['id']}\n";
        }
    }

    /**
     * Test getting character ship
     */
    public function testGetCharacterShip(): void
    {
        $this->skipIfNoCharacterId();

        $response = $this->esiClient->send(
            'getCharacterShip',
            $this->getCharacterId(),
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Character ship should not return an error');

        if (isset($response['ship'])) {
            $this->assertArrayHasKey('typeId', $response['ship']);
            $this->assertArrayHasKey('name', $response['ship']);
            echo "Character is flying: {$response['ship']['name']} (Type ID: {$response['ship']['typeId']})\n";
        }
    }

    /**
     * Test getting character online status
     */
    public function testGetCharacterOnline(): void
    {
        $this->skipIfNoCharacterId();

        $response = $this->esiClient->send(
            'getCharacterOnline',
            $this->getCharacterId(),
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Character online status should not return an error');

        if (isset($response['online'])) {
            $status = $response['online'] ? 'ONLINE' : 'OFFLINE';
            echo "Character is: {$status}\n";

            if (isset($response['lastLogin'])) {
                echo "Last login: {$response['lastLogin']}\n";
            }
            if (isset($response['lastLogout'])) {
                echo "Last logout: {$response['lastLogout']}\n";
            }
        }
    }

    /**
     * Test getting character roles
     */
    public function testGetCharacterRoles(): void
    {
        $this->skipIfNoCharacterId();

        $response = $this->esiClient->send(
            'getCharacterRoles',
            $this->getCharacterId(),
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Character roles should not return an error');

        if (isset($response['roles'])) {
            $this->assertIsArray($response['roles']);
            echo "Character has " . count($response['roles']) . " role(s)\n";
        }
    }

    /**
     * Test getting character clones
     */
    public function testGetCharacterClones(): void
    {
        $this->skipIfNoCharacterId();

        $response = $this->esiClient->send(
            'getCharacterClones',
            $this->getCharacterId(),
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Character clones should not return an error');

        if (isset($response['home']['location'])) {
            echo "Home station ID: {$response['home']['location']['id']}\n";
        }
    }

    /**
     * Test verifying character via SSO
     */
    public function testVerifyCharacter(): void
    {
        $response = $this->ssoClient->send(
            'getVerifyCharacter',
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertEquals($this->getCharacterId(), $response['id'], 'Character ID should match');

        echo "Verified character: {$response['name']} (ID: {$response['id']})\n";
    }

    /**
     * Test searching with authentication (allows structure search)
     */
    public function testSearch(): void
    {
        $this->skipIfNoCharacterId();

        $categories = ['solar_system'];
        $searchTerm = 'Jita';

        $response = $this->esiClient->send(
            'search',
            $categories,
            $searchTerm,
            $this->getCharacterId(),
            $this->getAccessToken(),
            false // strict = false
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Search should not return an error');

        if (isset($response['solarSystem'])) {
            $this->assertNotEmpty($response['solarSystem'], 'Should find Jita system');
            echo "Found " . count($response['solarSystem']) . " system(s) matching 'Jita'\n";
        }
    }

    /**
     * Test batch request with multiple authenticated endpoints
     */
    public function testBatchAuthenticatedRequests(): void
    {
        $this->skipIfNoCharacterId();

        $characterId = $this->getCharacterId();
        $accessToken = $this->getAccessToken();

        $configs = [
            ['getCharacterLocation', $characterId, $accessToken],
            ['getCharacterShip', $characterId, $accessToken],
            ['getCharacterOnline', $characterId, $accessToken]
        ];

        $responses = $this->esiClient->sendBatch($configs);

        $this->assertIsArray($responses);
        $this->assertCount(3, $responses, 'Should receive 3 responses');

        echo "Batch request completed:\n";
        foreach ($responses as $index => $response) {
            $this->assertIsArray($response);
            echo "  - Response " . ($index + 1) . ": " . (isset($response['error']) ? 'ERROR' : 'OK') . "\n";
        }
    }

    /**
     * Test getting character affiliation (bulk lookup)
     */
    public function testGetCharacterAffiliation(): void
    {
        $this->skipIfNoCharacterId();

        $characterIds = [
            $this->getCharacterId(),
            2117258361 // Curbside Pickup
        ];

        $response = $this->esiClient->send('getCharacterAffiliation', $characterIds);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Should return affiliation data');
        $this->assertGreaterThanOrEqual(1, count($response), 'Should have at least one affiliation');

        foreach ($response as $affiliation) {
            if (isset($affiliation['character']['id'])) {
                echo "Character {$affiliation['character']['id']} ";
                if (isset($affiliation['corporation']['id'])) {
                    echo "is in corporation {$affiliation['corporation']['id']}";
                }
                if (isset($affiliation['alliance']['id'])) {
                    echo " and alliance {$affiliation['alliance']['id']}";
                }
                echo "\n";
            }
        }
    }

    /**
     * Test getting corporation roles (requires director role)
     * Note: This may fail if character doesn't have proper roles
     */
    public function testGetCorporationRoles(): void
    {
        $this->skipIfNoCharacterId();

        // Get the character's corporation ID first
        $characterResponse = $this->esiClient->send('getCharacter', $this->getCharacterId());

        if (!isset($characterResponse['corporation']['id'])) {
            $this->markTestSkipped('Could not get character corporation ID');
        }

        $corporationId = $characterResponse['corporation']['id'];

        $response = $this->esiClient->send(
            'getCorporationRoles',
            $corporationId,
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        // This endpoint may return an error if character doesn't have proper roles
        // That's okay, we just want to test that it doesn't crash

        if (isset($response['roles']) && is_array($response['roles'])) {
            echo "Corporation has " . count($response['roles']) . " members with roles\n";
        } elseif (isset($response['error'])) {
            echo "Corporation roles: Access denied (expected if not director)\n";
        }
    }

    /**
     * Test getting universe structure (requires structure access)
     * Note: This may fail if character doesn't have access to the structure
     */
    public function testGetUniverseStructure(): void
    {
        $this->skipIfNoCharacterId();

        // Use a common player structure ID (may not have access)
        $structureId = 1000000000000; // Placeholder structure ID

        $response = $this->esiClient->send(
            'getUniverseStructure',
            $structureId,
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        // This endpoint will likely return an error for most structures
        // That's okay, we just want to test that it doesn't crash

        if (isset($response['id'])) {
            echo "Structure: {$response['name']}\n";
        } elseif (isset($response['error'])) {
            echo "Structure access: Denied (expected for most structures)\n";
        }
    }

    /**
     * Test setting waypoint in-game
     * Note: This actually sets a waypoint in the client!
     */
    public function testSetWaypoint(): void
    {
        $this->skipIfNoCharacterId();

        $destinationId = 30000142; // Jita
        $options = [
            'addToBeginning' => false,
            'clearOtherWaypoints' => false
        ];

        $response = $this->esiClient->send(
            'setWaypoint',
            $destinationId,
            $this->getAccessToken(),
            $options
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Set waypoint should not return an error');

        echo "Waypoint set to destination ID: {$destinationId}\n";
    }

    /**
     * Test opening information window in-game
     * Note: This actually opens a window in the client!
     */
    public function testOpenWindow(): void
    {
        $this->skipIfNoCharacterId();

        $targetId = 30000142; // Jita system

        $response = $this->esiClient->send(
            'openWindow',
            $targetId,
            $this->getAccessToken()
        );

        $this->assertIsArray($response);
        $this->assertNoError($response, 'Open window should not return an error');

        echo "Window opened for target ID: {$targetId}\n";
    }
}
