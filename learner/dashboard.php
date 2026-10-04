<?php
$required_role = 'learner';
$page_title    = 'Student Dashboard';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$uid = $current_user['id'];

// Overall attendance %
$att_stmt = $db->prepare("SELECT ROUND(AVG(CASE WHEN status='present' THEN 100 ELSE 0 END),1) AS pct FROM attendance WHERE student_id=?");
$att_stmt->execute([$uid]); $overall_att = $att_stmt->fetchColumn() ?? 0;

// Subjects below 75%
$low_att = $db->prepare(
    "SELECT s.name,ROUND(AVG(CASE WHEN a.status='present' THEN 100 ELSE 0 END),1) AS pct
     FROM attendance a JOIN subjects s ON a.subject_id=s.id
     WHERE a.student_id=? GROUP BY a.subject_id HAVING pct < 75"
);
$low_att->execute([$uid]); $low_att=$low_att->fetchAll();

// Pending assignments (not submitted)
$pending_asn = $db->prepare(
    "SELECT COUNT(*) FROM assignments a
     JOIN mentor_subjects ms ON a.subject_id=ms.subject_id
     JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id
     WHERE mst.student_id=? AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id=?)
     AND a.deadline >= NOW()"
);
$pending_asn->execute([$uid,$uid]); $pending_asn=(int)$pending_asn->fetchColumn();

// Assigned mentor
$mentor_stmt=$db->prepare("SELECT u.name FROM mentor_students ms JOIN users u ON ms.mentor_id=u.id WHERE ms.student_id=? LIMIT 1");
$mentor_stmt->execute([$uid]); $mentor_name=$mentor_stmt->fetchColumn() ?: 'Not assigned';

// Recent materials
$recent_mat=$db->prepare(
    "SELECT sm.title,sm.type,sm.uploaded_at,s.name AS subject FROM study_materials sm
     JOIN subjects s ON sm.subject_id=s.id
     JOIN mentor_subjects ms ON sm.subject_id=ms.subject_id
     JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id
     WHERE mst.student_id=? ORDER BY sm.uploaded_at DESC LIMIT 5"
);
$recent_mat->execute([$uid]); $recent_mat=$recent_mat->fetchAll();

// Unread notifications count (LEFT JOIN so students without a mentor still see 'all' notifications)
$notif_stmt=$db->prepare(
    "SELECT COUNT(*) FROM notifications n
     LEFT JOIN mentor_students mst ON mst.student_id=?
     WHERE (n.target_type='all'
        OR (n.target_type='mentor_students' AND mst.mentor_id IS NOT NULL AND n.target_id=mst.mentor_id)
        OR (n.target_type='department' AND n.target_id=?)
        OR (n.target_type='user' AND n.target_id=?))
     AND n.id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id=?)"
);
$notif_stmt->execute([$uid, (int)$current_user['department_id'], $uid, $uid]);
$unread_notifs=(int)$notif_stmt->fetchColumn();

$att_class = $overall_att >= 75 ? 'attendance-good' : ($overall_att >= 50 ? 'attendance-warn' : 'attendance-danger');

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4 animate__animated animate__fadeIn">
    <?php if ($low_att): ?>
    <div class="alert alert-warning alert-dismissible mb-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Attendance Warning:</strong> You are below 75% in <?= count($low_att) ?> subject(s):
        <?= implode(', ', array_map(fn($l)=>"<strong>{$l['name']}</strong> ({$l['pct']}%)", $low_att)) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card primary"><div class="card-body text-center">
                <div class="text-muted small">Overall Attendance</div>
                <div class="fs-2 fw-bold <?= $att_class ?>"><?= $overall_att ?>%</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card warning"><div class="card-body text-center">
                <div class="text-muted small">Pending Assignments</div>
                <div class="fs-2 fw-bold text-warning"><?= $pending_asn ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card success"><div class="card-body text-center">
                <div class="text-muted small">Unread Notifications</div>
                <div class="fs-2 fw-bold text-primary"><?= $unread_notifs ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card"><div class="card-body text-center">
                <div class="text-muted small">Mentor</div>
                <div class="fw-bold"><?= e($mentor_name) ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-person-circle me-2"></i>My Profile</div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Name</span><strong><?= e($current_user['name']) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Email</span><?= e($current_user['email']) ?></li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Year</span>Year <?= $current_user['year'] ?></li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-folder2-open me-2"></i>Recent Materials</div>
                <ul class="list-group list-group-flush">
                <?php if ($recent_mat): foreach ($recent_mat as $m): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><div class="fw-semibold small"><?= e($m['title']) ?></div><div class="text-muted" style="font-size:.8rem"><?= e($m['subject']) ?></div></div>
                        <span class="badge badge-<?= $m['type'] ?>"><?= strtoupper($m['type']) ?></span>
                    </li>
                <?php endforeach; else: ?>
                    <li class="list-group-item text-muted small">No materials yet.</li>
                <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
