<?php
/**
 * Unit tests for SystemTag class
 * Tests tag<->integer conversion logic for EVE wormhole system tagging
 */

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\SystemTag;

class SystemTagTest extends TestCase
{
    /**
     * Test intToTag with single character tags (A-Z, 0-25)
     */
    public function testIntToTagSingleCharacter(): void
    {
        // Test A-Z range (0-25)
        $this->assertEquals('A', SystemTag::intToTag(0));
        $this->assertEquals('B', SystemTag::intToTag(1));
        $this->assertEquals('C', SystemTag::intToTag(2));
        $this->assertEquals('Z', SystemTag::intToTag(25));
    }

    /**
     * Test intToTag with double character tags (AA-ZZ, 26-701)
     */
    public function testIntToTagDoubleCharacter(): void
    {
        // Test first double-char tag
        $this->assertEquals('AA', SystemTag::intToTag(26));

        // Test AB
        $this->assertEquals('AB', SystemTag::intToTag(27));

        // Test AZ (end of first double-char series)
        $this->assertEquals('AZ', SystemTag::intToTag(51));

        // Test BA (start of second series)
        $this->assertEquals('BA', SystemTag::intToTag(52));

        // Test ZZ (last valid tag)
        $this->assertEquals('ZZ', SystemTag::intToTag(701));
    }

    /**
     * Test intToTag with special Static tag
     */
    public function testIntToTagStatic(): void
    {
        $this->assertEquals('Static', SystemTag::intToTag(SystemTag::INT_STATIC));
        $this->assertEquals('Static', SystemTag::intToTag(1000));
    }

    /**
     * Test intToTag with invalid/custom values
     */
    public function testIntToTagInvalidValues(): void
    {
        // Negative numbers
        $this->assertEquals('?', SystemTag::intToTag(-1));
        $this->assertEquals('?', SystemTag::intToTag(-100));

        // Above valid range (>701)
        $this->assertEquals('?', SystemTag::intToTag(702));
        $this->assertEquals('?', SystemTag::intToTag(1000000));

        // Custom tag constant
        $this->assertEquals('?', SystemTag::intToTag(SystemTag::INT_CUSTOM));
        $this->assertEquals('?', SystemTag::intToTag(1001));
    }

    /**
     * Test tagToInt with single character tags (A-Z)
     */
    public function testTagToIntSingleCharacter(): void
    {
        // Test A-Z range
        $this->assertEquals(0, SystemTag::tagToInt('A'));
        $this->assertEquals(1, SystemTag::tagToInt('B'));
        $this->assertEquals(2, SystemTag::tagToInt('C'));
        $this->assertEquals(25, SystemTag::tagToInt('Z'));

        // Test lowercase (should normalize to uppercase)
        $this->assertEquals(0, SystemTag::tagToInt('a'));
        $this->assertEquals(25, SystemTag::tagToInt('z'));
    }

    /**
     * Test tagToInt with double character tags (AA-ZZ)
     */
    public function testTagToIntDoubleCharacter(): void
    {
        // Test first double-char tag
        $this->assertEquals(26, SystemTag::tagToInt('AA'));

        // Test AB
        $this->assertEquals(27, SystemTag::tagToInt('AB'));

        // Test AZ
        $this->assertEquals(51, SystemTag::tagToInt('AZ'));

        // Test BA
        $this->assertEquals(52, SystemTag::tagToInt('BA'));

        // Test ZZ (last valid tag)
        $this->assertEquals(701, SystemTag::tagToInt('ZZ'));

        // Test lowercase (should normalize)
        $this->assertEquals(26, SystemTag::tagToInt('aa'));
        $this->assertEquals(701, SystemTag::tagToInt('zz'));
    }

    /**
     * Test tagToInt with special Static tag
     */
    public function testTagToIntStatic(): void
    {
        $this->assertEquals(SystemTag::INT_STATIC, SystemTag::tagToInt('Static'));
        $this->assertEquals(1000, SystemTag::tagToInt('Static'));

        // Other case variations should return INT_CUSTOM (case-sensitive for "Static")
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('STATIC'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('static'));
    }

