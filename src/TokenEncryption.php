<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use RuntimeException;

/**
 * Class TokenEncryption
 *
 * Provides encryption and decryption for OAuth tokens
 */
class TokenEncryption
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32; // 256 bits

    /**
     * @var string Encryption key
     */
    private $key;

    /**
     * TokenEncryption constructor
     *
     * @param string|null $key Encryption key (32 bytes). If not provided, will try to get from environment
     * @throws RuntimeException If key is invalid
     */
    public function __construct(?string $key = null)
    {
        if ($key === null) {
            // Try to get from environment variable
            $key = getenv('ENCRYPTION_KEY');

            if ($key === false || empty($key)) {
                throw new RuntimeException(
                    'Encryption key not provided. Set ENCRYPTION_KEY environment variable or pass key to constructor'
                );
            }
        }

        // Validate key length
        if (strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException('Encryption key must be exactly 32 bytes (256 bits)');
        }

        $this->key = $key;
    }

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     * @throws RuntimeException If encryption fails
     */
    public function encrypt(string $data): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false) {
            throw new RuntimeException('Failed to get cipher IV length');
        }

        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Combine IV + tag + encrypted data and encode
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt data
     *
     * @param string $encryptedData Encrypted data (base64 encoded)
     * @return string Decrypted data
     * @throws RuntimeException If decryption fails
     */
    public function decrypt(string $encryptedData): string
    {
        $data = base64_decode($encryptedData, true);
        if ($data === false) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false) {
            throw new RuntimeException('Failed to get cipher IV length');
        }

        $tagLength = 16; // GCM tag is always 16 bytes

        // Extract IV, tag, and encrypted data
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $encrypted = substr($data, $ivLength + $tagLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed - data may be corrupted or key is incorrect');
        }

        return $decrypted;
    }

    /**
     * Generate a random encryption key
     *
     * @return string Random key (32 bytes)
     */
    public static function generateKey(): string
    {
        return random_bytes(self::KEY_LENGTH);
    }

    /**
     * Get the encryption key as a base64 string for storage
     *
     * @param string $key Binary key
     * @return string Base64 encoded key
     */
    public static function encodeKey(string $key): string
    {
        return base64_encode($key);
    }

    /**
     * Decode a base64 encoded key
     *
     * @param string $encodedKey Base64 encoded key
     * @return string Binary key
     */
    public static function decodeKey(string $encodedKey): string
    {
        $key = base64_decode($encodedKey, true);
        if ($key === false) {
            throw new RuntimeException('Invalid key format');
        }
        return $key;
    }
}
