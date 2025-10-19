<?php
/**
 * Unit tests for Db\Pool class
 */

namespace Tests\Unit\Lib\Db;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\Db\Pool;
use Exodus4D\Pathfinder\Lib\Db\Sql;
use Exodus4D\Pathfinder\Exception\ConfigException;

class PoolTest extends TestCase
{
    private $pool;
    private $mockGetConfig;
    private $mockRequiredVars;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock closures for configuration
        $this->mockGetConfig = function(string $alias) : array {
            return [
                'ALIAS' => $alias,
                'SCHEME' => 'mysql',
                'HOST' => 'localhost',
                'PORT' => 3306,
                'SOCKET' => '',
                'NAME' => 'test_db',
                'USER' => 'test_user',
                'PASS' => 'test_pass',
                'OPTIONS' => []
            ];
        };

        $this->mockRequiredVars = function(string $driver) : array {
            return [
                'CHARACTER_SET_DATABASE' => 'utf8mb4',
                'COLLATION_DATABASE' => 'utf8mb4_unicode_ci'
            ];
        };

        $this->pool = new Pool($this->mockGetConfig, $this->mockRequiredVars);
    }

    // Constructor Tests

    public function testConstructorSetsClosures(): void
    {
        $pool = new Pool($this->mockGetConfig, $this->mockRequiredVars);

        $this->assertInstanceOf(Pool::class, $pool);

        // Verify closures are set via reflection
        $reflection = new \ReflectionClass($pool);

        $getConfigProp = $reflection->getProperty('getConfig');
        $getConfigProp->setAccessible(true);
        $this->assertInstanceOf(\Closure::class, $getConfigProp->getValue($pool));

        $requiredVarsProp = $reflection->getProperty('requiredVars');
        $requiredVarsProp->setAccessible(true);
        $this->assertInstanceOf(\Closure::class, $requiredVarsProp->getValue($pool));
    }

    // Silent Mode Tests

    public function testSetSilentTrue(): void
    {
        $this->pool->setSilent(true);

        $this->assertTrue($this->pool->isSilent());
    }

    public function testSetSilentFalse(): void
    {
        $this->pool->setSilent(true);
        $this->pool->setSilent(false);

        $this->assertFalse($this->pool->isSilent());
    }

    public function testIsSilentDefaultFalse(): void
    {
        $this->assertFalse($this->pool->isSilent());
    }

    public function testSetSilentWithClearErrors(): void
    {
        // Add an error first
        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);
        $method->invoke($this->pool, 'test_alias', new \Exception('Test error'));

        // Verify error was added
        $errors = $this->pool->getErrors('test_alias');
        $this->assertCount(1, $errors);

        // Set silent with clear errors
        $this->pool->setSilent(true, true);

        // Verify errors were cleared
        $errors = $this->pool->getErrors('test_alias');
        $this->assertEmpty($errors);
    }

    // buildDnsFromConfig Tests

    public function testBuildDnsFromConfigWithHost(): void
    {
        $config = [
            'SCHEME' => 'mysql',
            'HOST' => 'localhost',
            'PORT' => 3306,
            'SOCKET' => '',
            'NAME' => 'pathfinder'
        ];

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('buildDnsFromConfig');
        $method->setAccessible(true);
        $result = $method->invoke($this->pool, $config);

        $this->assertEquals('mysql:host=localhost;port=3306;dbname=pathfinder', $result);
    }

    public function testBuildDnsFromConfigWithSocket(): void
    {
        $config = [
            'SCHEME' => 'mysql',
            'HOST' => 'localhost',
            'PORT' => 3306,
            'SOCKET' => '/var/run/mysqld/mysqld.sock',
            'NAME' => 'pathfinder'
        ];

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('buildDnsFromConfig');
        $method->setAccessible(true);
        $result = $method->invoke($this->pool, $config);

        $this->assertEquals('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=pathfinder', $result);
    }

    public function testBuildDnsFromConfigWithoutPort(): void
    {
        $config = [
            'SCHEME' => 'mysql',
            'HOST' => 'localhost',
            'PORT' => '',
            'SOCKET' => '',
            'NAME' => 'pathfinder'
        ];

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('buildDnsFromConfig');
        $method->setAccessible(true);
        $result = $method->invoke($this->pool, $config);

        $this->assertEquals('mysql:host=localhost;dbname=pathfinder', $result);
    }

    public function testBuildDnsFromConfigWithoutDbName(): void
    {
        $config = [
            'SCHEME' => 'mysql',
            'HOST' => 'localhost',
            'PORT' => 3306,
            'SOCKET' => '',
            'NAME' => ''
        ];

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('buildDnsFromConfig');
        $method->setAccessible(true);
        $result = $method->invoke($this->pool, $config);

        $this->assertEquals('mysql:host=localhost;port=3306', $result);
    }

    public function testBuildDnsFromConfigWithIPv6Host(): void
    {
        $config = [
            'SCHEME' => 'mysql',
            'HOST' => '::1',
            'PORT' => 3306,
            'SOCKET' => '',
            'NAME' => 'test'
        ];

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('buildDnsFromConfig');
        $method->setAccessible(true);
        $result = $method->invoke($this->pool, $config);

        $this->assertEquals('mysql:host=::1;port=3306;dbname=test', $result);
    }

    // Error Handling Tests

    public function testPushErrorAddsException(): void
    {
        $exception = new \Exception('Test error message');

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);
        $method->invoke($this->pool, 'test_alias', $exception);

        $errors = $this->pool->getErrors('test_alias');

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(\Exception::class, $errors[0]);
        $this->assertEquals('Test error message', $errors[0]->getMessage());
    }

    public function testPushErrorPreventsDuplicates(): void
    {
        $exception1 = new \Exception('Same error');
        $exception2 = new \Exception('Same error');

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);

        $method->invoke($this->pool, 'test_alias', $exception1);
        $method->invoke($this->pool, 'test_alias', $exception2);

        $errors = $this->pool->getErrors('test_alias');

        // Should only have one error (duplicate not added)
        $this->assertCount(1, $errors);
    }

    public function testPushErrorAllowsDifferentMessages(): void
    {
        $exception1 = new \Exception('Error 1');
        $exception2 = new \Exception('Error 2');

        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);

        $method->invoke($this->pool, 'test_alias', $exception1);
        $method->invoke($this->pool, 'test_alias', $exception2);

        $errors = $this->pool->getErrors('test_alias', 10); // Get up to 10 errors

        // Should have both errors
        $this->assertCount(2, $errors);
        $this->assertEquals('Error 2', $errors[0]->getMessage()); // Most recent first
        $this->assertEquals('Error 1', $errors[1]->getMessage());
    }

    public function testPushErrorLimitsTo5Errors(): void
    {
        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);

        // Add 10 different errors
        for ($i = 1; $i <= 10; $i++) {
            $method->invoke($this->pool, 'test_alias', new \Exception("Error $i"));
        }

        $errors = $this->pool->getErrors('test_alias', 10);

        // Should only keep 5 most recent
        $this->assertLessThanOrEqual(5, count($errors));
    }

    public function testGetErrorsWithLimit(): void
    {
        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);

        // Add 3 errors
        for ($i = 1; $i <= 3; $i++) {
            $method->invoke($this->pool, 'test_alias', new \Exception("Error $i"));
        }

        // Get only 2 most recent
        $errors = $this->pool->getErrors('test_alias', 2);

        $this->assertCount(2, $errors);
        $this->assertEquals('Error 3', $errors[0]->getMessage());
        $this->assertEquals('Error 2', $errors[1]->getMessage());
    }

    public function testGetErrorsForNonExistentAlias(): void
    {
        $errors = $this->pool->getErrors('nonexistent_alias');

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testGetErrorsMultipleAliases(): void
    {
        $reflection = new \ReflectionClass($this->pool);
        $method = $reflection->getMethod('pushError');
        $method->setAccessible(true);

        $method->invoke($this->pool, 'alias1', new \Exception('Error 1'));
        $method->invoke($this->pool, 'alias2', new \Exception('Error 2'));

        $errors1 = $this->pool->getErrors('alias1');
        $errors2 = $this->pool->getErrors('alias2');

        $this->assertCount(1, $errors1);
        $this->assertCount(1, $errors2);
        $this->assertEquals('Error 1', $errors1[0]->getMessage());
        $this->assertEquals('Error 2', $errors2[0]->getMessage());
    }

    // newDB Tests (with invalid credentials)

    public function testNewDBWithUnsupportedScheme(): void
    {
        $getConfig = function(string $alias) : array {
            return [
                'ALIAS' => $alias,
                'SCHEME' => 'postgresql',  // Unsupported
                'HOST' => 'localhost',
                'PORT' => 5432,
                'SOCKET' => '',
                'NAME' => 'test',
                'USER' => 'user',
                'PASS' => 'pass',
                'OPTIONS' => []
            ];
        };

        $pool = new Pool($getConfig, $this->mockRequiredVars);
        $pool->setSilent(true); // Suppress error logging

        $reflection = new \ReflectionClass($pool);
        $method = $reflection->getMethod('newDB');
        $method->setAccessible(true);

        $result = $method->invoke($pool, $getConfig('test'));

        // Should return null for unsupported scheme
        $this->assertNull($result);

        // Should have error logged
        $errors = $pool->getErrors('test');
        $this->assertCount(1, $errors);
        $this->assertInstanceOf(ConfigException::class, $errors[0]);
        $this->assertStringContainsString('not supported', $errors[0]->getMessage());
    }

    public function testNewDBWithInvalidCredentials(): void
    {
        $getConfig = function(string $alias) : array {
            return [
                'ALIAS' => $alias,
                'SCHEME' => 'mysql',
                'HOST' => 'localhost',
                'PORT' => 3306,
                'SOCKET' => '',
                'NAME' => 'nonexistent_db',
                'USER' => 'invalid_user_12345',
                'PASS' => 'invalid_pass_12345',
                'OPTIONS' => [\PDO::ATTR_TIMEOUT => 1] // Quick timeout
            ];
        };

        $pool = new Pool($getConfig, $this->mockRequiredVars);
        $pool->setSilent(true); // Suppress error logging

        $reflection = new \ReflectionClass($pool);
        $method = $reflection->getMethod('newDB');
        $method->setAccessible(true);

        $result = $method->invoke($pool, $getConfig('test'));

        // Should return null on connection failure
        $this->assertNull($result);

        // Should have PDOException logged
        $errors = $pool->getErrors('test');
        $this->assertCount(1, $errors);
        $this->assertInstanceOf(\PDOException::class, $errors[0]);
    }

    // Constants Tests

    public function testPoolNameConstant(): void
    {
        $this->assertEquals('DB', Pool::POOL_NAME);
    }

    public function testErrorSchemeConstant(): void
    {
        $this->assertIsString(Pool::ERROR_SCHEME);
        $this->assertStringContainsString('%s', Pool::ERROR_SCHEME);
    }
}
