<?php
/**
 * Unit tests for Util class
 */

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\Util;

class UtilTest extends TestCase
{
    // arrayChangeKeyCaseRecursive Tests

    public function testArrayChangeKeyCaseRecursiveLowercase(): void
    {
        $input = ['KEY1' => 'value1', 'KEY2' => ['NESTED' => 'value2']];
        $expected = ['key1' => 'value1', 'key2' => ['nested' => 'value2']];

        $result = Util::arrayChangeKeyCaseRecursive($input, CASE_LOWER);

        $this->assertEquals($expected, $result);
    }

    public function testArrayChangeKeyCaseRecursiveUppercase(): void
    {
        $input = ['key1' => 'value1', 'key2' => ['nested' => 'value2']];
        $expected = ['KEY1' => 'value1', 'KEY2' => ['NESTED' => 'value2']];

        $result = Util::arrayChangeKeyCaseRecursive($input, CASE_UPPER);

        $this->assertEquals($expected, $result);
    }

    public function testArrayChangeKeyCaseRecursiveDeepNesting(): void
    {
        $input = ['LEVEL1' => ['LEVEL2' => ['LEVEL3' => 'value']]];
        $expected = ['level1' => ['level2' => ['level3' => 'value']]];

        $result = Util::arrayChangeKeyCaseRecursive($input);

        $this->assertEquals($expected, $result);
    }

    // arrayFlattenByValue Tests

    public function testArrayFlattenByValue(): void
    {
        $input = [
            'a' => [1, 2],
            'b' => [3, [4, 5]],
            'c' => 6
        ];
        $expected = [1, 2, 3, 4, 5, 6];

        $result = Util::arrayFlattenByValue($input);

        $this->assertEquals($expected, $result);
    }

    public function testArrayFlattenByValueAlreadyFlat(): void
    {
        $input = [1, 2, 3, 4];
        $expected = [1, 2, 3, 4];

        $result = Util::arrayFlattenByValue($input);

        $this->assertEquals($expected, $result);
    }

    // arrayFlattenByKey Tests

    public function testArrayFlattenByKey(): void
    {
        $input = [
            'a' => ['x' => 1, 'y' => 2],
            'b' => ['z' => 3]
        ];
        $expected = ['x' => 1, 'y' => 2, 'z' => 3];

        $result = Util::arrayFlattenByKey($input);

        $this->assertEquals($expected, $result);
    }

    public function testArrayFlattenByKeyOverwrites(): void
    {
        $input = [
            'a' => ['x' => 1],
            'b' => ['x' => 2]
        ];
        $expected = ['x' => 2];

        $result = Util::arrayFlattenByKey($input);

        $this->assertEquals($expected, $result);
    }

    // arrayGetBy Tests

    public function testArrayGetBy(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ];
        $expected = [
            1 => ['name' => 'Alice'],
            2 => ['name' => 'Bob'],
            3 => ['name' => 'Charlie']
        ];

        $result = Util::arrayGetBy($input, 'id');

