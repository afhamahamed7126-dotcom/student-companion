<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$dept_id   = 0;
$mentor_id = 0;

try {
    $db = get_db();

    // Get user info
    $stmt = $db->prepare('SELECT department_id FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    $dept_id = (int)($u['department_id'] ?? 0);

    // Get mentor_id for learner
    $stmt2 = $db->prepare('SELECT mentor_id FROM mentor_students WHERE student_id = ? LIMIT 1');
    $stmt2->execute([$user_id]);
    $ms = $stmt2->fetch();
    $mentor_id = (int)($ms['mentor_id'] ?? 0);

    $sql = "SELECT n.id, n.title, n.message, n.created_at
            FROM notifications n
            WHERE (
                n.target_type = 'all'
                OR (n.target_type = 'department' AND n.target_id = ?)
                OR (n.target_type = 'mentor_students' AND n.target_id = ?)
                OR (n.target_type = 'user' AND n.target_id = ?)
            )
            AND n.id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id = ?)
            ORDER BY n.created_at DESC
            LIMIT 20";

    $stmt3 = $db->prepare($sql);
    $stmt3->execute([$dept_id, $mentor_id ?: 0, $user_id, $user_id]);
    $rows = $stmt3->fetchAll();

    echo json_encode(['count' => count($rows), 'notifications' => $rows]);
} catch (Throwable) {
    echo json_encode(['count' => 0, 'notifications' => []]);
}
