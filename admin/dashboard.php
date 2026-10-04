<?php
$required_role = 'admin';
$page_title    = 'HOD Dashboard';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db = get_db();

$stats = [
    'students'     => $db->query("SELECT COUNT(*) FROM users WHERE role='learner' AND status='active'")->fetchColumn(),
    'mentors'      => $db->query("SELECT COUNT(*) FROM users WHERE role='mentor'  AND status='active'")->fetchColumn(),
    'today_att'    => $db->query("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE date=CURDATE()")->fetchColumn(),
    'pending_leave'=> $db->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending' AND requested_to_role='admin'")->fetchColumn(),
];

$dept_perf = $db->query(
    "SELECT d.name, ROUND(AVG(CASE WHEN a.status='present' THEN 100 ELSE 0 END),1) AS pct
     FROM attendance a JOIN users u ON a.student_id=u.id JOIN departments d ON u.department_id=d.id
     GROUP BY d.id ORDER BY pct DESC"
)->fetchAll();

$recent_notifs = $db->query(
    "SELECT n.title, n.message, n.created_at, u.name AS creator
     FROM notifications n JOIN users u ON n.created_by=u.id ORDER BY n.created_at DESC LIMIT 5"
)->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/sidebar.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-content p-4 animate__animated animate__fadeIn">
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card primary h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-muted small">Total Students</div><div class="fs-3 fw-bold"><?= $stats['students'] ?></div></div>
                    <i class="bi bi-people-fill stat-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card success h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-muted small">Total Mentors</div><div class="fs-3 fw-bold"><?= $stats['mentors'] ?></div></div>
                    <i class="bi bi-person-badge-fill stat-icon text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card warning h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-muted small">Attendance Today</div><div class="fs-3 fw-bold"><?= $stats['today_att'] ?></div></div>
                    <i class="bi bi-calendar-check stat-icon text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card danger h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div><div class="text-muted small">Pending Leave</div><div class="fs-3 fw-bold"><?= $stats['pending_leave'] ?></div></div>
                    <i class="bi bi-calendar-x stat-icon text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-bar-chart-fill me-2"></i>Department Attendance</div>
                <div class="card-body"><canvas id="deptChart" height="100"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-bell-fill me-2"></i>Recent Notifications</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_notifs): foreach ($recent_notifs as $n): ?>
                        <li class="list-group-item">
                            <div class="fw-semibold small"><?= e($n['title']) ?></div>
                            <div class="text-muted" style="font-size:.8rem"><?= e($n['creator']) ?> · <?= format_date($n['created_at']) ?></div>
                        </li>
                        <?php endforeach; else: ?>
                        <li class="list-group-item text-muted small">No notifications yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="students.php?action=add" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Add Student</a>
                    <a href="mentors.php?action=add" class="btn btn-outline-success"><i class="bi bi-person-badge me-1"></i>Add Mentor</a>
                    <a href="notifications.php" class="btn btn-outline-warning"><i class="bi bi-megaphone me-1"></i>Send Notification</a>
                    <a href="analytics.php" class="btn btn-outline-info"><i class="bi bi-graph-up me-1"></i>View Analytics</a>
                    <a href="reports.php" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var deptLabels = <?= json_encode(array_column($dept_perf, 'name')) ?>;
var deptData   = <?= json_encode(array_column($dept_perf, 'pct')) ?>;
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: {
            labels: deptLabels,
            datasets: [{ label: 'Attendance %', data: deptData, backgroundColor: 'rgba(13,110,253,.7)', borderRadius: 4 }]
        },
        options: { scales: { y: { min: 0, max: 100, ticks: { callback: v => v+'%' } } }, plugins: { legend: { display: false } } }
    });
});
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
