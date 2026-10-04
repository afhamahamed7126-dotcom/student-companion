<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db   = get_db();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND status = "active" LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Delete old tokens
                $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);

                $token      = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires    = date('Y-m-d H:i:s', time() + 3600);

                $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)')->execute([$user['id'], $token_hash, $expires]);

                $reset_url = BASE_URL . 'reset_password.php?token=' . urlencode($token);

                // Send email via PHPMailer
                $mail_sent = false;
                $mail_error = '';
                if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                    require_once __DIR__ . '/vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_USER;
                        $mail->Password   = SMTP_PASS;
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = SMTP_PORT;
                        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                        $mail->addAddress($email);
                        $mail->Subject = 'Password Reset — ' . APP_NAME;
                        $mail->Body    = "Click the link to reset your password (valid 1 hour):\n\n{$reset_url}\n\nIf you did not request this, ignore this email.";
                        $mail->send();
                        $mail_sent = true;
                    } catch (Throwable $e) {
                        $mail_error = $e->getMessage();
                    }
                }
                if (!$mail_sent) {
                    $error = 'Could not send reset email. Please contact the administrator.' . ($mail_error ? ' (' . e($mail_error) . ')' : '');
                }
            }
            if (!$error) {
                $message = 'If that email is registered, a reset link has been sent. Check your inbox.';
            }
        } catch (Throwable) {
            $error = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){const t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-bs-theme',t);})();</script>
<style>body{min-height:100vh;display:flex;align-items:center;justify-content:center;}.fp-card{width:100%;max-width:420px;}</style>
</head>
<body>
<div class="fp-card p-4">
    <div class="text-center mb-4">
        <i class="bi bi-key-fill text-warning fs-1"></i>
        <h5 class="mt-2 fw-bold">Forgot Password</h5>
        <p class="text-muted small">Enter your email to receive a reset link</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if (!$message): ?>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST">
                <?= generate_csrf() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email address</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-warning w-100">Send Reset Link</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <p class="text-center mt-3"><a href="login.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Login</a></p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
