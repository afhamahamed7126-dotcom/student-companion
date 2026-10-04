<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    $dest = match($_SESSION['role']) {
        'admin'  => BASE_URL . 'admin/dashboard.php',
        'mentor' => BASE_URL . 'mentor/dashboard.php',
        default  => BASE_URL . 'learner/dashboard.php',
    };
    header('Location: ' . $dest);
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit;
