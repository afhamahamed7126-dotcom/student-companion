<?php
define('APP_NAME', 'Student Companion');
define('BASE_URL', 'http://localhost/student-companion/');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'student_companion');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session
define('SESSION_TIMEOUT', 1800); // 30 minutes

// File uploads
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads');
define('EXPORT_PATH', dirname(__DIR__) . '/exports');

define('ALLOWED_EXTENSIONS', ['pdf', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'mp4']);
define('ALLOWED_MIMES', [
    'pdf'  => 'application/pdf',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'mp4'  => 'video/mp4',
]);

// SMTP — Gmail App Password
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'hajamohideensafran12007@gmail.com');
define('SMTP_PASS', 'exvo vyly rwuo uglw');
define('SMTP_FROM', 'hajamohideensafran12007@gmail.com');
define('SMTP_FROM_NAME', 'student_companion');
