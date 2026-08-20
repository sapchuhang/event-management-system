<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

// ── Summary KPIs ──────────────────────────────────────────────────────────
$restrictedTables = getRestrictedTables();
if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $totalActive   = 0;
        $totalMale     = 0;
        $totalFemale   = 0;
        $totalEvents   = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
        $totalCheckins = 0;
        $eventsRaw     = [];
    } else {
        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE status='active' AND table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $totalActive = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE status='active' AND gender='Male' AND table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $totalMale = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE status='active' AND gender='Female' AND table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $totalFemale = (int)$stmt->fetchColumn();

        $totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN members m ON a.member_id = m.id WHERE m.table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $totalCheckins = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT e.*,
                   COUNT(a.id)                                              AS attended,
                   SUM(m.gender = 'Male'   AND a.id IS NOT NULL)            AS male_attended,
                   SUM(m.gender = 'Female' AND a.id IS NOT NULL)            AS female_attended
            FROM events e
            LEFT JOIN attendance a ON e.id = a.event_id
            LEFT JOIN members m    ON a.member_id = m.id AND m.status = 'active' AND m.table_no IN ($inClause)
            GROUP BY e.id
            ORDER BY e.event_date DESC
        ");
        $stmt->execute($restrictedTables);
        $eventsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $totalActive  = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
    $totalMale    = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active' AND gender='Male'")->fetchColumn();
    $totalFemale  = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active' AND gender='Female'")->fetchColumn();
    $totalEvents  = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $totalCheckins = (int) $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();

    // Per-event stats for all events
    $eventsRaw = $pdo->query("
        SELECT e.*,
               COUNT(a.id)                                              AS attended,
               SUM(m.gender = 'Male'   AND a.id IS NOT NULL)            AS male_attended,
               SUM(m.gender = 'Female' AND a.id IS NOT NULL)            AS female_attended
        FROM events e
        LEFT JOIN attendance a ON e.id = a.event_id
        LEFT JOIN members m    ON a.member_id = m.id AND m.status = 'active'
        GROUP BY e.id
        ORDER BY e.event_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$events = [];
$sumPct = 0;
foreach ($eventsRaw as $ev) {
    $att  = (int)$ev['attended'];
    $pct  = $totalActive > 0 ? round(($att / $totalActive) * 100, 1) : 0;
    $sumPct += $pct;
    $events[] = [
        'id'       => $ev['id'],
        'title'    => $ev['title'],
        'date'     => $ev['event_date'],
        'location' => $ev['location'] ?? '',
        'status'   => $ev['status'],
        'attended' => $att,
        'absent'   => max(0, $totalActive - $att),
        'pct'      => $pct,
        'male'     => (int)($ev['male_attended']   ?? 0),
        'female'   => (int)($ev['female_attended'] ?? 0),
    ];
}

// Best event by turnout %
$bestEvent = $events ? array_reduce($events, fn($c,$i) => $i['pct'] > ($c['pct'] ?? -1) ? $i : $c, null) : null;
$avgTurnout = count($events) > 0 ? round($sumPct / count($events), 1) : 0;

$pageTitle = 'Reports & Analytics';
require_once '../includes/header.php';
?>

<!-- ── Page header ────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Reports & Analytics</h4>
        <small class="text-muted">Attendance breakdown across all events</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button id="btnExportCSV" class="btn btn-sm btn-outline-success">
            <i class="fas fa-file-csv me-1"></i> Export CSV
        </button>
        <div class="btn-group btn-group-sm" role="group">
            <button id="viewCard"  class="btn btn-primary-custom active" title="Card view">
                <i class="fas fa-th-large"></i>
            </button>
            <button id="viewTable" class="btn btn-outline-secondary" title="Table view">
                <i class="fas fa-table"></i>
            </button>
        </div>
        <button class="btn btn-sm btn-outline-secondary d-print-none" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
    </div>
</div>

<!-- ── Summary KPI Strip ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-card--sm">
            <div class="kpi-icon kpi-icon--sm" style="background:var(--card-blue-bg);color:var(--card-blue-fg);">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="kpi-value kpi-value--sm counter" data-target="<?= $totalEvents ?>"><?= $totalEvents ?></div>
            <div class="kpi-label">Total Events</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-card--sm">
            <div class="kpi-icon kpi-icon--sm" style="background:rgba(16,185,129,.1);color:#059669;">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="kpi-value kpi-value--sm counter" data-target="<?= $totalCheckins ?>"><?= $totalCheckins ?></div>
            <div class="kpi-label">Total Check-ins</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-card--sm">
            <div class="kpi-icon kpi-icon--sm" style="background:rgba(139,92,246,.1);color:#7c3aed;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="kpi-value kpi-value--sm"><?= $avgTurnout ?>%</div>
            <div class="kpi-label">Avg. Turnout</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card kpi-card--sm">
            <div class="kpi-icon kpi-icon--sm" style="background:rgba(245,158,11,.1);color:#d97706;">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="kpi-value kpi-value--sm" style="font-size:1rem;line-height:1.3;font-weight:700;">
                <?= $bestEvent ? htmlspecialchars(mb_strimwidth($bestEvent['title'], 0, 18, '…')) : '—' ?>
            </div>
            <div class="kpi-label">Best Turnout (<?= $bestEvent ? $bestEvent['pct'] : 0 ?>%)</div>
        </div>
    </div>
</div>

<!-- ── CARD VIEW ──────────────────────────────────────────────────────── -->
<div id="cardView" class="row g-4">
    <?php if (empty($events)): ?>
        <div class="col-12">
            <div class="glass-panel p-5 text-center text-muted">
                <i class="fas fa-folder-open fa-3x mb-3 text-light"></i>
                <p>No events available to generate reports.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($events as $ev): ?>
        <div class="col-md-6 col-xl-4">
            <div class="glass-panel p-4 h-100 d-flex flex-column">
                <!-- Event header -->
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div style="flex:1;min-width:0;">
                        <h5 class="fw-bold text-primary mb-1 text-truncate"><?= htmlspecialchars($ev['title']) ?></h5>
                        <div class="text-muted small">
                            <i class="fas fa-calendar me-1"></i><?= htmlspecialchars($ev['date']) ?>
                            <?php if ($ev['location']): ?>
                                &nbsp;·&nbsp;<i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($ev['location']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge ms-2 event-status-badge status-<?= $ev['status'] ?>">
                        <?= ucfirst($ev['status']) ?>
                    </span>
                </div>

                <!-- Progress Ring + Stats -->
                <div class="d-flex align-items-center gap-4 mb-3">
                    <!-- SVG Progress Ring -->
                    <div class="progress-ring-wrap flex-shrink-0">
                        <?php
                            $r = 36; $circ = round(2 * M_PI * $r, 2);
                            $filled = round($circ * ($ev['pct'] / 100), 2);
                            $pctColor = $ev['pct'] >= 75 ? '#10b981' : ($ev['pct'] >= 40 ? '#f59e0b' : '#ef4444');
                        ?>
                        <svg width="90" height="90" viewBox="0 0 90 90">
                            <circle cx="45" cy="45" r="<?= $r ?>" fill="none" stroke="#e2e8f0" stroke-width="8"/>
                            <circle cx="45" cy="45" r="<?= $r ?>" fill="none"
                                stroke="<?= $pctColor ?>" stroke-width="8"
                                stroke-dasharray="<?= $filled ?> <?= $circ ?>"
                                stroke-dashoffset="<?= round($circ * 0.25, 2) ?>"
                                stroke-linecap="round"
                                class="ring-arc" data-val="<?= $filled ?>" data-circ="<?= $circ ?>"/>
                            <text x="45" y="48" text-anchor="middle" dominant-baseline="middle"
                                fill="<?= $pctColor ?>" font-size="14" font-weight="700" font-family="Inter"><?= $ev['pct'] ?>%</text>
                        </svg>
                    </div>
                    <!-- Count grid -->
                    <div class="row text-center g-0 flex-fill">
                        <div class="col-4 border-end">
                            <div class="fw-bold fs-5"><?= number_format($totalActive) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">MEMBERS</div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="fw-bold fs-5 text-success"><?= number_format($ev['attended']) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">PRESENT</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold fs-5 text-danger"><?= number_format($ev['absent']) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">ABSENT</div>
                        </div>
                    </div>
                </div>

                <!-- Gender visual bars -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span><i class="fas fa-mars me-1 text-primary"></i>Male <?= $ev['male'] ?>/<?= $totalMale ?></span>
                        <span><i class="fas fa-venus me-1 text-danger"></i>Female <?= $ev['female'] ?>/<?= $totalFemale ?></span>
                    </div>
                    <div class="gender-bar-wrap">
                        <?php
                            $mPct = $totalMale > 0   ? round($ev['male']   / $totalMale   * 100) : 0;
                            $fPct = $totalFemale > 0 ? round($ev['female'] / $totalFemale * 100) : 0;
                        ?>
                        <div class="gender-bar-track">
                            <div class="gender-bar-fill male"   style="width:<?= $mPct ?>%"></div>
                        </div>
                        <div class="gender-bar-track mt-1">
                            <div class="gender-bar-fill female" style="width:<?= $fPct ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-auto pt-2 d-flex gap-2">
                    <a href="event_report.php?event_id=<?= $ev['id'] ?>"
                       class="btn btn-sm btn-primary-custom flex-fill">
                        <i class="fas fa-file-alt me-1"></i> Full Report
                    </a>
                    <a href="attendance.php?event_id=<?= $ev['id'] ?>"
                       class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="fas fa-list me-1"></i> Attendance
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── TABLE VIEW ─────────────────────────────────────────────────────── -->
<div id="tableView" class="d-none">
    <div class="glass-panel p-0 overflow-hidden">
        <div class="table-responsive">
            <table id="reportsTable" class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="background:linear-gradient(135deg,#1e4644,#2b7370);color:#fff;">
                        <th class="px-4 py-3 sortable" data-col="title">Event <i class="fas fa-sort ms-1 small opacity-50"></i></th>
                        <th class="sortable" data-col="date">Date <i class="fas fa-sort ms-1 small opacity-50"></i></th>
                        <th>Location</th>
                        <th class="sortable" data-col="status">Status <i class="fas fa-sort ms-1 small opacity-50"></i></th>
                        <th class="text-center sortable" data-col="attended">Present <i class="fas fa-sort ms-1 small opacity-50"></i></th>
                        <th class="text-center">Absent</th>
                        <th class="text-center sortable" data-col="pct">Turnout % <i class="fas fa-sort ms-1 small opacity-50"></i></th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $ev):
                    $pctColor = $ev['pct'] >= 75 ? '#10b981' : ($ev['pct'] >= 40 ? '#f59e0b' : '#ef4444');
                ?>
                    <tr
                        data-title="<?= htmlspecialchars(strtolower($ev['title'])) ?>"
                        data-date="<?= $ev['date'] ?>"
                        data-status="<?= $ev['status'] ?>"
                        data-attended="<?= $ev['attended'] ?>"
                        data-pct="<?= $ev['pct'] ?>">
                        <td class="px-4 fw-medium"><?= htmlspecialchars($ev['title']) ?></td>
                        <td><?= $ev['date'] ?></td>
                        <td class="text-muted"><?= htmlspecialchars($ev['location'] ?: '—') ?></td>
                        <td>
                            <span class="badge event-status-badge status-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span>
                        </td>
                        <td class="text-center fw-bold text-success"><?= $ev['attended'] ?></td>
                        <td class="text-center text-danger"><?= $ev['absent'] ?></td>
                        <td class="text-center">
                            <span class="fw-bold" style="color:<?= $pctColor ?>;"><?= $ev['pct'] ?>%</span>
                            <div class="progress mt-1" style="height:4px;border-radius:2px;">
                                <div class="progress-bar" style="width:<?= $ev['pct'] ?>%;background:<?= $pctColor ?>;"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <a href="event_report.php?event_id=<?= $ev['id'] ?>" class="btn btn-xs btn-primary-custom py-1 px-2 me-1">
                                <i class="fas fa-file-alt"></i>
                            </a>
                            <a href="attendance.php?event_id=<?= $ev['id'] ?>" class="btn btn-xs btn-outline-secondary py-1 px-2">
                                <i class="fas fa-list"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print-only styles -->
<style>
@media print {
    .sidebar,#mainSidebar,.sidebar-overlay,.navbar,.sticky-top,.btn,.d-print-none { display:none!important; }
    .container-fluid,.row.g-0 { height:auto!important;overflow:visible!important;flex-wrap:wrap!important; }
    .col-md-2 { display:none!important; }
    .col-md-10,.col-12 { width:100%!important;max-width:100%!important;flex:0 0 100%!important;height:auto!important;overflow:visible!important; }
    .main-content { padding:0!important; }
    .glass-panel { box-shadow:none!important;border:1px solid #ddd!important;break-inside:avoid; }
    body { background:white!important;-webkit-print-color-adjust:exact;print-color-adjust:exact; }
}
</style>

<!-- Embed event data for CSV export -->
<script>
const REPORT_EVENTS = <?= json_encode($events) ?>;
const TOTAL_MEMBERS = <?= $totalActive ?>;

document.addEventListener('DOMContentLoaded', function () {

    /* ── Animated counters ──────────────────────────────────────── */
    document.querySelectorAll('.counter[data-target]').forEach(el => {
        const target = parseInt(el.dataset.target, 10);
        if (!target) return;
        let start = 0;
        const step = target / (1000 / 16);
        const t = setInterval(() => {
            start = Math.min(start + step, target);
            el.textContent = Math.floor(start).toLocaleString();
            if (start >= target) clearInterval(t);
        }, 16);
    });

    /* ── Progress ring animation ────────────────────────────────── */
    document.querySelectorAll('.ring-arc').forEach(arc => {
        const val   = parseFloat(arc.dataset.val);
        const circ  = parseFloat(arc.dataset.circ);
        arc.style.strokeDasharray = `0 ${circ}`;
        arc.style.transition = 'stroke-dasharray 1s ease';
        requestAnimationFrame(() => {
            setTimeout(() => {
                arc.style.strokeDasharray = `${val} ${circ}`;
            }, 100);
        });
    });

    /* ── View Toggle ────────────────────────────────────────────── */
    const cardView  = document.getElementById('cardView');
    const tableView = document.getElementById('tableView');
    const btnCard   = document.getElementById('viewCard');
    const btnTable  = document.getElementById('viewTable');

    btnCard.addEventListener('click', () => {
        cardView.classList.remove('d-none');
        tableView.classList.add('d-none');
        btnCard.classList.replace('btn-outline-secondary', 'btn-primary-custom');
        btnTable.classList.replace('btn-primary-custom',   'btn-outline-secondary');
    });
    btnTable.addEventListener('click', () => {
        tableView.classList.remove('d-none');
        cardView.classList.add('d-none');
        btnTable.classList.replace('btn-outline-secondary', 'btn-primary-custom');
        btnCard.classList.replace('btn-primary-custom',     'btn-outline-secondary');
    });

    /* ── Sortable table ─────────────────────────────────────────── */
    let sortDir = {};
    document.querySelectorAll('th.sortable').forEach(th => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            const col = th.dataset.col;
            sortDir[col] = !sortDir[col];
            const tbody = document.querySelector('#reportsTable tbody');
            [...tbody.querySelectorAll('tr')]
                .sort((a, b) => {
                    let va = a.dataset[col] ?? '';
                    let vb = b.dataset[col] ?? '';
                    const num = !isNaN(parseFloat(va));
                    if (num) { va = parseFloat(va); vb = parseFloat(vb); }
                    return sortDir[col]
                        ? (va > vb ? 1 : -1)
                        : (va < vb ? 1 : -1);
                })
                .forEach(tr => tbody.appendChild(tr));
        });
    });

    /* ── CSV Export ─────────────────────────────────────────────── */
    document.getElementById('btnExportCSV').addEventListener('click', () => {
        const headers = ['Event','Date','Location','Status','Total Members','Present','Absent','Turnout %','Male Attended','Female Attended'];
        const rows = REPORT_EVENTS.map(ev => [
            `"${ev.title.replace(/"/g,'""')}"`,
            ev.date, `"${ev.location}"`, ev.status,
            TOTAL_MEMBERS, ev.attended, ev.absent, ev.pct,
            ev.male, ev.female
        ]);
        const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a    = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: `event_reports_${new Date().toISOString().slice(0,10)}.csv`
        });
        a.click();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>