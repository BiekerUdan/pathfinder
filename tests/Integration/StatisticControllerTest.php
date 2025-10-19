<?php
/**
 * Integration tests for Statistic Controller
 * Tests the Statistic controller's date/week calculation methods
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Controller\Api\Statistic;

class StatisticControllerTest extends TestCase
{
    private static $f3;
    private $statisticController;

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

        // Create a fresh Statistic controller instance for each test
        $this->statisticController = new Statistic();
    }

    /**
     * Test concatYearWeek method
     * Tests that year and week are concatenated with proper padding
     */
    public function testConcatYearWeek(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('concatYearWeek');
        $method->setAccessible(true);

        // Test single-digit week (should be padded)
        $result = $method->invoke($this->statisticController, 2024, 1);
        $this->assertEquals('202401', $result);

        // Test double-digit week (no padding needed)
        $result = $method->invoke($this->statisticController, 2024, 52);
        $this->assertEquals('202452', $result);

        // Test week 10 (boundary case)
        $result = $method->invoke($this->statisticController, 2024, 10);
        $this->assertEquals('202410', $result);

        // Test week 9 (last single-digit)
        $result = $method->invoke($this->statisticController, 2024, 9);
        $this->assertEquals('202409', $result);

        echo "\n✓ Statistic concatYearWeek: Tested year/week concatenation with padding\n";
    }

    /**
     * Test getIsoWeeksInYear method
     * Tests that years with 52 and 53 weeks are correctly identified
     */
    public function testGetIsoWeeksInYear(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('getIsoWeeksInYear');
        $method->setAccessible(true);

        // 2024 has 52 weeks
        $result = $method->invoke($this->statisticController, 2024);
        $this->assertContains($result, [52, 53], '2024 should have 52 or 53 weeks');

        // 2015 has 53 weeks (known year with 53 weeks)
        $result = $method->invoke($this->statisticController, 2015);
        $this->assertEquals(53, $result, '2015 should have 53 weeks');

        // 2016 has 52 weeks
        $result = $method->invoke($this->statisticController, 2016);
        $this->assertEquals(52, $result, '2016 should have 52 weeks');

        // 2020 has 53 weeks (known year with 53 weeks)
        $result = $method->invoke($this->statisticController, 2020);
        $this->assertEquals(53, $result, '2020 should have 53 weeks');

        echo "\n✓ Statistic getIsoWeeksInYear: Validated weeks in different years\n";
    }

    /**
     * Test getWeekCount method
     * Tests week count calculation for different periods
     */
    public function testGetWeekCount(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('getWeekCount');
        $method->setAccessible(true);

        // Weekly period should return 1
        $result = $method->invoke($this->statisticController, 'weekly', 2024);
        $this->assertEquals(1, $result);

        // Monthly period should return 4
        $result = $method->invoke($this->statisticController, 'monthly', 2024);
        $this->assertEquals(4, $result);

        // Yearly period should return weeks in year
        $result = $method->invoke($this->statisticController, 'yearly', 2024);
        $this->assertContains($result, [52, 53]);

        // Yearly period for 2015 (53 weeks)
        $result = $method->invoke($this->statisticController, 'yearly', 2015);
        $this->assertEquals(53, $result);

        // Default/unknown period should return 1
        $result = $method->invoke($this->statisticController, 'unknown', 2024);
        $this->assertEquals(1, $result);

        echo "\n✓ Statistic getWeekCount: Validated week counts for different periods\n";
    }

    /**
     * Test calculateYearWeekOffset method - forward calculation
     * Tests moving forward in time across week and year boundaries
     */
    public function testCalculateYearWeekOffsetForward(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('calculateYearWeekOffset');
        $method->setAccessible(true);

        // Test moving forward 1 week (no boundary crossing)
        $result = $method->invoke($this->statisticController, 2024, 10, 1, false);
        $this->assertEquals(['year' => 2024, 'week' => 10], $result);

        // Test moving forward 5 weeks (no boundary crossing)
        $result = $method->invoke($this->statisticController, 2024, 10, 5, false);
        $this->assertEquals(['year' => 2024, 'week' => 14], $result);

        // Test moving forward across year boundary (week 52 -> week 1 next year)
        // Using 2016 which has 52 weeks
        $result = $method->invoke($this->statisticController, 2016, 52, 2, false);
        $this->assertEquals(['year' => 2017, 'week' => 1], $result);

        // Test moving forward from week 51 to week 3 next year (5 weeks)
        $result = $method->invoke($this->statisticController, 2016, 51, 5, false);
        $this->assertEquals(['year' => 2017, 'week' => 3], $result);

        echo "\n✓ Statistic calculateYearWeekOffset: Validated forward calculations\n";
    }

    /**
     * Test calculateYearWeekOffset method - backward calculation
     * Tests moving backward in time across week and year boundaries
     */
    public function testCalculateYearWeekOffsetBackward(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('calculateYearWeekOffset');
        $method->setAccessible(true);

        // Test moving backward 1 week (no boundary crossing)
        $result = $method->invoke($this->statisticController, 2024, 10, 1, true);
        $this->assertEquals(['year' => 2024, 'week' => 10], $result);

        // Test moving backward 5 weeks (no boundary crossing)
        $result = $method->invoke($this->statisticController, 2024, 10, 5, true);
        $this->assertEquals(['year' => 2024, 'week' => 6], $result);

        // Test moving backward across year boundary (week 1 -> week 52 previous year)
        // From week 1 2017, go back 2 weeks to week 52 2016
        $result = $method->invoke($this->statisticController, 2017, 1, 2, true);
        $this->assertEquals(['year' => 2016, 'week' => 52], $result);

        // Test moving backward from week 3 to week 51 previous year (5 weeks)
        $result = $method->invoke($this->statisticController, 2017, 3, 5, true);
        $this->assertEquals(['year' => 2016, 'week' => 51], $result);

        echo "\n✓ Statistic calculateYearWeekOffset: Validated backward calculations\n";
    }

    /**
     * Test calculateYearWeekOffset with boundary conditions
     * Tests edge cases like week 0, week > max, etc.
     */
    public function testCalculateYearWeekOffsetBoundaries(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('calculateYearWeekOffset');
        $method->setAccessible(true);

        // Test with week 0 (should be adjusted to week 1)
        $result = $method->invoke($this->statisticController, 2024, 0, 1, false);
        $this->assertEquals(['year' => 2024, 'week' => 1], $result);

        // Test with week > max weeks in year (should be adjusted to max)
        $result = $method->invoke($this->statisticController, 2016, 99, 1, false);
        $this->assertEquals(['year' => 2016, 'week' => 52], $result);

        // Test with negative week (should be adjusted to week 1)
        $result = $method->invoke($this->statisticController, 2024, -5, 1, false);
        $this->assertEquals(['year' => 2024, 'week' => 1], $result);

        echo "\n✓ Statistic calculateYearWeekOffset: Validated boundary conditions\n";
    }

    /**
     * Test calculateYearWeekOffset with year having 53 weeks
     * Tests calculations for years with 53 weeks (like 2015, 2020)
     */
    public function testCalculateYearWeekOffsetWith53WeekYear(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $method = $reflection->getMethod('calculateYearWeekOffset');
        $method->setAccessible(true);

        // 2015 has 53 weeks
        // Test moving forward from week 53 to week 1 next year
        $result = $method->invoke($this->statisticController, 2015, 53, 2, false);
        $this->assertEquals(['year' => 2016, 'week' => 1], $result);

        // Test moving backward from week 1 2016 to week 53 2015
        $result = $method->invoke($this->statisticController, 2016, 1, 2, true);
        $this->assertEquals(['year' => 2015, 'week' => 53], $result);

        echo "\n✓ Statistic calculateYearWeekOffset: Validated 53-week year calculations\n";
    }

    /**
     * Test real-world scenario: monthly statistics
     * Tests a realistic use case of calculating 4-week offset
     */
    public function testMonthlyStatisticsScenario(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $concatMethod = $reflection->getMethod('concatYearWeek');
        $concatMethod->setAccessible(true);
        $offsetMethod = $reflection->getMethod('calculateYearWeekOffset');
        $offsetMethod->setAccessible(true);
        $weekCountMethod = $reflection->getMethod('getWeekCount');
        $weekCountMethod->setAccessible(true);

        // Scenario: Get monthly statistics starting from week 10, 2024
        $yearStart = 2024;
        $weekStart = 10;
        $period = 'monthly';

        $weekCount = $weekCountMethod->invoke($this->statisticController, $period, $yearStart);
        $this->assertEquals(4, $weekCount);

        $offset = $offsetMethod->invoke($this->statisticController, $yearStart, $weekStart, $weekCount, false);
        $this->assertEquals(['year' => 2024, 'week' => 13], $offset);

        // Calculate date range strings
        $start = $concatMethod->invoke($this->statisticController, $yearStart, $weekStart);
        $end = $concatMethod->invoke($this->statisticController, $offset['year'], $offset['week']);

        $this->assertEquals('202410', $start);
        $this->assertEquals('202413', $end);

        echo "\n✓ Statistic real-world scenario: Validated monthly statistics calculation\n";
    }

    /**
     * Test real-world scenario: yearly statistics
     * Tests a realistic use case of calculating yearly offset
     */
    public function testYearlyStatisticsScenario(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $offsetMethod = $reflection->getMethod('calculateYearWeekOffset');
        $offsetMethod->setAccessible(true);
        $weekCountMethod = $reflection->getMethod('getWeekCount');
        $weekCountMethod->setAccessible(true);

        // Scenario: Get yearly statistics starting from week 1, 2016
        $yearStart = 2016;
        $weekStart = 1;
        $period = 'yearly';

        $weekCount = $weekCountMethod->invoke($this->statisticController, $period, $yearStart);
        $this->assertEquals(52, $weekCount, '2016 should have 52 weeks');

        $offset = $offsetMethod->invoke($this->statisticController, $yearStart, $weekStart, $weekCount, false);
        $this->assertEquals(['year' => 2016, 'week' => 52], $offset);

        echo "\n✓ Statistic real-world scenario: Validated yearly statistics calculation\n";
    }

    /**
     * Test real-world scenario: pagination backward
     * Tests calculating previous period for pagination
     */
    public function testPaginationBackwardScenario(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $offsetMethod = $reflection->getMethod('calculateYearWeekOffset');
        $offsetMethod->setAccessible(true);

        // Scenario: User viewing week 5-8 of 2024, wants to go to previous 4 weeks
        $currentStart = ['year' => 2024, 'week' => 5];
        $weekCount = 4;

        // Calculate previous period (go back weekCount + 1 weeks)
        $offsetPrev = $offsetMethod->invoke(
            $this->statisticController,
            $currentStart['year'],
            $currentStart['week'],
            $weekCount + 1,
            true
        );

        $this->assertEquals(['year' => 2024, 'week' => 1], $offsetPrev);

        echo "\n✓ Statistic pagination: Validated backward pagination\n";
    }

    /**
     * Test real-world scenario: pagination forward
     * Tests calculating next period for pagination
     */
    public function testPaginationForwardScenario(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $offsetMethod = $reflection->getMethod('calculateYearWeekOffset');
        $offsetMethod->setAccessible(true);

        // Scenario: User viewing week 5-8 of 2024, wants to go to next 4 weeks
        $currentStart = ['year' => 2024, 'week' => 5];
        $weekCount = 4;

        // Calculate next period (go forward weekCount + 1 weeks)
        $offsetNext = $offsetMethod->invoke(
            $this->statisticController,
            $currentStart['year'],
            $currentStart['week'],
            $weekCount + 1,
            false
        );

        $this->assertEquals(['year' => 2024, 'week' => 9], $offsetNext);

        echo "\n✓ Statistic pagination: Validated forward pagination\n";
    }

    /**
     * Test cross-year pagination
     * Tests pagination that crosses year boundaries
     */
    public function testCrossYearPagination(): void
    {
        $reflection = new \ReflectionClass($this->statisticController);
        $offsetMethod = $reflection->getMethod('calculateYearWeekOffset');
        $offsetMethod->setAccessible(true);

        // Scenario: Viewing weeks 50-53 of 2016, want next 4 weeks (crosses to 2017)
        $offsetNext = $offsetMethod->invoke($this->statisticController, 2016, 50, 5, false);
        $this->assertEquals(['year' => 2017, 'week' => 2], $offsetNext);

        // Scenario: Viewing weeks 1-4 of 2017, want previous 4 weeks (crosses to 2016)
        $offsetPrev = $offsetMethod->invoke($this->statisticController, 2017, 1, 5, true);
        $this->assertEquals(['year' => 2016, 'week' => 49], $offsetPrev);

        echo "\n✓ Statistic pagination: Validated cross-year pagination\n";
    }
}
