<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$event_id = $_GET['event_id'] ?? null;
if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if ($event && isAgmEvent($event['title'])) {
    $event['allowance_amount'] = 500.00;
}

if (!$event) {
    header('Location: events.php');
    exit;
}

// Fetch attendance with member details
$restrictedTables = getRestrictedTables();
$whereClause = "";
$params = [$event_id];
if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $whereClause = "AND 1=0";
    } else {
        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
        $whereClause = "AND m.table_no IN ($inClause)";
        foreach ($restrictedTables as $tbl) {
            $params[] = $tbl;
        }
    }
}

$stmt = $pdo->prepare("
    SELECT m.sn, m.member_no, m.full_name, m.gender, m.contact, m.page_number, m.table_no, m.file_number, a.attended_at
    FROM members m
    LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
    WHERE 1=1 {$whereClause}
    ORDER BY m.full_name ASC
");
$stmt->execute($params);
$members = $stmt->fetchAll();

$totalMembers = count($members);
$totalPresent = count(array_filter($members, fn($m) => $m['attended_at'] !== null));
$totalAbsent = $totalMembers - $totalPresent;
$attendanceRate = $totalMembers > 0 ? round(($totalPresent / $totalMembers) * 100, 1) : 0;

// Gender breakdown
$maleTotal = count(array_filter($members, fn($m) => $m['gender'] === 'Male'));
$femaleTotal = count(array_filter($members, fn($m) => $m['gender'] === 'Female'));
$malePresent = count(array_filter($members, fn($m) => $m['gender'] === 'Male' && $m['attended_at'] !== null));
$femalePresent = count(array_filter($members, fn($m) => $m['gender'] === 'Female' && $m['attended_at'] !== null));

// Fetch agenda items
$agendas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM agendas WHERE event_id = ? ORDER BY id ASC");
    $stmt->execute([$event_id]);
    $agendas = $stmt->fetchAll();
} catch (PDOException $e) {
}

// Fetch transportation allowance & staff cash float reconciliation data
$allowanceActive = ((float)$event['allowance_amount'] > 0);
$totalAllowancePaid = 0.00;
$staffCashReconciliation = [];

