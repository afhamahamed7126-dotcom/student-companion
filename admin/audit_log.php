<?php
$required_role = 'admin';
$page_title    = 'Audit Log';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db = get_db();

$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
$search = trim($_GET['q'] ?? '');

$per_page = 25; $page = max(1,(int)($_GET['page']??1));

$where = 'WHERE al.created_at BETWEEN ? AND ?';
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($search) { $where .= ' AND (u.name LIKE ? OR al.action LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }

$total_stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al JOIN users u ON al.user_id=u.id {$where}");
$total_stmt->execute($params);
$total = (int)$total_stmt->fetchColumn();
$pag   = paginate($total, $per_page, $page);

$logs_stmt = $db->prepare(
    "SELECT al.*,u.name AS user_name,u.role
     FROM audit_logs al JOIN users u ON al.user_id=u.id
     {$where} ORDER BY al.created_at DESC LIMIT {$per_page} OFFSET {$pag['offset']}"
);
$logs_stmt->execute($params);
$logs = $logs_stmt->fetchAll();

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2" method="GET">
                <div class="col-md-3">
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
                </div>
                <div class="col-md-3">
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search user or action..." value="<?= e($search) ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a href="audit_log.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold"><i class="bi bi-journal-text me-2"></i>Audit Log (<?= $total ?> entries)</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light"><tr><th>Timestamp</th><th>User</th><th>Role</th><th>Action</th><th>Table</th><th>Target ID</th></tr></thead>
                <tbody>
                <?php if ($logs): foreach ($logs as $l): ?>
                <tr>
                    <td class="small"><?= format_date($l['created_at']) ?></td>
                    <td><?= e($l['user_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($l['role']) ?></span></td>
                    <td><code><?= e($l['action']) ?></code></td>
                    <td><?= e($l['target_table'] ?? '—') ?></td>
                    <td><?= $l['target_id'] ?? '—' ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No log entries.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pag['total_pages'] > 1): ?>
    <nav class="mt-3"><ul class="pagination pagination-sm">
    <?php for($p=1;$p<=$pag['total_pages'];$p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?page=<?=$p?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&q=<?= urlencode($search) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
