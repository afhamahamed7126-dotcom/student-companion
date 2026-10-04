<?php
$required_role = 'learner';
$page_title    = 'Study Materials';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$uid = $current_user['id'];

$filter_subject = (int)($_GET['subject'] ?? 0);

// Get subjects this student can access via their mentor
$my_subjects_stmt=$db->prepare(
    "SELECT DISTINCT s.id,s.name FROM subjects s
     JOIN mentor_subjects ms ON ms.subject_id=s.id
     JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id
     WHERE mst.student_id=? ORDER BY s.name"
);
$my_subjects_stmt->execute([$uid]); $my_subjects=$my_subjects_stmt->fetchAll();

$mat_sql = "SELECT sm.*,s.name AS subject_name,u.name AS mentor_name
            FROM study_materials sm
            JOIN subjects s ON sm.subject_id=s.id
            JOIN mentor_subjects ms ON sm.subject_id=ms.subject_id
            JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id
            JOIN users u ON sm.mentor_id=u.id
            WHERE mst.student_id=?";
$mat_params=[$uid];
if ($filter_subject) { $mat_sql .= ' AND sm.subject_id=?'; $mat_params[]=$filter_subject; }
$mat_sql .= ' ORDER BY sm.uploaded_at DESC';

$mat_stmt=$db->prepare($mat_sql);$mat_stmt->execute($mat_params);$materials=$mat_stmt->fetchAll();

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Study Materials</h5>
        <form method="GET" class="d-flex gap-2">
            <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                <option value="">All Subjects</option>
                <?php foreach ($my_subjects as $s): ?><option value="<?= $s['id'] ?>" <?= $filter_subject==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="row g-3">
    <?php if ($materials): foreach ($materials as $m): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <?php
                $icon = match($m['type']) { 'pdf'=>'bi-file-earmark-pdf text-danger', 'ppt'=>'bi-file-earmark-slides text-warning', 'image'=>'bi-file-earmark-image text-success', 'video'=>'bi-file-earmark-play text-primary', default=>'bi-file-earmark' };
                ?>
                <i class="bi <?= $icon ?>" style="font-size:2.5rem"></i>
                <div class="mt-2 fw-semibold small"><?= e($m['title']) ?></div>
                <div class="text-muted" style="font-size:.8rem"><?= e($m['subject_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem">By <?= e($m['mentor_name']) ?> · <?= date('d M Y',strtotime($m['uploaded_at'])) ?></div>
            </div>
            <div class="card-footer text-center d-flex gap-2">
                <a href="<?= BASE_URL ?>view.php?file=<?= urlencode($m['file_path']) ?>&type=notes" class="btn btn-sm btn-outline-info w-50" target="_blank">
                    <i class="bi bi-eye me-1"></i>View
                </a>
                <a href="<?= BASE_URL ?>download.php?file=<?= urlencode($m['file_path']) ?>&type=notes" class="btn btn-sm btn-outline-primary w-50">
                    <i class="bi bi-download me-1"></i>Download
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="col-12"><div class="alert alert-info">No study materials available yet.</div></div>
    <?php endif; ?>
    </div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
