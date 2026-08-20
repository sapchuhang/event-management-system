<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$event_id = $_GET['event_id'] ?? null;

$event = null;
if ($event_id) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
}

$all_events = [];
$stmt_all = $pdo->query("SELECT id, title, event_date, location, status FROM events ORDER BY event_date DESC, id DESC");
$all_events = $stmt_all->fetchAll();

// ---------------------------------------------------------
// Pagination, Sorting, and Filtering parameters
// ---------------------------------------------------------
$allowedSortColumns = ['member_no', 'full_name', 'contact', 'page_number', 'table_no', 'file_number', 'attended_at'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'attended_at';
$dir = isset($_GET['dir']) ? (strtolower($_GET['dir']) === 'desc' ? 'desc' : 'asc') : (isset($_GET['sort']) ? 'asc' : 'desc');

$allowedPerPages = [10, 25, 50, 100, 250, 500];
$perPage = isset($_GET['per_page']) && in_array((int) $_GET['per_page'], $allowedPerPages) ? (int) $_GET['per_page'] : 25;

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? ''; // 'present', 'absent', or ''
$pageFilter = $_GET['page_number'] ?? ''; // Register physical page filter

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$members = [];
$datalistMembers = [];
$uniquePageNumbers = [];
$totalRecords = 0;
$totalPages = 1;

if ($event) {
    $restrictedTables = getRestrictedTables();

    // 1. Fetch active members for the autocomplete datalist (lightweight query)
    if ($restrictedTables !== null) {
        if (empty($restrictedTables)) {
            $stmtDatalist = $pdo->prepare("SELECT member_no, full_name FROM members WHERE 1=0");
            $stmtDatalist->execute();
        } else {
            $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
            $stmtDatalist = $pdo->prepare("SELECT member_no, full_name FROM members WHERE status = 'active' AND table_no IN ($inClause) ORDER BY full_name ASC");
            $stmtDatalist->execute($restrictedTables);
        }
    } else {
        $stmtDatalist = $pdo->prepare("SELECT member_no, full_name FROM members WHERE status = 'active' ORDER BY full_name ASC");
        $stmtDatalist->execute();
    }
    $datalistMembers = $stmtDatalist->fetchAll();

    // 2. Fetch unique physical register page numbers for filtering
    if ($restrictedTables !== null) {
        if (empty($restrictedTables)) {
            $stmtPages = $pdo->prepare("SELECT DISTINCT page_number FROM members WHERE 1=0");
            $stmtPages->execute();
        } else {
            $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
            $stmtPages = $pdo->prepare("SELECT DISTINCT page_number FROM members WHERE page_number IS NOT NULL AND page_number != '' AND table_no IN ($inClause) ORDER BY CAST(page_number AS UNSIGNED) ASC, page_number ASC");
            $stmtPages->execute($restrictedTables);
        }
    } else {
        $stmtPages = $pdo->query("SELECT DISTINCT page_number FROM members WHERE page_number IS NOT NULL AND page_number != '' ORDER BY CAST(page_number AS UNSIGNED) ASC, page_number ASC");
    }
    $uniquePageNumbers = $stmtPages->fetchAll(PDO::FETCH_COLUMN);

    // 3. Build query filters
    $queryParts = [];
    $params = [$event_id];

    if ($restrictedTables !== null) {
        if (empty($restrictedTables)) {
            $queryParts[] = "1=0";
        } else {
            $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
            $queryParts[] = "m.table_no IN ($inClause)";
            foreach ($restrictedTables as $tbl) {
                $params[] = $tbl;
            }
        }
    }

    if ($search !== '') {
        $queryParts[] = "(m.full_name LIKE ? OR m.member_no LIKE ? OR m.contact LIKE ? OR m.page_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($statusFilter !== '') {
        if ($statusFilter === 'present') {
            $queryParts[] = "a.attended_at IS NOT NULL";
        } elseif ($statusFilter === 'absent') {
            $queryParts[] = "a.attended_at IS NULL";
        }
    }

    if ($pageFilter !== '') {
        $queryParts[] = "m.page_number = ?";
        $params[] = $pageFilter;
    }

    $whereClause = "";
    if (!empty($queryParts)) {
        $whereClause = "AND " . implode(" AND ", $queryParts);
    }

    // 4. Construct Order By clause
    $orderBy = "ORDER BY ";
    if ($sort === 'page_number') {
        $orderBy .= "CAST(m.page_number AS UNSIGNED) {$dir}, m.page_number {$dir}, m.full_name ASC";
    } elseif ($sort === 'attended_at') {
        $orderBy .= "a.attended_at {$dir}, m.full_name ASC";
    } else {
        $orderBy .= "m.{$sort} {$dir}";
    }

    // 5. Total count for pagination
    $countSql = "
        SELECT COUNT(*) 
        FROM members m 
        LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
        WHERE 1=1 {$whereClause}
    ";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = $stmtCount->fetchColumn();

    // 6. Paginated records fetch
    $sql = "
        SELECT m.*, a.attended_at 
        FROM members m 
        LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
        WHERE 1=1 {$whereClause}
        {$orderBy}
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();

    $totalPages = max(1, ceil($totalRecords / $perPage));

    // Transportation Allowance configurations
    $allowanceAmount = (float)$event['allowance_amount'];
    $userAllocated = 0.00;
    $userPaid = 0.00;
    $userRemaining = 0.00;

    if ($allowanceAmount > 0) {
        $userId = $_SESSION['admin_id'];
        
        $stmtCash = $pdo->prepare("SELECT COALESCE(allocated_amount, 0.00) FROM staff_event_cash WHERE event_id = ? AND user_id = ?");
        $stmtCash->execute([$event_id, $userId]);
        $userAllocated = (float)$stmtCash->fetchColumn();

        $stmtPaid = $pdo->prepare("SELECT COALESCE(SUM(allowance_paid), 0.00) FROM attendance WHERE event_id = ? AND marked_by = ?");
        $stmtPaid->execute([$event_id, $userId]);
        $userPaid = (float)$stmtPaid->fetchColumn();

        $userRemaining = $userAllocated - $userPaid;
    }
}

// Helpers for sorting and pagination links preserving filter state
function getSortUrl($column, $currentSort, $currentDir, $search, $perPage, $statusFilter, $pageFilter, $event_id)
{
    $nextDir = 'asc';
    if ($currentSort === $column) {
        $nextDir = ($currentDir === 'asc') ? 'desc' : 'asc';
    }
    $params = [
        'event_id' => $event_id,
        'sort' => $column,
        'dir' => $nextDir,
        'per_page' => $perPage,
        'page' => 1
    ];
    if ($search !== '')
        $params['search'] = $search;
    if ($statusFilter !== '')
        $params['status'] = $statusFilter;
    if ($pageFilter !== '')
        $params['page_number'] = $pageFilter;
    return '?' . http_build_query($params);
}

function getSortIcon($column, $currentSort, $currentDir)
{
    if ($currentSort !== $column) {
        return '<i class="fas fa-sort text-muted ms-1 small opacity-50"></i>';
    }
    return $currentDir === 'asc'
        ? '<i class="fas fa-sort-up text-primary ms-1"></i>'
        : '<i class="fas fa-sort-down text-primary ms-1"></i>';
}

function getPageUrl($pageNum, $sort, $dir, $perPage, $search, $statusFilter, $pageFilter, $event_id)
{
    $params = [
        'event_id' => $event_id,
        'page' => $pageNum,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => $perPage
    ];
    if ($search !== '')
        $params['search'] = $search;
    if ($statusFilter !== '')
        $params['status'] = $statusFilter;
    if ($pageFilter !== '')
        $params['page_number'] = $pageFilter;
    return '?' . http_build_query($params);
}

// Generate the CSV export link preserving filters
$exportParams = ['event_id' => $event_id];
if ($search !== '')
    $exportParams['search'] = $search;
if ($statusFilter !== '')
    $exportParams['status'] = $statusFilter;
if ($pageFilter !== '')
    $exportParams['page_number'] = $pageFilter;
$exportUrl = "../actions/export_attendance.php?" . http_build_query($exportParams);

require_once '../includes/header.php';
?>
<style>
@media print {
    .sidebar, #mainSidebar, .sidebar-overlay, .navbar, .sticky-top, .btn, .d-print-none, form, nav {
        display: none !important;
    }
    .container-fluid, .row.g-0 {
        height: auto !important;
        overflow: visible !important;
        flex-wrap: wrap !important;
    }
    .col-md-2 {
        display: none !important;
    }
    .col-md-10, .col-12 {
        width: 100% !important;
        max-width: 100% !important;
        flex: 0 0 100% !important;
        height: auto !important;
        overflow: visible !important;
    }
    .main-content {
        padding: 0 !important;
    }
    .glass-panel {
        box-shadow: none !important;
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
    body {
        background: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Only show details of present members when printing */
    tr.absent-row {
        display: none !important;
    }
}
</style>
<?php if ($event): ?>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h4 class="fw-bold mb-0">Attendance <span class="d-none d-print-inline">-
                    <?= htmlspecialchars($event['title']) ?></span></h4>
            <?php if (!empty($all_events)): ?>
                <select class="form-select form-select-sm d-print-none" style="width: auto; min-width: 250px;"
                    onchange="location = '?event_id=' + this.value;">
                    <?php foreach ($all_events as $a): ?>
                        <option value="<?= htmlspecialchars($a['id']) ?>" <?= $a['id'] == $event_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['title']) ?> (<?= date('Y-m-d', strtotime($a['event_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="d-print-none d-flex gap-2">
            <?php if (isAdmin() && $allowanceAmount > 0): ?>
                <a href="event_cash.php?event_id=<?= $event_id ?>" class="btn btn-outline-primary"><i class="fas fa-coins me-2"></i> Manage Staff Cash</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-outline-success"><i
                    class="fas fa-download me-2"></i> Export CSV</a>
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-2"></i>
                Print</button>
            <a href="events.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back to Events</a>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Attendance Management</h4>
        <a href="events.php" class="btn btn-outline-secondary"><i class="fas fa-calendar-alt me-2"></i> Manage Events</a>
    </div>
<?php endif; ?>

<?php if (!$event): ?>
    <?php if (empty($all_events)): ?>
        <div class="glass-panel p-5 text-center">
            <i class="fas fa-calendar-times text-warning fa-3x mb-3"></i>
            <h4 class="fw-bold">No Events Available</h4>
            <p class="text-muted mb-4">Please create an event first in the Events section to start recording attendance.</p>
            <a href="events.php" class="btn btn-primary-custom"><i class="fas fa-plus me-2"></i> Create Event</a>
        </div>
    <?php else: ?>
        <div class="glass-panel p-5">
            <div class="text-center mb-5">
                <div class="d-inline-flex text-primary bg-primary bg-opacity-10 p-3 rounded-circle mb-3"
                    style="width: 70px; height: 70px; align-items: center; justify-content: center;">
                    <i class="fas fa-clipboard-check fa-2x"></i>
                </div>
                <h3 class="fw-bold text-dark">Select Event for Attendance</h3>
                <p class="text-muted">Choose an event from the active register below to begin marking and managing member
                    attendance.</p>
            </div>

            <div class="row g-4">
                <?php foreach ($all_events as $a):
                    $status = $a['status'] ?? 'upcoming';
                    $statusClasses = [
                        'ongoing' => ['bg-success-subtle text-success border-success-subtle', 'fa-play-circle', 'Ongoing'],
                        'upcoming' => ['bg-info-subtle text-info border-info-subtle', 'fa-calendar-alt', 'Upcoming'],
                        'completed' => ['bg-secondary-subtle text-secondary border-secondary-subtle', 'fa-check-double', 'Completed']
                    ];
                    $style = $statusClasses[$status] ?? $statusClasses['upcoming'];

                    // Fetch current attendance count for this specific event
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE event_id = ?");
                    $stmtCount->execute([$a['id']]);
                    $attnCount = $stmtCount->fetchColumn();
                    ?>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 stat-card p-4 position-relative overflow-hidden"
                            style="border-left: 5px solid var(--secondary) !important;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge border <?= $style[0] ?> px-3 py-2 fw-semibold d-flex align-items-center">
                                    <i class="fas <?= $style[1] ?> me-2"></i> <?= $style[2] ?>
                                </span>
                                <span class="text-muted small fw-medium">
                                    <i class="far fa-calendar-alt me-1"></i> <?= date('M d, Y', strtotime($a['event_date'])) ?>
                                </span>
                            </div>

                            <h5 class="fw-bold text-dark mb-3" style="line-height: 1.45;"><?= htmlspecialchars($a['title']) ?></h5>

                            <p class="text-muted small mb-4 d-flex align-items-center">
                                <i class="fas fa-map-marker-alt text-danger opacity-75 me-2" style="font-size: 1.1rem;"></i>
                                <span><?= !empty($a['location']) ? htmlspecialchars($a['location']) : 'No location specified' ?></span>
                            </p>

                            <div class="mt-auto border-top pt-3 d-flex align-items-center justify-content-between">
                                <div class="small text-muted">
                                    <strong class="text-primary font-monospace"
                                        style="font-size: 1.05rem;"><?= number_format($attnCount) ?></strong> checked-in
                                </div>
                                <a href="?event_id=<?= $a['id'] ?>"
                                    class="btn btn-sm btn-primary-custom px-4 d-inline-flex align-items-center">
                                    Select <i class="fas fa-chevron-right ms-2" style="font-size: 0.75rem;"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>

    <div class="row mb-4 d-print-none">
        <div class="col-md-12">
            <div class="glass-panel p-4">
                <?php if ($allowanceAmount > 0): 
                    $floatClass = ($userRemaining >= $allowanceAmount) ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle';
                ?>
                    <div class="alert <?= $floatClass ?> border d-flex justify-content-between align-items-center mb-3 py-2 px-3 small" id="cashFloatAlert">
                        <div>
                            <i class="fas fa-wallet me-2"></i>
                            Transportation Allowance: <strong>NPR <?= number_format($allowanceAmount, 2) ?> per member</strong>
                        </div>
                        <div class="text-end">
                            Your Cash Float: <strong class="font-monospace fs-6" id="remainingCashDisplay">NPR <?= number_format($userRemaining, 2) ?></strong> / NPR <?= number_format($userAllocated, 2) ?> remaining
                        </div>
                    </div>
                <?php endif; ?>
                <form id="attendanceForm" action="../actions/mark_attendance.php" method="POST"
                    class="d-flex align-items-end gap-3 flex-wrap">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <div class="flex-grow-1" style="min-width: 250px;">
                        <label class="form-label fw-medium">Search Member by Name or No.</label>
                        <input list="memberList" id="memberSearch" name="member_input" class="form-control"
                            placeholder="Start typing name or member no..." required autofocus autocomplete="off">
                        <datalist id="memberList">
                            <?php foreach ($datalistMembers as $m): ?>
                                <option value="<?= htmlspecialchars($m['member_no'] . ' - ' . $m['full_name']) ?>">
                                <?php endforeach; ?>
                        </datalist>
                    </div>
                    <button type="submit" class="btn btn-success px-4"><i class="fas fa-check-circle me-2"></i> Mark</button>
                    <button type="button" id="btn-scan-qr" class="btn btn-primary-custom px-4 d-flex align-items-center gap-2"><i class="fas fa-camera"></i> Scan QR Code</button>
                </form>
                
                <div id="qr-scanner-card" class="mt-4 d-none border border-light rounded p-4 text-center position-relative" style="background: rgba(255, 255, 255, 0.4); box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08); backdrop-filter: blur(10px); border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.18);">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-video me-2"></i>Live QR Code Scanner</h6>
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check form-switch text-start">
                                <input class="form-check-input" type="checkbox" id="fastCheckIn" checked>
                                <label class="form-check-label small fw-medium text-dark" for="fastCheckIn">Fast Check-In (Skip Popups)</label>
                            </div>
                            <button type="button" id="btn-close-scanner" class="btn-close" aria-label="Close"></button>
                        </div>
                    </div>
                    <div id="scanner-reader" class="mx-auto" style="width: 100%; max-width: 480px; border-radius: 8px; overflow: hidden; border: 1px solid #ccc;"></div>
                    <div class="text-muted small mt-2"><i class="fas fa-info-circle me-1"></i>Align the QR code within the camera viewfinder to scan.</div>
                </div>

                <div id="ajaxMessages" class="mt-3"></div>
            </div>
        </div>
    </div>

    <div class="glass-panel p-4">
        <!-- Table Filters Row (d-print-none) -->
        <form method="GET" action="" class="row g-3 mb-4 align-items-center d-print-none">
            <input type="hidden" name="event_id" value="<?= htmlspecialchars($event_id) ?>">
            <?php if ($sort !== 'page_number'): ?>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <?php endif; ?>
            <?php if ($dir !== 'asc'): ?>
                <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
            <?php endif; ?>

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-medium text-muted mb-1">Search Table</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, No, Contact..."
                    value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-medium text-muted mb-1">Register Page No.</label>
                <select name="page_number" class="form-select form-select-sm">
                    <option value="">-- All Pages --</option>
                    <?php foreach ($uniquePageNumbers as $pNum): ?>
                        <option value="<?= htmlspecialchars($pNum) ?>" <?= $pNum === $pageFilter ? 'selected' : '' ?>>Page
                            <?= htmlspecialchars($pNum) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-medium text-muted mb-1">Attendance Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                </select>
            </div>

            <div class="col-md-2 col-sm-4">
                <label class="form-label small fw-medium text-muted mb-1">Per Page</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ($allowedPerPages as $val): ?>
                        <option value="<?= $val ?>" <?= $val == $perPage ? 'selected' : '' ?>><?= $val ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-1 col-sm-2 d-flex align-items-end gap-1 mt-4">
                <button type="submit" class="btn btn-sm btn-primary-custom px-3" title="Apply Filter"><i
                        class="fas fa-filter"></i></button>
                <?php if ($search !== '' || $statusFilter !== '' || $pageFilter !== ''): ?>
                    <a href="?event_id=<?= $event_id ?>" class="btn btn-sm btn-outline-secondary" title="Clear Filters"><i
                            class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                Showing <?= count($members) ?> of <strong><?= number_format($totalRecords) ?></strong> members
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>
                            <a href="<?= getSortUrl('member_no', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>Member No.</span>
                                <?= getSortIcon('member_no', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('full_name', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>Full Name</span>
                                <?= getSortIcon('full_name', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('contact', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>Contact</span>
                                <?= getSortIcon('contact', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('page_number', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>Page No.</span>
                                <?= getSortIcon('page_number', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('table_no', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>Table No.</span>
                                <?= getSortIcon('table_no', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('file_number', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>File No.</span>
                                <?= getSortIcon('file_number', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('attended_at', $sort, $dir, $search, $perPage, $statusFilter, $pageFilter, $event_id) ?>"
                                class="text-decoration-none text-dark d-flex align-items-center justify-content-between">
                                <span>Attendance Status</span>
                                <?= getSortIcon('attended_at', $sort, $dir) ?>
                            </a>
                        </th>
                        <th>Time</th>
                        <?php if ($allowanceAmount > 0): ?>
                        <th>Allowance</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): 
                        $rowClass = $member['attended_at'] ? 'present-row' : 'absent-row';
                    ?>
                        <tr id="row-<?= $member['id'] ?>" class="<?= $rowClass ?>">
                            <td><?= htmlspecialchars($member['member_no']) ?></td>
                            <td class="fw-medium"><?= htmlspecialchars($member['full_name']) ?></td>
                            <td><?= htmlspecialchars($member['contact']) ?></td>
                            <td><span class="badge bg-secondary">Page
                                     <?= htmlspecialchars($member['page_number'] ?? '-') ?></span></td>
                            <td><span class="text-secondary"><?= htmlspecialchars($member['table_no'] ?? '-') ?></span></td>
                            <td><span class="text-secondary"><?= htmlspecialchars($member['file_number'] ?? '-') ?></span></td>
                            <td class="status-cell">
                                <?php if ($member['attended_at']): ?>
                                    <span class="badge bg-success mb-1"><i class="fas fa-check me-1"></i> Present</span><br>
                                    <a href="#" class="text-danger small text-decoration-none unmark-btn d-print-none"
                                        data-member-id="<?= $member['id'] ?>"
                                        data-name="<?= htmlspecialchars($member['full_name']) ?>"><i
                                            class="fas fa-undo me-1"></i>Undo</a>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Absent</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small time-cell">
                                <?= $member['attended_at'] ? date('h:i A', strtotime($member['attended_at'])) : '-' ?>
                            </td>
                            <?php if ($allowanceAmount > 0): ?>
                            <td class="allowance-cell font-monospace fw-medium text-success">
                                <?= $member['attended_at'] ? 'NPR ' . number_format($member['allowance_paid'] ?? $allowanceAmount, 2) : '-' ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="<?= $allowanceAmount > 0 ? 9 : 8 ?>" class="text-center py-4 text-muted">No attendance records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Web Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4 d-print-none">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="<?= getPageUrl($page - 1, $sort, $dir, $perPage, $search, $statusFilter, $pageFilter, $event_id) ?>">Previous</a>
                    </li>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link"
                                href="<?= getPageUrl($i, $sort, $dir, $perPage, $search, $statusFilter, $pageFilter, $event_id) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link"
                            href="<?= getPageUrl($page + 1, $sort, $dir, $perPage, $search, $statusFilter, $pageFilter, $event_id) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

<?php endif; ?>
<script src="<?= BASE_URL ?>assets/js/vendor/html5-qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const eventId = <?= $event_id ? $event_id : 'null' ?>;

        function showMessage(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
            Toast.fire({
                icon: type === 'danger' ? 'error' : type,
                title: message
            });
        }

        function updateCashFloatDisplay(remaining) {
            const remDisplay = document.getElementById('remainingCashDisplay');
            if (remDisplay) {
                const formatted = parseFloat(remaining).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                remDisplay.textContent = 'NPR ' + formatted;
                
                const alertBox = document.getElementById('cashFloatAlert');
                if (alertBox) {
                    const allowanceVal = <?= $allowanceAmount ?>;
                    if (parseFloat(remaining) < allowanceVal) {
                        alertBox.className = 'alert bg-danger-subtle text-danger border border-danger-subtle d-flex justify-content-between align-items-center mb-3 py-2 px-3 small';
                    } else {
                        alertBox.className = 'alert bg-success-subtle text-success border border-success-subtle d-flex justify-content-between align-items-center mb-3 py-2 px-3 small';
                    }
                }
            }
        }

        // ── QR Scanner Logic ──
        let html5QrCode = null;

        const btnScan = document.getElementById('btn-scan-qr');
        const btnCloseScanner = document.getElementById('btn-close-scanner');
        const scannerCard = document.getElementById('qr-scanner-card');

        if (btnScan) {
            btnScan.addEventListener('click', function() {
                if (scannerCard.classList.contains('d-none')) {
                    scannerCard.classList.remove('d-none');
                    startQrScanner();
                } else {
                    stopQrScanner();
                    scannerCard.classList.add('d-none');
                }
            });
        }

        if (btnCloseScanner) {
            btnCloseScanner.addEventListener('click', function() {
                stopQrScanner();
                scannerCard.classList.add('d-none');
            });
        }

        function playBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
                
                gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);

                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);

                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.15);
            } catch (e) {
                console.error("Audio beep failed: ", e);
            }
        }

        function startQrScanner() {
            html5QrCode = new Html5Qrcode("scanner-reader");
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                console.error("Error starting scanner: ", err);
                showMessage('danger', 'Unable to access camera: ' + err);
                scannerCard.classList.add('d-none');
            });
        }

        function stopQrScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    html5QrCode = null;
                }).catch(err => console.error("Error stopping scanner: ", err));
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            playBeep();
            const scannedMemberNo = decodedText.trim();
            console.log(`Scan result: ${scannedMemberNo}`);

            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.pause(true);
            }

            const isFastCheckIn = document.getElementById('fastCheckIn').checked;

            if (isFastCheckIn) {
                processInstantCheckIn(scannedMemberNo);
            } else {
                processInteractiveCheckIn(scannedMemberNo);
            }
        }

        function onScanFailure(error) {
            // Silent failure is normal since scan runs continuously
        }

        function processInstantCheckIn(memberInput) {
            fetch('../actions/mark_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ event_id: eventId, member_input: memberInput })
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.message || 'Mark failed'); });
                }
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Checked In!',
                        text: `${data.member.full_name} checked in successfully.`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });

                    updateAttendanceTable(data.member);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Scan Error',
                        text: data.message,
                        timer: 2500,
                        showConfirmButton: false
                    });
                }
                
                setTimeout(() => {
                    if (html5QrCode) html5QrCode.resume();
                }, 1500);
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Scan Error',
                    text: err.message || 'An error occurred during instant check-in.',
                    timer: 2500,
                    showConfirmButton: false
                });
                
                setTimeout(() => {
                    if (html5QrCode) html5QrCode.resume();
                }, 1500);
            });
        }

        function processInteractiveCheckIn(memberInput) {
            fetch(`../actions/get_member_details.php?event_id=${eventId}&member_input=${encodeURIComponent(memberInput)}`, {
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.message || 'Member not found'); });
                }
                return res.json();
            })
            .then(data => {
                const member = data.member;

                if (member.attended_at) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Already Marked Present',
                        html: `<strong>${member.full_name}</strong> (No. ${member.member_no}) has already marked attendance.`,
                        confirmButtonColor: '#235857'
                    }).then(() => {
                        if (html5QrCode) html5QrCode.resume();
                    });
                    return;
                }

                Swal.fire({
                    title: 'Verify Member scanned',
                    html: `
                        <div class="text-start mt-2">
                            <table class="table table-striped table-bordered align-middle small mb-0">
                                <tr>
                                    <th class="w-35 text-secondary">Member No.</th>
                                    <td><strong class="text-dark">${member.member_no}</strong></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Full Name</th>
                                    <td><strong class="text-dark">${member.full_name}</strong></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Table No.</th>
                                    <td><span class="fw-medium">${member.table_no || '-'}</span></td>
                                </tr>
                                <tr>
                                    <th class="text-secondary">Register Page</th>
                                    <td>Page ${member.page_number || '-'}</td>
                                </tr>
                                <?php if ($allowanceAmount > 0): ?>
                                <tr>
                                    <th class="text-secondary text-success fw-bold"><i class="fas fa-coins me-1"></i>Allowance</th>
                                    <td><span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">NPR <?= number_format($allowanceAmount, 2) ?></span></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#235857',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Confirm & Mark Present',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('../actions/mark_attendance.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({ event_id: eventId, member_input: memberInput })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Attendance Marked!',
                                    text: `Attendance marked successfully for ${member.full_name}`,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                updateAttendanceTable(data.member);
                                if (data.remaining_cash !== undefined) {
                                    updateCashFloatDisplay(data.remaining_cash);
                                }
                            } else {
                                showMessage('danger', data.message);
                            }
                            if (html5QrCode) html5QrCode.resume();
                        })
                        .catch(err => {
                            showMessage('danger', 'An error occurred while marking attendance.');
                            if (html5QrCode) html5QrCode.resume();
                        });
                    } else {
                        if (html5QrCode) html5QrCode.resume();
                    }
                });
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Scan Error',
                    text: err.message || 'An error occurred while fetching member details.'
                }).then(() => {
                    if (html5QrCode) html5QrCode.resume();
                });
            });
        }

        function updateAttendanceTable(member) {
            let row = document.getElementById('row-' + member.id);
            if (!row) {
                const tbody = document.querySelector('table tbody');
                if (tbody) {
                    // Remove "No attendance records found." row if exists
                    const emptyRow = tbody.querySelector('tr td.text-center');
                    if (emptyRow) {
                        tbody.innerHTML = '';
                    }

                    row = document.createElement('tr');
                    row.id = 'row-' + member.id;
                    row.className = 'present-row';
                    row.innerHTML = `
                        <td>${escapeHtml(member.member_no)}</td>
                        <td class="fw-medium">${escapeHtml(member.full_name)}</td>
                        <td>${escapeHtml(member.contact || '-')}</td>
                        <td><span class="badge bg-secondary">Page ${escapeHtml(member.page_number || '-')}</span></td>
                        <td><span class="text-secondary">${escapeHtml(member.table_no || '-')}</span></td>
                        <td><span class="text-secondary">${escapeHtml(member.file_number || '-')}</span></td>
                        <td class="status-cell">
                            <span class="badge bg-success mb-1"><i class="fas fa-check me-1"></i> Present</span><br>
                            <a href="#" class="text-danger small text-decoration-none unmark-btn d-print-none" 
                               data-member-id="${member.id}" 
                               data-name="${escapeHtml(member.full_name)}"><i class="fas fa-undo me-1"></i>Undo</a>
                        </td>
                        <td class="text-muted small time-cell">${member.attended_at}</td>
                        <?php if ($allowanceAmount > 0): ?>
                        <td class="allowance-cell font-monospace fw-medium text-success">NPR <?= number_format($allowanceAmount, 2) ?></td>
                        <?php endif; ?>
                    `;
                    tbody.insertBefore(row, tbody.firstChild);
                }
            } else {
                row.className = 'present-row';
                row.querySelector('.status-cell').innerHTML = `
                    <span class="badge bg-success mb-1"><i class="fas fa-check me-1"></i> Present</span><br>
                    <a href="#" class="text-danger small text-decoration-none unmark-btn d-print-none" data-member-id="${member.id}" data-name="${member.full_name}"><i class="fas fa-undo me-1"></i>Undo</a>
                `;
                row.querySelector('.time-cell').textContent = member.attended_at;

                <?php if ($allowanceAmount > 0): ?>
                const allowanceCell = row.querySelector('.allowance-cell');
                if (allowanceCell) {
                    allowanceCell.textContent = 'NPR <?= number_format($allowanceAmount, 2) ?>';
                }
                <?php endif; ?>

                // Bring the row to the top of the table
                const tbody = row.parentElement;
                tbody.insertBefore(row, tbody.firstChild);
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        const form = document.getElementById('attendanceForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const memberInput = document.getElementById('memberSearch').value;

                // 1. Fetch member details first
                fetch(`../actions/get_member_details.php?event_id=${eventId}&member_input=${encodeURIComponent(memberInput)}`, {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(err => { throw new Error(err.message || 'Member not found'); });
                        }
                        return res.json();
                    })
                    .then(data => {
                        const member = data.member;

                        // 2. Check if already marked present
                        if (member.attended_at) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Already Marked Present',
                                html: `<strong>${member.full_name}</strong> (No. ${member.member_no}) has already marked attendance at <strong>${member.attended_at}</strong>.`,
                                confirmButtonColor: '#235857'
                            });
                            return;
                        }

                        // 3. Show confirmation dialog
                        Swal.fire({
                            title: 'Confirm Member Details',
                            html: `
                            <div class="text-start mt-2">
                                <p class="mb-3 text-center text-muted small">Please verify the member details before marking attendance.</p>
                                <table class="table table-striped table-bordered align-middle small mb-0">
                                    <tr>
                                        <th class="w-35 text-secondary">Member No.</th>
                                        <td><strong class="text-dark">${member.member_no}</strong></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary">Full Name</th>
                                        <td><strong class="text-dark">${member.full_name}</strong></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary">Contact</th>
                                        <td>${member.contact || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary">Register Page</th>
                                        <td><span class="badge bg-secondary px-2 py-1">Page ${member.page_number || '-'}</span></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary">Table No.</th>
                                        <td><span class="fw-medium">${member.table_no || '-'}</span></td>
                                    </tr>
                                    <tr>
                                        <th class="text-secondary">File No.</th>
                                        <td>${member.file_number || '-'}</td>
                                    </tr>
                                    <?php if ($allowanceAmount > 0): ?>
                                    <tr>
                                        <th class="text-secondary text-success fw-bold"><i class="fas fa-coins me-1"></i>Allowance</th>
                                        <td><span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">NPR <?= number_format($allowanceAmount, 2) ?></span></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        `,
                            showCancelButton: true,
                            confirmButtonColor: '#235857',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Confirm & Mark Present',
                            cancelButtonText: 'Cancel',
                            focusConfirm: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // 4. Mark present
                                fetch('../actions/mark_attendance.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({ event_id: eventId, member_input: memberInput })
                                })
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.success) {
                                            // SweetAlert success toast instead of standard alert
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Attendance Marked!',
                                                text: `Attendance marked successfully for ${member.full_name}`,
                                                timer: 2000,
                                                showConfirmButton: false
                                            });

                                            document.getElementById('memberSearch').value = '';
                                            document.getElementById('memberSearch').focus();

                                            // Update table row dynamically if it exists on screen
                                            const row = document.getElementById('row-' + member.id);
                                            if (row) {
                                                row.className = 'present-row';
                                                row.querySelector('.status-cell').innerHTML = `
                                            <span class="badge bg-success mb-1"><i class="fas fa-check me-1"></i> Present</span><br>
                                            <a href="#" class="text-danger small text-decoration-none unmark-btn d-print-none" data-member-id="${member.id}" data-name="${member.full_name}"><i class="fas fa-undo me-1"></i>Undo</a>
                                        `;
                                                row.querySelector('.time-cell').textContent = data.member.attended_at;

                                                // Bring the row to the absolute top of the table body immediately!
                                                const tbody = row.parentElement;
                                                tbody.insertBefore(row, tbody.firstChild);
                                            }
                                            if (data.remaining_cash !== undefined) {
                                                updateCashFloatDisplay(data.remaining_cash);
                                            }
                                        } else {
                                            showMessage('danger', data.message);
                                        }
                                    })
                                    .catch(err => {
                                        showMessage('danger', 'An error occurred while marking attendance.');
                                    });
                            }
                        });
                    })
                    .catch(err => {
                        showMessage('danger', err.message || 'An error occurred while fetching member details.');
                    });
            });
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('.unmark-btn')) {
                e.preventDefault();
                const btn = e.target.closest('.unmark-btn');
                const memberId = btn.getAttribute('data-member-id');
                const memberName = btn.getAttribute('data-name');

                if (confirm(`Are you sure you want to unmark attendance for ${memberName}?`)) {
                    fetch('../actions/unmark_attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ event_id: eventId, member_id: memberId })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                showMessage('success', data.message);
                                // Update table row
                                const row = document.getElementById('row-' + memberId);
                                if (row) {
                                    row.className = 'absent-row';
                                    row.querySelector('.status-cell').innerHTML = `<span class="badge bg-danger"><i class="fas fa-times me-1"></i> Absent</span>`;
                                    row.querySelector('.time-cell').textContent = '-';
                                }
                            } else {
                                showMessage('danger', data.message);
                            }
                        })
                        .catch(err => {
                            showMessage('danger', 'An error occurred while unmarking attendance.');
                        });
                }
            }
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>