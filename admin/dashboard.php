<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$restrictedTables = getRestrictedTables();

if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $totalMembers = 0;
        $activeMembers = 0;
        $totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
        $totalCheckIns = 0;
        $avgTurnout = 0;
        $topEventName = '—';
        $topEventCnt = 0;
        $recentActivities = [];
    } else {
        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $totalMembers = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE status='active' AND table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $activeMembers = (int)$stmt->fetchColumn();

        $totalEvents = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN members m ON a.member_id = m.id WHERE m.table_no IN ($inClause)");
        $stmt->execute($restrictedTables);
        $totalCheckIns = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT e.id, COUNT(a.id) AS attended
            FROM events e 
            LEFT JOIN attendance a ON e.id = a.event_id
            LEFT JOIN members m ON a.member_id = m.id AND m.table_no IN ($inClause)
            GROUP BY e.id
        ");
        $stmt->execute($restrictedTables);
        $evRows = $stmt->fetchAll();
        $avgTurnout = 0;
        if ($totalMembers > 0 && count($evRows) > 0) {
            $sumPct = 0;
            foreach ($evRows as $r) { $sumPct += ($r['attended'] / $totalMembers) * 100; }
            $avgTurnout = round($sumPct / count($evRows), 1);
        }

        $stmt = $pdo->prepare("
            SELECT e.title, COUNT(a.id) AS cnt
            FROM events e 
            LEFT JOIN attendance a ON e.id = a.event_id
            LEFT JOIN members m ON a.member_id = m.id AND m.table_no IN ($inClause)
            GROUP BY e.id, e.title ORDER BY cnt DESC LIMIT 1
        ");
        $stmt->execute($restrictedTables);
        $topRow = $stmt->fetch();
        $topEventName = $topRow['title'] ?? '—';
        $topEventCnt  = (int)($topRow['cnt'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT a.attended_at, m.full_name, m.member_no, ev.title
            FROM attendance a
            JOIN members m ON a.member_id = m.id
            JOIN events ev ON a.event_id = ev.id
            WHERE m.table_no IN ($inClause)
            ORDER BY a.attended_at DESC LIMIT 10
        ");
        $stmt->execute($restrictedTables);
        $recentActivities = $stmt->fetchAll();
    }
} else {
    // ── Core KPI queries ─────────────────────────────────────────────────────────
    $totalMembers  = (int) $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $activeMembers = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
    $totalEvents   = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $totalCheckIns = (int) $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();

    // Average turnout %
    $evRows = $pdo->query("
        SELECT e.id, COUNT(a.id) AS attended
        FROM events e LEFT JOIN attendance a ON e.id = a.event_id
        GROUP BY e.id
    ")->fetchAll();
    $avgTurnout = 0;
    if ($totalMembers > 0 && count($evRows) > 0) {
        $sumPct = 0;
        foreach ($evRows as $r) { $sumPct += ($r['attended'] / $totalMembers) * 100; }
        $avgTurnout = round($sumPct / count($evRows), 1);
    }

    // Top event
    $topRow = $pdo->query("
        SELECT e.title, COUNT(a.id) AS cnt
        FROM events e LEFT JOIN attendance a ON e.id = a.event_id
        GROUP BY e.id, e.title ORDER BY cnt DESC LIMIT 1
    ")->fetch();
    $topEventName = $topRow['title'] ?? '—';
    $topEventCnt  = (int)($topRow['cnt'] ?? 0);

    // Recent 10 activities
    $recentActivities = $pdo->query("
        SELECT a.attended_at, m.full_name, m.member_no, ev.title
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        JOIN events ev ON a.event_id = ev.id
        ORDER BY a.attended_at DESC LIMIT 10
    ")->fetchAll();
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    $i = '';
    foreach ($words as $w) { if (!empty($w)) { $i .= strtoupper(substr($w,0,1)); } if (strlen($i)>=2) break; }
    return $i ?: 'M';
}

$pageTitle = 'Dashboard';
require_once '../includes/header.php';
?>

<!-- ── Welcome Row ───────────────────────────────────────────────────────── -->
<div class="page-header mb-4">
    <div>
        <h3 class="fw-bold mb-0" style="color: var(--primary);">Welcome Back,
            <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></h3>
        <p class="text-muted mb-0 mt-1" style="font-size:0.875rem;">Real-time analytics overview — <?= date('l, d F Y') ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="reports.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="events.php"  class="btn btn-primary-custom btn-sm"><i class="fas fa-plus"></i> New Event</a>
    </div>
</div>

<!-- ── KPI Tiles Row ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Total Members -->
    <div class="col-6 col-md-4 col-xl">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--card-blue-bg);color:var(--card-blue-fg);">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-value counter" data-target="<?= $totalMembers ?>"><?= number_format($totalMembers) ?></div>
            <div class="kpi-label">Total Members</div>
        </div>
    </div>
    <!-- Active Members -->
    <div class="col-6 col-md-4 col-xl">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(16,185,129,.1);color:#059669;">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="kpi-value counter" data-target="<?= $activeMembers ?>"><?= number_format($activeMembers) ?></div>
            <div class="kpi-label">Active Members</div>
        </div>
    </div>
    <!-- Total Events -->
    <div class="col-6 col-md-4 col-xl">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--card-green-bg);color:var(--card-green-fg);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="kpi-value counter" data-target="<?= $totalEvents ?>"><?= number_format($totalEvents) ?></div>
            <div class="kpi-label">Total Events</div>
        </div>
    </div>
    <!-- Total Check-ins -->
    <div class="col-6 col-md-4 col-xl">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--card-amber-bg);color:var(--card-amber-fg);">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="kpi-value counter" data-target="<?= $totalCheckIns ?>"><?= number_format($totalCheckIns) ?></div>
            <div class="kpi-label">Total Check-ins</div>
        </div>
    </div>
    <!-- Avg Turnout -->
    <div class="col-6 col-md-4 col-xl">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(139,92,246,.1);color:#7c3aed;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="kpi-value"><?= $avgTurnout ?>%</div>
            <div class="kpi-label">Avg. Turnout</div>
        </div>
    </div>
</div>

<!-- Top Event Banner -->
<?php if ($topEventName !== '—'): ?>
<div class="top-event-banner mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="top-event-icon"><i class="fas fa-trophy"></i></div>
        <div>
            <div class="top-event-sub">🏆 Top Attended Event</div>
            <div class="top-event-title"><?= htmlspecialchars($topEventName) ?></div>
        </div>
        <div class="ms-auto text-end">
            <div class="top-event-count"><?= number_format($topEventCnt) ?></div>
            <div class="top-event-sub">check-ins</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Charts Row 1: Monthly Trend + Event Status ────────────────────────── -->
<div class="row g-4 mb-4">
    <!-- Monthly Attendance Trend -->
    <div class="col-lg-8">
        <div class="glass-panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">Monthly Attendance Trend</h5>
                    <small class="text-muted">Check-ins over the past 12 months</small>
                </div>
                <span class="badge bg-light text-secondary border">Last 12 Months</span>
            </div>
            <div style="position:relative;height:270px;">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Event Status Distribution -->
    <div class="col-lg-4">
        <div class="glass-panel p-4 h-100">
            <div class="mb-4">
                <h5 class="fw-bold mb-0">Event Status</h5>
                <small class="text-muted">Distribution by lifecycle stage</small>
            </div>
            <div class="d-flex align-items-center justify-content-center" style="position:relative;height:180px;">
                <canvas id="eventStatusChart"></canvas>
            </div>
            <div id="statusLegend" class="mt-3"></div>
        </div>
    </div>
</div>

<!-- ── Charts Row 2: Gender Stacked Bar + Turnout Line ───────────────────── -->
<div class="row g-4 mb-4">
    <!-- Gender Stacked by Event -->
    <div class="col-lg-7">
        <div class="glass-panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">Gender Participation</h5>
                    <small class="text-muted">Male vs Female attendance per event</small>
                </div>
                <span class="badge bg-light text-secondary border">Last 8 Events</span>
            </div>
            <div style="position:relative;height:260px;">
                <canvas id="genderStackedChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Classic Turnout Line + Member Status Doughnut side by side -->
    <div class="col-lg-5">
        <div class="glass-panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-0">Event Turnout</h5>
                    <small class="text-muted">Last 6 events attendance</small>
                </div>
            </div>
            <div style="position:relative;height:200px;">
                <canvas id="turnoutChart"></canvas>
            </div>
            <div class="row text-center mt-3 pt-3 border-top">
                <div class="col-6">
                    <small class="text-muted d-block">Active</small>
                    <strong class="text-success font-monospace fs-5"><?= number_format($activeMembers) ?></strong>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Inactive</small>
                    <strong class="text-danger font-monospace fs-5"><?= number_format($totalMembers - $activeMembers) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Activity Feed ──────────────────────────────────────────────── -->
<div class="glass-panel p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0">Recent Check-ins</h5>
        <a href="reports.php" class="btn btn-sm btn-outline-secondary">View All Reports</a>
    </div>
    <?php if (empty($recentActivities)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-clipboard-list fa-3x mb-3 text-light"></i>
            <p class="mb-0">No recent attendances found.</p>
        </div>
    <?php else: ?>
        <div class="activity-feed">
            <?php
            $bgClasses = ['bg-primary','bg-success','bg-warning','bg-info','bg-danger'];
            foreach ($recentActivities as $idx => $act):
                $initials = getInitials($act['full_name']);
                $charSum  = array_sum(array_map('ord', str_split($act['full_name'])));
                $bgClass  = $bgClasses[$charSum % count($bgClasses)];
            ?>
            <div class="activity-item d-flex align-items-center justify-content-between px-2">
                <div class="d-flex align-items-center">
                    <div class="avatar-initial me-3 <?= $bgClass ?> bg-opacity-75">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <?= htmlspecialchars($act['full_name']) ?>
                            <span class="text-secondary small font-monospace ms-2">(No. <?= htmlspecialchars($act['member_no']) ?>)</span>
                        </h6>
                        <small class="text-muted">Checked in for <strong class="text-primary"><?= htmlspecialchars($act['title']) ?></strong></small>
                    </div>
                </div>
                <div class="text-end text-muted small">
                    <i class="far fa-clock me-1"></i> <?= date('M d, h:i A', strtotime($act['attended_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// ── PHP data for Chart.js (Turnout chart - kept inline) ──────────────────
$chartEvents = $pdo->query("
    SELECT a.title, COUNT(at.id) AS attendance_count
    FROM events a LEFT JOIN attendance at ON a.id = at.event_id
    GROUP BY a.id, a.title, a.event_date
    ORDER BY a.event_date ASC LIMIT 6
")->fetchAll();
$turnoutLabels = json_encode(array_column($chartEvents, 'title'));
$turnoutData   = json_encode(array_map('intval', array_column($chartEvents, 'attendance_count')));
?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── Animated counters ────────────────────────────────────────────── */
    document.querySelectorAll('.counter[data-target]').forEach(el => {
        const target = parseInt(el.dataset.target, 10);
        if (target === 0) return;
        let start = 0;
        const duration = 1200;
        const step = target / (duration / 16);
        const timer = setInterval(() => {
            start = Math.min(start + step, target);
            el.textContent = Math.floor(start).toLocaleString();
            if (start >= target) clearInterval(timer);
        }, 16);
    });

    /* ── Shared chart theme ───────────────────────────────────────────── */
    const tooltipDefaults = {
        padding: 12, cornerRadius: 8,
        backgroundColor: '#0d2423', titleColor: '#fff', bodyColor: '#fff',
    };
    const tickFont = { family: 'Inter', size: 11 };
    const gridColor = 'rgba(0,0,0,0.05)';

    /* ── 1. Monthly Trend Chart ───────────────────────────────────────── */
    fetch('analytics_data.php?type=monthly_trend')
        .then(r => r.json())
        .then(d => {
            const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
            const grad = ctx.createLinearGradient(0, 0, 0, 270);
            grad.addColorStop(0, 'rgba(43,115,112,0.35)');
            grad.addColorStop(1, 'rgba(43,115,112,0.01)');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Check-ins',
                        data: d.data,
                        borderColor: '#2b7370',
                        backgroundColor: grad,
                        borderWidth: 2.5,
                        fill: true, tension: 0.45,
                        pointBackgroundColor: '#2b7370',
                        pointBorderColor: '#fff', pointBorderWidth: 2,
                        pointRadius: 4, pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: tooltipDefaults },
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: '#64748b', font: tickFont, stepSize: 1 } },
                        x: { grid: { display: false }, ticks: { color: '#64748b', font: tickFont, maxRotation: 45 } }
                    }
                }
            });
        });

    /* ── 2. Event Status Doughnut ─────────────────────────────────────── */
    fetch('analytics_data.php?type=event_status_counts')
        .then(r => r.json())
        .then(d => {
            const labels  = ['Upcoming', 'Ongoing', 'Completed'];
            const values  = [d.upcoming, d.ongoing, d.completed];
            const colors  = ['#3b82f6', '#f59e0b', '#10b981'];
            const ctx = document.getElementById('eventStatusChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: colors, borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '70%',
                    plugins: { legend: { display: false }, tooltip: tooltipDefaults }
                }
            });
            // Custom legend
            const lg = document.getElementById('statusLegend');
            labels.forEach((lbl, i) => {
                lg.innerHTML += `
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="d-flex align-items-center gap-2">
                      <span style="width:10px;height:10px;border-radius:50%;background:${colors[i]};display:inline-block;"></span>
                      <small class="text-muted">${lbl}</small>
                    </div>
                    <strong class="small">${values[i]}</strong>
                  </div>`;
            });
        });

    /* ── 3. Gender Stacked Bar ────────────────────────────────────────── */
    fetch('analytics_data.php?type=gender_by_event')
        .then(r => r.json())
        .then(d => {
            const ctx = document.getElementById('genderStackedChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        { label: 'Male',   data: d.male,   backgroundColor: 'rgba(37,99,235,0.75)',  borderRadius: 4, borderSkipped: false },
                        { label: 'Female', data: d.female, backgroundColor: 'rgba(236,72,153,0.75)', borderRadius: 4, borderSkipped: false }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { padding: 16, color: '#475569', font: tickFont, usePointStyle: true } },
                        tooltip: { ...tooltipDefaults, mode: 'index', intersect: false }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { color: '#64748b', font: tickFont } },
                        y: { stacked: true, beginAtZero: true, grid: { color: gridColor }, ticks: { color: '#64748b', font: tickFont, stepSize: 1 } }
                    }
                }
            });
        });

    /* ── 4. Classic Event Turnout (last 6) ───────────────────────────── */
    const turnoutCtx = document.getElementById('turnoutChart').getContext('2d');
    const tGrad = turnoutCtx.createLinearGradient(0, 0, 0, 200);
    tGrad.addColorStop(0, 'rgba(43,115,112,0.35)');
    tGrad.addColorStop(1, 'rgba(43,115,112,0.01)');
    new Chart(turnoutCtx, {
        type: 'line',
        data: {
            labels: <?= $turnoutLabels ?>,
            datasets: [{
                label: 'Attended',
                data: <?= $turnoutData ?>,
                borderColor: '#2b7370', backgroundColor: tGrad,
                borderWidth: 2.5, fill: true, tension: 0.4,
                pointBackgroundColor: '#2b7370', pointBorderColor: '#fff',
                pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: tooltipDefaults },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: '#64748b', font: tickFont, stepSize: 1 } },
                x: { grid: { display: false }, ticks: { color: '#64748b', font: tickFont, maxRotation: 30 } }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>