<?php
$required_role = 'admin';
$page_title    = 'Mentor Management';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db     = get_db();
$action = $_GET['action'] ?? 'list';
$error  = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $name    = trim($_POST['name'] ?? '');
        $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $dept_id = (int)($_POST['department_id'] ?? 0);
        $subjects= array_map('intval', $_POST['subjects'] ?? []);
        $auto_pwd= bin2hex(random_bytes(6));

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$dept_id) {
            $error = 'Fill all required fields.';
        } else {
            try {
                $db->prepare('INSERT INTO users (name,email,password_hash,role,department_id,force_password_reset) VALUES (?,?,?,?,?,1)')
                   ->execute([$name,$email,password_hash($auto_pwd,PASSWORD_DEFAULT),'mentor',$dept_id]);
                $mid = (int)$db->lastInsertId();
                foreach ($subjects as $sid) {
                    $sub = $db->prepare('SELECT year FROM subjects WHERE id=?'); $sub->execute([$sid]); $sr = $sub->fetch();
                    if ($sr) $db->prepare('INSERT IGNORE INTO mentor_subjects (mentor_id,subject_id,department_id,year) VALUES (?,?,?,?)')->execute([$mid,$sid,$dept_id,$sr['year']]);
                }
                log_audit($current_user['id'],'added_mentor','users',$mid);
                
                $mail_subject = "Welcome to " . APP_NAME;
                $mail_body = "Hello {$name},\n\nYour mentor account has been created.\n\nYour temporary password is: {$auto_pwd}\n\nPlease log in and change your password immediately.\n\nLogin here: " . BASE_URL . "\n\nRegards,\nAdmin Team";
                $mail_sent = send_email($email, $mail_subject, $mail_body) ? ' (Email sent)' : ' (Email failed)';

                flash('success',"Mentor added. Auto-password: <strong>{$auto_pwd}</strong>" . $mail_sent);
                header('Location: mentors.php'); exit;
            } catch (Throwable $e) {
                $error = strpos($e->getMessage(),'Duplicate')!==false?'Email exists.':'Error: '.$e->getMessage();
            }
        }

    } elseif ($act === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $dept_id = (int)($_POST['department_id'] ?? 0);
        $subjects= array_map('intval', $_POST['subjects'] ?? []);
        if ($id && $name && $dept_id) {
            $db->prepare('UPDATE users SET name=?,department_id=? WHERE id=? AND role="mentor"')->execute([$name,$dept_id,$id]);
            $db->prepare('DELETE FROM mentor_subjects WHERE mentor_id=?')->execute([$id]);
            foreach ($subjects as $sid) {
                $sub=$db->prepare('SELECT year FROM subjects WHERE id=?');$sub->execute([$sid]);$sr=$sub->fetch();
                if ($sr) $db->prepare('INSERT IGNORE INTO mentor_subjects (mentor_id,subject_id,department_id,year) VALUES (?,?,?,?)')->execute([$id,$sid,$dept_id,$sr['year']]);
            }
            log_audit($current_user['id'],'edited_mentor','users',$id);
            flash('success','Mentor updated.');
            header('Location: mentors.php'); exit;
        } else { $error='Invalid data.'; }

    } elseif ($act === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt=$db->prepare('SELECT status FROM users WHERE id=? AND role="mentor"');$stmt->execute([$id]);$row=$stmt->fetch();
            $new=($row['status']==='active')?'inactive':'active';
            $db->prepare('UPDATE users SET status=? WHERE id=?')->execute([$new,$id]);
            log_audit($current_user['id'],"set_mentor_{$new}",'users',$id);
            flash('success',"Mentor {$new}.");
            header('Location: mentors.php'); exit;
        }
    }
}

$departments = $db->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();

$edit_mentor = null;
$edit_subjects = [];
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt=$db->prepare('SELECT * FROM users WHERE id=? AND role="mentor" LIMIT 1');$stmt->execute([(int)$_GET['id']]);
    $edit_mentor=$stmt->fetch();
    $stmt2=$db->prepare('SELECT subject_id FROM mentor_subjects WHERE mentor_id=?');$stmt2->execute([$edit_mentor['id']]);
    $edit_subjects=array_column($stmt2->fetchAll(),'subject_id');
}

