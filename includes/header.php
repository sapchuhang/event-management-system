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

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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

    <!-- Page transition & top progress bar -->
    <style>
        /* ── Top Progress Bar ───────────────────────────────────── */
        #page-progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, #14b8a6, #083844, #14b8a6);
            background-size: 200% 100%;
            z-index: 99999;
            transition: width 0.4s ease;
            animation: progressShimmer 1.4s linear infinite;
            border-radius: 0 2px 2px 0;
            box-shadow: 0 0 10px rgba(20, 184, 166, 0.6);
        }

        @keyframes progressShimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ── Page Transition Overlay ────────────────────────────── */
        #page-loader {
            position: fixed;
            inset: 0;
            background: rgba(8, 56, 68, 0.18);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 99998;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        #page-loader.active {
            opacity: 1;
            pointer-events: all;
        }

        /* ── Spinner inside overlay ─────────────────────────────── */
        #page-loader-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            opacity: 0;
            transition: opacity 0.2s ease 0.1s;
        }

        #page-loader.active #page-loader-spinner {
            opacity: 1;
        }

        .loader-ring {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(20, 184, 166, 0.2);
            border-top-color: #14b8a6;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
        }

        .loader-ring-outer {
            width: 64px;
            height: 64px;
            border: 2px solid rgba(8, 56, 68, 0.12);
            border-bottom-color: #083844;
            border-radius: 50%;
            animation: spin 1.2s linear infinite reverse;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loader-ring-wrap {
            position: relative;
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #083844;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.7;
        }

        /* ── Page fade-in on load ───────────────────────────────── */
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .main-content {
            animation: pageFadeIn 0.35s ease both;
        }
    </style>
</head>

<body>
    <!-- Top progress bar -->
    <div id="page-progress-bar"></div>

    <!-- Page transition overlay -->
    <div id="page-loader">
        <div id="page-loader-spinner">
            <div class="loader-ring-wrap">
                <div class="loader-ring"></div>
                <div class="loader-ring-outer"></div>
            </div>
            <span class="loader-text">Loading…</span>
        </div>
    </div>

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
                            <i class="fas fa-user-circle fs-3" style="color: var(--primary);"></i>
                        </div>
                    </div>
                </nav>
                <div class="main-content">