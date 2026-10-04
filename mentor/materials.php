<?php
$required_role = 'mentor';
$page_title    = 'Study Materials';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$mid = $current_user['id'];
$error = $success = '';

$my_subjects = $db->prepare('SELECT ms.subject_id,s.name FROM mentor_subjects ms JOIN subjects s ON ms.subject_id=s.id WHERE ms.mentor_id=?');
$my_subjects->execute([$mid]); $my_subjects = $my_subjects->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf($_POST['csrf_token'] ?? '');
    $act = $_POST['action'] ?? '';

    if ($act === 'upload') {
        $title      = trim($_POST['title'] ?? '');
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        if (!$title || !$subject_id) {
            $error = 'Title and subject are required.';
        } elseif (empty($_FILES['file']['name'])) {
            $error = 'Please select a file to upload.';
        } else {
            $upload = handle_file_upload('file', UPLOAD_PATH . '/notes');
            if (!$upload['success']) {
                $error = $upload['error'];
            } else {
                // Determine type from extension
                $ext  = strtolower(pathinfo($upload['filename'], PATHINFO_EXTENSION));
                $type = match(true) {
                    $ext === 'pdf'             => 'pdf',
                    in_array($ext,['ppt','pptx']) => 'ppt',
                    in_array($ext,['jpg','jpeg','png']) => 'image',
                    $ext === 'mp4'             => 'video',
                    default                    => 'pdf',
                };
                $db->prepare('INSERT INTO study_materials (mentor_id,subject_id,title,file_path,type) VALUES (?,?,?,?,?)')
                   ->execute([$mid,$subject_id,$title,$upload['filename'],$type]);
                log_audit($mid,'uploaded_material','study_materials',(int)$db->lastInsertId());
                flash('success','Material uploaded successfully.');
                header('Location: materials.php'); exit;
            }
        }

    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt=$db->prepare('SELECT file_path FROM study_materials WHERE id=? AND mentor_id=?');$stmt->execute([$id,$mid]);$row=$stmt->fetch();
            if ($row) {
                $db->prepare('DELETE FROM study_materials WHERE id=?')->execute([$id]);
                @unlink(UPLOAD_PATH . '/notes/' . $row['file_path']);
                log_audit($mid,'deleted_material','study_materials',$id);
            }
            flash('success','Material deleted.'); header('Location: materials.php'); exit;
        }
    }
}

$filter_subject = (int)($_GET['subject'] ?? 0);
$mat_sql = "SELECT sm.*,s.name AS subject_name FROM study_materials sm JOIN subjects s ON sm.subject_id=s.id WHERE sm.mentor_id=?";
$mat_params = [$mid];
if ($filter_subject) { $mat_sql .= ' AND sm.subject_id=?'; $mat_params[] = $filter_subject; }
$mat_sql .= ' ORDER BY sm.uploaded_at DESC';
$mat_stmt=$db->prepare($mat_sql);$mat_stmt->execute($mat_params);$materials=$mat_stmt->fetchAll();

$flash_msg=get_flash('success');
require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
<?php if ($flash_msg): ?><div class="alert alert-success alert-auto-dismiss alert-dismissible"><?= e($flash_msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-upload me-2"></i>Upload Material</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= generate_csrf() ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($my_subjects as $s): ?><option value="<?= $s['subject_id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">File *</label><input type="file" name="file" class="form-control" accept=".pdf,.ppt,.pptx,.jpg,.jpeg,.png,.mp4" required><div class="form-text">PDF, PPT, JPG, PNG, MP4 — max 10 MB</div></div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i>Upload</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center fw-bold">
                <span><i class="bi bi-folder-fill me-2"></i>Uploaded Materials</span>
                <form method="GET" class="d-flex gap-1">
                    <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                        <option value="">All Subjects</option>
                        <?php foreach ($my_subjects as $s): ?><option value="<?= $s['subject_id'] ?>" <?= $filter_subject==$s['subject_id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Title</th><th>Subject</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($materials): foreach ($materials as $m): ?>
                    <tr>
                        <td><?= e($m['title']) ?></td>
                        <td><?= e($m['subject_name']) ?></td>
                        <td><span class="badge badge-<?= $m['type'] ?>"><?= strtoupper($m['type']) ?></span></td>
                        <td class="small"><?= format_date($m['uploaded_at']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>view.php?file=<?= urlencode($m['file_path']) ?>&type=notes" class="btn btn-sm btn-outline-info" target="_blank" title="View"><i class="bi bi-eye"></i></a>
                            <a href="<?= BASE_URL ?>download.php?file=<?= urlencode($m['file_path']) ?>&type=notes" class="btn btn-sm btn-outline-primary" title="Download"><i class="bi bi-download"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirmAction('Delete this material?')">
                                <?= generate_csrf() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No materials uploaded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
