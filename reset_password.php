<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$error   = '';
$success = '';
$valid   = false;
$user_id = 0;

$raw_token = $_GET['token'] ?? ($_POST['token'] ?? '');
if ($raw_token) {
    $token_hash = hash('sha256', $raw_token);
    try {
        $stmt = get_db()->prepare(
            'SELECT pr.user_id FROM password_resets pr WHERE pr.token = ? AND pr.expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$token_hash]);
        $row = $stmt->fetch();
        if ($row) {
            $valid   = true;
            $user_id = (int)$row['user_id'];
        } else {
            $error = 'This reset link is invalid or has expired.';
        }
    } catch (Throwable) {
        $error = 'System error. Please try again.';
    }
} else {
    $error = 'No reset token provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    validate_csrf($_POST['csrf_token'] ?? '');
    $new_pass     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db   = get_db();
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare('UPDATE users SET password_hash = ?, force_password_reset = 0 WHERE id = ?')->execute([$hash, $user_id]);
            $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user_id]);
            redirect(BASE_URL . 'login.php?reset=1');
        } catch (Throwable) {
            $error = 'Could not reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){const t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
<style>body{min-height:100vh;display:flex;align-items:center;justify-content:center;}.rp-card{width:100%;max-width:420px;}</style>
</head>
<body>
<div class="rp-card p-4">
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill text-primary fs-1"></i>
        <h5 class="mt-2 fw-bold">Reset Password</h5>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($valid && !$success): ?>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST">
                <?= generate_csrf() ?>
                <input type="hidden" name="token" value="<?= e($raw_token) ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                    <div class="form-text">At least 8 characters</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Reset Password</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <p class="text-center mt-3"><a href="login.php" class="small"><i class="bi bi-arrow-left"></i> Back to Login</a></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
