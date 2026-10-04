<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce login
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(BASE_URL . 'login.php');
}

// Session timeout
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        redirect(BASE_URL . 'login.php?timeout=1');
    }
}
$_SESSION['last_activity'] = time();

// Role guard — caller sets $required_role before including this file
if (!empty($required_role) && $_SESSION['role'] !== $required_role) {
    redirect(get_role_dashboard($_SESSION['role']));
}

// Fetch current user
$current_user = null;
try {
    $stmt = get_db()->prepare('SELECT id, name, email, role, department_id, year, status FROM users WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
} catch (Throwable) {}

if (!$current_user) {
    session_unset();
    session_destroy();
    redirect(BASE_URL . 'login.php');
}

// Refresh CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
