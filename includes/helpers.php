<?php
function generate_csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) . '">';
}

function validate_csrf(string $token): void {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
}

function flash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): string {
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function log_audit(int $user_id, string $action, string $table = '', int $target_id = 0): void {
    try {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO audit_logs (user_id, action, target_table, target_id) VALUES (?,?,?,?)');
        $stmt->execute([$user_id, $action, $table ?: null, $target_id ?: null]);
    } catch (Throwable) {}
}

function handle_file_upload(string $field, string $destination_dir, array $allowed_ext = []): array {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error.'];
    }
    $allowed = $allowed_ext ?: ALLOWED_EXTENSIONS;
    $original = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return ['success' => false, 'error' => 'File type not allowed.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$field]['tmp_name']);
    finfo_close($finfo);
    $expected_mime = ALLOWED_MIMES[$ext] ?? '';
    if ($mime !== $expected_mime) {
        return ['success' => false, 'error' => 'File MIME type mismatch.'];
    }
    if ($_FILES[$field]['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds limit (10 MB).'];
    }
    $safe_name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], rtrim($destination_dir, '/') . '/' . $safe_name)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
    return ['success' => true, 'filename' => $safe_name];
}

function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages = (int) ceil($total / $per_page);
    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'offset'       => ($current_page - 1) * $per_page,
    ];
}

function format_date(string $datetime): string {
    return date('d M Y, h:i A', strtotime($datetime));
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function get_role_dashboard(string $role): string {
    return match($role) {
        'admin'  => BASE_URL . 'admin/dashboard.php',
        'mentor' => BASE_URL . 'mentor/dashboard.php',
        default  => BASE_URL . 'learner/dashboard.php',
    };
}

function send_email(string $to, string $subject, string $body): bool {
    if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) return false;
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        return $mail->send();
    } catch (Throwable) {
        return false;
    }
}
