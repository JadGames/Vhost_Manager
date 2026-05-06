<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Symmetric authenticated encryption for secrets stored in SQLite.
 *
 * Uses libsodium secretbox (XSalsa20-Poly1305) with a per-value random nonce.
 * Encrypted values are prefixed with "enc:v1:" to allow transparent migration
 * from legacy plaintext data — unencrypted values are returned as-is on read.
 *
 * The master key is a 64-character lowercase hex string (32 bytes) supplied via
 * the VHM_SECRET_KEY environment variable.  Generate one with:
 *   php -r "echo sodium_bin2hex(random_bytes(32)) . PHP_EOL;"
 *
 * IMPORTANT: If the key is lost, all encrypted secrets are unrecoverable.
 * Back up the key independently of the database.
 */
final class SecretEncryption
{
    private const PREFIX = 'enc:v1:';

    /** @var string Binary 32-byte key */
    private readonly string $key;

    public function __construct(string $hexKey)
    {
        if (strlen($hexKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2 || !ctype_xdigit($hexKey)) {
            throw new RuntimeException(
                'VHM_SECRET_KEY must be a 64-character lowercase hex string (32 bytes). ' .
                'Generate one with: php -r "echo sodium_bin2hex(random_bytes(32)) . PHP_EOL;"'
            );
        }

        $this->key = sodium_hex2bin($hexKey);
    }

    /**
     * Encrypt a plaintext string.  Returns a self-contained, base64-encoded
     * token that includes the random nonce and authentication tag.
     */
    public function encrypt(string $plaintext): string
    {
        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * If the value does not carry the "enc:v1:" prefix it is treated as legacy
     * plaintext and returned unchanged — this makes migration transparent.
     *
     * @throws RuntimeException on authentication failure or malformed input.
     */
    public function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            // Legacy plaintext — return as-is, will be re-encrypted on next write.
            return $value;
        }

        $encoded = substr($value, strlen(self::PREFIX));
        $decoded = base64_decode($encoded, true);

        $minLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if ($decoded === false || strlen($decoded) < $minLength) {
            throw new RuntimeException('Encrypted secret value is malformed or truncated.');
        }

        $nonce      = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new RuntimeException(
                'Failed to decrypt secret: authentication failed. ' .
                'The encryption key may be incorrect or the value is corrupted.'
            );
        }

        return $plaintext;
    }

    /**
     * Returns true if the value was produced by encrypt() and has not been
     * tampered with structurally (the prefix is present and the payload is
     * base64-decodable to the minimum expected length).
     */
    public function isEncrypted(string $value): bool
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return false;
        }

        $decoded = base64_decode(substr($value, strlen(self::PREFIX)), true);
        $minLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

        return $decoded !== false && strlen($decoded) >= $minLength;
    }
}
