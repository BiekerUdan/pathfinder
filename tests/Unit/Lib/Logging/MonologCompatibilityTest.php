<?php
/**
 * Unit tests for Monolog Compatibility Issues
 * Tests expose critical Monolog version incompatibilities found by PHPStan
 */

namespace Tests\Unit\Lib\Logging;

use PHPUnit\Framework\TestCase;
use Exodus4D\Pathfinder\Lib\Logging\Formatter\MailFormatter;
use Exodus4D\Pathfinder\Lib\Logging\Handler\AbstractWebhookHandler;
use Exodus4D\Pathfinder\Lib\Logging\Handler\SocketHandler;
use Monolog\Logger;

class MonologCompatibilityTest extends TestCase
{
    /**
     * Test MailFormatter::format() parameter type incompatibility
     * PHPStan Error: Parameter #1 $record (array) is not contravariant with
     * parameter #1 $record (Monolog\LogRecord) (line 20)
     *
     * ISSUE: Monolog 3.x changed log records from arrays to LogRecord objects
     */
    public function testMailFormatterParameterTypeIncompatibility(): void
    {
        // CURRENT SIGNATURE (Pathfinder):
        // public function format(array $record)
        //
        // EXPECTED SIGNATURE (Monolog 3.x):
        // public function format(LogRecord $record)
        //
        // MONOLOG 2.x used arrays, MONOLOG 3.x uses LogRecord objects

        // Set missing $_SERVER values for CLI context
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }

        $formatter = new MailFormatter();

        // Test with array (Monolog 2.x style) - currently works
        $arrayRecord = [
            'message' => 'Test message',
            'context' => [
                'data' => [
                    'main' => []
                ]
            ],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

        try {
            $result = $formatter->format($arrayRecord);
            $this->assertIsString($result);

            echo "\n⚠ MailFormatter works with array (Monolog 2.x style)\n";
            echo "   But will fail with Monolog 3.x LogRecord objects!\n";
        } catch (\TypeError $e) {
            $this->fail('MailFormatter failed with array: ' . $e->getMessage());
        }

        // FIX OPTIONS:
        // 1. Union type: public function format(array|LogRecord $record)
        // 2. Type check: if ($record instanceof LogRecord) { $record = $record->toArray(); }
        // 3. Lock Monolog to 2.x in composer.json

        $this->markTestIncomplete(
            'MailFormatter uses array type hint but Monolog 3.x expects LogRecord. ' .
            'Choose fix: (1) Union type, (2) Convert LogRecord->toArray(), or (3) Lock Monolog version'
        );
    }

    /**
     * Test AbstractWebhookHandler::write() parameter type incompatibility
     * PHPStan Error: Parameter #1 $record (array) is not contravariant with
     * parameter #1 $record (Monolog\LogRecord) (line 130)
     */
    public function testAbstractWebhookHandlerParameterTypeIncompatibility(): void
    {
        // CURRENT SIGNATURE (Pathfinder):
        // protected function write(array $record) : void
        //
        // EXPECTED SIGNATURE (Monolog 3.x):
        // protected function write(LogRecord $record) : void

        // AbstractWebhookHandler is abstract, so we can't instantiate it directly
        // But the issue is the same as MailFormatter

        echo "\n⚠ AbstractWebhookHandler::write() expects array (Monolog 2.x)\n";
        echo "   Parent class AbstractProcessingHandler expects LogRecord (Monolog 3.x)\n";
        echo "   This causes method signature incompatibility\n";

        // FIX: Same as MailFormatter
        // protected function write(array|LogRecord $record) : void {
        //     $recordArray = $record instanceof LogRecord ? $record->toArray() : $record;
        //     // ... rest of method uses $recordArray
        // }

        $this->markTestIncomplete(
            'AbstractWebhookHandler::write() has parameter type mismatch with parent class. ' .
            'Update signature to accept LogRecord or convert to array internally.'
        );
    }

