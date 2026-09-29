<?php

namespace Tests\Unit\Controller\Api\Rest;

use PHPUnit\Framework\TestCase;

/**
 * Batch 1: Connection PUT IDOR regression guard.
 *
 * The update flow must resolve existing connections through the already-authorized
 * map ($map->getConnectionById()) and never load them globally by request ID.
 * A full multi-map exploit test needs a database; this guard keeps the fix from
 * regressing at the source level. See tests/Integration/ConnectionIdorTest.php
 * for the DB-backed cross-map case.
 */
class ConnectionPutIdorTest extends TestCase
{
    private string $controllerPath = __DIR__ . '/../../../../../app/Controller/Api/Rest/Connection.php';

    public function testPutUsesMapScopedLookup(): void
    {
        $source = file_get_contents($this->controllerPath);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'getConnectionById',
            $source,
            'Connection::put() must resolve existing connections via $map->getConnectionById()'
        );
    }

    public function testPutDoesNotLoadConnectionGloballyByRequestId(): void
    {
        $source = file_get_contents($this->controllerPath);
        $this->assertNotFalse($source);

        $this->assertStringNotContainsString(
            'getById((int)$requestData',
            $source,
            'Connection::put() must not load connections globally via getById(request id)'
        );
        $this->assertStringNotContainsString(
            "getById((int)(\$requestData['id'])",
            $source,
            'Connection::put() must not load connections globally via getById(request id)'
        );
    }

    public function testPutRejectsUnknownIdWithoutSaving(): void
    {
        $source = file_get_contents($this->controllerPath);
        $this->assertNotFalse($source);

        // Unknown / foreign-map IDs must bail out before any save() call path.
        $putStart = strpos($source, 'function put(');
        $this->assertNotFalse($putStart);
        $putBody = substr($source, $putStart);
        $this->assertStringContainsString('is_null($connection)', $putBody);
    }
}
