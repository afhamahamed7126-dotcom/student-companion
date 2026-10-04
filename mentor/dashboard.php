<?php
$required_role = 'mentor';
$page_title    = 'Mentor Dashboard';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$mid = $current_user['id'];

$student_count = $db->prepare('SELECT COUNT(*) FROM mentor_students WHERE mentor_id=?');
$student_count->execute([$mid]); $student_count = (int)$student_count->fetchColumn();

$subjects = $db->prepare('SELECT s.name FROM mentor_subjects ms JOIN subjects s ON ms.subject_id=s.id WHERE ms.mentor_id=?');
$subjects->execute([$mid]); $subjects = $subjects->fetchAll();

$pending_eval = $db->prepare(
    "SELECT COUNT(*) FROM assignment_submissions asub
     JOIN assignments a ON asub.assignment_id=a.id
     WHERE a.mentor_id=? AND asub.status IN ('submitted','late')"
);
$pending_eval->execute([$mid]); $pending_eval = (int)$pending_eval->fetchColumn();

$pending_leaves = $db->prepare(
    "SELECT COUNT(*) FROM leave_requests lr
     JOIN mentor_students ms ON lr.requested_by=ms.student_id
     WHERE ms.mentor_id=? AND lr.status='pending' AND lr.requested_to_role='mentor'"
);
$pending_leaves->execute([$mid]); $pending_leaves = (int)$pending_leaves->fetchColumn();

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4 animate__animated animate__fadeIn">
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card primary"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted small">My Students</div><div class="fs-3 fw-bold"><?= $student_count ?></div></div>
                <i class="bi bi-people-fill stat-icon text-primary"></i>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card success"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted small">My Subjects</div><div class="fs-3 fw-bold"><?= count($subjects) ?></div></div>
                <i class="bi bi-book-fill stat-icon text-success"></i>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card warning"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted small">Pending Evaluations</div><div class="fs-3 fw-bold"><?= $pending_eval ?></div></div>
                <i class="bi bi-file-earmark-check stat-icon text-warning"></i>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card danger"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted small">Student Leaves</div><div class="fs-3 fw-bold"><?= $pending_leaves ?></div></div>
                <i class="bi bi-calendar-x stat-icon text-danger"></i>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-book me-2"></i>Assigned Subjects</div>
                <ul class="list-group list-group-flush">
                <?php foreach ($subjects as $s): ?>
                    <li class="list-group-item"><?= e($s['name']) ?></li>
                <?php endforeach; ?>
                <?php if (!$subjects): ?><li class="list-group-item text-muted">No subjects assigned.</li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="attendance.php?action=mark" class="btn btn-outline-primary"><i class="bi bi-calendar3 me-2"></i>Mark Attendance</a>
                    <a href="assignments.php?tab=create" class="btn btn-outline-success"><i class="bi bi-plus-circle me-2"></i>Create Assignment</a>
                    <a href="assignments.php?tab=evaluate" class="btn btn-outline-warning"><i class="bi bi-check2-circle me-2"></i>Evaluate Submissions</a>
                    <a href="materials.php" class="btn btn-outline-info"><i class="bi bi-upload me-2"></i>Upload Material</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