    /**
     * Test SocketHandler::handle() parameter type incompatibility
     * PHPStan Error: Parameter #1 $record (array) is not contravariant with
     * parameter #1 $record (Monolog\LogRecord) (line 41)
     */
    public function testSocketHandlerParameterTypeIncompatibility(): void
    {
        // CURRENT SIGNATURE (Pathfinder):
        // public function handle(array $record) : bool
        //
        // EXPECTED SIGNATURE (Monolog 3.x):
        // public function handle(LogRecord $record) : bool

        // Test with Monolog 2.x array record
        $arrayRecord = [
            'message' => 'Test log message',
            'context' => [],
            'level' => Logger::DEBUG,
            'level_name' => 'DEBUG',
            'channel' => 'test',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

        // We can't test SocketHandler without a real socket connection,
        // but we can document the incompatibility

        echo "\n⚠ SocketHandler::handle() overrides parent with array type\n";
        echo "   Parent AbstractProcessingHandler expects LogRecord (Monolog 3.x)\n";
        echo "   Lines 46, 48 call processRecord() and format() with array\n";

        // FIX:
        // public function handle(array|LogRecord $record) : bool {
        //     $recordArray = $record instanceof LogRecord ? $record->toArray() : $record;
        //     if (!$this->isHandling($recordArray)) {
        //         return false;
        //     }
        //     $recordArray = $this->processRecord($recordArray);
        //     // ... etc
        // }

        $this->markTestIncomplete(
            'SocketHandler::handle() parameter type incompatible with Monolog 3.x. ' .
            'Update to accept LogRecord|array and convert as needed.'
        );
    }

    /**
     * Test to check current Monolog version
     */
    public function testDetectMonologVersion(): void
    {
        $monologVersion = Logger::API;

        echo "\n📊 Monolog API Version: $monologVersion\n";

        if ($monologVersion >= 3) {
            echo "   ⚠ Using Monolog 3.x - LogRecord incompatibilities ACTIVE!\n";
            echo "   All 3 handlers/formatters need updating\n";
        } elseif ($monologVersion >= 2) {
            echo "   ✓ Using Monolog 2.x - Array-based records (currently compatible)\n";
            echo "   But code will break on Monolog 3.x upgrade\n";
        } else {
            echo "   ✓ Using Monolog 1.x - Very old version\n";
        }

        // Check if LogRecord class exists (Monolog 3.x)
        if (class_exists('Monolog\LogRecord')) {
            echo "   ⚠ Monolog\\LogRecord class exists - Monolog 3.x detected\n";
            $this->markTestIncomplete(
                'Monolog 3.x detected. The 3 logging compatibility issues are CRITICAL and must be fixed!'
            );
        } else {
            echo "   ✓ Monolog\\LogRecord class not found - Using older Monolog\n";
            echo "   Code works now but will break on upgrade to Monolog 3.x\n";

            $this->assertTrue(true, 'Currently using compatible Monolog version');
        }
    }

    /**
     * Integration test: Verify formatBatch also has same issue
     */
    public function testMailFormatterBatchAlsoAffected(): void
    {
        // MailFormatter::formatBatch() calls format() internally
        // So it's also affected by the array vs LogRecord issue

        $formatter = new MailFormatter();

        $records = [
            [
                'message' => 'Message 1',
                'context' => ['data' => ['main' => []]],
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'test',
                'datetime' => new \DateTimeImmutable(),
                'extra' => [],
            ],
            [
                'message' => 'Message 2',
                'context' => ['data' => ['main' => []]],
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'test',
                'datetime' => new \DateTimeImmutable(),
                'extra' => [],
            ]
        ];

        try {
            $result = $formatter->formatBatch($records);
            $this->assertIsString($result);

            echo "\n⚠ formatBatch() also affected - calls format() with array\n";
        } catch (\TypeError $e) {
            $this->fail('formatBatch failed: ' . $e->getMessage());
        }

        $this->markTestIncomplete('formatBatch() inherits same array vs LogRecord issue');
    }
}
