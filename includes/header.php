<?php
// includes/header.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Event Management System') ?> – SUYOGYA SACCOS</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/img/logo.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Nepali Datepicker CSS (local) -->
    <link href="<?= BASE_URL ?>assets/css/vendor/nepaliDatePicker.min.css" rel="stylesheet" type="text/css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">

    <!-- Sidebar overlay and mobile styles are in assets/css/style.css -->
</head>

<body>
    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid p-0" style="height:100vh; overflow:hidden;">
        <div class="row g-0" style="height:100%; flex-wrap:nowrap;">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar d-none d-md-block" id="mainSidebar" style="height:100vh; overflow-y:auto; flex-shrink:0;">
                <div class="sidebar-brand text-center mb-4">
                    <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" class="mb-2 sidebar-brand-logo"
                        style="height: 42px; filter: brightness(0) invert(1);">
                    <h6 class="text-white fw-bold tracking-wider mb-0">SUYOGYA SACCOS</h6>
                    <span
                        class="badge bg-white bg-opacity-10 text-white-50 small px-2 py-1 mt-2 d-inline-block border border-white border-opacity-10">Admin
                        Panel</span>
                </div>
                <hr class="sidebar-divider my-3 opacity-25">
                <div class="sidebar-nav">
                    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                    <a href="<?= BASE_URL ?>admin/dashboard.php"
                        class="nav-link-custom <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt me-3"></i> <span>Dashboard</span>
                    </a>
                    <a href="<?= BASE_URL ?>admin/events.php"
                        class="nav-link-custom <?= $currentPage == 'events.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-alt me-3"></i> <span>Events</span>
                    </a>
                    <a href="<?= BASE_URL ?>admin/members.php"
                        class="nav-link-custom <?= ($currentPage == 'members.php' || $currentPage == 'add_member.php' || $currentPage == 'edit_member.php') ? 'active' : '' ?>">
                        <i class="fas fa-users me-3"></i> <span>Members</span>
                    </a>
                    <a href="<?= BASE_URL ?>admin/attendance.php"
                        class="nav-link-custom <?= $currentPage == 'attendance.php' ? 'active' : '' ?>">
                        <i class="fas fa-clipboard-check me-3"></i> <span>Attendance</span>
                    </a>
                    <a href="<?= BASE_URL ?>admin/agenda.php"
                        class="nav-link-custom <?= $currentPage == 'agenda.php' ? 'active' : '' ?>">
                        <i class="fas fa-list-ul me-3"></i> <span>Agenda</span>
                    </a>
                    <a href="<?= BASE_URL ?>admin/speakers.php"
                        class="nav-link-custom <?= $currentPage == 'speakers.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-tie me-3"></i> <span>Speakers</span>
                    </a>
                    <a href="<?= BASE_URL ?>admin/reports.php"
                        class="nav-link-custom <?= $currentPage == 'reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar me-3"></i> <span>Reports</span>
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="<?= BASE_URL ?>admin/users.php"
                        class="nav-link-custom <?= ($currentPage == 'users.php' || $currentPage == 'add_user.php' || $currentPage == 'edit_user.php') ? 'active' : '' ?>">
                        <i class="fas fa-user-shield me-3"></i> <span>Users</span>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="sidebar-footer mt-auto pt-4">
                    <a href="<?= BASE_URL ?>logout.php" class="nav-link-custom text-danger-custom">
                        <i class="fas fa-sign-out-alt me-3"></i> <span>Logout</span>
                    </a>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-10 col-12" style="height:100vh; overflow-y:auto;">
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-4 py-3 sticky-top">
                    <div class="container-fluid">
                        <!-- Mobile hamburger -->
                        <button class="btn btn-outline-secondary d-md-none me-3" id="sidebarToggle"
                            aria-label="Open menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <span class="navbar-brand mb-0 h4 fw-bold text-primary d-flex align-items-center">
                            <img src="<?= BASE_URL ?>assets/img/header.png" alt="Logo" height="55"
                                class="me-2 d-inline-block align-text-top ">
                            <span class="d-none d-sm-inline">EVENT MANAGEMENT PANEL</span>
                        </span>
                        <div class="d-flex align-items-center ms-auto">
                            <span class="me-3 fw-medium d-none d-sm-inline">Welcome,
                                <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
                                <span class="badge ms-1 text-capitalize" style="font-size: 0.7rem; background: var(--primary); color: #fff; border-radius: 5px;">
                                    <?= htmlspecialchars($_SESSION['admin_role'] ?? 'admin') ?>
                                </span>
                            </span>
                            <i class="fas fa-user-circle text-success fs-3"></i>
                        </div>
                    </div>
                </nav>
                <div class="main-content">