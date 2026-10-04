<?php
$required_role = 'admin';
$page_title    = 'Student Management';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db     = get_db();
$action = $_GET['action'] ?? 'list';
$error  = $success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $name      = trim($_POST['name'] ?? '');
        $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $dept_id   = (int)($_POST['department_id'] ?? 0);
        $year      = (int)($_POST['year'] ?? 0);
        $mentor_id = (int)($_POST['mentor_id'] ?? 0);
        $auto_pwd  = bin2hex(random_bytes(6)); // 12-char hex

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$dept_id || $year < 1 || $year > 4) {
            $error = 'Please fill all required fields correctly.';
        } else {
            try {
                $db->prepare('INSERT INTO users (name,email,password_hash,role,department_id,year,force_password_reset) VALUES (?,?,?,?,?,?,1)')
                   ->execute([$name, $email, password_hash($auto_pwd, PASSWORD_DEFAULT), 'learner', $dept_id, $year]);
                $student_id = (int)$db->lastInsertId();
                if ($mentor_id) {
                    $db->prepare('INSERT IGNORE INTO mentor_students (mentor_id,student_id) VALUES (?,?)')->execute([$mentor_id, $student_id]);
                }
                log_audit($current_user['id'], 'added_student', 'users', $student_id);
                
                $mail_subject = "Welcome to " . APP_NAME;
                $mail_body = "Hello {$name},\n\nYour learner account has been created.\n\nYour temporary password is: {$auto_pwd}\n\nPlease log in and change your password immediately.\n\nLogin here: " . BASE_URL . "\n\nRegards,\nAdmin Team";
                $mail_sent = send_email($email, $mail_subject, $mail_body) ? ' (Email sent)' : ' (Email failed)';
                
                flash('success', "Student added. Auto-password: <strong>{$auto_pwd}</strong>" . $mail_sent);
                header('Location: students.php'); exit;
            } catch (Throwable $e) {
                $error = strpos($e->getMessage(), 'Duplicate') !== false ? 'Email already exists.' : 'Error: ' . $e->getMessage();
            }
        }

    } elseif ($act === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $dept_id = (int)($_POST['department_id'] ?? 0);
        $year    = (int)($_POST['year'] ?? 0);
        $mentor_id = (int)($_POST['mentor_id'] ?? 0);
        if ($id && $name && $dept_id && $year >= 1 && $year <= 4) {
            $db->prepare('UPDATE users SET name=?,department_id=?,year=? WHERE id=? AND role="learner"')->execute([$name,$dept_id,$year,$id]);
            $db->prepare('DELETE FROM mentor_students WHERE student_id=?')->execute([$id]);
            if ($mentor_id) $db->prepare('INSERT IGNORE INTO mentor_students (mentor_id,student_id) VALUES (?,?)')->execute([$mentor_id,$id]);
            log_audit($current_user['id'], 'edited_student', 'users', $id);
            flash('success', 'Student updated successfully.');
            header('Location: students.php'); exit;
        } else { $error = 'Invalid data.'; }

    } elseif ($act === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare('SELECT status FROM users WHERE id=? AND role="learner"');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $new = ($row['status'] === 'active') ? 'inactive' : 'active';
            $db->prepare('UPDATE users SET status=? WHERE id=?')->execute([$new, $id]);
            log_audit($current_user['id'], "set_student_{$new}", 'users', $id);
            flash('success', "Student {$new}.");
            header('Location: students.php'); exit;
        }
    }
}

$departments = $db->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();
$mentors     = $db->query('SELECT id,name,department_id FROM users WHERE role="mentor" AND status="active" ORDER BY name')->fetchAll();

// Fetch for edit
$edit_student = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT u.*, ms.mentor_id FROM users u LEFT JOIN mentor_students ms ON ms.student_id=u.id WHERE u.id=? AND u.role="learner" LIMIT 1');
    $stmt->execute([(int)$_GET['id']]);
    $edit_student = $stmt->fetch();
}

// Paginated list
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$where = $search ? "AND (u.name LIKE ? OR u.email LIKE ?)" : '';
$params = $search ? ["%{$search}%", "%{$search}%"] : [];

$count_stmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE u.role='learner' {$where}");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$pag   = paginate($total, $per_page, $page);

$list_stmt = $db->prepare(
    "SELECT u.id,u.name,u.email,u.year,u.status,d.name AS dept,
            um.name AS mentor_name
     FROM users u
     LEFT JOIN departments d ON u.department_id=d.id
     LEFT JOIN mentor_students ms ON ms.student_id=u.id
     LEFT JOIN users um ON um.id=ms.mentor_id
     WHERE u.role='learner' {$where}
     ORDER BY u.name LIMIT {$per_page} OFFSET {$pag['offset']}"
);
$list_stmt->execute($params);
$students = $list_stmt->fetchAll();

$flash_msg = get_flash('success');
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/sidebar.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-dismissible alert-auto-dismiss"><?= $flash_msg ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card mb-4">
    <div class="card-header fw-bold"><i class="bi bi-person-plus me-2"></i><?= $action === 'add' ? 'Add Student' : 'Edit Student' ?></div>
    <div class="card-body">
        <form method="POST">
            <?= generate_csrf() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $edit_student['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($edit_student['name'] ?? '') ?>" required>
                </div>
                <?php if ($action === 'add'): ?>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Department *</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($edit_student['department_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year *</label>
                    <select name="year" class="form-select" required>
                        <?php for ($y=1;$y<=4;$y++): ?>
                            <option value="<?=$y?>" <?= ($edit_student['year'] ?? 0) == $y ? 'selected' : '' ?>><?=$y?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assign Mentor</label>
                    <select name="mentor_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($mentors as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($edit_student['mentor_id'] ?? 0) == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><?= $action === 'add' ? 'Add Student' : 'Save Changes' ?></button>
                <a href="students.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Students (<?= $total ?>)</h5>
    <a href="students.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Add Student</a>
</div>

<form class="mb-3 d-flex gap-2" method="GET">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or email..." value="<?= e($search) ?>">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
    <?php if ($search): ?><a href="students.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>#</th><th>Name</th><th>Email</th><th>Dept</th><th>Year</th><th>Mentor</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($students): foreach ($students as $i => $s): ?>
            <tr>
                <td><?= $pag['offset'] + $i + 1 ?></td>
                <td><?= e($s['name']) ?></td>
                <td><?= e($s['email']) ?></td>
                <td><?= e($s['dept'] ?? '—') ?></td>
                <td>Year <?= $s['year'] ?></td>
                <td><?= e($s['mentor_name'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $s['status']==='active'?'success':'secondary' ?>"><?= $s['status'] ?></span></td>
                <td>
                    <a href="students.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm">Edit</a>
                    <form method="POST" class="d-inline" onsubmit="return confirmAction('Toggle student status?')">
                        <?= generate_csrf() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button class="btn btn-sm btn-outline-<?= $s['status']==='active'?'danger':'success' ?>"><?= $s['status']==='active'?'Deactivate':'Activate' ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No students found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
<?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
    <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?=$p?><?= $search?"&q=".urlencode($search):'' ?>"><?=$p?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
