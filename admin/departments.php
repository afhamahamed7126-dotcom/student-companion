<?php
$required_role = 'admin';
$page_title    = 'Departments & Subjects';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db    = get_db();
$uid   = $current_user['id'];
$tab   = $_GET['tab'] ?? 'departments';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';

    // --- Department actions ---
    if ($act === 'add_dept') {
        $name = trim($_POST['dept_name'] ?? '');
        if (!$name) {
            $error = 'Department name is required.'; $tab = 'departments';
        } else {
            try {
                $db->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$name]);
                log_audit($uid, 'added_department', 'departments', (int)$db->lastInsertId());
                flash('success', 'Department added.');
                header('Location: departments.php?tab=departments'); exit;
            } catch (Throwable $e) {
                $error = strpos($e->getMessage(), 'Duplicate') !== false ? 'Department already exists.' : $e->getMessage();
                $tab = 'departments';
            }
        }

    } elseif ($act === 'edit_dept') {
        $id   = (int)($_POST['dept_id'] ?? 0);
        $name = trim($_POST['dept_name'] ?? '');
        if ($id && $name) {
            $db->prepare('UPDATE departments SET name=? WHERE id=?')->execute([$name, $id]);
            log_audit($uid, 'edited_department', 'departments', $id);
            flash('success', 'Department updated.');
            header('Location: departments.php?tab=departments'); exit;
        } else { $error = 'Invalid data.'; $tab = 'departments'; }

    } elseif ($act === 'delete_dept') {
        $id = (int)($_POST['dept_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM departments WHERE id=?')->execute([$id]);
            log_audit($uid, 'deleted_department', 'departments', $id);
            flash('success', 'Department deleted.');
            header('Location: departments.php?tab=departments'); exit;
        }

    // --- Subject actions ---
    } elseif ($act === 'add_subject') {
        $dept_id  = (int)($_POST['department_id'] ?? 0);
        $name     = trim($_POST['subject_name'] ?? '');
        $year     = (int)($_POST['year'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 0);
        if (!$dept_id || !$name || $year < 1 || $year > 4 || $semester < 1 || $semester > 2) {
            $error = 'All subject fields are required (Year 1-4, Semester 1-2).'; $tab = 'subjects';
        } else {
            $db->prepare('INSERT INTO subjects (name, department_id, year, semester) VALUES (?,?,?,?)')->execute([$name, $dept_id, $year, $semester]);
            log_audit($uid, 'added_subject', 'subjects', (int)$db->lastInsertId());
            flash('success', 'Subject added.');
            header('Location: departments.php?tab=subjects'); exit;
        }

    } elseif ($act === 'edit_subject') {
        $id       = (int)($_POST['subject_id'] ?? 0);
        $dept_id  = (int)($_POST['department_id'] ?? 0);
        $name     = trim($_POST['subject_name'] ?? '');
        $year     = (int)($_POST['year'] ?? 0);
        $semester = (int)($_POST['semester'] ?? 0);
        if ($id && $dept_id && $name && $year >= 1 && $year <= 4 && $semester >= 1 && $semester <= 2) {
            $db->prepare('UPDATE subjects SET name=?,department_id=?,year=?,semester=? WHERE id=?')->execute([$name, $dept_id, $year, $semester, $id]);
            log_audit($uid, 'edited_subject', 'subjects', $id);
            flash('success', 'Subject updated.');
            header('Location: departments.php?tab=subjects'); exit;
        } else { $error = 'Invalid data.'; $tab = 'subjects'; }

    } elseif ($act === 'delete_subject') {
        $id = (int)($_POST['subject_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM subjects WHERE id=?')->execute([$id]);
            log_audit($uid, 'deleted_subject', 'subjects', $id);
            flash('success', 'Subject deleted.');
            header('Location: departments.php?tab=subjects'); exit;
        }
    }
}

$departments = $db->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$subjects    = $db->query(
    'SELECT s.*, d.name AS dept_name FROM subjects s JOIN departments d ON s.department_id=d.id ORDER BY d.name, s.year, s.semester, s.name'
)->fetchAll();

$edit_dept    = null;
$edit_subject = null;
if (isset($_GET['edit_dept'])) {
    foreach ($departments as $d) { if ($d['id'] == $_GET['edit_dept']) { $edit_dept = $d; break; } }
}
if (isset($_GET['edit_subject'])) {
    foreach ($subjects as $s) { if ($s['id'] == $_GET['edit_subject']) { $edit_subject = $s; break; } }
}

$flash_msg = get_flash('success');
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/sidebar.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-content p-4">

