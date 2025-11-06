<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use RuntimeException;

/**
 * Class CSRFProtection
 *
 * Provides CSRF token generation and validation
 */
class CSRFProtection
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_TIME_NAME = 'csrf_token_time';
    private const TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Generate a new CSRF token
     *
     * @return string The generated token
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            throw new RuntimeException('Session must be started before generating CSRF token');
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_TIME_NAME] = time();

        return $token;
    }

    /**
     * Get the current CSRF token, generating one if it doesn't exist
     *
     * @return string The current token
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            throw new RuntimeException('Session must be started before getting CSRF token');
        }

        if (!isset($_SESSION[self::TOKEN_NAME]) || self::isTokenExpired()) {
            return self::generateToken();
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Validate a CSRF token
     *
     * @param string|null $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }

        if (!isset($_SESSION[self::TOKEN_NAME]) || $token === null) {
            return false;
        }

        if (self::isTokenExpired()) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    /**
     * Check if the current token is expired
     *
     * @return bool True if expired, false otherwise
     */
    private static function isTokenExpired(): bool
    {
        if (!isset($_SESSION[self::TOKEN_TIME_NAME])) {
            return true;
        }

        return (time() - $_SESSION[self::TOKEN_TIME_NAME]) > self::TOKEN_LIFETIME;
    }

    /**
     * Get HTML input field for CSRF token
     *
     * @return string HTML input field
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Verify CSRF token from POST request
     *
     * @throws RuntimeException If token is invalid
     */
    public static function verifyPostToken(): void
    {
        $token = $_POST['csrf_token'] ?? null;

        if (!self::validateToken($token)) {
            throw new RuntimeException('Invalid CSRF token');
        }
    }
}
