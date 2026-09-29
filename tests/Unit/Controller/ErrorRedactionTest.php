<?php

namespace Tests\Unit\Controller;

use Exodus4D\Pathfinder\Exception\DatabaseException;
use Exodus4D\Pathfinder\Exception\PathfinderException;
use Exodus4D\Pathfinder\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Batch 1: production error redaction regression tests.
 */
class ErrorRedactionTest extends TestCase
{
    private $prevErrorHandler;
    private $prevExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        // F3 Base::instance() registers global error/exception handlers on first
        // creation; capture the pre-test handlers so tearDown can pop test-pushed ones.
        $this->prevErrorHandler = set_error_handler(function () { return false; });
        restore_error_handler();
        $this->prevExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
        \Base::instance()->set('DEBUG', 0);
    }

    protected function tearDown(): void
    {
        \Base::instance()->set('DEBUG', 0);
        $this->restoreHandlers();
        parent::tearDown();
    }

    /**
     * pop any error/exception handlers pushed during the test,
     * restoring the handlers that were active in setUp (PHPUnit's)
     */
    private function restoreHandlers(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $top = set_error_handler(function () { return false; });
            restore_error_handler();
            if ($top === $this->prevErrorHandler) {
                break;
            }
            restore_error_handler();
        }
        for ($i = 0; $i < 10; $i++) {
            $top = set_exception_handler(function () {});
            restore_exception_handler();
            if ($top === $this->prevExceptionHandler) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testDatabaseExceptionRedactedInProduction(): void
    {
        \Base::instance()->set('DEBUG', 0);
        $error = (new DatabaseException("SELECT * FROM `connection` WHERE `type` NOT REGEXP ''' OR 1=1 --"))->getError();
        $this->assertSame(500, $error->code);
        $this->assertSame('Internal Server Error', $error->text);
        $this->assertObjectNotHasProperty('trace', $error);
    }

    public function testGeneric500RedactedInProduction(): void
    {
        \Base::instance()->set('DEBUG', 0);
        $error = (new PathfinderException('/var/www/pathfinder/app/Model/... PDO stack trace...'))->getError();
        $this->assertSame(500, $error->code);
        $this->assertSame('Internal Server Error', $error->text);
    }

    public function testValidationErrorKeepsMessageInProduction(): void
    {
        \Base::instance()->set('DEBUG', 0);
        $error = (new ValidationException('Name is required', 'name'))->getError();
        $this->assertSame(422, $error->code);
        $this->assertSame('Name is required', $error->text);
    }

    public function testDebugModeKeepsDetails(): void
    {
        \Base::instance()->set('DEBUG', 1);
        $error = (new DatabaseException('secret sql detail'))->getError();
        $this->assertSame('secret sql detail', $error->text);
    }
}
