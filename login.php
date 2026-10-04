<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in
if (!empty($_SESSION['user_id'])) {
    redirect(get_role_dashboard($_SESSION['role']));
}

$error = '';
$success = '';

if ($_GET['timeout'] ?? false) $error = 'Your session expired due to inactivity. Please log in again.';
if ($_GET['logged_out'] ?? false) $success = 'You have been logged out successfully.';
if ($_GET['reset'] ?? false) $success = 'Password reset successfully. You can now log in.';

// CSRF for GET
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');

    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $stmt = get_db()->prepare(
                'SELECT id, name, email, password_hash, role, status, force_password_reset FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Contact the HOD.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['name']          = $user['name'];
                    $_SESSION['last_activity'] = time();

                    if ($user['force_password_reset']) {
                        redirect(BASE_URL . 'change_password.php');
                    }

                    $dest = $_SESSION['redirect_after_login'] ?? get_role_dashboard($user['role']);
                    unset($_SESSION['redirect_after_login']);
                    redirect($dest);
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Throwable $e) {
            $error = 'A system error occurred. Please try again.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script>
(function(){const t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();
</script>
<style>
body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: url('assets/images/login_bg.png') center/cover no-repeat fixed;
}
.login-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: -1;
}
.login-card {
    width: 100%;
    max-width: 450px;
    z-index: 1;
}
/* Ensure dark mode toggle looks good on the overlay */
.theme-toggle-btn {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
    color: white;
    backdrop-filter: blur(4px);
}
.theme-toggle-btn:hover {
    background: rgba(255,255,255,0.2);
    color: white;
}
</style>
</head>
<body>
<div class="login-overlay"></div>
<div class="login-card p-4 animate__animated animate__fadeInUp">
    <div class="card glass-card shadow-lg border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-gradient text-white rounded-circle mb-3 shadow-sm" style="width: 64px; height: 64px;">
                    <i class="bi bi-mortarboard-fill fs-2"></i>
                </div>
                <h3 class="fw-bold mb-1" style="font-family: var(--bs-heading-font-family);"><?= APP_NAME ?></h3>
                <p class="text-muted small">Sign in to continue</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= generate_csrf() ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing: 0.5px;">Email address</label>
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control fs-6" placeholder="Enter your email" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small text-muted text-uppercase" style="letter-spacing: 0.5px;">Password</label>
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="pwd" class="form-control fs-6" placeholder="Enter your password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()" style="background-color: var(--sidebar-bg); border-color: var(--bs-border-color);"><i class="bi bi-eye" id="eye-icon"></i></button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="small text-primary text-decoration-none fw-medium">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fs-6 shadow-sm">Sign In</button>
            </form>
        </div>
    </div>
    <div class="text-center mt-4">
        <button class="btn btn-sm theme-toggle-btn rounded-pill px-3 shadow-sm" onclick="toggleTheme()"><i class="bi bi-moon-stars me-2" id="theme-icon"></i>Toggle Theme</button>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(){const p=document.getElementById('pwd'),i=document.getElementById('eye-icon');p.type=p.type==='password'?'text':'password';i.className=p.type==='password'?'bi bi-eye':'bi bi-eye-slash';}
function toggleTheme(){const h=document.documentElement,c=h.getAttribute('data-bs-theme'),n=c==='dark'?'light':'dark';h.setAttribute('data-bs-theme',n);localStorage.setItem('theme',n);
const icon = document.getElementById('theme-icon');
icon.className = n === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';}
document.addEventListener('DOMContentLoaded', () => {
    const t = localStorage.getItem('theme') || 'light';
    document.getElementById('theme-icon').className = t === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';
});
</script>
</body>
</html>
