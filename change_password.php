<?php
$required_role = null; // any role
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';

$error = $success = '';
$page_title = 'Change Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');

    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    try {
        $stmt = get_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([(int)$current_user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pass !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $uid  = (int)$current_user['id'];
            get_db()->prepare('UPDATE users SET password_hash = ?, force_password_reset = 0 WHERE id = ?')
                    ->execute([$hash, $uid]);
            log_audit($uid, 'changed_password', 'users', $uid);
            $success = 'Password changed successfully.';
        }
    } catch (Throwable) {
        $error = 'System error. Please try again.';
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-bold"><i class="bi bi-key me-2"></i>Change Password</div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                        <form method="POST">
                            <?= generate_csrf() ?>
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
