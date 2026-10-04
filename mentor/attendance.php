<?php
$required_role = 'mentor';
$page_title    = 'Attendance';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$mid = $current_user['id'];
$action = $_GET['action'] ?? 'view';
$error  = $success = '';

// Get subjects for this mentor
$subj_stmt = $db->prepare(
    'SELECT ms.subject_id,s.name,ms.department_id,ms.year FROM mentor_subjects ms JOIN subjects s ON ms.subject_id=s.id WHERE ms.mentor_id=?'
);
$subj_stmt->execute([$mid]);
$my_subjects = $subj_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $date       = $_POST['date'] ?? date('Y-m-d');
    $statuses   = $_POST['status'] ?? [];

    if (!$subject_id || !$date) {
        $error = 'Subject and date are required.';
    } else {
        $ins = $db->prepare(
            'INSERT INTO attendance (student_id,subject_id,date,status,marked_by) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status=VALUES(status)'
        );
        foreach ($statuses as $student_id => $status) {
            if (in_array($status, ['present','absent'], true)) {
                $ins->execute([(int)$student_id, $subject_id, $date, $status, $mid]);
            }
        }
        log_audit($mid, 'marked_attendance', 'attendance', $subject_id);
        flash('success', 'Attendance saved for ' . date('d M Y', strtotime($date)));
        header('Location: attendance.php?action=mark'); exit;
    }
}

// Load students for selected subject
$selected_subject_id = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));
$students_for_att = [];
if ($selected_subject_id || $action === 'mark') {
    $sid_to_use = $selected_subject_id ?: ($my_subjects[0]['subject_id'] ?? 0);
    if ($sid_to_use) {
        // Find department+year for this subject
        $sub_info = null;
        foreach ($my_subjects as $ms) { if ($ms['subject_id'] == $sid_to_use) { $sub_info = $ms; break; } }
        if ($sub_info) {
            $stu_stmt = $db->prepare(
                'SELECT u.id,u.name FROM users u JOIN mentor_students ms ON u.id=ms.student_id
                 WHERE ms.mentor_id=? AND u.department_id=? AND u.year=? AND u.status="active" ORDER BY u.name'
            );
            $stu_stmt->execute([$mid, $sub_info['department_id'], $sub_info['year']]);
            $students_for_att = $stu_stmt->fetchAll();
        }
    }
}

// View mode: subject filter + date range
$view_subject = (int)($_GET['vs'] ?? 0);
$view_from    = $_GET['vfrom'] ?? date('Y-m-01');
$view_to      = $_GET['vto']   ?? date('Y-m-d');

$att_records = [];
if ($action === 'view') {
    $vsql = "SELECT u.name AS student,s.name AS subject,a.date,a.status
             FROM attendance a JOIN users u ON a.student_id=u.id JOIN subjects s ON a.subject_id=s.id
             WHERE a.marked_by=? AND a.date BETWEEN ? AND ?";
    $vparams = [$mid, $view_from, $view_to];
    if ($view_subject) { $vsql .= ' AND a.subject_id=?'; $vparams[] = $view_subject; }
    $vsql .= ' ORDER BY a.date DESC,u.name LIMIT 100';
    $vs = $db->prepare($vsql); $vs->execute($vparams);
    $att_records = $vs->fetchAll();
}

$flash_msg = get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $action==='mark'?'active':'' ?>" href="?action=mark">Mark Attendance</a></li>
    <li class="nav-item"><a class="nav-link <?= $action==='view'?'active':'' ?>" href="?action=view">View Records</a></li>
</ul>

<?php if ($action === 'mark'): ?>
<div class="card mb-3" style="max-width:500px">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 mb-3">
            <input type="hidden" name="action" value="mark">
            <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Select Subject</option>
                <?php foreach ($my_subjects as $ms): ?>
                <option value="<?= $ms['subject_id'] ?>" <?= $selected_subject_id==$ms['subject_id']?'selected':'' ?>><?= e($ms['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($students_for_att): ?>
<form method="POST" action="?action=mark">
    <?= generate_csrf() ?>
    <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?: ($my_subjects[0]['subject_id']??0) ?>">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center fw-bold">
            <span><i class="bi bi-calendar3 me-2"></i>Mark Attendance</span>
            <input type="date" name="date" class="form-control form-control-sm w-auto" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>#</th><th>Student Name</th><th>Present</th><th>Absent</th></tr></thead>
                <tbody>
                <?php foreach ($students_for_att as $i => $stu): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= e($stu['name']) ?></td>
                    <td><input type="radio" name="status[<?= $stu['id'] ?>]" value="present" checked class="form-check-input"></td>
                    <td><input type="radio" name="status[<?= $stu['id'] ?>]" value="absent" class="form-check-input"></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Attendance</button>
        </div>
    </div>
</form>
<?php elseif ($selected_subject_id || count($my_subjects)): ?>
<div class="alert alert-info">No students assigned for the selected subject/year.</div>
<?php else: ?>
<div class="alert alert-warning">No subjects assigned to you yet. Ask HOD to assign subjects.</div>
<?php endif; ?>

<?php else: // view ?>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <input type="hidden" name="action" value="view">
            <div class="col-md-3">
                <select name="vs" class="form-select form-select-sm">
                    <option value="">All Subjects</option>
                    <?php foreach ($my_subjects as $ms): ?>
                    <option value="<?= $ms['subject_id'] ?>" <?= $view_subject==$ms['subject_id']?'selected':'' ?>><?= e($ms['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input type="date" name="vfrom" class="form-control form-control-sm" value="<?= e($view_from) ?>"></div>
            <div class="col-md-3"><input type="date" name="vto" class="form-control form-control-sm" value="<?= e($view_to) ?>"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Student</th><th>Subject</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php if ($att_records): foreach ($att_records as $r): ?>
            <tr>
                <td><?= e($r['student']) ?></td>
                <td><?= e($r['subject']) ?></td>
                <td><?= e($r['date']) ?></td>
                <td><span class="badge bg-<?= $r['status']==='present'?'success':'danger' ?>"><?= $r['status'] ?></span></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No records.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
