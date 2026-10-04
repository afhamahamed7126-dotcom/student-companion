<?php
$role = $_SESSION['role'] ?? '';
$base = BASE_URL;
$role_label = match($role) { 'admin' => 'HOD', 'mentor' => 'Mentor', default => 'Student' };
$role_badge = match($role) { 'admin' => 'danger', 'mentor' => 'warning', default => 'primary' };
?>
<div class="content-wrapper flex-grow-1 d-flex flex-column">
<nav class="navbar navbar-expand-md sticky-top px-4 py-2 border-bottom-0 shadow-sm animate__animated animate__fadeInDown">
    <!-- Hamburger for mobile -->
    <button class="btn btn-sm border-0 d-md-none me-2 text-muted" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="bi bi-list fs-4"></i>
    </button>

    <div class="d-flex align-items-center">
        <span class="navbar-brand fw-bold d-none d-md-inline mb-0 h5 text-primary tracking-tight"><?= htmlspecialchars($page_title ?? APP_NAME, ENT_QUOTES) ?></span>
        <span class="navbar-brand fw-bold d-md-none mb-0 h5 text-primary"><?= APP_NAME ?></span>
    </div>

    <div class="ms-auto d-flex align-items-center gap-3">
        <!-- Notification Bell -->
        <a href="<?php
            echo match($role) {
                'admin'  => $base.'admin/notifications.php',
                'mentor' => $base.'mentor/dashboard.php',
                default  => $base.'learner/notifications.php',
            };
        ?>" class="btn btn-sm btn-light rounded-circle position-relative shadow-sm p-2" id="notif-btn" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-bell text-secondary fs-6"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notif-badge">0</span>
        </a>

        <!-- Theme Toggle -->
        <button class="btn btn-sm btn-light rounded-circle shadow-sm p-2" onclick="toggleTheme()" title="Toggle theme" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-moon-stars text-secondary fs-6" id="theme-icon"></i>
        </button>

        <div class="vr mx-1 opacity-25"></div>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light rounded-pill shadow-sm d-flex align-items-center gap-2 px-3 py-2 border-0" data-bs-toggle="dropdown" style="background: var(--sidebar-bg);">
                <div class="bg-primary bg-gradient rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 28px; height: 28px; font-size: 0.8rem;">
                    <?= strtoupper(substr($current_user['name'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="fw-medium d-none d-sm-inline"><?= htmlspecialchars($current_user['name'] ?? '', ENT_QUOTES) ?></span>
                <span class="badge bg-<?= $role_badge ?> bg-gradient rounded-pill px-2 py-1 ms-1"><?= $role_label ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 py-2 glass-card">
                <li><a class="dropdown-item py-2 px-4" href="<?= $base ?>change_password.php"><i class="bi bi-key me-2 text-muted"></i>Change Password</a></li>
                <li><hr class="dropdown-divider opacity-10"></li>
                <li><a class="dropdown-item py-2 px-4 text-danger" href="<?= $base ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

