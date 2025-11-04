<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

/**
 * Class SessionSecurity
 *
 * Provides secure session initialization and management
 */
class SessionSecurity
{
    /**
     * Start a secure session with recommended security settings
     *
     * @param bool $forceHttps Whether to force HTTPS for cookies (set to false for local dev)
     * @return void
     */
    public static function startSecureSession(bool $forceHttps = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Configure session security settings
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Strict');

        // Only force secure cookies if HTTPS is available
        if ($forceHttps && (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
            ini_set('session.cookie_secure', '1');
        }

        // Use strong session ID
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        // Session timeout
        ini_set('session.gc_maxlifetime', '3600'); // 1 hour

        session_start();

        // Regenerate session ID on first access to prevent session fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
        }

        // Check for session hijacking
        self::validateSession();
    }

    /**
     * Validate session to prevent hijacking
     *
     * @return void
     */
    private static function validateSession(): void
    {
        // Check if session is too old (4 hours)
        if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > 14400) {
            self::destroySession();
            return;
        }

        // Validate user agent to prevent session hijacking
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            // Possible session hijacking
            self::destroySession();
            return;
        }

        // Periodically regenerate session ID (every 30 minutes)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Destroy the current session
     *
     * @return void
     */
    public static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Delete the session cookie
            if (isset($_COOKIE[session_name()])) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
        }
    }
}
