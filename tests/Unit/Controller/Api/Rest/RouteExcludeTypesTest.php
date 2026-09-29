<?php

namespace Tests\Unit\Controller\Api\Rest;

use Exodus4D\Pathfinder\Controller\Api\Rest\Route;
use Exodus4D\Pathfinder\Model\Pathfinder\ConnectionModel;
use PHPUnit\Framework\TestCase;

/**
 * Batch 1: Route excludeTypes SQL injection regression tests.
 */
class RouteExcludeTypesTest extends TestCase
{
    private $prevErrorHandler;
    private $prevExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        // Loading F3-backed model classes registers global handlers on first use;
        // capture the pre-test handlers so tearDown can pop test-pushed ones.
        $this->prevErrorHandler = set_error_handler(function () { return false; });
        restore_error_handler();
        $this->prevExceptionHandler = set_exception_handler(function () {});
        restore_exception_handler();
    }

    protected function tearDown(): void
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
        parent::tearDown();
    }
    public function testValidTypesPassThrough(): void
    {
        $filtered = Route::filterRouteExcludeTypes(['wh_jump_mass_s', 'wh_jump_mass_m']);
        $this->assertSame(['wh_jump_mass_s', 'wh_jump_mass_m'], $filtered);

        $regexp = Route::buildExcludeTypesRegexp(['wh_jump_mass_s', 'wh_jump_mass_m']);
        $this->assertSame('wh_jump_mass_s|wh_jump_mass_m', $regexp);
    }

    public function testSingleQuoteInjectionRejected(): void
    {
        $filtered = Route::filterRouteExcludeTypes(["wh_jump_mass_s' OR '1'='1"]);
        $this->assertSame([], $filtered);
        $this->assertNull(Route::buildExcludeTypesRegexp(["wh_jump_mass_s' OR '1'='1"]));
    }

    public function testBackslashAndCommentRejected(): void
    {
        $payloads = [
            "wh_fresh\\",
            "wh_fresh' -- ",
            "wh_fresh' /*",
            "wh_fresh'; DROP TABLE connection; --",
        ];
        foreach ($payloads as $payload) {
            $this->assertSame([], Route::filterRouteExcludeTypes([$payload]), "payload: $payload");
        }
    }

    public function testRegexpMetacharsRejected(): void
    {
        // Even though these are "just" regexp, they must not reach SQL unvalidated.
        $payloads = ['.*', 'wh_(fresh|reduced)', '[a-z]+', '^wh_fresh$', 'wh_fresh|wh_reduced'];
        foreach ($payloads as $payload) {
            $this->assertSame([], Route::filterRouteExcludeTypes([$payload]), "payload: $payload");
        }
    }

    public function testMixedValidInvalidKeepsOnlyValid(): void
    {
        $filtered = Route::filterRouteExcludeTypes([
            'wh_jump_mass_s',
            "wh_jump_mass_m' OR 1=1 --",
            'not_a_type',
            'wh_jump_mass_l',
        ]);
        $this->assertSame(['wh_jump_mass_s', 'wh_jump_mass_l'], $filtered);
        $this->assertSame('wh_jump_mass_s|wh_jump_mass_l', Route::buildExcludeTypesRegexp([
            'wh_jump_mass_s',
            "wh_jump_mass_m' OR 1=1 --",
            'not_a_type',
            'wh_jump_mass_l',
        ]));
    }

    public function testEmptyArrayYieldsNullRegexp(): void
    {
        $this->assertSame([], Route::filterRouteExcludeTypes([]));
        $this->assertNull(Route::buildExcludeTypesRegexp([]));
    }

    public function testNonStringValuesRejected(): void
    {
        $this->assertSame([], Route::filterRouteExcludeTypes([123, null, true, ['wh_fresh']]));
    }

    public function testDuplicatesRemoved(): void
    {
        $filtered = Route::filterRouteExcludeTypes(['wh_fresh', 'wh_fresh', 'wh_reduced']);
        $this->assertSame(['wh_fresh', 'wh_reduced'], $filtered);
    }

    public function testWhitelistAccessorMatchesFilterSource(): void
    {
        $whitelist = ConnectionModel::getConnectionTypeWhitelist();
        $this->assertContains('wh_jump_mass_s', $whitelist);
        $this->assertContains('wh_fresh', $whitelist);
        // every filtered value must be in the whitelist
        foreach (Route::filterRouteExcludeTypes($whitelist) as $type) {
            $this->assertContains($type, $whitelist);
        }
    }

    public function testBuiltRegexpMatchesOnlyAllowedTypes(): void
    {
        // Sanity: built pattern uses MySQL-compatible alternation of safe tokens.
        $regexp = Route::buildExcludeTypesRegexp(['wh_fresh', 'wh_reduced']);
        $this->assertNotNull($regexp);
        // PHP-side check with same pattern anchored (MySQL REGEXP is unanchored substring match)
        $this->assertSame(1, preg_match('/' . $regexp . '/', '["wh_fresh"]'));
        $this->assertSame(0, preg_match('/' . $regexp . '/', '["wh_jump_mass_s"]'));
    }
}
