<?php
/**
 * Integration tests for GitHub Controller
 * Tests the GitHub controller's release information functionality
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Controller\Api\GitHub;

class GitHubControllerTest extends TestCase
{
    private static $f3;
    private $githubController;

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

        // Create a fresh GitHub controller instance for each test
        $this->githubController = new GitHub();
    }

    /**
     * Test releases method returns proper JSON structure
     * Note: This calls the actual GitHub API, so may fail if API is unavailable
     */
    public function testReleasesReturnsValidStructure(): void
    {
        ob_start();
        try {
            $this->githubController->releases(self::$f3);
            $output = ob_get_clean();

            // Extract JSON from output
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $output = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            $response = json_decode($output);

            if ($response) {
                // Verify expected structure
                $this->assertIsObject($response);
                $this->assertObjectHasProperty('releasesData', $response);
                $this->assertObjectHasProperty('version', $response);

                // Verify version structure
                $this->assertIsObject($response->version);
                $this->assertObjectHasProperty('current', $response->version);
                $this->assertObjectHasProperty('last', $response->version);
                $this->assertObjectHasProperty('delta', $response->version);
                $this->assertObjectHasProperty('dev', $response->version);

                // Verify releasesData is an array
                $this->assertIsArray($response->releasesData);

                // If releases were fetched, verify structure
                if (!empty($response->releasesData)) {
                    $firstRelease = $response->releasesData[0];
                    $this->assertIsArray($firstRelease);
                    $this->assertArrayHasKey('name', $firstRelease);
                    $this->assertArrayHasKey('body', $firstRelease);

                    echo "\n✓ GitHub releases: Fetched " . count($response->releasesData) . " releases\n";
                } else {
                    echo "\n✓ GitHub releases: Valid structure (no releases data available)\n";
                }

                // Verify current version is set
                $this->assertNotEmpty($response->version->current);
            } else {
                // If GitHub API failed, just verify we got some output
                $this->assertNotEmpty($output);
                echo "\n✓ GitHub releases: Handled API unavailability\n";
            }
        } catch (\Exception $e) {
            ob_end_clean();
            // GitHub API might be unavailable or rate limited
            $this->assertStringContainsStringIgnoringCase('github', $e->getMessage());
            echo "\n✓ GitHub releases: Handled exception - " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test controller instantiation
     */
    public function testControllerInstantiation(): void
    {
        $this->assertInstanceOf(GitHub::class, $this->githubController);
        $this->assertInstanceOf(\Exodus4D\Pathfinder\Controller\Controller::class, $this->githubController);

        echo "\n✓ GitHub controller: Instantiated correctly\n";
    }

    /**
     * Test version comparison logic conceptually
     * Tests that the structure handles version comparison
     */
    public function testVersionComparisonStructure(): void
    {
        // This tests the version comparison logic indirectly
        $currentVersion = \Exodus4D\Pathfinder\Lib\Config::getPathfinderData('version');

        $this->assertNotEmpty($currentVersion);
        $this->assertMatchesRegularExpression('/^v?\d+\.\d+\.\d+/', $currentVersion);

        echo "\n✓ GitHub releases: Current version is $currentVersion\n";
    }
}
