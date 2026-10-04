<?php
$required_role = 'learner';
$page_title    = 'Assignments';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$uid = $current_user['id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    if (!$assignment_id || empty($_FILES['file']['name'])) {
        $error = 'Select a file to submit.';
    } else {
        // Check if already submitted
        $chk=$db->prepare('SELECT id FROM assignment_submissions WHERE assignment_id=? AND student_id=?');
        $chk->execute([$assignment_id,$uid]); $existing=$chk->fetch();
        if ($existing) {
            $error = 'You have already submitted this assignment.';
        } else {
            $upload=handle_file_upload('file', UPLOAD_PATH.'/assignments', ['pdf','ppt','pptx']);
            if (!$upload['success']) { $error=$upload['error']; }
            else {
                // Check deadline
                $dl_stmt=$db->prepare('SELECT deadline FROM assignments WHERE id=?'); $dl_stmt->execute([$assignment_id]);
                $asgn=$dl_stmt->fetch();
                $status = ($asgn && strtotime($asgn['deadline']) < time()) ? 'late' : 'submitted';
                $db->prepare('INSERT INTO assignment_submissions (assignment_id,student_id,file_path,status) VALUES (?,?,?,?)')
                   ->execute([$assignment_id,$uid,$upload['filename'],$status]);
                log_audit($uid,'submitted_assignment','assignment_submissions',(int)$db->lastInsertId());
                flash('success','Assignment submitted' . ($status==='late'?' (marked as late)':'') . '.');
                header('Location: assignments.php'); exit;
            }
        }
    }
}

// Fetch all assignments for this student's subjects via mentor
$assignments = $db->prepare(
    "SELECT a.*,s.name AS subject_name,u.name AS mentor_name,
            asub.id AS sub_id, asub.status AS sub_status, asub.marks, asub.feedback, asub.submitted_at
     FROM assignments a
     JOIN subjects s ON a.subject_id=s.id
     JOIN mentor_subjects ms ON a.subject_id=ms.subject_id
     JOIN mentor_students mst ON mst.mentor_id=ms.mentor_id
     JOIN users u ON a.mentor_id=u.id
     LEFT JOIN assignment_submissions asub ON asub.assignment_id=a.id AND asub.student_id=?
     WHERE mst.student_id=?
     ORDER BY a.deadline ASC"
);
$assignments->execute([$uid,$uid]); $assignments=$assignments->fetchAll();

$flash_msg=get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
<?php if ($assignments): foreach ($assignments as $a):
    $is_past = strtotime($a['deadline']) < time();
    $can_submit = !$a['sub_id'] && !$is_past;
    $status_badge = match(true) {
        !$a['sub_id'] && $is_past   => ['danger','Missed'],
        !$a['sub_id']               => ['secondary','Not Submitted'],
        $a['sub_status']==='evaluated' => ['success','Evaluated'],
        $a['sub_status']==='late'   => ['warning','Late'],
        default                     => ['primary','Submitted'],
    };
?>
<div class="col-md-6">
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
            <strong><?= e($a['title']) ?></strong>
            <span class="badge bg-<?= $status_badge[0] ?>"><?= $status_badge[1] ?></span>
        </div>
        <div class="card-body">
            <div class="text-muted small mb-2"><?= e($a['subject_name']) ?> · <?= e($a['mentor_name']) ?></div>
            <?php if ($a['description']): ?><p class="small"><?= e($a['description']) ?></p><?php endif; ?>
            <div class="small"><strong>Deadline:</strong> <span class="<?= $is_past?'text-danger':'' ?>"><?= e($a['deadline']) ?></span></div>
            <?php if ($a['file_path']): ?>
            <a href="<?= BASE_URL ?>download.php?file=<?= urlencode($a['file_path']) ?>&type=assignments" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-download me-1"></i>Download Question</a>
            <?php endif; ?>
            <?php if ($a['sub_id'] && $a['sub_status']==='evaluated'): ?>
            <div class="mt-2 alert alert-success py-2 small"><strong>Marks:</strong> <?= $a['marks'] ?? '—' ?>/100 &nbsp; <strong>Feedback:</strong> <?= e($a['feedback']??'—') ?></div>
            <?php endif; ?>
        </div>
        <?php if ($can_submit): ?>
        <div class="card-footer">
            <form method="POST" enctype="multipart/form-data">
                <?= generate_csrf() ?>
                <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                <div class="input-group">
                    <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.ppt,.pptx" required>
                    <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                </div>
                <div class="form-text">PDF, PPT only · max 10 MB</div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; else: ?>
<div class="col-12"><div class="alert alert-info">No assignments found.</div></div>
<?php endif; ?>
</div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
