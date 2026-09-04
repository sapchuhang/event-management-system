<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

// Whitelist and sanitise sorting parameters
$allowedSortColumns = ['sn', 'member_no', 'full_name', 'contact', 'page_number', 'table_no', 'file_number', 'status', 'id'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'id';
$dir = isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc' ? 'asc' : 'desc';

// Whitelist and sanitise page size parameters
$allowedPerPages = [10, 15, 25, 50, 100];
$perPage = isset($_GET['per_page']) && in_array((int) $_GET['per_page'], $allowedPerPages) ? (int) $_GET['per_page'] : 10;

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$restrictedTables = getRestrictedTables();
$whereParts = [];
$params = [];

if ($search) {
    $whereParts[] = "(full_name LIKE ? OR member_no LIKE ? OR contact LIKE ? OR sn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $whereParts[] = "1=0";
    } else {
        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
        $whereParts[] = "table_no IN ($inClause)";
        foreach ($restrictedTables as $tbl) {
            $params[] = $tbl;
        }
    }
}

$whereClause = "";
if (!empty($whereParts)) {
    $whereClause = "WHERE " . implode(" AND ", $whereParts);
}

$countSql = "SELECT COUNT(*) FROM members {$whereClause}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalMembers = $stmtCount->fetchColumn();

$sql = "SELECT * FROM members {$whereClause} ORDER BY {$sort} {$dir} LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$members = $stmt->fetchAll();
$totalPages = max(1, ceil($totalMembers / $perPage));

// Helper to generate sort link URLs preserving other state values
function getSortUrl($column, $currentSort, $currentDir, $search, $perPage)
{
    $nextDir = 'asc';
    if ($currentSort === $column) {
        $nextDir = ($currentDir === 'asc') ? 'desc' : 'asc';
    }

    $params = [
        'sort' => $column,
        'dir' => $nextDir,
        'per_page' => $perPage,
        'page' => 1
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    return '?' . http_build_query($params);
}

// Helper to render sort arrows/icons elegantly
function getSortIcon($column, $currentSort, $currentDir)
{
    if ($currentSort !== $column) {
        return '<i class="fas fa-sort text-muted ms-1 small opacity-50"></i>';
    }
    return $currentDir === 'asc'
        ? '<i class="fas fa-sort-up text-primary ms-1"></i>'
        : '<i class="fas fa-sort-down text-primary ms-1"></i>';
}

// Helper to generate pagination URLs preserving all active state values
function getPageUrl($pageNum, $sort, $dir, $perPage, $search)
{
    $params = [
        'page' => $pageNum,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => $perPage
    ];
    if ($search !== '') {
        $params['search'] = $search;
    }
    return '?' . http_build_query($params);
}

$pageTitle = 'Members';
require_once '../includes/header.php';
?>
<style>
    .members-table-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    /* ── Toolbar ─────────────────────────────────────── */
    .members-toolbar {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: #fff;
    }

    /* ── Table Head ──────────────────────────────────── */
    .members-table thead th {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-muted);
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
        padding: 0.75rem 0.75rem;
        vertical-align: middle;
    }

    /* ── Table Body ──────────────────────────────────── */
    .members-table tbody td {
        padding: 0.75rem 0.75rem;
        border-color: var(--border);
        vertical-align: middle;
        font-size: 0.875rem;
    }

    /* First & last column edge insets */
    .members-table th:first-child,
    .members-table td:first-child {
        padding-left: 1.5rem !important;
    }

    .members-table th:last-child,
    .members-table td:last-child {
        padding-right: 1.5rem !important;
    }

    /* ── Sort links ──────────────────────────────────── */
    .members-table thead th a {
        color: var(--text-muted) !important;
        transition: color 0.15s ease;
        text-decoration: none;
    }

    .members-table thead th a:hover {
        color: var(--primary) !important;
    }

    .members-table thead th a:hover i {
        opacity: 1 !important;
    }

    /* ── Row hover ───────────────────────────────────── */
    .members-table tbody tr:hover td {
        background-color: rgba(20, 184, 166, 0.04);
    }
</style>

