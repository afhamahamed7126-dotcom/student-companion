<?php
$required_role = 'learner';
$page_title    = 'My Attendance';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$uid = $current_user['id'];

$subj_att = $db->prepare(
    "SELECT s.name AS subject,
            COUNT(*) AS total,
            SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent,
            ROUND(AVG(CASE WHEN a.status='present' THEN 100 ELSE 0 END),1) AS pct
     FROM attendance a JOIN subjects s ON a.subject_id=s.id
     WHERE a.student_id=? GROUP BY a.subject_id ORDER BY s.name"
);
$subj_att->execute([$uid]); $subj_att=$subj_att->fetchAll();

$overall=$db->prepare("SELECT ROUND(AVG(CASE WHEN status='present' THEN 100 ELSE 0 END),1) FROM attendance WHERE student_id=?");
$overall->execute([$uid]); $overall_pct=(float)$overall->fetchColumn();

function att_class(float $pct): string {
    return $pct >= 75 ? 'attendance-good' : ($pct >= 50 ? 'attendance-warn' : 'attendance-danger');
}

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
    <div class="card mb-4" style="max-width:300px">
        <div class="card-body text-center">
            <div class="text-muted small">Overall Attendance</div>
            <div class="display-5 fw-bold <?= att_class($overall_pct) ?>"><?= $overall_pct ?>%</div>
            <div class="progress mt-2"><div class="progress-bar bg-<?= $overall_pct>=75?'success':($overall_pct>=50?'warning':'danger') ?>" style="width:<?= $overall_pct ?>%"></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold"><i class="bi bi-calendar3 me-2"></i>Subject-wise Attendance</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Subject</th><th>Total Classes</th><th>Present</th><th>Absent</th><th>Percentage</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if ($subj_att): foreach ($subj_att as $row): ?>
                <tr>
                    <td><?= e($row['subject']) ?></td>
                    <td><?= $row['total'] ?></td>
                    <td><?= $row['present'] ?></td>
                    <td><?= $row['absent'] ?></td>
                    <td>
                        <span class="<?= att_class((float)$row['pct']) ?>"><?= $row['pct'] ?>%</span>
                        <div class="progress mt-1" style="height:6px"><div class="progress-bar bg-<?= $row['pct']>=75?'success':($row['pct']>=50?'warning':'danger') ?>" style="width:<?= $row['pct'] ?>%"></div></div>
                    </td>
                    <td>
                        <?php if ($row['pct'] < 75): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Low</span>
                        <?php else: ?>
                        <span class="badge bg-success">Good</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No attendance records yet.</td></tr>
                <?php endif; ?>
                </tbody>
                <?php if ($subj_att): ?>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>Overall</td>
                        <td><?= array_sum(array_column($subj_att,'total')) ?></td>
                        <td><?= array_sum(array_column($subj_att,'present')) ?></td>
                        <td><?= array_sum(array_column($subj_att,'absent')) ?></td>
                        <td class="<?= att_class($overall_pct) ?>"><?= $overall_pct ?>%</td>
                        <td>—</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