        $this->assertEquals($expected, $result);
    }

    public function testArrayGetByKeepKey(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ];
        $expected = [
            1 => ['id' => 1, 'name' => 'Alice'],
            2 => ['id' => 2, 'name' => 'Bob']
        ];

        $result = Util::arrayGetBy($input, 'id', false);

        $this->assertEquals($expected, $result);
    }

    // is_assoc Tests

    public function testIsAssocTrue(): void
    {
        $input = ['key1' => 'value1', 'key2' => 'value2'];

        $result = Util::is_assoc($input);

        $this->assertTrue($result);
    }

    public function testIsAssocFalse(): void
    {
        $input = [1, 2, 3, 4];

        $result = Util::is_assoc($input);

        $this->assertFalse($result);
    }

    public function testIsAssocFalseForSequentialNumbered(): void
    {
        $input = [0 => 'a', 1 => 'b', 2 => 'c'];

        $result = Util::is_assoc($input);

        $this->assertFalse($result);
    }

    public function testIsAssocTrueForNonSequential(): void
    {
        $input = [0 => 'a', 2 => 'b', 3 => 'c'];

        $result = Util::is_assoc($input);

        $this->assertTrue($result);
    }

    public function testIsAssocFalseForNonArray(): void
    {
        $input = 'not an array';

        $result = Util::is_assoc($input);

        $this->assertFalse($result);
    }

    // arrayChangeKeys Tests

    public function testArrayChangeKeys(): void
    {
        $input = ['firstName' => 'John', 'lastName' => 'Doe'];
        $expected = ['first_name' => 'John', 'last_name' => 'Doe'];

        $callback = function($key) {
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
        };

        $result = Util::arrayChangeKeys($input, $callback);

        $this->assertEquals($expected, $result);
    }

    public function testArrayChangeKeysUppercase(): void
    {
        $input = ['name' => 'value', 'key' => 'data'];
        $expected = ['NAME' => 'value', 'KEY' => 'data'];

        $result = Util::arrayChangeKeys($input, 'strtoupper');

        $this->assertEquals($expected, $result);
    }

    // convertScopesString Tests

    public function testConvertScopesString(): void
    {
        $input = 'esi-location.read_location.v1 esi-skills.read_skills.v1';
        $expected = [
            'esi-location.read_location.v1',
            'esi-skills.read_skills.v1'
        ];

        $result = Util::convertScopesString($input);

        $this->assertEquals($expected, $result);
    }

    public function testConvertScopesStringSorted(): void
    {
        $input = 'z-scope b-scope a-scope';
        $expected = ['a-scope', 'b-scope', 'z-scope'];

        $result = Util::convertScopesString($input);

        $this->assertEquals($expected, $result);
    }

    public function testConvertScopesStringEmpty(): void
    {
        $input = '';

        $result = Util::convertScopesString($input);

        $this->assertNull($result);
    }

    public function testConvertScopesStringLowercase(): void
    {
        $input = 'ESI-LOCATION.READ_LOCATION.V1';
        $expected = ['esi-location.read_location.v1'];

        $result = Util::convertScopesString($input);

        $this->assertEquals($expected, $result);
    }

    // obscureString Tests

    public function testObscureString(): void
    {
        $input = 'password123456';

        $result = Util::obscureString($input);

        $this->assertStringContainsString('___', $result);
        $this->assertStringContainsString('[14]', $result);
    }

    public function testObscureStringShort(): void
    {
        $input = 'abc';

        $result = Util::obscureString($input);

        $this->assertStringContainsString('___', $result);
        $this->assertStringContainsString('[3]', $result);
    }

    public function testObscureStringEmpty(): void
    {
        $input = '';

        $result = Util::obscureString($input);

        $this->assertEquals('', $result);
    }

    public function testObscureStringCustomMaxHide(): void
    {
        $input = 'verylongpassword';

        $result = Util::obscureString($input, 5);

        $this->assertStringContainsString('[16]', $result);
    }

    // getHashFromScopes Tests

    public function testGetHashFromScopes(): void
    {
        $scopes = ['scope1', 'scope2', 'scope3'];

        $result = Util::getHashFromScopes($scopes);

        $this->assertIsString($result);
        $this->assertEquals(32, strlen($result)); // MD5 hash length
    }

    public function testGetHashFromScopesConsistent(): void
    {
        $scopes = ['scope1', 'scope2', 'scope3'];

        $result1 = Util::getHashFromScopes($scopes);
        $result2 = Util::getHashFromScopes($scopes);

        $this->assertEquals($result1, $result2);
    }

    public function testGetHashFromScopesOrderIndependent(): void
    {
        $scopes1 = ['scope1', 'scope2', 'scope3'];
        $scopes2 = ['scope3', 'scope1', 'scope2'];

        $result1 = Util::getHashFromScopes($scopes1);
        $result2 = Util::getHashFromScopes($scopes2);

        $this->assertEquals($result1, $result2);
    }

    // filesystemInfo Tests

    public function testFilesystemInfoNull(): void
    {
        $result = Util::filesystemInfo(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFilesystemInfoNonExistent(): void
    {
        $result = Util::filesystemInfo('/path/that/does/not/exist');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // roundToInterval Tests

    public function testRoundToIntervalSeconds(): void
    {
        $dateTime = new \DateTime('2025-01-15 12:34:57');

        Util::roundToInterval($dateTime, 'sec', 5, 'floor');

        $this->assertEquals('2025-01-15 12:34:55', $dateTime->format('Y-m-d H:i:s'));
    }

    public function testRoundToIntervalMinutes(): void
    {
        $dateTime = new \DateTime('2025-01-15 12:34:57');

        Util::roundToInterval($dateTime, 'min', 5, 'floor');

        $this->assertEquals('2025-01-15 12:30:00', $dateTime->format('Y-m-d H:i:s'));
    }

    public function testRoundToIntervalHours(): void
    {
        $dateTime = new \DateTime('2025-01-15 13:34:57');

        Util::roundToInterval($dateTime, 'hour', 2, 'floor');

        $this->assertEquals('2025-01-15 12:00:00', $dateTime->format('Y-m-d H:i:s'));
    }

    public function testRoundToIntervalCeil(): void
    {
        $dateTime = new \DateTime('2025-01-15 12:34:57');

        Util::roundToInterval($dateTime, 'sec', 10, 'ceil');

        $this->assertEquals('2025-01-15 12:35:00', $dateTime->format('Y-m-d H:i:s'));
    }

    public function testRoundToIntervalRound(): void
    {
        $dateTime = new \DateTime('2025-01-15 12:34:57');

        Util::roundToInterval($dateTime, 'sec', 10, 'round');

        $this->assertEquals('2025-01-15 12:35:00', $dateTime->format('Y-m-d H:i:s'));
    }
}
