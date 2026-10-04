<?php
$required_role = 'admin';
$page_title    = 'Leave Requests';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if ($id && in_array($act, ['approve','reject'], true)) {
        $status = $act === 'approve' ? 'approved' : 'rejected';
        $db->prepare('UPDATE leave_requests SET status=?,approved_by=? WHERE id=? AND requested_to_role="admin"')
           ->execute([$status, $current_user['id'], $id]);
        // Notify the requester directly
        $req=$db->prepare('SELECT requested_by FROM leave_requests WHERE id=?'); $req->execute([$id]); $lr=$req->fetch();
        if ($lr) {
            $db->prepare('INSERT INTO notifications (created_by,title,message,target_type,target_id) VALUES (?,?,?,?,?)')
               ->execute([$current_user['id'], 'Leave Request ' . ucfirst($status),
                "Your leave request has been {$status} by HOD.", 'user', (int)$lr['requested_by']]);
        }
        log_audit($current_user['id'], "leave_{$status}", 'leave_requests', $id);
        flash('success', "Leave request {$status}.");
        header('Location: leave.php'); exit;
    }
}

$tab = $_GET['tab'] ?? 'pending';
$status_filter = in_array($tab, ['pending','approved','rejected']) ? $tab : 'pending';

$requests = $db->prepare(
    "SELECT lr.*,u.name AS requester_name,u.email AS requester_email,
            ua.name AS approver_name
     FROM leave_requests lr
     JOIN users u ON lr.requested_by=u.id
     LEFT JOIN users ua ON lr.approved_by=ua.id
     WHERE lr.requested_to_role='admin' AND lr.status=?
     ORDER BY lr.created_at DESC"
);
$requests->execute([$status_filter]);
$items = $requests->fetchAll();

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $counts[$s] = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE requested_to_role='admin' AND status=?");
    $counts[$s]->execute([$s]);
    $counts[$s] = (int)$counts[$s]->fetchColumn();
}

$flash_msg = get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<h5 class="mb-3">Mentor Leave Requests</h5>
<ul class="nav nav-tabs mb-3">
    <?php foreach (['pending'=>'warning','approved'=>'success','rejected'=>'danger'] as $s=>$clr): ?>
    <li class="nav-item"><a class="nav-link <?= $tab===$s?'active':'' ?>" href="?tab=<?=$s?>">
        <?= ucfirst($s) ?> <span class="badge bg-<?= $clr ?>"><?= $counts[$s] ?></span>
    </a></li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Mentor</th><th>Reason</th><th>Dates</th><th>Applied On</th><?php if ($tab==='pending'): ?><th>Action</th><?php else: ?><th>Decided By</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if ($items): foreach ($items as $item): ?>
            <tr>
                <td><strong><?= e($item['requester_name']) ?></strong><div class="text-muted small"><?= e($item['requester_email']) ?></div></td>
                <td><?= e($item['reason']) ?></td>
                <td><?= e($item['from_date']) ?> → <?= e($item['to_date']) ?></td>
                <td class="small"><?= format_date($item['created_at']) ?></td>
                <?php if ($tab==='pending'): ?>
                <td>
                    <form method="POST" class="d-inline">
                        <?= generate_csrf() ?><input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                    </form>
                    <form method="POST" class="d-inline">
                        <?= generate_csrf() ?><input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                    </form>
                </td>
                <?php else: ?>
                <td><?= e($item['approver_name'] ?? '—') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No <?= $status_filter ?> requests.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
