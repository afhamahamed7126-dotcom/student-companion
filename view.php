<?php
$required_role = null; // any authenticated role
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$file_param = $_GET['file'] ?? '';
$type       = $_GET['type'] ?? '';

// Reject path traversal
if (!$file_param || strpos($file_param, '..') !== false || strpos($file_param, '/') !== false || strpos($file_param, '\\') !== false) {
    http_response_code(400);
    die('Invalid file request.');
}

$allowed_types = ['notes', 'assignments', 'profiles'];
if (!in_array($type, $allowed_types, true)) {
    http_response_code(400);
    die('Invalid file type.');
}

$db  = get_db();
$uid = $current_user['id'];
$role= $current_user['role'];
$allowed = false;

if ($type === 'notes') {
    if ($role === 'admin') {
        $allowed = true;
    } elseif ($role === 'mentor') {
        $stmt = $db->prepare('SELECT id FROM study_materials WHERE file_path=? AND mentor_id=?');
        $stmt->execute([$file_param, $uid]); $allowed = (bool)$stmt->fetch();
    } else {
        $stmt = $db->prepare(
            "SELECT sm.id FROM study_materials sm
             JOIN mentor_subjects ms ON sm.subject_id=ms.subject_id
             JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id
             WHERE mst.student_id=? AND sm.file_path=? LIMIT 1"
        );
        $stmt->execute([$uid, $file_param]); $allowed = (bool)$stmt->fetch();
    }
} elseif ($type === 'assignments') {
    if ($role === 'admin') {
        $allowed = true;
    } elseif ($role === 'mentor') {
        $stmt = $db->prepare("SELECT id FROM assignments WHERE file_path=? AND mentor_id=?");
        $stmt->execute([$file_param, $uid]); $allowed = (bool)$stmt->fetch();
        if (!$allowed) {
            $stmt2 = $db->prepare(
                "SELECT asub.id FROM assignment_submissions asub JOIN assignments a ON asub.assignment_id=a.id WHERE asub.file_path=? AND a.mentor_id=?"
            );
            $stmt2->execute([$file_param, $uid]); $allowed = (bool)$stmt2->fetch();
        }
    } else {
        $stmt = $db->prepare("SELECT id FROM assignment_submissions WHERE file_path=? AND student_id=?");
        $stmt->execute([$file_param, $uid]); $allowed = (bool)$stmt->fetch();
        if (!$allowed) {
            $stmt2 = $db->prepare(
                "SELECT a.id FROM assignments a JOIN mentor_subjects ms ON a.subject_id=ms.subject_id
                 JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id WHERE a.file_path=? AND mst.student_id=? LIMIT 1"
            );
            $stmt2->execute([$file_param, $uid]); $allowed = (bool)$stmt2->fetch();
        }
    }
} elseif ($type === 'profiles') {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    die('Access denied.');
}

$full_path = UPLOAD_PATH . '/' . $type . '/' . $file_param;
if (!file_exists($full_path)) {
    http_response_code(404);
    die('File not found.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $full_path);
finfo_close($finfo);

// Only allow inline view for viewable types; others fall back to download
$viewable_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4'];
$disposition = in_array($mime, $viewable_mimes) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($file_param) . '"');
header('Content-Length: ' . filesize($full_path));
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;
