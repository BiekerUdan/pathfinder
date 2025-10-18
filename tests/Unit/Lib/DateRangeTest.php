<?php
/**
 * Unit tests for DateRange class
 */

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\DateRange;
use Exodus4D\Pathfinder\Exception\DateException;

class DateRangeTest extends TestCase
{
    /**
     * Test creating a valid date range (from before to)
     */
    public function testCreateValidDateRangeForward(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00');
        $to = new \DateTime('2025-01-31 23:59:59');

        $range = new DateRange($from, $to);

        $this->assertInstanceOf(DateRange::class, $range);
    }

    /**
     * Test creating a date range with reversed dates (to before from)
     * Should automatically swap them
     */
    public function testCreateValidDateRangeReversed(): void
    {
        $from = new \DateTime('2025-01-31 23:59:59');
        $to = new \DateTime('2025-01-01 00:00:00');

        $range = new DateRange($from, $to);

        $this->assertInstanceOf(DateRange::class, $range);
    }

    /**
     * Test that creating a range with identical dates throws exception
     */
    public function testCreateInvalidDateRangeSameTime(): void
    {
        $this->expectException(DateException::class);
        $this->expectExceptionCode(3000);
        $this->expectExceptionMessage('A period cannot be the same time');

        $date = new \DateTime('2025-01-15 12:00:00');
        new DateRange($date, $date);
    }

    /**
     * Test inRange returns true for date within range
     */
    public function testInRangeTrue(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00');
        $to = new \DateTime('2025-01-31 23:59:59');
        $checkDate = new \DateTime('2025-01-15 12:00:00');

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }

    /**
     * Test inRange returns false for date before range
     */
    public function testInRangeFalseBefore(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00');
        $to = new \DateTime('2025-01-31 23:59:59');
        $checkDate = new \DateTime('2024-12-31 23:59:59');

        $range = new DateRange($from, $to);

        $this->assertFalse($range->inRange($checkDate));
    }

    /**
     * Test inRange returns false for date after range
     */
    public function testInRangeFalseAfter(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00');
        $to = new \DateTime('2025-01-31 23:59:59');
        $checkDate = new \DateTime('2025-02-01 00:00:00');

        $range = new DateRange($from, $to);

        $this->assertFalse($range->inRange($checkDate));
    }

    /**
     * Test inRange returns true for date exactly at range start
     */
    public function testInRangeTrueAtStart(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00');
        $to = new \DateTime('2025-01-31 23:59:59');
        $checkDate = new \DateTime('2025-01-01 00:00:00');

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }

    /**
     * Test inRange returns true for date exactly at range end
     */
    public function testInRangeTrueAtEnd(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00');
        $to = new \DateTime('2025-01-31 23:59:59');
        $checkDate = new \DateTime('2025-01-31 23:59:59');

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }

    /**
     * Test date range with reversed dates still works correctly
     */
    public function testInRangeWithReversedDates(): void
    {
        $from = new \DateTime('2025-01-31 23:59:59');
        $to = new \DateTime('2025-01-01 00:00:00');
        $checkDate = new \DateTime('2025-01-15 12:00:00');

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }

    /**
     * Test with timezone-aware dates
     */
    public function testInRangeWithTimezones(): void
    {
        $from = new \DateTime('2025-01-01 00:00:00', new \DateTimeZone('UTC'));
        $to = new \DateTime('2025-01-31 23:59:59', new \DateTimeZone('UTC'));
        $checkDate = new \DateTime('2025-01-15 12:00:00', new \DateTimeZone('UTC'));

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }

    /**
     * Test with very short time range (seconds)
     */
    public function testInRangeShortTimeSpan(): void
    {
        $from = new \DateTime('2025-01-01 12:00:00');
        $to = new \DateTime('2025-01-01 12:00:10');
        $checkDate = new \DateTime('2025-01-01 12:00:05');

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }

    /**
     * Test with microseconds difference
     */
    public function testInRangeMicroseconds(): void
    {
        $from = new \DateTime('2025-01-01 12:00:00.000000');
        $to = new \DateTime('2025-01-01 12:00:00.000100');
        $checkDate = new \DateTime('2025-01-01 12:00:00.000050');

        $range = new DateRange($from, $to);

        $this->assertTrue($range->inRange($checkDate));
    }
}
