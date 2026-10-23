<?php
/**
 * Versioned authenticated encryption for ESI OAuth tokens at rest.
 *
 * - Uses libsodium secretbox (XSalsa20-Poly1305) with a random nonce per value.
 * - Stored format: `v1:<keyFingerprint>:<base64(nonce || ciphertext)>`.
 *   The fingerprint identifies the encrypting key, so rotation progress
 *   is visible in the data itself.
 * - Keys come from the environment (per-instance K8s Secret), never from code:
 *   `PF-ENV-ESI_CRYPTO_KEY` (current, encrypt + decrypt) and
 *   `PF-ENV-ESI_CRYPTO_KEY_OLD` (previous, decrypt-only for rotation).
 *   Each value is base64 of a 32-byte key; see generateKey().
 * - Values without the `v1:` prefix are treated as legacy plaintext and
 *   returned as-is (lazy migration: rows re-encrypt on next token write).
 * - No-key operation stores plaintext and logs a one-time warning so an
 *   upgrade without the Secret deployed does not break logins; production
 *   instances MUST configure the key (see deploy docs).
 */

namespace Exodus4D\Pathfinder\Lib\Crypto;

use Exodus4D\Pathfinder\Lib\Config;

class TokenCrypto {

    const VERSION = 'v1';

    private static $warnedNoKey = false;

    /**
     * stable fingerprint identifying a key (first 12 URL-safe base64 chars
     * of its SHA-256) for the stored version prefix
     * @param string $rawKey raw binary key
     * @return string
     */
    public static function keyFingerprint(string $rawKey) : string {
        return substr(strtr(base64_encode(hash('sha256', $rawKey, true)), '+/', '-_'), 0, 12);
    }

    /**
     * @return string|null raw binary current key (encrypt + decrypt)
     */
    public static function getCurrentKey() : ?string {
        return self::parseKey((string)Config::getEnvironmentData('ESI_CRYPTO_KEY'));
    }

    /**
     * @return string|null raw binary previous key (decrypt-only for rotation)
     */
    public static function getPreviousKey() : ?string {
        $previous = self::parseKey((string)Config::getEnvironmentData('ESI_CRYPTO_KEY_OLD'));
        if(!is_null($previous) && $previous !== self::getCurrentKey()){
            return $previous;
        }
        return null;
    }

    /**
     * @return array fingerprint => raw binary key (only valid configured keys)
     */
    public static function getKeys() : array {
        $keys = [];
        if(!is_null($current = self::getCurrentKey())){
            $keys[self::keyFingerprint($current)] = $current;
        }
        if(!is_null($previous = self::getPreviousKey())){
            $fp = self::keyFingerprint($previous);
            if(!isset($keys[$fp])){
                $keys[$fp] = $previous;
            }
        }
        return $keys;
    }

    /**
     * @return bool whether at least one usable key is configured
     */
    public static function hasKeys() : bool {
        return !empty(self::getKeys());
    }

    /**
     * generate a new random key, base64-encoded for env/Secret storage
     * @return string
     */
    public static function generateKey() : string {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    /**
     * encrypt a plaintext token for storage (no-op for empty values)
     * -> without a configured key the value is stored as-is (legacy behavior)
     *    and a one-time warning is logged; configure the key in production
     * @param string $plaintext
     * @return string
     */
    public static function encrypt(string $plaintext) : string {
        if($plaintext === ''){
            return '';
        }
        if(self::isEncrypted($plaintext)){
            // already encrypted (e.g. defensive double-set) -> keep as-is
            return $plaintext;
        }
        $keys = self::getKeys();
        if(is_null($current = self::getCurrentKey())){
            self::warnNoKey();
            return $plaintext;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $current);
        sodium_memzero($plaintext);
        return self::VERSION . ':' . self::keyFingerprint($current) . ':' . base64_encode($nonce . $cipher);
    }

    /**
     * decrypt a stored value (null on failure)
     * -> legacy plaintext (no version prefix) is returned as-is for migration
     * -> failures are logged WITHOUT the stored value; callers should treat
     *    null as "credential unusable" (refresh from provider or re-login)
     * @param mixed $stored
     * @return string|null
     */
    public static function decrypt($stored) : ?string {
        if(!is_string($stored) || $stored === ''){
            return '';
        }
        if(!self::isEncrypted($stored)){
            return $stored;
        }
        $parts = explode(':', $stored, 3);
        if(count($parts) !== 3){
            error_log('TokenCrypto: malformed encrypted value (refusing to decrypt)');
            return null;
        }
        $raw = base64_decode($parts[2], true);
        if($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES){
            error_log('TokenCrypto: malformed encrypted payload');
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        foreach(self::getKeys() as $keyId => $key){
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if($plain !== false){
                return $plain;
            }
        }
        error_log('TokenCrypto: decryption failed with all configured keys');
        return null;
    }

    /**
     * @param mixed $stored
     * @return bool
     */
    public static function isEncrypted($stored) : bool {
        return is_string($stored) && strpos($stored, self::VERSION . ':') === 0;
    }

    /**
     * @param string $configured base64-encoded key material
     * @return string|null raw key or null when missing/invalid
     */
    private static function parseKey(string $configured) : ?string {
        if($configured === ''){
            return null;
        }
        $raw = base64_decode($configured, true);
        if($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES){
            error_log('TokenCrypto: ignoring misconfigured key (must be base64 of 32 bytes)');
            return null;
        }
        return $raw;
    }

    private static function warnNoKey() : void {
        if(!self::$warnedNoKey){
            self::$warnedNoKey = true;
            error_log('TokenCrypto: no ESI_CRYPTO_KEY configured - tokens stored unencrypted');
        }
    }
}