<?php if ($flash_msg): ?>
<div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="d-flex align-items-center mb-3">
    <i class="bi bi-building fs-4 me-2 text-primary"></i>
    <h5 class="mb-0 fw-bold">Departments &amp; Subjects</h5>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'departments' ? 'active' : '' ?>" href="?tab=departments">
            <i class="bi bi-building me-1"></i> Departments
            <span class="badge bg-primary ms-1"><?= count($departments) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'subjects' ? 'active' : '' ?>" href="?tab=subjects">
            <i class="bi bi-journal-bookmark me-1"></i> Subjects
            <span class="badge bg-primary ms-1"><?= count($subjects) ?></span>
        </a>
    </li>
</ul>

<?php if ($tab === 'departments'): ?>
<div class="row g-4">
    <!-- Add / Edit Department -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-bold">
                <i class="bi bi-<?= $edit_dept ? 'pencil' : 'plus-circle' ?> me-2"></i>
                <?= $edit_dept ? 'Edit Department' : 'Add Department' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= generate_csrf() ?>
                    <input type="hidden" name="action" value="<?= $edit_dept ? 'edit_dept' : 'add_dept' ?>">
                    <?php if ($edit_dept): ?>
                    <input type="hidden" name="dept_id" value="<?= $edit_dept['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department Name *</label>
                        <input type="text" name="dept_name" class="form-control"
                               value="<?= e($edit_dept['name'] ?? '') ?>"
                               placeholder="e.g. Computer Science" required autofocus>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $edit_dept ? 'Save Changes' : 'Add Department' ?>
                        </button>
                        <?php if ($edit_dept): ?>
                        <a href="departments.php?tab=departments" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Departments List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-list-ul me-2"></i>All Departments</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>Name</th><th>Subjects</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($departments): foreach ($departments as $i => $d):
                        $sub_count = count(array_filter($subjects, fn($s) => $s['department_id'] == $d['id']));
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($d['name']) ?></strong></td>
                        <td><span class="badge bg-info"><?= $sub_count ?> subjects</span></td>
                        <td>
                            <a href="?tab=departments&edit_dept=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirmAction('Delete this department? All subjects and related data will be removed!')">
                                <?= generate_csrf() ?>
                                <input type="hidden" name="action" value="delete_dept">
                                <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No departments yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: // subjects tab ?>
<div class="row g-4">
    <!-- Add / Edit Subject -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header fw-bold">
                <i class="bi bi-<?= $edit_subject ? 'pencil' : 'plus-circle' ?> me-2"></i>
                <?= $edit_subject ? 'Edit Subject' : 'Add Subject' ?>
            </div>
            <div class="card-body">
                <?php if (!$departments): ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Add a department first before adding subjects.
                </div>
                <?php else: ?>
                <form method="POST">
                    <?= generate_csrf() ?>
                    <input type="hidden" name="action" value="<?= $edit_subject ? 'edit_subject' : 'add_subject' ?>">
                    <?php if ($edit_subject): ?>
                    <input type="hidden" name="subject_id" value="<?= $edit_subject['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Name *</label>
                        <input type="text" name="subject_name" class="form-control"
                               value="<?= e($edit_subject['name'] ?? '') ?>"
                               placeholder="e.g. Data Structures" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Department *</label>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select department</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= ($edit_subject['department_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
                                <?= e($d['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Year *</label>
                            <select name="year" class="form-select" required>
                                <option value="">Year</option>
                                <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?= $y ?>" <?= ($edit_subject['year'] ?? 0) == $y ? 'selected' : '' ?>>
                                    Year <?= $y ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Semester *</label>
                            <select name="semester" class="form-select" required>
                                <option value="">Sem</option>
                                <option value="1" <?= ($edit_subject['semester'] ?? 0) == 1 ? 'selected' : '' ?>>Semester 1</option>
                                <option value="2" <?= ($edit_subject['semester'] ?? 0) == 2 ? 'selected' : '' ?>>Semester 2</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?= $edit_subject ? 'Save Changes' : 'Add Subject' ?>
                        </button>
                        <?php if ($edit_subject): ?>
                        <a href="departments.php?tab=subjects" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Subjects List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-journal-bookmark me-2"></i>All Subjects</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>Subject</th><th>Department</th><th>Year</th><th>Semester</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($subjects): foreach ($subjects as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= e($s['dept_name']) ?></span></td>
                        <td>Year <?= $s['year'] ?></td>
                        <td>Sem <?= $s['semester'] ?></td>
                        <td>
                            <a href="?tab=subjects&edit_subject=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirmAction('Delete this subject? Attendance and assignments linked to it will also be removed.')">
                                <?= generate_csrf() ?>
                                <input type="hidden" name="action" value="delete_subject">
                                <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No subjects yet. Add one using the form.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
