<?php
$required_role = 'learner';
$page_title    = 'Leave Request';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$uid = $current_user['id'];
$error = $success = '';

// Get assigned mentor
$ms_stmt=$db->prepare('SELECT mentor_id FROM mentor_students WHERE student_id=? LIMIT 1');
$ms_stmt->execute([$uid]); $ms=$ms_stmt->fetch();
$mentor_id = $ms['mentor_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');
    $from_date = $_POST['from_date'] ?? '';
    $to_date   = $_POST['to_date'] ?? '';

    if (!$reason || !$from_date || !$to_date) {
        $error = 'All fields are required.';
    } elseif (!$mentor_id) {
        $error = 'No mentor assigned. Please contact HOD.';
    } else {
        $db->prepare('INSERT INTO leave_requests (requested_by,requested_to_role,reason,from_date,to_date) VALUES (?,?,?,?,?)')
           ->execute([$uid,'mentor',$reason,$from_date,$to_date]);
        log_audit($uid,'submitted_leave','leave_requests',(int)$db->lastInsertId());
        flash('success','Leave request submitted to your mentor.');
        header('Location: leave.php'); exit;
    }
}

$my_leaves=$db->prepare(
    "SELECT lr.*,u.name AS approver FROM leave_requests lr LEFT JOIN users u ON lr.approved_by=u.id
     WHERE lr.requested_by=? ORDER BY lr.created_at DESC"
);
$my_leaves->execute([$uid]); $my_leaves=$my_leaves->fetchAll();

$flash_msg=get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-calendar-plus me-2"></i>Apply for Leave</div>
            <div class="card-body">
                <?php if (!$mentor_id): ?><div class="alert alert-warning small">No mentor assigned. Contact HOD.</div><?php endif; ?>
                <form method="POST">
                    <?= generate_csrf() ?>
                    <div class="mb-3"><label class="form-label">Reason *</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                    <div class="mb-3"><label class="form-label">From Date *</label><input type="date" name="from_date" class="form-control" min="<?= date('Y-m-d') ?>" required></div>
                    <div class="mb-3"><label class="form-label">To Date *</label><input type="date" name="to_date" class="form-control" min="<?= date('Y-m-d') ?>" required></div>
                    <button type="submit" class="btn btn-primary w-100" <?= !$mentor_id?'disabled':'' ?>>Submit Request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-clock-history me-2"></i>My Leave History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Reason</th><th>Dates</th><th>Status</th><th>Decided By</th></tr></thead>
                    <tbody>
                    <?php if ($my_leaves): foreach ($my_leaves as $l): ?>
                    <tr>
                        <td><?= e($l['reason']) ?></td>
                        <td class="small"><?= e($l['from_date']) ?> → <?= e($l['to_date']) ?></td>
                        <td><span class="badge bg-<?= match($l['status']){'pending'=>'warning','approved'=>'success',default=>'danger'} ?>"><?= $l['status'] ?></span></td>
                        <td><?= e($l['approver']??'Pending') ?></td>
                    </tr>
                    <?php endforeach; else: ?><tr><td colspan="4" class="text-muted text-center py-3">No leave requests.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
