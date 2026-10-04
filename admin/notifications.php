<?php
$required_role = 'admin';
$page_title    = 'Notifications';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db    = get_db();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $target_type = $_POST['target_type'] ?? 'all';
    $target_id   = ($target_type !== 'all') ? (int)($_POST['target_id'] ?? 0) : null;

    if (!$title || !$message) {
        $error = 'Title and message are required.';
    } else {
        $db->prepare('INSERT INTO notifications (created_by,title,message,target_type,target_id) VALUES (?,?,?,?,?)')
           ->execute([$current_user['id'], $title, $message, $target_type, $target_id]);
        log_audit($current_user['id'],'created_notification','notifications',(int)$db->lastInsertId());
        flash('success', 'Notification published successfully.');
        header('Location: notifications.php'); exit;
    }
}

$departments = $db->query('SELECT id,name FROM departments ORDER BY name')->fetchAll();
$mentors     = $db->query('SELECT id,name FROM users WHERE role="mentor" AND status="active" ORDER BY name')->fetchAll();

$per_page = 15; $page = max(1,(int)($_GET['page']??1));
$total = (int)$db->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
$pag   = paginate($total,$per_page,$page);
$notifs = $db->query(
    "SELECT n.*,u.name AS creator FROM notifications n JOIN users u ON n.created_by=u.id
     ORDER BY n.created_at DESC LIMIT {$per_page} OFFSET {$pag['offset']}"
)->fetchAll();

$flash_msg = get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-header fw-bold"><i class="bi bi-megaphone-fill me-2"></i>Create Notification</div>
    <div class="card-body">
        <form method="POST">
            <?= generate_csrf() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Target</label>
                    <select name="target_type" id="target-type" class="form-select" onchange="updateTargetUI(this.value)">
                        <option value="all">All Users</option>
                        <option value="department">By Department</option>
                        <option value="mentor_students">By Mentor's Students</option>
                    </select>
                </div>
                <div class="col-md-4 d-none" id="target-dept">
                    <label class="form-label">Department</label>
                    <select name="target_id" class="form-select">
                        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-none" id="target-mentor">
                    <label class="form-label">Mentor</label>
                    <select name="target_id" class="form-select">
                        <?php foreach ($mentors as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-warning mt-3"><i class="bi bi-send me-1"></i>Publish Notification</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Notifications</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Title</th><th>Target</th><th>Created By</th><th>Date</th></tr></thead>
            <tbody>
            <?php if ($notifs): foreach ($notifs as $n): ?>
            <tr>
                <td><strong><?= e($n['title']) ?></strong><div class="text-muted small"><?= e(substr($n['message'],0,60)) ?>…</div></td>
                <td><span class="badge bg-secondary"><?= e($n['target_type']) ?><?= $n['target_id']?' #'.$n['target_id']:'' ?></span></td>
                <td><?= e($n['creator']) ?></td>
                <td class="small"><?= format_date($n['created_at']) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center text-muted py-3">No notifications yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<script>
function updateTargetUI(v) {
    document.getElementById('target-dept').classList.toggle('d-none', v!=='department');
    document.getElementById('target-mentor').classList.toggle('d-none', v!=='mentor_students');
}
</script>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
