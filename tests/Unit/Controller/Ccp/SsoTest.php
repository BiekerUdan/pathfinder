<?php
/**
 * Unit tests for SSO Controller
 * Tests expose critical issues found by PHPStan
 */

namespace Tests\Unit\Controller\Ccp;

use PHPUnit\Framework\TestCase;

class SsoTest extends TestCase
{
    /**
     * Test getCcpJwkData() missing return statement
     * PHPStan Error: Method should return array but return statement is missing (line 483)
     *
     * ISSUE: When ssoClient()->send('getJWKS') returns empty/null, the method
     * writes to log but doesn't return anything, causing null return instead of array.
     */
    public function testGetCcpJwkDataMissingReturnStatement(): void
    {
        // We can't easily test this without mocking the entire SSO system,
        // but we can document the issue and verify the fix once applied.

        // EXPECTED BEHAVIOR:
        // When JWK data is not available, method should return empty array []
        // instead of null/undefined

        // CURRENT BEHAVIOR (BUG):
        // Line 478-484:
        // if( !empty($jwkJson) ){
        //     array_walk($jwkJson['keys'], function(&$item){$item = (array) $item;});
        //     return $jwkJson;  // ✅ Returns array
        // }else{
        //     self::getSSOLogger()->write(...);  // ❌ No return statement!
        // }

        // IMPACT: Critical
        // - Return type violation: method declares ": array" but returns null
        // - Will cause TypeError when caller tries to use return value as array
        // - Called by verifyJwtAccessToken() which passes result to JWK::parseKeySet()

        // FIX: Add return statement in else block
        // }else{
        //     self::getSSOLogger()->write(sprintf(self::ERROR_LOGIN_FAILED, __METHOD__));
        //     return [];  // ✅ Return empty array
        // }

        $this->markTestIncomplete(
            'This test documents the missing return statement in Sso::getCcpJwkData() line 483. ' .
            'Fix by adding "return [];" in the else block. ' .
            'After fixing, this test should be updated to verify the fix.'
        );
    }

    /**
     * Test that demonstrates the impact of the missing return
     */
    public function testMissingReturnCausesTypeError(): void
    {
        // When getCcpJwkData() returns null instead of array,
        // it causes downstream errors in verifyJwtAccessToken()

        // Line 463 in Sso.php:
        // $decodedJwt = JWT::decode($accessToken, JWK::parseKeySet($ccpJwks));
        //                                                            ^^^^^^^^^
        //                                                            null instead of array!

        // JWK::parseKeySet() expects array but gets null -> TypeError

        $this->markTestIncomplete(
            'This test demonstrates that missing return causes TypeError ' .
            'when JWK::parseKeySet() receives null instead of array. ' .
            'Update this test after fix to verify correct behavior.'
        );
    }
}
