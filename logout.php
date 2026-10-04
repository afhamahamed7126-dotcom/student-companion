<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
session_unset();
session_destroy();

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

header('Location: ' . BASE_URL . 'login.php?logged_out=1');
exit;