if ($allowanceActive) {
    $stmtSum = $pdo->prepare("SELECT COALESCE(SUM(allowance_paid), 0.00) FROM attendance WHERE event_id = ?");
    $stmtSum->execute([$event_id]);
    $totalAllowancePaid = (float)$stmtSum->fetchColumn();

    $stmtRecon = $pdo->prepare("
        SELECT u.username, 
               COALESCE(sec.allocated_amount, 0.00) AS allocated,
               COALESCE(payouts.total_paid, 0.00) AS paid
        FROM admin_users u
        JOIN staff_event_cash sec ON u.id = sec.user_id AND sec.event_id = :event_id
        LEFT JOIN (
            SELECT marked_by, SUM(allowance_paid) AS total_paid
            FROM attendance 
            WHERE event_id = :event_id2
            GROUP BY marked_by
        ) payouts ON u.id = payouts.marked_by
        ORDER BY u.username ASC
    ");
    $stmtRecon->execute(['event_id' => $event_id, 'event_id2' => $event_id]);
    $staffCashReconciliation = $stmtRecon->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Report – <?= htmlspecialchars($event['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #235857;
            --secondary: #3B8A7F;
            --light: #D3D9D4;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f8;
            color: #1a1a2e;
        }

        .report-wrapper {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .report-header {
            background: var(--primary);
            color: white;
            padding: 30px 40px;
        }

        .report-header h2 {
            font-weight: 700;
            margin: 0;
        }

        .report-header .subtitle {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .report-body {
            padding: 30px 40px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }

        .meta-card {
            background: #f8fffe;
            border: 1px solid #d0ebe8;
            border-radius: 8px;
            padding: 14px 16px;
        }

        .meta-card .label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }

        .meta-card .value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
            margin-top: 4px;
        }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }

        .stat-box {
            text-align: center;
            padding: 16px;
            border-radius: 8px;
            font-weight: 700;
        }

        .stat-box.total {
            background: #e8f5f5;
            color: var(--primary);
        }

        .stat-box.present {
            background: #d1e7dd;
            color: #198754;
        }

        .stat-box.absent {
            background: #f8d7da;
            color: #dc3545;
        }

        .stat-box.rate {
            background: #fff3cd;
            color: #856404;
        }

        .stat-box .num {
            font-size: 1.8rem;
            line-height: 1;
        }

        .stat-box .lbl {
            font-size: 0.75rem;
            margin-top: 4px;
            font-weight: 500;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid var(--light);
            padding-bottom: 6px;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        th {
            background: var(--primary);
            color: white;
            padding: 10px 12px;
            text-align: left;
        }

        td {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:nth-child(even) td {
            background: #f8fffe;
        }

        .badge-present {
            background: #d1e7dd;
            color: #198754;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .badge-absent {
            background: #f8d7da;
            color: #dc3545;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .agenda-item {
            padding: 10px 14px;
            border-left: 3px solid var(--secondary);
            background: #f8fffe;
            border-radius: 0 6px 6px 0;
            margin-bottom: 8px;
        }

        .agenda-item h6 {
            margin: 0 0 4px;
            font-weight: 600;
            color: var(--primary);
        }

        .agenda-item p {
            margin: 0;
            font-size: 0.82rem;
            color: #6c757d;
        }

        .report-footer {
            background: #f4f6f8;
            padding: 16px 40px;
            font-size: 0.8rem;
            color: #6c757d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .no-print {
            margin: 20px auto;
            max-width: 900px;
            display: flex;
            gap: 10px;
        }

        /* CSS Counter for table rows */
        table.attendance-table {
            counter-reset: rowNumber;
        }
        table.attendance-table tbody tr {
            counter-increment: rowNumber;
        }
        table.attendance-table tbody tr td.row-num::before {
            content: counter(rowNumber);
        }

        /* Screen behavior when hiding absent members */
        body.hide-absent tr.absent-row {
            display: none !important;
        }

        @media print {
            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .report-wrapper {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }

            /* Always hide absent members on print */
            tr.absent-row {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <div class="no-print d-flex align-items-center gap-3">
        <a href="events.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to
            Events</a>
        <div class="form-check form-switch mb-0 ms-2">
            <input class="form-check-input" type="checkbox" id="toggleAbsent" checked>
            <label class="form-check-label small fw-medium" for="toggleAbsent">Show Absent Members on Screen</label>
        </div>
        <button onclick="window.print()" class="btn btn-sm ms-auto" style="background:var(--primary);color:white;">
            <i class="fas fa-print me-1"></i> Print / Save PDF
        </button>
    </div>

    <div class="report-wrapper">

        <!-- Header -->
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="subtitle mb-1">SUYOGYA SACCOS – Event Report</div>
                    <h2><?= htmlspecialchars($event['title']) ?></h2>
                </div>
                <div class="text-end subtitle">
                    <div>Generated: <?= date('d M Y, h:i A') ?></div>
                </div>
            </div>
        </div>

        <div class="report-body">

            <!-- Event Meta -->
            <div class="meta-grid">
                <div class="meta-card">
                    <div class="label">Date</div>
                    <div class="value"><i class="fas fa-calendar me-1"></i><?= htmlspecialchars($event['event_date']) ?>
                    </div>
                </div>
                <div class="meta-card">
                    <div class="label">Location</div>
                    <div class="value"><i
                            class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($event['location']) ?></div>
                </div>
                <div class="meta-card">
                    <div class="label">Status</div>
                    <div class="value"><?= ucfirst(htmlspecialchars($event['status'])) ?></div>
                </div>
            </div>

            <!-- Attendance Stats -->
            <div class="section-title">Attendance Summary</div>
            <div class="stat-row mb-4">
                <div class="stat-box total">
                    <div class="num"><?= $totalMembers ?></div>
                    <div class="lbl">Total Members</div>
                </div>
                <div class="stat-box present">
                    <div class="num"><?= $totalPresent ?></div>
                    <div class="lbl">Present</div>
                </div>
                <div class="stat-box absent">
                    <div class="num"><?= $totalAbsent ?></div>
                    <div class="lbl">Absent</div>
                </div>
                <div class="stat-box rate">
                    <div class="num"><?= $attendanceRate ?>%</div>
                    <div class="lbl">Attendance Rate</div>
                </div>
            </div>

            <!-- Gender Breakdown -->
            <div class="section-title">Gender Breakdown</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:28px;">
                <div
                    style="background:#e8f4ff; border:1px solid #b8d8f8; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:14px;">
                    <i class="fas fa-mars" style="font-size:2rem; color:#235857;"></i>
                    <div>
                        <div style="font-size:0.7rem; text-transform:uppercase; color:#6c757d; font-weight:600;">Male
                        </div>
                        <div style="font-size:1.5rem; font-weight:700; color:#235857;"><?= $malePresent ?> <span
                                style="font-size:0.9rem; color:#6c757d; font-weight:400;">/ <?= $maleTotal ?>
                                attended</span></div>
                    </div>
                </div>
                <div
                    style="background:#fff0f3; border:1px solid #f8b8c8; border-radius:8px; padding:14px 16px; display:flex; align-items:center; gap:14px;">
                    <i class="fas fa-venus" style="font-size:2rem; color:#dc3545;"></i>
                    <div>
                        <div style="font-size:0.7rem; text-transform:uppercase; color:#6c757d; font-weight:600;">Female
                        </div>
                        <div style="font-size:1.5rem; font-weight:700; color:#dc3545;"><?= $femalePresent ?> <span
                                style="font-size:0.9rem; color:#6c757d; font-weight:400;">/ <?= $femaleTotal ?>
                                attended</span></div>
                    </div>
                </div>
            </div>

            <!-- Allowance & Cash Float Reconciliation -->
            <?php if ($allowanceActive): ?>
            <div class="section-title">Transportation Allowance & Payout Reconciliation</div>
            <div class="mb-4">
                <div class="meta-grid mb-3">
                    <div class="meta-card">
                        <div class="label">Allowance Rate</div>
                        <div class="value">NPR <?= number_format($event['allowance_amount'], 2) ?></div>
                    </div>
                    <div class="meta-card">
                        <div class="label">Total Paid Out</div>
                        <div class="value">NPR <?= number_format($totalAllowancePaid, 2) ?></div>
                    </div>
                    <div class="meta-card">
                        <div class="label">Presents Paid</div>
                        <div class="value"><?= $totalPresent ?> Members</div>
                    </div>
                </div>
                
                <h6 class="fw-bold mb-2 text-secondary small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Staff Float Payout Breakdown</h6>
                <table class="mb-4 text-start" style="width:100%; border: 1px solid #dee2e6;">
                    <thead>
                        <tr style="background:#f8f9fa; color:#212529;">
                            <th style="background:#f8f9fa; color:#212529; border-bottom: 2px solid #dee2e6; padding: 8px 12px;">Staff Username</th>
                            <th style="background:#f8f9fa; color:#212529; border-bottom: 2px solid #dee2e6; padding: 8px 12px;">Allocated Cash Float</th>
                            <th style="background:#f8f9fa; color:#212529; border-bottom: 2px solid #dee2e6; padding: 8px 12px;">Amount Paid Out</th>
                            <th style="background:#f8f9fa; color:#212529; border-bottom: 2px solid #dee2e6; padding: 8px 12px;">Remaining Cash Float</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffCashReconciliation as $row): 
                            $rem = $row['allocated'] - $row['paid'];
                        ?>
                        <tr>
                            <td class="fw-medium text-dark" style="padding: 8px 12px; border-bottom: 1px solid #dee2e6;"><?= htmlspecialchars($row['username']) ?></td>
                            <td style="padding: 8px 12px; border-bottom: 1px solid #dee2e6;">NPR <?= number_format($row['allocated'], 2) ?></td>
                            <td class="text-danger" style="padding: 8px 12px; border-bottom: 1px solid #dee2e6;">NPR <?= number_format($row['paid'], 2) ?></td>
                            <td class="fw-bold <?= $rem >= $event['allowance_amount'] ? 'text-success' : 'text-danger' ?>" style="padding: 8px 12px; border-bottom: 1px solid #dee2e6;">NPR <?= number_format($rem, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($staffCashReconciliation)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted" style="padding: 8px 12px;">No staff cash floats allocated.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Agenda -->
                <?php if (!empty($agendas)): ?>
                <div class="section-title">Agenda Items</div>
                <div class="mb-4">
                            <?php foreach ($agendas as $i => $agenda): ?>
                        <div class="agenda-item">
                            <h6><?= ($i + 1) ?>. <?= htmlspecialchars($agenda['title']) ?></h6>
                                        <?php if ($agenda['description']): ?>
                                <p><?= nl2br(htmlspecialchars($agenda['description'])) ?></p>
                                        <?php endif; ?>
                        </div>
                            <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <!-- Attendance Table -->
            <div class="section-title">Member Attendance List</div>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>Member No.</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Contact</th>
                        <th>Page No.</th>
                        <th>Table No.</th>
                        <th>Status</th>
                        <th>Check-In Time</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($members as $i => $member): 
                            $statusClass = $member['attended_at'] ? 'present-row' : 'absent-row';
                        ?>
                        <tr class="<?= $statusClass ?>">
                            <td class="row-num"><?= $member['sn'] !== null ? htmlspecialchars($member['sn']) : '—' ?></td>
                            <td><?= htmlspecialchars($member['member_no']) ?></td>
                            <td><?= htmlspecialchars($member['full_name']) ?></td>
                            <td><?= htmlspecialchars($member['gender'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['contact']) ?></td>
                            <td><?= htmlspecialchars($member['page_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['table_no'] ?? '-') ?></td>
                            <td>
                                        <?php if ($member['attended_at']): ?>
                                    <span class="badge-present">&#10003; Present</span>
                                        <?php else: ?>
                                    <span class="badge-absent">&#10007; Absent</span>
                                        <?php endif; ?>
                            </td>
                            <td><?= $member['attended_at'] ? date('h:i A', strtotime($member['attended_at'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>

        </div>

        <!-- Footer -->
        <div class="report-footer">
            <span>SUYOGYA SACCOS &copy; <?= date('Y') ?></span>
            <span>Event Management System</span>
            <span>Printed: <?= date('d M Y') ?></span>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleAbsent = document.getElementById('toggleAbsent');
            if (toggleAbsent) {
                toggleAbsent.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.remove('hide-absent');
                    } else {
                        document.body.classList.add('hide-absent');
                    }
                });
            }
        });
    </script>
</body>

</html>