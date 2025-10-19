<?php
/**
 * Unit tests for Config class
 */

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\Config;

class ConfigTest extends TestCase
{
    /**
     * @var \Base
     */
    private static $f3;

    /**
     * Set up F3 instance before running tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Initialize F3 framework for tests that need it
        self::$f3 = \Base::instance();

        // Set up minimal test configuration
        self::$f3->set('NAMESPACE', 'Exodus4D\\Pathfinder');
        self::$f3->set('TZ', 'UTC');

        // Set up getTimeZone callable (required for inDownTimeRange tests)
        self::$f3->set('getTimeZone', function() : \DateTimeZone {
            return new \DateTimeZone('UTC');
        });

        // Set up test environment data
        self::$f3->set('ENVIRONMENT.TEST_KEY', 'test_value');
        self::$f3->set('ENVIRONMENT.CCP_SSO_DOWNTIME', '11:00');
        self::$f3->set('ENVIRONMENT.SOCKET_HOST', '127.0.0.1');
        self::$f3->set('ENVIRONMENT.SOCKET_PORT', '5555');
        self::$f3->set('ENVIRONMENT.DB_PF_DNS', 'mysql:host=localhost;port=3306;dbname=pathfinder');
        self::$f3->set('ENVIRONMENT.DB_PF_NAME', 'pathfinder');
        self::$f3->set('ENVIRONMENT.DB_PF_USER', 'root');
        self::$f3->set('ENVIRONMENT.DB_PF_PASS', 'password');
        self::$f3->set('ENVIRONMENT.SMTP_HOST', 'smtp.example.com');
        self::$f3->set('ENVIRONMENT.SMTP_PORT', '587');
        self::$f3->set('ENVIRONMENT.SMTP_SCHEME', 'tls');
        self::$f3->set('ENVIRONMENT.SMTP_USER', 'user@example.com');
        self::$f3->set('ENVIRONMENT.SMTP_PASS', 'smtp_password');
        self::$f3->set('ENVIRONMENT.SMTP_FROM', 'noreply@example.com');

        // Set up Pathfinder data
        self::$f3->set('PATHFINDER.NAME', 'Pathfinder Test');
        self::$f3->set('PATHFINDER.NOTIFICATION.TEST', 'test@example.com');
        self::$f3->set('PATHFINDER.MAP.PRIVATE.MAX_COUNT', 10);

        // Set up Plugin data
        self::$f3->set('PLUGIN.TEST_ENABLED', true);
        self::$f3->set('PLUGIN.TEST.OPTION1', 'value1');
        self::$f3->set('PLUGIN.DISABLED_ENABLED', false);

        // Set up requirements
        self::$f3->set('REQUIREMENTS.MYSQL.PDO_TIMEOUT', 3);
        self::$f3->set('REQUIREMENTS.MYSQL.VARS.CHARACTER_SET_CONNECTION', 'utf8mb4');
        self::$f3->set('REQUIREMENTS.MYSQL.VARS.COLLATION_CONNECTION', 'utf8mb4_unicode_520_ci');
        self::$f3->set('REQUIREMENTS.MYSQL.VARS.DEFAULT_STORAGE_ENGINE', 'InnoDB');
    }

    // parseDSN Tests

    public function testParseDSNRedis(): void
    {
        $dsn = 'redis=localhost:6379:2';
        $conf = [];

        $result = Config::parseDSN($dsn, $conf);

        $this->assertTrue($result);
        $this->assertEquals('redis', $conf['type']);
        $this->assertEquals('localhost', $conf['host']);
        $this->assertEquals(6379, $conf['port']);
        $this->assertEquals(2, $conf['db']);
    }

    public function testParseDSNRedisWithAuth(): void
    {
        $dsn = 'redis=localhost:6379:2:mypassword';
        $conf = [];

        $result = Config::parseDSN($dsn, $conf);

        $this->assertTrue($result);
        $this->assertEquals('redis', $conf['type']);
        $this->assertEquals('localhost', $conf['host']);
        $this->assertEquals(6379, $conf['port']);
        $this->assertEquals(2, $conf['db']);
        $this->assertEquals('mypassword', $conf['auth']);
    }

    public function testParseDSNRedisDefaults(): void
    {
        $dsn = 'redis=localhost';
        $conf = [];

        $result = Config::parseDSN($dsn, $conf);

        $this->assertTrue($result);
        $this->assertEquals('redis', $conf['type']);
        $this->assertEquals('localhost', $conf['host']);
        $this->assertEquals(6379, $conf['port']);
        $this->assertNull($conf['db']);
    }

    public function testParseDSNFolder(): void
    {
        $dsn = 'folder=/tmp/cache';
        $conf = [];

        $result = Config::parseDSN($dsn, $conf);

        $this->assertTrue($result);
        $this->assertEquals('folder', $conf['type']);
        $this->assertEquals('/tmp/cache', $conf['folder']);
    }

    public function testParseDSNInvalid(): void
    {
        $dsn = 'invalid_format';
        $conf = [];

        $result = Config::parseDSN($dsn, $conf);

        $this->assertFalse($result);
    }

    // formatTimeInterval Tests

    public function testFormatTimeIntervalZero(): void
    {
        $result = Config::formatTimeInterval(0);

        $this->assertEquals('', $result);
    }

    public function testFormatTimeIntervalSeconds(): void
    {
        $result = Config::formatTimeInterval(45);

        $this->assertEquals('45s', $result);
    }

    public function testFormatTimeIntervalMinutes(): void
    {
        $result = Config::formatTimeInterval(125);

        $this->assertEquals('2m 5s', $result);
    }

    public function testFormatTimeIntervalHours(): void
    {
        $result = Config::formatTimeInterval(3665);

        $this->assertEquals('1h 1m 5s', $result);
    }

    public function testFormatTimeIntervalDays(): void
    {
        $result = Config::formatTimeInterval(90061);

        $this->assertEquals('1d 1h 1m 1s', $result);
    }

    public function testFormatTimeIntervalOnlyMinutes(): void
    {
        $result = Config::formatTimeInterval(120);

        $this->assertEquals('2m ', $result);
    }

    // ttlLeft Tests

    public function testTtlLeftFalse(): void
    {
        $result = Config::ttlLeft(false, 60);

        $this->assertEquals(60, $result);
    }

    public function testTtlLeftTrue(): void
    {
        $result = Config::ttlLeft(true, 60);

        $this->assertEquals(0, $result);
    }

    public function testTtlLeftArray(): void
    {
        $futureTime = microtime(true) + 30;
        $result = Config::ttlLeft([$futureTime], 60);

        $this->assertGreaterThan(25, $result);
        $this->assertLessThanOrEqual(30, $result);
    }

    public function testTtlLeftArrayExpired(): void
    {
        $pastTime = microtime(true) - 10;
        $result = Config::ttlLeft([$pastTime], 60);

        $this->assertEquals(0, $result);
    }

    public function testTtlLeftNegativeMax(): void
    {
        $result = Config::ttlLeft(false, -10);

        $this->assertEquals(0, $result);
    }

    // withNamespace Tests

    public function testWithNamespaceNoClass(): void
    {
        $result = Config::withNamespace(null);

        $this->assertEquals('Exodus4D\\Pathfinder', $result);
    }

    public function testWithNamespaceWithClass(): void
    {
        $result = Config::withNamespace('Model\\SystemModel');

        $this->assertEquals('Exodus4D\\Pathfinder\\Model\\SystemModel', $result);
    }

    public function testWithNamespaceEmptyString(): void
    {
        $result = Config::withNamespace('');

        $this->assertEquals('Exodus4D\\Pathfinder', $result);
    }

    // getHttpStatusByCode Tests

    public function testGetHttpStatusByCode200(): void
    {
        $result = Config::getHttpStatusByCode(200);

        $this->assertEquals('OK', $result);
    }

    public function testGetHttpStatusByCode404(): void
    {
        $result = Config::getHttpStatusByCode(404);

        $this->assertEquals('Not Found', $result);
    }

    public function testGetHttpStatusByCode422(): void
    {
        $result = Config::getHttpStatusByCode(422);

        $this->assertEquals('Unprocessable Entity', $result);
    }

    public function testGetHttpStatusByCode500(): void
    {
        $result = Config::getHttpStatusByCode(500);

        $this->assertEquals('Internal Server Error', $result);
    }

    // isValidSMTPConfig Tests

    public function testIsValidSMTPConfigValid(): void
    {
        $config = new \stdClass();
        $config->host = 'smtp.example.com';
        $config->username = 'user';
        $config->from = ['test@example.com' => 'Test User'];
        $config->to = 'recipient@example.com';

        $result = Config::isValidSMTPConfig($config);

        $this->assertTrue($result);
    }

    public function testIsValidSMTPConfigMissingHost(): void
    {
        $config = new \stdClass();
        $config->host = '';
        $config->username = 'user';
        $config->from = ['test@example.com' => 'Test User'];
        $config->to = 'recipient@example.com';

        $result = Config::isValidSMTPConfig($config);

        $this->assertFalse($result);
    }

    public function testIsValidSMTPConfigMissingUsername(): void
    {
        $config = new \stdClass();
        $config->host = 'smtp.example.com';
        $config->username = '';
        $config->from = ['test@example.com' => 'Test User'];
        $config->to = 'recipient@example.com';

        $result = Config::isValidSMTPConfig($config);

        $this->assertFalse($result);
    }

    public function testIsValidSMTPConfigInvalidFromEmail(): void
    {
        $config = new \stdClass();
        $config->host = 'smtp.example.com';
        $config->username = 'user';
        $config->from = ['invalid-email' => 'Test User'];
        $config->to = 'recipient@example.com';

        $result = Config::isValidSMTPConfig($config);

        $this->assertFalse($result);
    }

    public function testIsValidSMTPConfigFromAsString(): void
    {
        $config = new \stdClass();
        $config->host = 'smtp.example.com';
        $config->username = 'user';
        $config->from = 'test@example.com';
        $config->to = 'recipient@example.com';

        $result = Config::isValidSMTPConfig($config);

        $this->assertTrue($result);
    }

    // getEnvironmentData Tests

    public function testGetEnvironmentData(): void
    {
        $result = Config::getEnvironmentData('TEST_KEY');

        $this->assertEquals('test_value', $result);
    }

    public function testGetEnvironmentDataNonExistent(): void
    {
        $result = Config::getEnvironmentData('NON_EXISTENT_KEY');

        $this->assertNull($result);
    }

    // getPathfinderData Tests

    public function testGetPathfinderData(): void
    {
        $result = Config::getPathfinderData('NAME');

        $this->assertEquals('Pathfinder Test', $result);
    }

    public function testGetPathfinderDataNested(): void
    {
        $result = Config::getPathfinderData('NOTIFICATION.TEST');

        $this->assertEquals('test@example.com', $result);
    }

    public function testGetPathfinderDataNonExistent(): void
    {
        $result = Config::getPathfinderData('NON_EXISTENT');

        $this->assertNull($result);
    }

    // getSocketUri Tests

    public function testGetSocketUri(): void
    {
        $result = Config::getSocketUri();

        $this->assertEquals('tcp://127.0.0.1:5555', $result);
    }

    public function testGetSocketUriMissingConfig(): void
    {
        // Temporarily remove socket config
        $host = self::$f3->get('ENVIRONMENT.SOCKET_HOST');
        self::$f3->clear('ENVIRONMENT.SOCKET_HOST');

        $result = Config::getSocketUri();

        $this->assertFalse($result);

        // Restore config
        self::$f3->set('ENVIRONMENT.SOCKET_HOST', $host);
    }

    // getSMTPConfig Tests

    public function testGetSMTPConfig(): void
    {
        $result = Config::getSMTPConfig();

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertEquals('smtp.example.com', $result->host);
        $this->assertEquals('587', $result->port);
        $this->assertEquals('tls', $result->scheme);
        $this->assertEquals('user@example.com', $result->username);
        $this->assertEquals('smtp_password', $result->password);
        $this->assertIsArray($result->from);
        $this->assertArrayHasKey('noreply@example.com', $result->from);
    }

    // getNotificationMail Tests

    public function testGetNotificationMail(): void
    {
        $result = Config::getNotificationMail('TEST');

        $this->assertEquals('test@example.com', $result);
    }

    // getMapsDefaultConfig Tests

    public function testGetMapsDefaultConfig(): void
    {
        $result = Config::getMapsDefaultConfig('PRIVATE');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('max_count', $result);
        $this->assertEquals(10, $result['max_count']);
    }

    // getPluginConfig Tests

    public function testGetPluginConfigEnabled(): void
    {
        $result = Config::getPluginConfig('TEST');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('OPTION1', $result);
        $this->assertEquals('value1', $result['OPTION1']);
    }

    public function testGetPluginConfigDisabled(): void
    {
        $result = Config::getPluginConfig('DISABLED');

        $this->assertNull($result);
    }

    public function testGetPluginConfigCheckDisabled(): void
    {
        $result = Config::getPluginConfig('DISABLED', false);

        $this->assertIsArray($result);
    }

    // getDatabaseConfig Tests

    public function testGetDatabaseConfig(): void
    {
        $result = Config::getDatabaseConfig(self::$f3, 'PF');

        $this->assertIsArray($result);
        $this->assertEquals('PF', $result['ALIAS']);
        $this->assertEquals('mysql', $result['SCHEME']);
        $this->assertEquals('localhost', $result['HOST']);
        $this->assertEquals(3306, $result['PORT']);
        $this->assertEquals('pathfinder', $result['NAME']);
        $this->assertEquals('root', $result['USER']);
        $this->assertEquals('password', $result['PASS']);
        $this->assertArrayHasKey('OPTIONS', $result);
    }

    public function testGetDatabaseConfigOptions(): void
    {
        $result = Config::getDatabaseConfig(self::$f3, 'PF');

        $this->assertArrayHasKey('OPTIONS', $result);
        $this->assertArrayHasKey(\PDO::ATTR_ERRMODE, $result['OPTIONS']);
        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $result['OPTIONS'][\PDO::ATTR_ERRMODE]);
        $this->assertArrayHasKey(\PDO::MYSQL_ATTR_COMPRESS, $result['OPTIONS']);
        $this->assertTrue($result['OPTIONS'][\PDO::MYSQL_ATTR_COMPRESS]);
    }

    // getRequiredDbVars Tests

    public function testGetRequiredDbVars(): void
    {
        $result = Config::getRequiredDbVars(self::$f3, 'mysql');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('CHARACTER_SET_CONNECTION', $result);
        $this->assertEquals('utf8mb4', $result['CHARACTER_SET_CONNECTION']);
        $this->assertArrayHasKey('COLLATION_CONNECTION', $result);
        $this->assertEquals('utf8mb4_unicode_520_ci', $result['COLLATION_CONNECTION']);
    }

    public function testGetRequiredDbVarsNonExistent(): void
    {
        $result = Config::getRequiredDbVars(self::$f3, 'postgresql');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // inDownTimeRange Tests

    public function testInDownTimeRangeOutside(): void
    {
        // Use current date with time that's outside downtime range (configured as 11:00)
        $dateCheck = new \DateTime('now', new \DateTimeZone('UTC'));
        $dateCheck->setTime(12, 0, 0);

        $result = Config::inDownTimeRange($dateCheck);

        $this->assertFalse($result);
    }

    public function testInDownTimeRangeInside(): void
    {
        // Use current date with time that's inside downtime range
        // Downtime is 11:00, buffer extends from 10:59 to 11:09
        $dateCheck = new \DateTime('now', new \DateTimeZone('UTC'));
        $dateCheck->setTime(11, 5, 0);

        $result = Config::inDownTimeRange($dateCheck);

        $this->assertTrue($result);
    }

    public function testInDownTimeRangeAtStart(): void
    {
        // 10:59 should be just inside the buffer (11:00 - 1 minute buffer)
        $dateCheck = new \DateTime('now', new \DateTimeZone('UTC'));
        $dateCheck->setTime(10, 59, 0);

        $result = Config::inDownTimeRange($dateCheck);

        $this->assertTrue($result);
    }

    public function testInDownTimeRangeAtEnd(): void
    {
        // 11:09 should be inside (11:00 + 8 minutes + 1 minute buffer)
        $dateCheck = new \DateTime('now', new \DateTimeZone('UTC'));
        $dateCheck->setTime(11, 9, 0);

        $result = Config::inDownTimeRange($dateCheck);

        $this->assertTrue($result);
    }

    public function testInDownTimeRangeAfterEnd(): void
    {
        // 11:11 should be outside (after 11:00 + 8 minutes + 1 minute buffer)
        $dateCheck = new \DateTime('now', new \DateTimeZone('UTC'));
        $dateCheck->setTime(11, 11, 0);

        $result = Config::inDownTimeRange($dateCheck);

        $this->assertFalse($result);
    }

    // pingDomain Tests

    public function testPingDomainLocalhost(): void
    {
        // Test pinging localhost on a common port
        $result = Config::pingDomain('127.0.0.1', 80, 1);

        // Result should be either positive (connection time in ms) or -1 (failed)
        $this->assertIsInt($result);
    }

    public function testPingDomainInvalidPort(): void
    {
        // Test pinging an unlikely-to-be-open port
        $result = Config::pingDomain('127.0.0.1', 99999, 1);

        $this->assertEquals(-1, $result);
    }
}
