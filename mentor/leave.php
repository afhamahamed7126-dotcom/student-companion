<?php
$required_role = 'mentor';
$page_title    = 'Leave';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$mid = $current_user['id'];
$tab = $_GET['tab'] ?? 'approve';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';

    if ($act === 'submit_own') {
        $reason    = trim($_POST['reason'] ?? '');
        $from_date = $_POST['from_date'] ?? '';
        $to_date   = $_POST['to_date'] ?? '';
        if (!$reason || !$from_date || !$to_date) {
            $error = 'All fields are required.'; $tab='own';
        } else {
            $db->prepare('INSERT INTO leave_requests (requested_by,requested_to_role,reason,from_date,to_date) VALUES (?,?,?,?,?)')
               ->execute([$mid,'admin',$reason,$from_date,$to_date]);
            flash('success','Leave request submitted to HOD.');
            header('Location: leave.php?tab=own'); exit;
        }

    } elseif (in_array($act,['approve','reject'],true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $status = $act === 'approve' ? 'approved' : 'rejected';
            $db->prepare('UPDATE leave_requests SET status=?,approved_by=? WHERE id=? AND requested_to_role="mentor"')
               ->execute([$status,$mid,$id]);
            $req=$db->prepare('SELECT requested_by FROM leave_requests WHERE id=?');$req->execute([$id]);$lr=$req->fetch();
            if ($lr) {
                $db->prepare('INSERT INTO notifications (created_by,title,message,target_type,target_id) VALUES (?,?,?,?,?)')
                   ->execute([$mid,'Leave Request '.ucfirst($status),"Your leave has been {$status} by your mentor.",'user',(int)$lr['requested_by']]);
            }
            log_audit($mid,"leave_{$status}",'leave_requests',$id);
            flash('success',"Leave {$status}.");
            header('Location: leave.php?tab=approve'); exit;
        }
    }
}

// Student leave requests pending for this mentor
$student_leaves = $db->prepare(
    "SELECT lr.*,u.name AS student_name FROM leave_requests lr
     JOIN mentor_students ms ON lr.requested_by=ms.student_id
     JOIN users u ON lr.requested_by=u.id
     WHERE ms.mentor_id=? AND lr.requested_to_role='mentor' AND lr.status='pending'
     ORDER BY lr.created_at DESC"
);
$student_leaves->execute([$mid]); $student_leaves=$student_leaves->fetchAll();

// My own leave requests
$my_leaves = $db->prepare(
    "SELECT lr.*,u.name AS approver FROM leave_requests lr LEFT JOIN users u ON lr.approved_by=u.id
     WHERE lr.requested_by=? ORDER BY lr.created_at DESC"
);
$my_leaves->execute([$mid]); $my_leaves=$my_leaves->fetchAll();

$flash_msg=get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='approve'?'active':'' ?>" href="?tab=approve">
        Student Requests <span class="badge bg-warning text-dark"><?= count($student_leaves) ?></span>
    </a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='own'?'active':'' ?>" href="?tab=own">My Leave</a></li>
</ul>

<?php if ($tab === 'own'): ?>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header fw-bold">Submit Leave Request</div>
            <div class="card-body">
                <form method="POST">
                    <?= generate_csrf() ?>
                    <input type="hidden" name="action" value="submit_own">
                    <div class="mb-3"><label class="form-label">Reason *</label><textarea name="reason" class="form-control" rows="3" required></textarea></div>
                    <div class="mb-3"><label class="form-label">From Date *</label><input type="date" name="from_date" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">To Date *</label><input type="date" name="to_date" class="form-control" required></div>
                    <button type="submit" class="btn btn-primary w-100">Submit to HOD</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header fw-bold">My Leave History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Reason</th><th>Dates</th><th>Status</th><th>Decided By</th></tr></thead>
                    <tbody>
                    <?php if ($my_leaves): foreach ($my_leaves as $l): ?>
                    <tr>
                        <td><?= e($l['reason']) ?></td>
                        <td><?= e($l['from_date']) ?> → <?= e($l['to_date']) ?></td>
                        <td><span class="badge bg-<?= match($l['status']){'pending'=>'warning','approved'=>'success',default=>'danger'} ?>"><?= $l['status'] ?></span></td>
                        <td><?= e($l['approver']??'—') ?></td>
                    </tr>
                    <?php endforeach; else: ?><tr><td colspan="4" class="text-muted text-center">No requests.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: // approve student leaves ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Student</th><th>Reason</th><th>Dates</th><th>Applied</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($student_leaves): foreach ($student_leaves as $l): ?>
            <tr>
                <td><strong><?= e($l['student_name']) ?></strong></td>
                <td><?= e($l['reason']) ?></td>
                <td><?= e($l['from_date']) ?> → <?= e($l['to_date']) ?></td>
                <td class="small"><?= format_date($l['created_at']) ?></td>
                <td>
                    <form method="POST" class="d-inline">
                        <?= generate_csrf() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                    </form>
                    <form method="POST" class="d-inline">
                        <?= generate_csrf() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No pending student leave requests.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