$subjects_by_dept = [];
foreach ($db->query('SELECT id,name,department_id,year,semester FROM subjects ORDER BY department_id,year,name')->fetchAll() as $s) {
    $subjects_by_dept[$s['department_id']][] = $s;
}

$per_page=20; $page=max(1,(int)($_GET['page']??1));
$total=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='mentor'")->fetchColumn();
$pag=paginate($total,$per_page,$page);
$mentors=$db->query("SELECT u.id,u.name,u.email,u.status,d.name AS dept,
    (SELECT COUNT(*) FROM mentor_students ms WHERE ms.mentor_id=u.id) AS student_count
    FROM users u LEFT JOIN departments d ON u.department_id=d.id WHERE u.role='mentor' ORDER BY u.name LIMIT {$per_page} OFFSET {$pag['offset']}")->fetchAll();

$flash_msg=get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-dismissible alert-auto-dismiss"><?= $flash_msg ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($action==='add'||$action==='edit'): ?>
<div class="card mb-4">
    <div class="card-header fw-bold"><i class="bi bi-person-badge me-2"></i><?= $action==='add'?'Add Mentor':'Edit Mentor' ?></div>
    <div class="card-body">
        <form method="POST">
            <?= generate_csrf() ?>
            <input type="hidden" name="action" value="<?= $action ?>">
            <?php if ($action==='edit'): ?><input type="hidden" name="id" value="<?= $edit_mentor['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($edit_mentor['name']??'') ?>" required>
                </div>
                <?php if ($action==='add'): ?>
                <div class="col-md-5">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label">Department *</label>
                    <select name="department_id" id="dept-select" class="form-select" required onchange="filterSubjects(this.value)">
                        <option value="">Select</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($edit_mentor['department_id']??0)==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Assign Subjects</label>
                    <div id="subject-list" class="row g-2">
                        <?php foreach ($subjects_by_dept as $did => $subs): ?>
                        <div class="dept-subjects col-12" data-dept="<?= $did ?>" style="display:none">
                            <?php foreach ($subs as $sub): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="subjects[]" value="<?= $sub['id'] ?>" id="sub<?= $sub['id'] ?>"
                                    <?= in_array($sub['id'],$edit_subjects)?'checked':'' ?>>
                                <label class="form-check-label small" for="sub<?= $sub['id'] ?>"><?= e($sub['name']) ?> (Y<?= $sub['year'] ?>S<?= $sub['semester'] ?>)</label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><?= $action==='add'?'Add Mentor':'Save' ?></button>
                <a href="mentors.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
var subjectsByDept = <?= json_encode($subjects_by_dept) ?>;
function filterSubjects(did) {
    document.querySelectorAll('.dept-subjects').forEach(function(el){ el.style.display='none'; });
    if(did) { var el=document.querySelector('.dept-subjects[data-dept="'+did+'"]'); if(el) el.style.display=''; }
}
document.addEventListener('DOMContentLoaded',function(){ filterSubjects(document.getElementById('dept-select').value); });
</script>
<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Mentors (<?= $total ?>)</h5>
    <a href="mentors.php?action=add" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Add Mentor</a>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if ($mentors): foreach ($mentors as $i=>$m): ?>
            <tr>
                <td><?= $pag['offset']+$i+1 ?></td>
                <td><?= e($m['name']) ?></td>
                <td><?= e($m['email']) ?></td>
                <td><?= e($m['dept']??'—') ?></td>
                <td><span class="badge bg-info"><?= $m['student_count'] ?></span></td>
                <td><span class="badge bg-<?= $m['status']==='active'?'success':'secondary' ?>"><?= $m['status'] ?></span></td>
                <td>
                    <a href="mentors.php?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" class="d-inline" onsubmit="return confirmAction('Toggle mentor status?')">
                        <?= generate_csrf() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button class="btn btn-sm btn-outline-<?= $m['status']==='active'?'danger':'success' ?>"><?= $m['status']==='active'?'Deactivate':'Activate' ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No mentors found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
