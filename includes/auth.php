<?php
// =========================================================
// InterLink — Auth & Session Helpers
// =========================================================
require_once __DIR__ . '/config.php';

// Buffer output to prevent headers-already-sent errors on shared hosting
if (ob_get_level() === 0) ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    $lifetime = 30 * 24 * 60 * 60; // 30 days
    ini_set('session.gc_maxlifetime', $lifetime);

    // Use samesite=Lax — supported PHP 7.3+; fall back silently on older builds
    $cookieParams = [
        'lifetime' => $lifetime,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    // On InfinityFree HTTPS, add secure flag
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $cookieParams['secure'] = true;
    }
    @session_set_cookie_params($cookieParams);
    @session_start();

    // Refresh cookie lifetime on each request so active users stay logged in
    if (isset($_SESSION['user_id'])) {
        $refresh = $cookieParams;
        unset($refresh['lifetime']); // 'lifetime' is invalid in setcookie() options
        $refresh['expires'] = time() + $lifetime;
        @setcookie(session_name(), session_id(), $refresh);
    }
}


function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserRole(): string {
    return $_SESSION['role'] ?? 'user';
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
