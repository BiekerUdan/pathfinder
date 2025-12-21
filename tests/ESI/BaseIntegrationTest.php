<?php
/**
 * Base class for ESI Integration Tests
 */

namespace Tests\ESI;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\ESI\Client\Ccp\Sso\Sso;
use Exodus4D\Pathfinder\ESI\Client\Ccp\Esi\Esi;

abstract class BaseIntegrationTest extends TestCase
{
    /**
     * @var string|null
     */
    protected static $accessToken;

    /**
     * @var int|null
     */
    protected static $tokenExpiresAt;

    /**
     * @var Esi
     */
    protected $esiClient;

    /**
     * @var Sso
     */
    protected $ssoClient;

    /**
     * Set up before running any tests in the class
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Check if credentials are configured
        if (empty(ESI_REFRESH_TOKEN) || empty(ESI_CLIENT_ID) || empty(ESI_CLIENT_SECRET)) {
            self::markTestSkipped(
                'ESI credentials not configured. Copy .env.example to .env and add your credentials.'
            );
        }

        // Get fresh access token using refresh token
        self::refreshAccessToken();
    }

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Check if access token is expired and refresh if needed
        if (self::isTokenExpired()) {
            self::refreshAccessToken();
        }

        // Initialize ESI client
        $this->esiClient = new Esi(ESI_BASE_URL);
        $this->esiClient->setDataSource(ESI_DATASOURCE);

        // Initialize SSO client
        $this->ssoClient = new Sso(SSO_BASE_URL);
    }

    /**
     * Refresh the access token using the refresh token
     */
    protected static function refreshAccessToken(): void
    {
        $sso = new Sso(SSO_BASE_URL);

        echo "Refreshing access token...\n";

        try {
            $tokenData = $sso->send(
                'getAccess',
                [ESI_CLIENT_ID, ESI_CLIENT_SECRET],
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => ESI_REFRESH_TOKEN
                ]
            );

            if (isset($tokenData['accessToken'])) {
                self::$accessToken = $tokenData['accessToken'];
                // Set expiration time (access tokens typically expire in 20 minutes)
                $expiresIn = $tokenData['expiresIn'] ?? 1200;
                self::$tokenExpiresAt = time() + $expiresIn - 60; // Refresh 1 minute early
                echo "Access token refreshed successfully (expires in {$expiresIn}s)\n";
            } else {
                throw new \RuntimeException('Failed to refresh access token: ' . json_encode($tokenData));
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to refresh access token: ' . $e->getMessage());
        }
    }

    /**
     * Check if the current access token is expired
     *
     * @return bool
     */
    protected static function isTokenExpired(): bool
    {
        if (self::$tokenExpiresAt === null) {
            return true;
        }

        return time() >= self::$tokenExpiresAt;
    }

    /**
     * Get the current access token
     *
     * @return string
     */
    protected function getAccessToken(): string
    {
        if (self::isTokenExpired()) {
            self::refreshAccessToken();
        }

        return self::$accessToken ?? '';
    }

    /**
     * Get the test character ID
     *
     * @return int
     */
    protected function getCharacterId(): int
    {
        return ESI_CHARACTER_ID;
    }

    /**
     * Helper to assert no error in response
     *
     * @param array $response
     * @param string $message
     */
    protected function assertNoError(array $response, string $message = ''): void
    {
        $this->assertArrayNotHasKey('error', $response,
            $message ?: 'Response contains an error: ' . json_encode($response));
    }

    /**
     * Helper to print response for debugging
     *
     * @param array $response
     * @param string $label
     */
    protected function printResponse(array $response, string $label = 'Response'): void
    {
        echo "\n{$label}:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    }

    /**
     * Skip test if character ID is not set
     */
    protected function skipIfNoCharacterId(): void
    {
        if (empty($this->getCharacterId())) {
            $this->markTestSkipped('ESI_CHARACTER_ID not configured in .env file');
        }
    }
}
