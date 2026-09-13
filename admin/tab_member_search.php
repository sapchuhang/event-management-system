<?php
// admin/tab_member_search.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Fetch events for context selector
$eventsStmt = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC, id DESC");
$events = $eventsStmt->fetchAll();
$selectedEventId = !empty($_GET['event_id']) ? (int)$_GET['event_id'] : ($events[0]['id'] ?? 0);

// Fetch unique tables for quick filter
$tablesStmt = $pdo->query("SELECT DISTINCT table_no FROM members WHERE table_no IS NOT NULL AND table_no != '' ORDER BY CAST(table_no AS UNSIGNED) ASC, table_no ASC");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Member Search & Detail (Tab Device)';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> – SUYOGYA SACCOS</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/img/logo.png">

    <!-- Plus Jakarta Sans Font (Local) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/plus-jakarta-sans-local.css">

    <!-- Bootstrap CSS (Local) -->
    <link href="<?= BASE_URL ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (Local) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <!-- SweetAlert2 (Local) -->
    <script src="<?= BASE_URL ?>assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <!-- QR Code Generator -->
    <script src="<?= BASE_URL ?>assets/js/vendor/qrcode.min.js"></script>
    <!-- HTML5 QR Scanner -->
    <script src="<?= BASE_URL ?>assets/js/vendor/html5-qrcode.min.js"></script>

    <style>
        :root {
            --brand-teal: #083844;
            --brand-teal-light: #0d5c6f;
            --brand-accent: #0d9488;
            --brand-accent-subtle: rgba(13, 148, 136, 0.12);
            --surface: #ffffff;
            --bg-page: #f1f5f9;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* ── Tablet Kiosk Header ───────────────────────── */
        .tab-kiosk-header {
            background: linear-gradient(135deg, #083844 0%, #0c4d5d 100%);
            color: #ffffff;
            padding: 0.85rem 1.25rem;
            box-shadow: 0 4px 20px rgba(8, 56, 68, 0.15);
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .brand-logo-img {
            height: 42px;
            filter: brightness(0) invert(1);
        }

        .badge-role-viewer {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            letter-spacing: 0.03em;
        }

        .badge-role-admin {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.4);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
        }

        .badge-role-staff {
            background: rgba(14, 165, 233, 0.2);
            color: #7dd3fc;
            border: 1px solid rgba(14, 165, 233, 0.4);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
        }

        /* ── Search Container ─────────────────────────── */
        .tab-search-wrapper {
            position: relative;
            max-width: 820px;
            margin: 0 auto;
        }

        .tab-search-input-group {
            position: relative;
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            border: 2px solid transparent;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 0.35rem 0.75rem;
        }

        .tab-search-input-group:focus-within {
            border-color: var(--brand-accent);
            box-shadow: 0 12px 30px rgba(13, 148, 136, 0.2);
        }

        .tab-search-input {
            width: 100%;
            height: 54px;
            font-size: 1.15rem;
            font-weight: 600;
            border: none;
            outline: none;
            background: transparent;
            padding: 0 0.85rem;
            color: var(--text-primary);
        }

        .tab-search-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
            font-size: 1.05rem;
        }

        .btn-tab-search-clear {
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
            margin-right: 0.5rem;
        }

        .btn-tab-search-clear:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-tab-scan-qr {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            height: 48px;
            padding: 0 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .btn-tab-scan-qr:hover, .btn-tab-scan-qr:active {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        /* ── Autocomplete / Suggestion Dropdown ───────── */
        .tab-suggestions-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.18);
            border: 1px solid var(--border-color);
            max-height: 440px;
            overflow-y: auto;
            z-index: 1050;
            display: none;
            padding: 0.5rem;
        }

        .tab-suggestion-item {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s ease;
            border-bottom: 1px solid #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .tab-suggestion-item:last-child {
            border-bottom: none;
        }

        .tab-suggestion-item:hover, .tab-suggestion-item.active {
            background: #f0fdfa;
            border-color: #ccfbf1;
            transform: translateX(2px);
        }

        .tab-suggestion-name {
            font-size: 1.12rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-suggestion-highlight {
            background-color: #fef08a;
            color: #854d0e;
            padding: 0.05rem 0.2rem;
            border-radius: 4px;
        }

        .tab-suggestion-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }

        .pill-badge {
            font-size: 0.76rem;
            font-weight: 600;
            padding: 0.22rem 0.55rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .pill-member-no {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .pill-table {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
            font-weight: 700;
        }

        .pill-page {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .pill-phone {
            background: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }

        .pill-attended {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        /* ── Detail Card Presentation ────────────────── */
        .member-kiosk-card {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            animation: cardFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .kiosk-hero-header {
            background: linear-gradient(135deg, #083844 0%, #0e4c5c 100%);
            color: #ffffff;
            padding: 1.75rem 2rem;
            position: relative;
        }

        .kiosk-hero-name {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }

        /* ── Giant Featured Metric Tiles for Tabs ────── */
        .kiosk-tile-giant {
            border-radius: 16px;
            padding: 1.25rem 1rem;
            text-align: center;
            border: 2px solid transparent;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .kiosk-tile-table {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #f59e0b;
            color: #92400e;
        }

        .kiosk-tile-table .tile-value {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
            color: #b45309;
            margin: 0.25rem 0;
        }

        .kiosk-tile-page {
            background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
            border-color: #14b8a6;
            color: #0f766e;
        }

        .kiosk-tile-page .tile-value {
            font-size: 2.75rem;
            font-weight: 800;
            line-height: 1;
            color: #0f766e;
            margin: 0.25rem 0;
        }

        .kiosk-tile-file {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-color: #cbd5e1;
            color: #334155;
        }

        .kiosk-tile-file .tile-value {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            color: #1e293b;
            margin: 0.25rem 0;
        }

        .kiosk-tile-sn {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #3b82f6;
            color: #1e40af;
        }

        .kiosk-tile-sn .tile-value {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            color: #1d4ed8;
            margin: 0.25rem 0;
        }

        .tile-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tile-hint {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }

        /* ── Field Info Grid ──────────────────────────── */
        .info-cell {
            padding: 0.85rem 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }

        .info-cell-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.25rem;
        }

        .info-cell-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #0f172a;
        }

        /* ── QR Scanner Card Modal ───────────────────── */
        #scanner-modal-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
        }

        /* ── Empty State ──────────────────────────────── */
        .empty-kiosk-state {
            padding: 4rem 2rem;
            text-align: center;
            background: #ffffff;
            border-radius: 20px;
            border: 2px dashed #cbd5e1;
            margin-top: 1rem;
        }

        /* ── Print Slip Styles ────────────────────────── */
        @media print {
            body * {
                visibility: hidden;
            }
            #printableSlip, #printableSlip * {
                visibility: visible;
            }
            #printableSlip {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 320px;
                padding: 1rem;
                font-family: sans-serif;
            }
        }
    </style>
</head>

<body>
    <!-- Kiosk Header -->
    <header class="tab-kiosk-header">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= BASE_URL ?>assets/img/logo.png" alt="SUYOGYA SACCOS" class="brand-logo-img">
                <div>
                    <h5 class="fw-bold mb-0 text-white tracking-wide" style="letter-spacing: -0.01em;">SUYOGYA SACCOS</h5>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="text-white-50 small d-none d-sm-inline">Member Help Desk &bull; Tablet Kiosk</span>
                        <?php if (isViewer()): ?>
                            <span class="badge-role-viewer"><i class="fas fa-tablet-screen-button me-1"></i>Viewer Role</span>
                        <?php elseif (isAdmin()): ?>
                            <span class="badge-role-admin"><i class="fas fa-user-shield me-1"></i>Admin</span>
                        <?php else: ?>
                            <span class="badge-role-staff"><i class="fas fa-user me-1"></i>Staff</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Fullscreen toggle -->
                <button type="button" id="btnToggleFullscreen" class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 px-3 py-2 rounded-3" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i> <span class="d-none d-md-inline">Fullscreen</span>
                </button>

                <?php if (!isViewer()): ?>
                    <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 px-3 py-2 rounded-3">
                        <i class="fas fa-arrow-left"></i> <span class="d-none d-md-inline">Admin Dashboard</span>
                    </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-danger d-flex align-items-center gap-1 px-3 py-2 rounded-3" title="Sign Out">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-md-inline">Logout</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="container py-4" style="max-width: 980px;">
        <!-- Context / Event Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 px-2">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small fw-bold text-uppercase"><i class="fas fa-calendar-check me-1 text-teal"></i> Active Event:</span>
                <select id="eventSelector" class="form-select form-select-sm fw-semibold shadow-none border-secondary-subtle" style="width: auto; min-width: 200px; border-radius: 8px;">
                    <?php foreach ($events as $evt): ?>
                        <option value="<?= $evt['id'] ?>" <?= $evt['id'] == $selectedEventId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($evt['title']) ?> (<?= date('M d, Y', strtotime($evt['event_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="text-muted small">
                <i class="fas fa-fingerprint me-1 text-primary"></i> Touch-friendly fast search for Tab operators
            </div>
        </div>

        <!-- Giant Tablet Search Bar -->
        <div class="tab-search-wrapper mb-4">
            <div class="tab-search-input-group">
                <i class="fas fa-search fs-4 text-muted ms-2 me-1"></i>
                <input type="text" id="tabSearchInput" class="tab-search-input"
                    placeholder="Type name, member no, phone, or S.N..." autocomplete="off" autofocus>
                
                <button type="button" id="btnClearSearch" class="btn-tab-search-clear d-none" title="Clear Search">
                    <i class="fas fa-times"></i>
                </button>

                <button type="button" id="btnOpenScanner" class="btn-tab-scan-qr" title="Scan QR Code via Camera">
                    <i class="fas fa-qrcode fs-5"></i>
                    <span>Scan QR</span>
                </button>
            </div>

            <!-- Floating Suggestion Panel (Making Suggestions Easier) -->
            <div id="tabSuggestions" class="tab-suggestions-dropdown">
                <!-- Live suggestion items inserted via JavaScript -->
            </div>
        </div>

        <!-- Quick Table Filter Pills -->
        <?php if (!empty($tables)): ?>
            <div class="mb-4 d-flex align-items-center gap-2 overflow-x-auto pb-2 px-1">
                <span class="small fw-bold text-muted text-nowrap"><i class="fas fa-filter me-1"></i> Quick Filter:</span>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 active quick-table-filter" data-table="">All</button>
                <?php foreach (array_slice($tables, 0, 12) as $t): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 quick-table-filter" data-table="<?= htmlspecialchars($t) ?>">
                        Table <?= htmlspecialchars($t) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Member Details Display Area -->
        <div id="memberDetailContainer">
            <!-- Empty state displayed by default -->
            <div class="empty-kiosk-state" id="emptyKioskState">
                <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-search fs-1" style="color: var(--brand-teal);"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Ready to Search Member</h4>
                <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                    Start typing the member's <strong>full name</strong>, <strong>member number</strong>, <strong>mobile number</strong>, or <strong>S.N.</strong> in the search bar above, or tap <strong>Scan QR</strong> to scan their QR pass.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill"><i class="fas fa-check text-success me-1"></i> Instant name suggestions</span>
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill"><i class="fas fa-chair text-warning me-1"></i> Instant table number lookup</span>
                    <span class="badge bg-white text-secondary border px-3 py-2 rounded-pill"><i class="fas fa-book text-info me-1"></i> Signature book page number</span>
                </div>
            </div>

            <!-- Member Detail Card (Hidden initially, shown when member selected) -->
            <div class="member-kiosk-card d-none" id="activeMemberCard">
                <!-- Hero Header -->
                <div class="kiosk-hero-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-2 py-1 rounded-pill small" id="detailMemberNo">
                                    No. #0000
                                </span>
                                <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-2 py-1 rounded-pill small" id="detailSn">
                                    S.N. 000
                                </span>
                                <span class="badge bg-success bg-opacity-75 text-white px-2 py-1 rounded-pill small" id="detailStatus">
                                    Active
                                </span>
                            </div>
                            <h2 class="kiosk-hero-name" id="detailFullName">Member Full Name</h2>
                            <div class="text-white-50 d-flex align-items-center gap-3 flex-wrap small">
                                <span><i class="fas fa-venus-mars me-1"></i> <span id="detailGender">Male</span></span>
                                <span><i class="fas fa-phone me-1"></i> <span id="detailContact">9841000000</span></span>
                            </div>
                        </div>

                        <!-- Top Action Buttons -->
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" id="btnNextSearch" class="btn btn-light text-dark fw-bold px-3 py-2 rounded-3 d-flex align-items-center gap-2 shadow-sm">
                                <i class="fas fa-plus"></i>
                                <span>Next Member</span>
                            </button>
                            <button type="button" id="btnPrintSlip" class="btn btn-outline-light px-3 py-2 rounded-3 d-flex align-items-center gap-2">
                                <i class="fas fa-print"></i>
                                <span>Print Slip</span>
                            </button>
                        </div>
                    </div>

                    <!-- Attendance Status Banner -->
                    <div class="mt-3 p-2 px-3 rounded-3 d-flex align-items-center gap-2 small" id="detailAttendanceBanner" style="background: rgba(255, 255, 255, 0.15);">
                        <i class="fas fa-info-circle"></i>
                        <span id="detailAttendanceText">Attendance status</span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-4 bg-white">
                    <!-- Featured Event Routing Tiles (Table, Page, File, SN) -->
                    <h6 class="text-uppercase fw-bold text-muted small tracking-wider mb-3">Event Seating & Register Information</h6>
                    <div class="row g-3 mb-4">
                        <!-- Table No -->
                        <div class="col-6 col-md-3">
                            <div class="kiosk-tile-giant kiosk-tile-table">
                                <span class="tile-label"><i class="fas fa-chair me-1"></i> Seating Table</span>
                                <div class="tile-value" id="tileTableNo">—</div>
                                <span class="tile-hint">Direct member here</span>
                            </div>
                        </div>

                        <!-- Page No -->
                        <div class="col-6 col-md-3">
                            <div class="kiosk-tile-giant kiosk-tile-page">
                                <span class="tile-label"><i class="fas fa-book-open me-1"></i> Register Page</span>
                                <div class="tile-value" id="tilePageNo">—</div>
                                <span class="tile-hint">Signature book page</span>
                            </div>
                        </div>

                        <!-- File No -->
                        <div class="col-6 col-md-3">
                            <div class="kiosk-tile-giant kiosk-tile-file">
                                <span class="tile-label"><i class="fas fa-folder me-1"></i> File Number</span>
                                <div class="tile-value" id="tileFileNo">—</div>
                                <span class="tile-hint">Member record file</span>
                            </div>
                        </div>

                        <!-- Serial No -->
                        <div class="col-6 col-md-3">
                            <div class="kiosk-tile-giant kiosk-tile-sn">
                                <span class="tile-label"><i class="fas fa-hashtag me-1"></i> Serial No. (S.N.)</span>
                                <div class="tile-value" id="tileSn">—</div>
                                <span class="tile-hint">Master list serial</span>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Info & QR Code Section -->
                    <div class="row g-4 align-items-center pt-2 border-top">
                        <div class="col-md-8">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="info-cell">
                                        <div class="info-cell-label">Member ID Number</div>
                                        <div class="info-cell-value font-monospace text-primary" id="cellMemberNo">—</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-cell">
                                        <div class="info-cell-label">Contact / Phone</div>
                                        <div class="info-cell-value" id="cellContact">—</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-cell">
                                        <div class="info-cell-label">Membership Status</div>
                                        <div class="info-cell-value text-capitalize" id="cellStatus">—</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-cell">
                                        <div class="info-cell-label">Gender</div>
                                        <div class="info-cell-value" id="cellGender">—</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- On-Screen Member QR Pass -->
                        <div class="col-md-4 text-center border-start ps-md-4">
                            <div class="small fw-bold text-muted mb-2 text-uppercase tracking-wider">Member QR Badge</div>
                            <div id="memberQrCodeContainer" class="d-inline-flex p-2 bg-white border rounded-3 shadow-sm"></div>
                            <div class="text-muted small mt-1 font-monospace" id="qrCaptionText">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- QR Code Camera Scanner Modal -->
    <div class="modal fade" id="scannerModal" tabindex="-1" aria-labelledby="scannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h6 class="modal-title fw-bold" id="scannerModalLabel"><i class="fas fa-camera me-2 text-teal"></i> Camera QR Code Scanner</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center bg-light">
                    <div id="tab-scanner-reader" class="mx-auto rounded-3 overflow-hidden shadow-sm" style="width: 100%; max-width: 380px; min-height: 260px; background: #000;"></div>
                    <div class="text-muted small mt-3">
                        <i class="fas fa-info-circle me-1"></i> Hold the member's QR pass in front of the camera to view details instantly.
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Close Scanner</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Printable Slip Container -->
    <div id="printableSlip" class="d-none">
        <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 12px;">
            <h4 style="margin: 0; font-weight: bold;">SUYOGYA SACCOS</h4>
            <div style="font-size: 11px;">MEMBER SEATING PASS</div>
        </div>
        <div style="margin-bottom: 10px;">
            <div style="font-size: 12px; color: #555;">Name:</div>
            <div id="printName" style="font-size: 18px; font-weight: bold;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div>
                <div style="font-size: 11px; color: #555;">Member No:</div>
                <div id="printMemberNo" style="font-weight: bold; font-size: 14px;"></div>
            </div>
            <div>
                <div style="font-size: 11px; color: #555;">S.N:</div>
                <div id="printSn" style="font-weight: bold; font-size: 14px;"></div>
            </div>
        </div>
        <div style="border: 2px solid #000; padding: 8px; text-align: center; margin: 10px 0; border-radius: 6px;">
            <div style="font-size: 11px; font-weight: bold;">YOUR ASSIGNED TABLE:</div>
            <div id="printTable" style="font-size: 32px; font-weight: 900; line-height: 1.1;"></div>
            <div style="font-size: 10px;">Signature Ledger Page: <strong id="printPage"></strong></div>
        </div>
        <div style="text-align: center; margin-top: 10px;">
            <div id="printQrContainer" style="display: inline-block;"></div>
            <div style="font-size: 9px; margin-top: 4px; color: #555;">Please show this slip at your table.</div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS (Local) -->
    <script src="<?= BASE_URL ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput       = document.getElementById('tabSearchInput');
        const btnClear          = document.getElementById('btnClearSearch');
        const suggestionsBox    = document.getElementById('tabSuggestions');
        const emptyState        = document.getElementById('emptyKioskState');
        const activeCard        = document.getElementById('activeMemberCard');
        const eventSelector     = document.getElementById('eventSelector');
        const btnNextSearch     = document.getElementById('btnNextSearch');
        const btnPrintSlip      = document.getElementById('btnPrintSlip');
        const btnToggleFs       = document.getElementById('btnToggleFullscreen');
        const btnOpenScanner    = document.getElementById('btnOpenScanner');

        let debounceTimer = null;
        let activeIndex = -1;
        let currentSuggestions = [];
        let html5QrScannerInstance = null;
        let qrCodeObj = null;
        let activeMemberData = null;

        // Sound effect on scan / successful selection
        function playSuccessSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08); // A5
                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            } catch (e) {}
        }

        // ── 1. Live Autocomplete & Easier Suggestions ──────────────
        searchInput.addEventListener('input', function () {
            const val = this.value.trim();
            if (val.length > 0) {
                btnClear.classList.remove('d-none');
            } else {
                btnClear.classList.add('d-none');
            }

            clearTimeout(debounceTimer);
            if (val.length === 0) {
                hideSuggestions();
                return;
            }

            debounceTimer = setTimeout(() => {
                fetchSuggestions(val);
            }, 180); // Fast 180ms debounce for responsive typing
        });

        // Clear button
        btnClear.addEventListener('click', function () {
            searchInput.value = '';
            btnClear.classList.add('d-none');
            hideSuggestions();
            searchInput.focus();
        });

        // Fetch suggestions from backend
        function fetchSuggestions(query) {
            const eventId = eventSelector.value;
            fetch(`../actions/search_members_tab.php?q=${encodeURIComponent(query)}&event_id=${eventId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.members && data.members.length > 0) {
                        currentSuggestions = data.members;
                        renderSuggestions(data.members, query);
                    } else {
                        currentSuggestions = [];
                        renderNoResults(query);
                    }
                })
                .catch(err => {
                    console.error("Search error: ", err);
                });
        }

        // Highlight matching substring
        function highlightMatch(text, query) {
            if (!query || !text) return text || '';
            const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${escaped})`, 'gi');
            return text.replace(regex, '<span class="tab-suggestion-highlight">$1</span>');
        }

        // Render the rich, easy-to-read suggestions
        function renderSuggestions(members, query) {
            suggestionsBox.innerHTML = '';
            activeIndex = -1;

            members.forEach((m, idx) => {
                const item = document.createElement('div');
                item.className = 'tab-suggestion-item';
                item.dataset.index = idx;

                const nameHighlighted = highlightMatch(m.full_name, query);
                const noHighlighted = highlightMatch(m.member_no, query);

                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="tab-suggestion-name">
                            <i class="fas fa-user-circle text-teal opacity-75"></i>
                            <span>${nameHighlighted}</span>
                            <span class="badge ${m.status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'} border px-2 py-0 small" style="font-size: 0.65rem;">
                                ${m.status}
                            </span>
                        </div>
                        ${m.is_attended ? '<span class="pill-badge pill-attended"><i class="fas fa-check-circle"></i> Attended (' + m.attended_at + ')</span>' : ''}
                    </div>
                    <div class="tab-suggestion-badges">
                        <span class="pill-badge pill-member-no"><i class="fas fa-id-badge"></i> No. ${noHighlighted}</span>
                        <span class="pill-badge pill-table"><i class="fas fa-chair"></i> Table ${m.table_no || '—'}</span>
                        <span class="pill-badge pill-page"><i class="fas fa-book"></i> Page ${m.page_number || '—'}</span>
                        ${m.sn && m.sn !== '—' ? '<span class="pill-badge pill-page"><i class="fas fa-hashtag"></i> S.N. ' + m.sn + '</span>' : ''}
                        ${m.contact && m.contact !== '—' ? '<span class="pill-badge pill-phone"><i class="fas fa-phone"></i> ' + highlightMatch(m.contact, query) + '</span>' : ''}
                    </div>
                `;

                item.addEventListener('click', function () {
                    selectMember(m);
                });

                suggestionsBox.appendChild(item);
            });

            suggestionsBox.style.display = 'block';
        }

        function renderNoResults(query) {
            suggestionsBox.innerHTML = `
                <div class="p-3 text-center text-muted small">
                    <i class="fas fa-search me-1 opacity-50"></i> No members found matching "<strong>${highlightMatch(query, '')}</strong>".
                </div>
            `;
            suggestionsBox.style.display = 'block';
        }

        function hideSuggestions() {
            suggestionsBox.style.display = 'none';
            activeIndex = -1;
        }

        // Close suggestions on outside click
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                hideSuggestions();
            }
        });

        // ── 2. Keyboard Navigation in Suggestions ──────────────────
        searchInput.addEventListener('keydown', function (e) {
            const items = suggestionsBox.querySelectorAll('.tab-suggestion-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateActiveSuggestion(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateActiveSuggestion(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && currentSuggestions[activeIndex]) {
                    selectMember(currentSuggestions[activeIndex]);
                } else if (currentSuggestions.length > 0) {
                    selectMember(currentSuggestions[0]);
                }
            } else if (e.key === 'Escape') {
                hideSuggestions();
            }
        });

        function updateActiveSuggestion(items) {
            items.forEach((it, idx) => {
                if (idx === activeIndex) {
                    it.classList.add('active');
                    it.scrollIntoView({ block: 'nearest' });
                } else {
                    it.classList.remove('active');
                }
            });
        }

        // ── 3. Displaying Member Details ───────────────────────────
        function selectMember(member) {
            activeMemberData = member;
            playSuccessSound();

            hideSuggestions();
            searchInput.value = `${member.member_no} - ${member.full_name}`;
            btnClear.classList.remove('d-none');

            // Populate Hero Header
            document.getElementById('detailFullName').textContent = member.full_name;
            document.getElementById('detailMemberNo').textContent = `No. ${member.member_no}`;
            document.getElementById('detailSn').textContent = `S.N. ${member.sn || '—'}`;
            document.getElementById('detailStatus').textContent = member.status ? (member.status.charAt(0).toUpperCase() + member.status.slice(1)) : 'Active';
            document.getElementById('detailGender').textContent = member.gender || '—';
            document.getElementById('detailContact').textContent = member.contact || '—';

            // Populate Attendance Banner
            const attBanner = document.getElementById('detailAttendanceBanner');
            const attText = document.getElementById('detailAttendanceText');
            if (member.is_attended) {
                attBanner.className = 'mt-3 p-2 px-3 rounded-3 d-flex align-items-center gap-2 small bg-success bg-opacity-75 text-white';
                attText.innerHTML = `<strong>✓ Present:</strong> Checked in at <strong>${member.attended_at}</strong> (${member.attended_date || ''}) for <em>${member.event_title}</em>`;
            } else {
                attBanner.className = 'mt-3 p-2 px-3 rounded-3 d-flex align-items-center gap-2 small bg-white bg-opacity-15 text-white';
                attText.innerHTML = `<i class="fas fa-clock opacity-75"></i> Not marked present yet for <em>${member.event_title}</em>`;
            }

            // Populate Giant Tiles
            document.getElementById('tileTableNo').textContent = member.table_no || '—';
            document.getElementById('tilePageNo').textContent = member.page_number || '—';
            document.getElementById('tileFileNo').textContent = member.file_number || '—';
            document.getElementById('tileSn').textContent = member.sn || '—';

            // Populate Info Cells
            document.getElementById('cellMemberNo').textContent = member.member_no;
            document.getElementById('cellContact').innerHTML = member.contact && member.contact !== '—' 
                ? `<a href="tel:${member.contact}" class="text-decoration-none text-dark">${member.contact}</a> <button class="btn btn-sm btn-link p-0 ms-1 text-muted" onclick="navigator.clipboard.writeText('${member.contact}')" title="Copy"><i class="far fa-copy"></i></button>`
                : '—';
            document.getElementById('cellStatus').textContent = member.status || 'Active';
            document.getElementById('cellGender').textContent = member.gender || '—';

            // Render QR code
            const qrContainer = document.getElementById('memberQrCodeContainer');
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: member.member_no,
                width: 110,
                height: 110,
                colorDark: "#083844",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
            document.getElementById('qrCaptionText').textContent = member.member_no;

            // Show active card, hide empty state
            emptyState.classList.add('d-none');
            activeCard.classList.remove('d-none');

            // Smoothly scroll into view if needed
            activeCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // ── 4. "Next Member" Reset Button ─────────────────────────
        btnNextSearch.addEventListener('click', function () {
            searchInput.value = '';
            btnClear.classList.add('d-none');
            hideSuggestions();
            searchInput.focus();
            activeCard.classList.add('d-none');
            emptyState.classList.remove('d-none');
        });

        // ── 5. Print Slip Action ──────────────────────────────────
        btnPrintSlip.addEventListener('click', function () {
            if (!activeMemberData) return;
            document.getElementById('printName').textContent = activeMemberData.full_name;
            document.getElementById('printMemberNo').textContent = activeMemberData.member_no;
            document.getElementById('printSn').textContent = activeMemberData.sn || '—';
            document.getElementById('printTable').textContent = activeMemberData.table_no || '—';
            document.getElementById('printPage').textContent = activeMemberData.page_number || '—';

            const printQr = document.getElementById('printQrContainer');
            printQr.innerHTML = '';
            new QRCode(printQr, {
                text: activeMemberData.member_no,
                width: 90,
                height: 90,
                colorDark: "#000000",
                colorLight: "#ffffff"
            });

            window.print();
        });

        // ── 6. Fullscreen Mode Toggle for Tab Kiosk ─────────────────
        btnToggleFs.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log("Fullscreen request failed: ", err);
                });
                btnToggleFs.innerHTML = '<i class="fas fa-compress"></i> <span class="d-none d-md-inline">Exit Fullscreen</span>';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
                btnToggleFs.innerHTML = '<i class="fas fa-expand"></i> <span class="d-none d-md-inline">Fullscreen</span>';
            }
        });

        // ── 7. Quick Table Filter Buttons ─────────────────────────
        document.querySelectorAll('.quick-table-filter').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.quick-table-filter').forEach(b => b.classList.remove('active', 'btn-primary'));
                this.classList.add('active');
                
                const tableVal = this.dataset.table;
                if (tableVal) {
                    searchInput.value = tableVal;
                    btnClear.classList.remove('d-none');
                    fetchSuggestions(tableVal);
                } else {
                    searchInput.value = '';
                    btnClear.classList.add('d-none');
                    hideSuggestions();
                }
            });
        });

        // ── 8. Event Selector Change ──────────────────────────────
        eventSelector.addEventListener('change', function () {
            if (activeMemberData) {
                // Re-fetch member details with the newly selected event
                fetch(`../actions/get_member_details.php?event_id=${this.value}&member_input=${encodeURIComponent(activeMemberData.member_no)}`, {
                    headers: { 'X-CSRF-TOKEN': '<?= generateCsrfToken() ?>' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.member) {
                        selectMember({
                            ...activeMemberData,
                            is_attended: !empty(data.member.attended_at),
                            attended_at: data.member.attended_at ? new Date(data.member.attended_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : null,
                            attended_date: data.member.attended_at ? new Date(data.member.attended_at).toLocaleDateString() : null,
                            event_title: data.member.event_title || 'Selected Event'
                        });
                    }
                });
            }
        });

        // ── 9. Camera QR Code Scanner ─────────────────────────────
        const scannerModalEl = document.getElementById('scannerModal');
        const scannerModal = new bootstrap.Modal(scannerModalEl);

        btnOpenScanner.addEventListener('click', function () {
            scannerModal.show();
        });

        scannerModalEl.addEventListener('shown.bs.modal', function () {
            startCameraScanner();
        });

        scannerModalEl.addEventListener('hidden.bs.modal', function () {
            stopCameraScanner();
        });

        function startCameraScanner() {
            html5QrScannerInstance = new Html5Qrcode("tab-scanner-reader");
            const config = { fps: 10, qrbox: { width: 240, height: 240 } };

            html5QrScannerInstance.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    // Scanned successfully
                    stopCameraScanner();
                    scannerModal.hide();
                    
                    const code = decodedText.trim();
                    searchInput.value = code;
                    btnClear.classList.remove('d-none');

                    // Fetch and select member directly
                    fetch(`../actions/get_member_details.php?event_id=${eventSelector.value}&member_input=${encodeURIComponent(code)}`, {
                        headers: { 'X-CSRF-TOKEN': '<?= generateCsrfToken() ?>' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.member) {
                            const m = data.member;
                            selectMember({
                                id: m.id,
                                sn: m.sn,
                                member_no: m.member_no,
                                full_name: m.full_name,
                                gender: m.gender,
                                contact: m.contact,
                                page_number: m.page_number,
                                table_no: m.table_no,
                                file_number: m.file_number,
                                status: m.status,
                                is_attended: Boolean(m.attended_at),
                                attended_at: m.attended_at ? new Date(m.attended_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : null,
                                event_title: m.event_title || 'Current Event'
                            });
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Member Not Found',
                                text: `No member record found for scanned code: "${code}"`,
                                confirmButtonColor: '#083844'
                            });
                        }
                    })
                    .catch(err => {
                        console.error("Scan lookup error: ", err);
                    });
                },
                (errorMessage) => {
                    // Ongoing frame scanning (normal ignore)
                }
            ).catch(err => {
                console.error("Camera access error: ", err);
                Swal.fire({
                    icon: 'error',
                    title: 'Camera Error',
                    text: 'Unable to access tablet camera. Please ensure camera permissions are enabled.',
                    confirmButtonColor: '#083844'
                });
                scannerModal.hide();
            });
        }

        function stopCameraScanner() {
            if (html5QrScannerInstance && html5QrScannerInstance.isScanning) {
                html5QrScannerInstance.stop().then(() => {
                    html5QrScannerInstance = null;
                }).catch(err => console.error("Stop error: ", err));
            }
        }
    });
    </script>
</body>
</html>
