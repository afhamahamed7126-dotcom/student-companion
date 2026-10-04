<?php
$required_role = 'learner';
$page_title    = 'Notifications';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$db  = get_db();
$uid = $current_user['id'];
$dept_id = (int)($current_user['department_id'] ?? 0);

// Get mentor_id
$ms_stmt=$db->prepare('SELECT mentor_id FROM mentor_students WHERE student_id=? LIMIT 1');
$ms_stmt->execute([$uid]); $ms=$ms_stmt->fetch(); $mentor_id=(int)($ms['mentor_id']??0);

// Fetch all notifications for this user
$notifs=$db->prepare(
    "SELECT n.id,n.title,n.message,n.created_at,u.name AS creator,
            (SELECT nr.id FROM notification_reads nr WHERE nr.notification_id=n.id AND nr.user_id=?) AS read_id
     FROM notifications n JOIN users u ON n.created_by=u.id
     WHERE n.target_type='all'
        OR (n.target_type='department' AND n.target_id=?)
        OR (n.target_type='mentor_students' AND n.target_id=?)
        OR (n.target_type='user' AND n.target_id=?)
     ORDER BY n.created_at DESC LIMIT 50"
);
$notifs->execute([$uid,$dept_id,$mentor_id,$uid]); $notifs=$notifs->fetchAll();

// Mark all unread as read
$unread_ids=[];
foreach ($notifs as $n) { if (!$n['read_id']) $unread_ids[]=(int)$n['id']; }
if ($unread_ids) {
    $placeholders=implode(',',array_fill(0,count($unread_ids),'(?,?)'));
    $ins_params=[];
    foreach ($unread_ids as $nid) { $ins_params[]=$nid; $ins_params[]=$uid; }
    $db->prepare("INSERT IGNORE INTO notification_reads (notification_id,user_id) VALUES {$placeholders}")->execute($ins_params);
}

require_once dirname(__DIR__).'/includes/header.php';
require_once dirname(__DIR__).'/includes/sidebar.php';
require_once dirname(__DIR__).'/includes/navbar.php';
?>
<div class="main-content p-4">
    <h5 class="mb-3"><i class="bi bi-bell-fill me-2 text-warning"></i>Notifications</h5>
    <?php if ($notifs): foreach ($notifs as $n): ?>
    <div class="card mb-2 <?= !$n['read_id']?'border-primary':'' ?>">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <?php if (!$n['read_id']): ?><span class="badge bg-primary me-1">New</span><?php endif; ?>
                    <strong><?= e($n['title']) ?></strong>
                    <div class="small mt-1"><?= e($n['message']) ?></div>
                </div>
                <div class="text-muted small text-nowrap ms-3"><?= format_date($n['created_at']) ?></div>
            </div>
            <div class="text-muted" style="font-size:.8rem">From: <?= e($n['creator']) ?></div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="alert alert-info">No notifications yet.</div>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__).'/includes/footer.php'; ?>
