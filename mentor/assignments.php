<?php
$required_role = 'mentor';
$page_title    = 'Assignments';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$mid = $current_user['id'];
$tab = $_GET['tab'] ?? 'list';
$error = $success = '';

// My subjects
$my_subjects = $db->prepare('SELECT ms.subject_id,s.name FROM mentor_subjects ms JOIN subjects s ON ms.subject_id=s.id WHERE ms.mentor_id=?');
$my_subjects->execute([$mid]); $my_subjects = $my_subjects->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';

    if ($act === 'create') {
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $deadline   = $_POST['deadline'] ?? '';
        $file_path  = null;

        if (!$title || !$subject_id || !$deadline) {
            $error = 'Title, subject and deadline are required.';
            $tab = 'create';
        } else {
            if (!empty($_FILES['file']['name'])) {
                $upload = handle_file_upload('file', UPLOAD_PATH . '/assignments', ['pdf','ppt','pptx']);
                if (!$upload['success']) { $error = $upload['error']; $tab='create'; }
                else $file_path = $upload['filename'];
            }
            if (!$error) {
                $db->prepare('INSERT INTO assignments (mentor_id,subject_id,title,description,file_path,deadline) VALUES (?,?,?,?,?,?)')
                   ->execute([$mid,$subject_id,$title,$desc,$file_path,$deadline]);
                $aid = (int)$db->lastInsertId();
                // Notify all students under this mentor
                $db->prepare('INSERT INTO notifications (created_by,title,message,target_type,target_id) VALUES (?,?,?,?,?)')
                   ->execute([$mid, 'New Assignment: '.$title, "A new assignment has been posted: {$title}. Deadline: {$deadline}.", 'mentor_students', $mid]);
                log_audit($mid,'created_assignment','assignments',$aid);
                flash('success','Assignment created and students notified.');
                header('Location: assignments.php?tab=list'); exit;
            }
        }

    } elseif ($act === 'evaluate') {
        $sub_id   = (int)($_POST['submission_id'] ?? 0);
        $marks    = isset($_POST['marks']) ? max(0,min(100,(int)$_POST['marks'])) : null;
        $feedback = trim($_POST['feedback'] ?? '');
        if ($sub_id) {
            $db->prepare('UPDATE assignment_submissions SET marks=?,feedback=?,status="evaluated" WHERE id=?')
               ->execute([$marks,$feedback,$sub_id]);
            // Notify only the specific student whose submission was evaluated
            $s=$db->prepare('SELECT student_id FROM assignment_submissions WHERE id=?');$s->execute([$sub_id]);$sr=$s->fetch();
            if ($sr) {
                $db->prepare('INSERT INTO notifications (created_by,title,message,target_type,target_id) VALUES (?,?,?,?,?)')
                   ->execute([$mid,'Assignment Evaluated','Your assignment has been evaluated. Check your marks.','user',(int)$sr['student_id']]);
            }
            log_audit($mid,'evaluated_submission','assignment_submissions',$sub_id);
            flash('success','Evaluation saved.');
            header('Location: assignments.php?tab=evaluate'); exit;
        }
    }
}

// Assignments list
$assignments = $db->prepare(
    "SELECT a.*,s.name AS subject_name,
     (SELECT COUNT(*) FROM assignment_submissions asub WHERE asub.assignment_id=a.id) AS sub_count
     FROM assignments a JOIN subjects s ON a.subject_id=s.id WHERE a.mentor_id=? ORDER BY a.created_at DESC"
);
$assignments->execute([$mid]); $assignments = $assignments->fetchAll();

// Submissions to evaluate
$submissions = $db->prepare(
    "SELECT asub.*,u.name AS student_name,a.title AS assignment_title,a.deadline
     FROM assignment_submissions asub
     JOIN assignments a ON asub.assignment_id=a.id
     JOIN users u ON asub.student_id=u.id
     WHERE a.mentor_id=? AND asub.status IN ('submitted','late')
     ORDER BY asub.submitted_at ASC"
);
$submissions->execute([$mid]); $submissions = $submissions->fetchAll();

$flash_msg = get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='list'?'active':'' ?>" href="?tab=list">My Assignments</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='create'?'active':'' ?>" href="?tab=create">Create Assignment</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='evaluate'?'active':'' ?>" href="?tab=evaluate">
        Evaluate <span class="badge bg-warning text-dark"><?= count($submissions) ?></span>
    </a></li>
</ul>

<?php if ($tab === 'create'): ?>
<div class="card" style="max-width:700px">
    <div class="card-header fw-bold"><i class="bi bi-plus-circle me-2"></i>Create Assignment</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= generate_csrf() ?>
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Subject *</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach ($my_subjects as $s): ?><option value="<?= $s['subject_id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Deadline *</label><input type="datetime-local" name="deadline" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Attachment (PDF/PPT)</label><input type="file" name="file" class="form-control" accept=".pdf,.ppt,.pptx"></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-send me-1"></i>Publish Assignment</button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'evaluate'): ?>
<?php if ($submissions): foreach ($submissions as $sub): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
        <strong><?= e($sub['student_name']) ?></strong>
        <span class="badge bg-<?= $sub['status']==='late'?'danger':'primary' ?>"><?= $sub['status'] ?></span>
    </div>
    <div class="card-body">
        <div class="mb-2"><strong>Assignment:</strong> <?= e($sub['assignment_title']) ?> &nbsp;|&nbsp; <strong>Deadline:</strong> <?= e($sub['deadline']) ?></div>
        <div class="mb-3">
            <strong>Submission:</strong>
            <a href="<?= BASE_URL ?>download.php?file=<?= urlencode($sub['file_path']) ?>&type=assignments" class="btn btn-sm btn-outline-primary ms-2"><i class="bi bi-download me-1"></i>Download</a>
        </div>
        <form method="POST">
            <?= generate_csrf() ?>
            <input type="hidden" name="action" value="evaluate">
            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
            <div class="row g-2">
                <div class="col-md-2"><label class="form-label">Marks (0-100)</label><input type="number" name="marks" class="form-control" min="0" max="100"></div>
                <div class="col-md-8"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="2"></textarea></div>
                <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-success w-100">Save</button></div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; else: ?>
<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>No pending evaluations. All caught up!</div>
<?php endif; ?>

<?php else: // list ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Title</th><th>Subject</th><th>Deadline</th><th>Submissions</th><th>File</th></tr></thead>
            <tbody>
            <?php if ($assignments): foreach ($assignments as $a): ?>
            <tr>
                <td><strong><?= e($a['title']) ?></strong><div class="text-muted small"><?= e(substr($a['description']??'',0,60)) ?></div></td>
                <td><?= e($a['subject_name']) ?></td>
                <td><?= e($a['deadline']) ?></td>
                <td><span class="badge bg-info"><?= $a['sub_count'] ?></span></td>
                <td><?php if ($a['file_path']): ?><a href="<?= BASE_URL ?>download.php?file=<?= urlencode($a['file_path']) ?>&type=assignments" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a><?php else: ?>—<?php endif; ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No assignments created yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
