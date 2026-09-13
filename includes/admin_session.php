<?php
declare(strict_types=1);

/*
 * Admin sessions remain valid for 24 hours since the most recent
 * authenticated admin request.
 */
const ADMIN_SESSION_IDLE_SECONDS = 86400;

$adminSessionSecure =
    !empty($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off';

if (session_status() === PHP_SESSION_NONE) {

    ini_set(
        'session.gc_maxlifetime',
        (string)ADMIN_SESSION_IDLE_SECONDS
    );

    session_set_cookie_params([
        'lifetime' => ADMIN_SESSION_IDLE_SECONDS,
        'path' => '/',
        'secure' => $adminSessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$adminRole =
    $_SESSION['user_role']
    ?? $_SESSION['role']
    ?? '';

$adminAuthenticated =
    !empty($_SESSION['user_id']) &&
    $adminRole === 'admin';

if ($adminAuthenticated) {

    $now = time();

    $lastActivity =
        isset($_SESSION['admin_last_activity_at'])
            ? (int)$_SESSION['admin_last_activity_at']
            : 0;

    /*
     * If the session is older than the permitted inactivity window,
     * destroy the authenticated session before the requested admin
     * page continues.
     */
    if (
        $lastActivity > 0 &&
        ($now - $lastActivity) > ADMIN_SESSION_IDLE_SECONDS
    ) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?: '/',
                    'domain' => $params['domain'] ?: '',
                    'secure' => (bool)$params['secure'],
                    'httponly' => (bool)$params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();

        /*
         * Leave a fresh, empty PHP session available so downstream
         * auth wrappers can safely redirect to login.
         */
        session_start();

    } else {

        /*
         * Sliding inactivity window.
         */
        $_SESSION['admin_last_activity_at'] = $now;

        setcookie(
            session_name(),
            session_id(),
            [
                'expires' =>
                    $now + ADMIN_SESSION_IDLE_SECONDS,
                'path' => '/',
                'secure' => $adminSessionSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
