<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

log_activity($userId, 'logout', 'User logged out');

foreach (array_keys($_COOKIE) as $cookieName) {
    setcookie($cookieName, '', time() - 3600, '/');
}

logout_user();