    /**
     * Test tagToInt with invalid/custom tags
     */
    public function testTagToIntInvalidTags(): void
    {
        // Out of bounds single characters
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('1'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('@'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('['));

        // Out of bounds double characters
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('1A'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('A1'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('@@'));

        // Too long
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('ABC'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('ABCD'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('CustomTag'));

        // Empty string
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt(''));
    }

    /**
     * Test round-trip conversion: int -> tag -> int
     */
    public function testRoundTripIntToTagToInt(): void
    {
        // Test valid single-char range
        for ($i = 0; $i <= 25; $i++) {
            $tag = SystemTag::intToTag($i);
            $int = SystemTag::tagToInt($tag);
            $this->assertEquals($i, $int, "Round-trip failed for int $i (tag: $tag)");
        }

        // Test valid double-char range (sample)
        $testInts = [26, 27, 51, 52, 100, 200, 300, 400, 500, 600, 700, 701];
        foreach ($testInts as $i) {
            $tag = SystemTag::intToTag($i);
            $int = SystemTag::tagToInt($tag);
            $this->assertEquals($i, $int, "Round-trip failed for int $i (tag: $tag)");
        }

        // Test Static
        $this->assertEquals(SystemTag::INT_STATIC, SystemTag::tagToInt(SystemTag::intToTag(SystemTag::INT_STATIC)));
    }

    /**
     * Test round-trip conversion: tag -> int -> tag
     */
    public function testRoundTripTagToIntToTag(): void
    {
        // Test single-char tags
        $singleCharTags = ['A', 'B', 'M', 'Z'];
        foreach ($singleCharTags as $tag) {
            $int = SystemTag::tagToInt($tag);
            $resultTag = SystemTag::intToTag($int);
            $this->assertEquals($tag, $resultTag, "Round-trip failed for tag $tag (int: $int)");
        }

        // Test double-char tags
        $doubleCharTags = ['AA', 'AB', 'AZ', 'BA', 'MZ', 'ZA', 'ZZ'];
        foreach ($doubleCharTags as $tag) {
            $int = SystemTag::tagToInt($tag);
            $resultTag = SystemTag::intToTag($int);
            $this->assertEquals($tag, $resultTag, "Round-trip failed for tag $tag (int: $int)");
        }

        // Test Static
        $this->assertEquals('Static', SystemTag::intToTag(SystemTag::tagToInt('Static')));
    }

    /**
     * Test boundary conditions
     */
    public function testBoundaryConditions(): void
    {
        // Test boundary between single and double char (25->Z, 26->AA)
        $this->assertEquals('Z', SystemTag::intToTag(25));
        $this->assertEquals('AA', SystemTag::intToTag(26));

        $this->assertEquals(25, SystemTag::tagToInt('Z'));
        $this->assertEquals(26, SystemTag::tagToInt('AA'));

        // Test last valid tag
        $this->assertEquals('ZZ', SystemTag::intToTag(701));
        $this->assertEquals(701, SystemTag::tagToInt('ZZ'));

        // Test first invalid values
        $this->assertEquals('?', SystemTag::intToTag(702));
        $this->assertEquals('?', SystemTag::intToTag(-1));
    }

    /**
     * Test case sensitivity (tags should be case-insensitive except for "Static")
     */
    public function testCaseSensitivity(): void
    {
        // Single char - case insensitive
        $this->assertEquals(SystemTag::tagToInt('A'), SystemTag::tagToInt('a'));
        $this->assertEquals(SystemTag::tagToInt('Z'), SystemTag::tagToInt('z'));

        // Double char - case insensitive
        $this->assertEquals(SystemTag::tagToInt('AA'), SystemTag::tagToInt('aa'));
        $this->assertEquals(SystemTag::tagToInt('AA'), SystemTag::tagToInt('Aa'));
        $this->assertEquals(SystemTag::tagToInt('ZZ'), SystemTag::tagToInt('zz'));

        // Static is case-sensitive - only exact "Static" works
        $this->assertEquals(SystemTag::INT_STATIC, SystemTag::tagToInt('Static'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('STATIC'));
        $this->assertEquals(SystemTag::INT_CUSTOM, SystemTag::tagToInt('static'));
    }

    /**
     * Test specific known mappings for EVE wormhole tagging
     */
    public function testKnownMappings(): void
    {
        // Test some specific known tags that might be commonly used
        $knownMappings = [
            0 => 'A',      // First single char
            25 => 'Z',     // Last single char
            26 => 'AA',    // First double char
            51 => 'AZ',    // A-series complete
            52 => 'BA',    // B-series start
            77 => 'BZ',    // B-series complete
            78 => 'CA',    // C-series start
            701 => 'ZZ',   // Last valid
            1000 => 'Static', // Static tag
        ];

        foreach ($knownMappings as $int => $expectedTag) {
            $this->assertEquals($expectedTag, SystemTag::intToTag($int), "Mapping failed for int $int");
            $this->assertEquals($int, SystemTag::tagToInt($expectedTag), "Mapping failed for tag $expectedTag");
        }
    }

    /**
     * Test constants are defined correctly
     */
    public function testConstants(): void
    {
        $this->assertEquals(1000, SystemTag::INT_STATIC);
        $this->assertEquals(1001, SystemTag::INT_CUSTOM);
        $this->assertEquals('Static', SystemTag::TAG_STATIC);
    }

    /**
     * Test sequential double-char tags
     */
    public function testSequentialDoubleCharTags(): void
    {
        // Test that double-char tags increment correctly
        // AA=26, AB=27, AC=28... AZ=51, BA=52, BB=53...

        // First series (A*)
        for ($i = 0; $i < 26; $i++) {
            $expected = 'A' . chr(65 + $i);
            $this->assertEquals($expected, SystemTag::intToTag(26 + $i));
        }

        // Second series (B*)
        for ($i = 0; $i < 26; $i++) {
            $expected = 'B' . chr(65 + $i);
            $this->assertEquals($expected, SystemTag::intToTag(52 + $i));
        }
    }

    /**
     * Test all valid tags can be converted both ways
     */
    public function testAllValidTagsConvertible(): void
    {
        // Test all valid integers (0-701)
        for ($i = 0; $i <= 701; $i++) {
            $tag = SystemTag::intToTag($i);
            $this->assertNotEquals('?', $tag, "Int $i should produce valid tag, got '?'");

            $roundTrip = SystemTag::tagToInt($tag);
            $this->assertEquals($i, $roundTrip, "Round-trip failed for int $i -> tag $tag -> int $roundTrip");
        }
    }
}
