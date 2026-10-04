<?php
$required_role = 'admin';
$page_title    = 'Analytics Dashboard';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db = get_db();

// Attendance % per department
$dept_att = $db->query(
    "SELECT d.name, ROUND(AVG(CASE WHEN a.status='present' THEN 100 ELSE 0 END),1) AS pct
     FROM attendance a JOIN users u ON a.student_id=u.id JOIN departments d ON u.department_id=d.id
     GROUP BY d.id ORDER BY pct DESC"
)->fetchAll();

// Attendance % per subject
$subj_att = $db->query(
    "SELECT s.name AS subject, ROUND(AVG(CASE WHEN a.status='present' THEN 100 ELSE 0 END),1) AS pct
     FROM attendance a JOIN subjects s ON a.subject_id=s.id
     GROUP BY s.id ORDER BY pct DESC LIMIT 10"
)->fetchAll();

// Assignment submission rate
$assign_rate = $db->query(
    "SELECT s.name AS subject,
       COUNT(asub.id) AS total_submissions,
       COUNT(a.id) AS total_assignments,
       ROUND(COUNT(asub.id)/NULLIF(COUNT(a.id),0)*100,1) AS rate
     FROM assignments a
     JOIN subjects s ON a.subject_id=s.id
     LEFT JOIN assignment_submissions asub ON asub.assignment_id=a.id
     GROUP BY a.subject_id ORDER BY rate DESC LIMIT 10"
)->fetchAll();

// Low attendance students (<75%)
$low_att = $db->query(
    "SELECT u.name,s.name AS subject,
       ROUND(AVG(CASE WHEN a.status='present' THEN 100 ELSE 0 END),1) AS pct
     FROM attendance a JOIN users u ON a.student_id=u.id JOIN subjects s ON a.subject_id=s.id
     GROUP BY a.student_id,a.subject_id HAVING pct < 75 ORDER BY pct ASC LIMIT 20"
)->fetchAll();

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-bar-chart me-2"></i>Department Attendance %</div>
                <div class="card-body"><canvas id="deptChart" height="120"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-bar-chart-steps me-2"></i>Subject Attendance %</div>
                <div class="card-body"><canvas id="subjChart" height="120"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-file-earmark-check me-2"></i>Assignment Submission Rate</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Subject</th><th>Submitted</th><th>Total</th><th>Rate</th></tr></thead>
                        <tbody>
                        <?php if ($assign_rate): foreach ($assign_rate as $r): ?>
                        <tr>
                            <td><?= e($r['subject']) ?></td>
                            <td><?= $r['total_submissions'] ?></td>
                            <td><?= $r['total_assignments'] ?></td>
                            <td><div class="progress" style="height:16px"><div class="progress-bar" style="width:<?= $r['rate'] ?>%"><?= $r['rate'] ?>%</div></div></td>
                        </tr>
                        <?php endforeach; else: ?><tr><td colspan="4" class="text-muted text-center">No data.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Students Below 75% Attendance</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Student</th><th>Subject</th><th>Attendance %</th></tr></thead>
                        <tbody>
                        <?php if ($low_att): foreach ($low_att as $l): ?>
                        <tr>
                            <td><?= e($l['name']) ?></td>
                            <td><?= e($l['subject']) ?></td>
                            <td><span class="attendance-danger"><?= $l['pct'] ?>%</span></td>
                        </tr>
                        <?php endforeach; else: ?><tr><td colspan="3" class="text-muted text-center py-3">All students above 75%.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    new Chart(document.getElementById('deptChart'),{type:'bar',data:{
        labels:<?= json_encode(array_column($dept_att,'name')) ?>,
        datasets:[{label:'Attendance %',data:<?= json_encode(array_column($dept_att,'pct')) ?>,backgroundColor:'rgba(13,110,253,.7)',borderRadius:4}]
    },options:{scales:{y:{min:0,max:100,ticks:{callback:v=>v+'%'}}},plugins:{legend:{display:false}}}});

    new Chart(document.getElementById('subjChart'),{type:'bar',data:{
        labels:<?= json_encode(array_column($subj_att,'subject')) ?>,
        datasets:[{label:'Attendance %',data:<?= json_encode(array_column($subj_att,'pct')) ?>,backgroundColor:'rgba(25,135,84,.7)',borderRadius:4}]
    },options:{indexAxis:'y',scales:{x:{min:0,max:100,ticks:{callback:v=>v+'%'}}},plugins:{legend:{display:false}}}});
});
</script>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