<div class="page-header mb-4">
    <h4>Members</h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="../actions/export_members.php" class="btn btn-outline-success">
            <i class="fas fa-download me-1"></i> Export CSV
        </a>
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
        <a href="add_member.php" class="btn btn-primary-custom">
            <i class="fas fa-plus me-1"></i> Add Member
        </a>
    </div>
</div>

<div class="members-table-card">
    <!-- Card Toolbar -->
    <div class="members-toolbar">
        <div class="row align-items-center g-2">
            <div class="col-md-6 col-lg-5">
                <form action="" method="GET" class="m-0">
                    <?php if ($sort !== 'id'): ?>
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <?php endif; ?>
                    <?php if ($dir !== 'desc'): ?>
                        <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?= htmlspecialchars($perPage) ?>">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0"
                            placeholder="Search by name, member no, or contact..."
                            value="<?= htmlspecialchars($search) ?>" style="font-size: 0.85rem;">
                        <?php if ($search): ?>
                            <a href="members.php?per_page=<?= $perPage ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"
                                class="btn btn-sm btn-outline-secondary border-start-0" title="Clear search">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-primary-custom px-3">Search</button>
                    </div>
                </form>
            </div>
            <div class="col-md-6 col-lg-7 d-flex justify-content-md-end align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Show</span>
                    <select class="form-select form-select-sm" style="width: 72px;" onchange="location = this.value;">
                        <?php foreach ($allowedPerPages as $val): ?>
                            <?php
                            $params = ['per_page' => $val, 'page' => 1, 'sort' => $sort, 'dir' => $dir];
                            if ($search !== '')
                                $params['search'] = $search;
                            $url = '?' . http_build_query($params);
                            ?>
                            <option value="<?= htmlspecialchars($url) ?>" <?= $val == $perPage ? 'selected' : '' ?>><?= $val ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-muted small">entries</span>
                </div>
                <div class="vr opacity-25 d-none d-sm-block"></div>
                <span class="badge bg-light text-dark border px-3 py-2 fw-medium" style="font-size: 0.8rem;">
                    Total: <strong class="text-primary ms-1"><?= number_format($totalMembers) ?></strong>
                </span>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="mb-0">
        <table class="table table-hover align-middle mb-0 members-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 75px; min-width: 75px;">
                        <a href="<?= getSortUrl('sn', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <span>S.N.</span>
                            <?= getSortIcon('sn', $sort, $dir) ?>
                        </a>
                    </th>
                    <th style="width: 130px; min-width: 125px;">
                        <a href="<?= getSortUrl('member_no', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <span>Member No.</span>
                            <?= getSortIcon('member_no', $sort, $dir) ?>
                        </a>
                    </th>
                    <th style="min-width: 180px;">
                        <a href="<?= getSortUrl('full_name', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <span>Full Name</span>
                            <?= getSortIcon('full_name', $sort, $dir) ?>
                        </a>
                    </th>
                    <th style="width: 130px; min-width: 120px;">
                        <a href="<?= getSortUrl('contact', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <span>Contact</span>
                            <?= getSortIcon('contact', $sort, $dir) ?>
                        </a>
                    </th>
                    <th class="text-center" style="width: 100px; min-width: 90px;">
                        <a href="<?= getSortUrl('page_number', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center justify-content-center gap-1 w-100">
                            <span>Page No.</span>
                            <?= getSortIcon('page_number', $sort, $dir) ?>
                        </a>
                    </th>
                    <th class="text-center" style="width: 100px; min-width: 90px;">
                        <a href="<?= getSortUrl('table_no', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center justify-content-center gap-1 w-100">
                            <span>Table No.</span>
                            <?= getSortIcon('table_no', $sort, $dir) ?>
                        </a>
                    </th>
                    <th class="text-center" style="width: 95px; min-width: 85px;">
                        <a href="<?= getSortUrl('file_number', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center justify-content-center gap-1 w-100">
                            <span>File No.</span>
                            <?= getSortIcon('file_number', $sort, $dir) ?>
                        </a>
                    </th>
                    <th class="text-center" style="width: 100px; min-width: 95px;">
                        <a href="<?= getSortUrl('status', $sort, $dir, $search, $perPage) ?>"
                            class="text-decoration-none d-inline-flex align-items-center justify-content-center gap-1 w-100">
                            <span>Status</span>
                            <?= getSortIcon('status', $sort, $dir) ?>
                        </a>
                    </th>
                    <th class="text-center" style="width: 105px; min-width: 95px;">Gender</th>
                    <th class="text-end" style="width: 140px; min-width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td class="text-center fw-semibold text-muted font-monospace small">
                            <?= $member['sn'] !== null ? htmlspecialchars($member['sn']) : '<span class="opacity-50">—</span>' ?>
                        </td>
                        <td>
                            <span
                                class="font-monospace fw-semibold text-dark"><?= htmlspecialchars($member['member_no']) ?></span>
                        </td>
                        <td class="fw-semibold text-dark text-nowrap">
                            <?= htmlspecialchars($member['full_name']) ?>
                        </td>
                        <td class="text-nowrap text-muted small">
                            <?= htmlspecialchars($member['contact'] ?: '—') ?>
                        </td>
                        <td class="text-center">
                            <span
                                class="badge bg-light text-dark border px-2 py-1 fw-semibold"><?= htmlspecialchars($member['page_number'] ?? '-') ?></span>
                        </td>
                        <td class="text-center">
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($member['table_no'] ?? '-') ?></span>
                        </td>
                        <td class="text-center">
                            <span class="text-muted small"><?= htmlspecialchars($member['file_number'] ?? '-') ?></span>
                        </td>
                        <td class="text-center">
                            <span
                                class="badge <?= $member['status'] == 'active' ? 'badge-completed' : 'badge-secondary' ?>">
                                <?= ucfirst($member['status']) ?>
                            </span>
                        </td>
                        <td class="text-center text-nowrap">
                            <?php
                            $g = $member['gender'] ?? '';
                            if ($g === 'Male')
                                echo '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="fas fa-mars me-1"></i>Male</span>';
                            elseif ($g === 'Female')
                                echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fas fa-venus me-1"></i>Female</span>';
                            else
                                echo '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">' . htmlspecialchars($g ?: 'N/A') . '</span>';
                            ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-outline-success btn-view-qr"
                                    data-member-no="<?= htmlspecialchars($member['member_no']) ?>"
                                    data-name="<?= htmlspecialchars($member['full_name']) ?>"
                                    data-contact="<?= htmlspecialchars($member['contact'] ?? 'N/A') ?>"
                                    data-page="<?= htmlspecialchars($member['page_number'] ?? 'N/A') ?>"
                                    data-table="<?= htmlspecialchars($member['table_no'] ?? 'N/A') ?>"
                                    data-file="<?= htmlspecialchars($member['file_number'] ?? 'N/A') ?>"
                                    title="View QR Ticket">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <a href="edit_member.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-primary"
                                    title="Edit Member">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="../actions/delete_member_action.php" class="d-inline m-0"
                                    onsubmit="return confirm('Are you sure you want to delete this member? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Member">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="fas fa-users-slash fs-2 mb-2 d-block opacity-25"></i>
                            No members found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Card Footer / Pagination -->
    <div
        class="p-3 px-4 border-top bg-light bg-opacity-50 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
        <div class="text-muted small">
            <?php
            $startCount = $totalMembers > 0 ? $offset + 1 : 0;
            $endCount = min($offset + $perPage, $totalMembers);
            ?>
            Showing <strong class="text-dark"><?= number_format($startCount) ?></strong> to <strong
                class="text-dark"><?= number_format($endCount) ?></strong> of <strong
                class="text-dark"><?= number_format($totalMembers) ?></strong> members
        </div>

        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= getPageUrl($page - 1, $sort, $dir, $perPage, $search) ?>">
                            <i class="fas fa-chevron-left me-1 small"></i> Previous
                        </a>
                    </li>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= getPageUrl($i, $sort, $dir, $perPage, $search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= getPageUrl($page + 1, $sort, $dir, $perPage, $search) ?>">
                            Next <i class="fas fa-chevron-right ms-1 small"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/vendor/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const qrButtons = document.querySelectorAll('.btn-view-qr');
        qrButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const memberNo = this.getAttribute('data-member-no');
                const name = this.getAttribute('data-name');
                const contact = this.getAttribute('data-contact');
                const page = this.getAttribute('data-page');
                const table = this.getAttribute('data-table');
                const file = this.getAttribute('data-file');

                Swal.fire({
                    title: 'Member E-Ticket',
                    html: `
                    <div id="ticket-badge" class="p-3 my-2" style="border: 2px dashed #333; border-radius: 8px; background-color: #fff; color: #000; font-family: sans-serif; text-align: center; max-width: 300px; margin: 0 auto;">
                        <h5 style="margin: 0 0 5px 0; font-weight: 800; color: #083844;">SUYOGYA SACCOS</h5>
                        <p style="margin: 0 0 15px 0; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #666;">Official Entry Ticket</p>
                        
                        <div id="modal-qrcode-container" style="display: inline-block; padding: 10px; background: #fff; border: 1px solid #ddd; margin-bottom: 12px;"></div>
                        
                        <h4 style="margin: 5px 0; font-weight: bold; color: #111;">${name}</h4>
                        <div style="font-size: 13px; font-weight: bold; margin-bottom: 10px; color: #083844;">Member No: ${memberNo}</div>
                        
                        <div style="border-top: 1px dashed #ccc; margin-top: 10px; padding-top: 10px; text-align: left; font-size: 12px; line-height: 1.6;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Table No:</span>
                                <span style="font-weight: bold;">${table}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Register Page:</span>
                                <span style="font-weight: bold;">Page ${page}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">File No:</span>
                                <span style="font-weight: bold;">${file}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Contact:</span>
                                <span style="font-weight: bold;">${contact}</span>
                            </div>
                        </div>
                    </div>
                `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print me-1"></i> Print Ticket',
                    cancelButtonText: 'Close',
                    confirmButtonColor: '#083844',
                    cancelButtonColor: '#6c757d',
                    didOpen: () => {
                        new QRCode(document.getElementById('modal-qrcode-container'), {
                            text: memberNo,
                            width: 140,
                            height: 140,
                            colorDark: "#000000",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const printWindow = window.open('', '_blank', 'width=600,height=600');
                        const qrImgSrc = document.querySelector('#modal-qrcode-container img').src;

                        printWindow.document.write(`
                        <html>
                        <head>
                            <title>Print Member Badge - ${name}</title>
                            <style>
                                @page { size: auto; margin: 0mm; }
                                body { margin: 1.5cm; font-family: Arial, sans-serif; background: #fff; color: #000; }
                                .badge-container {
                                    border: 3px dashed #000;
                                    border-radius: 12px;
                                    padding: 25px;
                                    text-align: center;
                                    max-width: 320px;
                                    margin: 0 auto;
                                }
                                .org-title { margin: 0 0 5px 0; font-size: 20px; font-weight: 800; color: #083844; }
                                .sub-title { margin: 0 0 20px 0; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #666; }
                                .qr-img { width: 160px; height: 160px; margin: 10px auto 20px auto; display: block; border: 1px solid #eee; padding: 10px; }
                                .name { margin: 0 0 5px 0; font-size: 22px; font-weight: bold; }
                                .member-no { margin: 0 0 15px 0; font-size: 15px; font-weight: bold; color: #083844; }
                                .details-table { width: 100%; border-top: 1px dashed #ccc; padding-top: 15px; font-size: 13px; text-align: left; }
                                .details-table td { padding: 4px 0; }
                                .details-table td.val { text-align: right; font-weight: bold; }
                                .text-muted { color: #666; }
                            </style>
                        </head>
                        <body>
                            <div class="badge-container">
                                <h2 class="org-title">SUYOGYA SACCOS</h2>
                                <div class="sub-title">Official Entry Ticket</div>
                                <img src="${qrImgSrc}" class="qr-img" />
                                <h3 class="name">${name}</h3>
                                <div class="member-no">Member No: ${memberNo}</div>
                                <table class="details-table">
                                    <tr>
                                        <td class="text-muted">Table No:</td>
                                        <td class="val">${table}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Register Page:</td>
                                        <td class="val">Page ${page}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">File No:</td>
                                        <td class="val">${file}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Contact:</td>
                                        <td class="val">${contact}</td>
                                    </tr>
                                </table>
                            </div>
                            <script>
                                window.onload = function() {
                                    window.print();
                                    setTimeout(function() { window.close(); }, 500);
                                };
                            <\/script>
                        </body>
                        </html>
                    `);
                        printWindow.document.close();
                    }
                });
            });
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>