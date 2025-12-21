<?php
/**
 * Debug test to inspect actual API responses
 */

namespace Tests\ESI;

class DebugResponsesTest extends BaseIntegrationTest
{
    /**
     * Debug: Get character response
     */
    public function testDebugGetCharacter(): void
    {
        $characterId = 2117258361; // Curbside Pickup

        $response = $this->esiClient->send('getCharacter', $characterId);

        echo "\n=== getCharacter Response ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);
    }

    /**
     * Debug: Verify character response
     */
    public function testDebugVerifyCharacter(): void
    {
        $response = $this->ssoClient->send(
            'getVerifyCharacter',
            $this->getAccessToken()
        );

        echo "\n=== getVerifyCharacter Response ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);
    }

    /**
     * Debug: Get character affiliation
     */
    public function testDebugGetCharacterAffiliation(): void
    {
        $characterIds = [2117258361, $this->getCharacterId()]; // Curbside Pickup + authenticated character

        $response = $this->esiClient->send('getCharacterAffiliation', $characterIds);

        echo "\n=== getCharacterAffiliation Response ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);
    }

    /**
     * Debug: Get universe system
     */
    public function testDebugGetUniverseSystem(): void
    {
        $systemId = 30000142; // Jita

        $response = $this->esiClient->send('getUniverseSystem', $systemId);

        echo "\n=== getUniverseSystem Response ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);
    }

    /**
     * Debug: Get route
     */
    public function testDebugGetRoute(): void
    {
        $sourceId = 30000142;  // Jita
        $targetId = 30002187;  // Amarr

        $response = $this->esiClient->send('getRoute', $sourceId, $targetId);

        echo "\n=== getRoute Response ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);
    }

    /**
     * Debug: Get universe star
     */
    public function testDebugGetUniverseStar(): void
    {
        $starId = 40009076; // Jita's star (correct ID from system data)

        $response = $this->esiClient->send('getUniverseStar', $starId);

        echo "\n=== getUniverseStar Response ===\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";

        $this->assertIsArray($response);
    }
}
