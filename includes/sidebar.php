<?php
$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));

function nav_link(string $href, string $icon, string $label, string $current_page): string {
    $active = (basename($href) === $current_page) ? ' active shadow-sm' : '';
    return '<li class="nav-item"><a class="nav-link' . $active . ' d-flex align-items-center" href="' . $href . '"><i class="bi bi-' . $icon . ' me-3 fs-5"></i><span>' . $label . '</span></a></li>';
}

$base = BASE_URL;
?>

<!-- Desktop Sidebar       --> 
<nav class="sidebar d-none d-md-flex flex-column flex-shrink-0 p-3 border-end-0 shadow-sm z-3 animate__animated animate__fadeInLeft">
  <!--Logo Section  -->
    <a href="<?= $base ?>index.php" class="d-flex align-items-center mb-4 text-decoration-none px-2 mt-2">
        <div class="bg-primary bg-gradient text-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center">
            <i class="bi bi-mortarboard-fill fs-5"></i>
        </div>
        <span class="fw-bold fs-5 text-primary tracking-tight" style="font-family: var(--bs-heading-font-family);"><?= APP_NAME ?></span>
    </a>
    <!-- Role-Based Menu (Lines 25–48)      --> 
    <ul class="nav nav-pills flex-column mb-auto gap-1">
        <li class="nav-item mb-2 px-2 small text-muted text-uppercase fw-semibold" style="letter-spacing: 1px; font-size: 0.75rem;">Menu</li>
        <?php if ($role === 'admin'): ?>
            <?= nav_link($base.'admin/dashboard.php', 'grid-1x2', 'Dashboard', $current_page) ?>
            <?= nav_link($base.'admin/departments.php', 'building', 'Departments', $current_page) ?>
            <?= nav_link($base.'admin/students.php', 'people', 'Students', $current_page) ?>
            <?= nav_link($base.'admin/mentors.php', 'person-video3', 'Mentors', $current_page) ?>
            <?= nav_link($base.'admin/notifications.php', 'bell', 'Notifications', $current_page) ?>
            <?= nav_link($base.'admin/leave.php', 'calendar-check', 'Leave Requests', $current_page) ?>
            <?= nav_link($base.'admin/analytics.php', 'bar-chart', 'Analytics', $current_page) ?>
            <?= nav_link($base.'admin/reports.php', 'file-earmark-arrow-down', 'Reports', $current_page) ?>
            <?= nav_link($base.'admin/audit_log.php', 'journal-text', 'Audit Log', $current_page) ?>
        <?php elseif ($role === 'mentor'): ?>
            <?= nav_link($base.'mentor/dashboard.php', 'grid-1x2', 'Dashboard', $current_page) ?>
            <?= nav_link($base.'mentor/attendance.php', 'calendar3', 'Attendance', $current_page) ?>
            <?= nav_link($base.'mentor/assignments.php', 'file-earmark-text', 'Assignments', $current_page) ?>
            <?= nav_link($base.'mentor/materials.php', 'folder', 'Study Materials', $current_page) ?>
            <?= nav_link($base.'mentor/leave.php', 'calendar-check', 'Leave', $current_page) ?>
        <?php elseif ($role === 'learner'): ?>
            <?= nav_link($base.'learner/dashboard.php', 'grid-1x2', 'Dashboard', $current_page) ?>
            <?= nav_link($base.'learner/assignments.php', 'file-earmark-text', 'Assignments', $current_page) ?>
            <?= nav_link($base.'learner/attendance.php', 'calendar3', 'Attendance', $current_page) ?>
            <?= nav_link($base.'learner/materials.php', 'folder', 'Study Materials', $current_page) ?>
            <?= nav_link($base.'learner/notifications.php', 'bell', 'Notifications', $current_page) ?>
            <?= nav_link($base.'learner/leave.php', 'calendar-check', 'Leave Request', $current_page) ?>
        <?php endif; ?>
    </ul>
        <!-- User Profile Card       --> 

    <div class="mt-auto pt-3">
        <div class="card border-0 shadow-sm bg-primary bg-gradient bg-opacity-10 rounded-4 overflow-hidden">
            <div class="card-body p-3 d-flex flex-column align-items-center text-center">
                <div class="bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
                    <i class="bi bi-person text-primary fs-5"></i>
                </div>
                <div class="small fw-semibold text-truncate w-100"><?= htmlspecialchars($current_user['name'] ?? '', ENT_QUOTES) ?></div>
                <div class="small text-muted mb-3 w-100 text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES) ?></div>
                <a href="<?= $base ?>logout.php" class="btn btn-sm btn-light w-100 text-danger shadow-sm rounded-pill"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start border-0 shadow" tabindex="-1" id="mobileSidebar" style="background: var(--sidebar-bg);">
    <div class="offcanvas-header border-bottom border-opacity-10 px-4 py-3">
        <div class="d-flex align-items-center">
            <div class="bg-primary bg-gradient text-white rounded p-1 me-2 shadow-sm d-flex align-items-center justify-content-center">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h6 class="offcanvas-title fw-bold text-primary mb-0" style="font-family: var(--bs-heading-font-family);"><?= APP_NAME ?></h6>
        </div>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <ul class="nav nav-pills flex-column p-3 gap-1 mb-auto">
            <li class="nav-item mb-2 px-2 small text-muted text-uppercase fw-semibold" style="letter-spacing: 1px; font-size: 0.75rem;">Menu</li>
            <?php if ($role === 'admin'): ?>
                <?= nav_link($base.'admin/dashboard.php', 'grid-1x2', 'Dashboard', $current_page) ?>
                <?= nav_link($base.'admin/departments.php', 'building', 'Departments', $current_page) ?>
                <?= nav_link($base.'admin/students.php', 'people', 'Students', $current_page) ?>
                <?= nav_link($base.'admin/mentors.php', 'person-video3', 'Mentors', $current_page) ?>
                <?= nav_link($base.'admin/notifications.php', 'bell', 'Notifications', $current_page) ?>
                <?= nav_link($base.'admin/leave.php', 'calendar-check', 'Leave Requests', $current_page) ?>
                <?= nav_link($base.'admin/analytics.php', 'bar-chart', 'Analytics', $current_page) ?>
                <?= nav_link($base.'admin/reports.php', 'file-earmark-arrow-down', 'Reports', $current_page) ?>
                <?= nav_link($base.'admin/audit_log.php', 'journal-text', 'Audit Log', $current_page) ?>
            <?php elseif ($role === 'mentor'): ?>
                <?= nav_link($base.'mentor/dashboard.php', 'grid-1x2', 'Dashboard', $current_page) ?>
                <?= nav_link($base.'mentor/attendance.php', 'calendar3', 'Attendance', $current_page) ?>
                <?= nav_link($base.'mentor/assignments.php', 'file-earmark-text', 'Assignments', $current_page) ?>
                <?= nav_link($base.'mentor/materials.php', 'folder', 'Study Materials', $current_page) ?>
                <?= nav_link($base.'mentor/leave.php', 'calendar-check', 'Leave', $current_page) ?>
            <?php elseif ($role === 'learner'): ?>
                <?= nav_link($base.'learner/dashboard.php', 'grid-1x2', 'Dashboard', $current_page) ?>
                <?= nav_link($base.'learner/assignments.php', 'file-earmark-text', 'Assignments', $current_page) ?>
                <?= nav_link($base.'learner/attendance.php', 'calendar3', 'Attendance', $current_page) ?>
                <?= nav_link($base.'learner/materials.php', 'folder', 'Study Materials', $current_page) ?>
                <?= nav_link($base.'learner/notifications.php', 'bell', 'Notifications', $current_page) ?>
                <?= nav_link($base.'learner/leave.php', 'calendar-check', 'Leave Request', $current_page) ?>
            <?php endif; ?>
        </ul>
        <div class="p-4 bg-light bg-opacity-10 border-top border-opacity-10">
            <a href="<?= $base ?>logout.php" class="btn btn-danger w-100 rounded-pill shadow-sm"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </div>
</div>
