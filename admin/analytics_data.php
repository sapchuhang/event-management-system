<?php
// admin/analytics_data.php
// Lightweight JSON API endpoint for dashboard chart data
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$type = $_GET['type'] ?? '';

try {
    switch ($type) {

        // ── Last 12 months of total check-ins ────────────────────────────
        case 'monthly_trend':
            $restrictedTables = getRestrictedTables();
            if ($restrictedTables !== null) {
                if (empty($restrictedTables)) {
                    $rows = [];
                } else {
                    $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
                    $stmtTrend = $pdo->prepare("
                        SELECT
                            DATE_FORMAT(a.attended_at, '%Y-%m') AS ym,
                            DATE_FORMAT(a.attended_at, '%b %Y')  AS label,
                            COUNT(*)                            AS total
                        FROM attendance a
                        JOIN members m ON a.member_id = m.id
                        WHERE a.attended_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                          AND m.table_no IN ($inClause)
                        GROUP BY ym, label
                        ORDER BY ym ASC
                    ");
                    $stmtTrend->execute($restrictedTables);
                    $rows = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $rows = $pdo->query("
                    SELECT
                        DATE_FORMAT(attended_at, '%Y-%m') AS ym,
                        DATE_FORMAT(attended_at, '%b %Y')  AS label,
                        COUNT(*)                            AS total
                    FROM attendance
                    WHERE attended_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY ym, label
                    ORDER BY ym ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
            }

            // Fill all 12 months even if some have zero data
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $key   = date('Y-m', strtotime("-$i months"));
                $label = date('M Y',  strtotime("-$i months"));
                $months[$key] = ['label' => $label, 'total' => 0];
            }
            foreach ($rows as $r) {
                if (isset($months[$r['ym']])) {
                    $months[$r['ym']]['total'] = (int) $r['total'];
                }
            }
            $out = array_values($months);
            echo json_encode([
                'labels' => array_column($out, 'label'),
                'data'   => array_column($out, 'total'),
            ]);
            break;

        // ── Event status breakdown ────────────────────────────────────────
        case 'event_status_counts':
            $rows = $pdo->query("
                SELECT status, COUNT(*) AS cnt
                FROM events
                GROUP BY status
            ")->fetchAll(PDO::FETCH_KEY_PAIR);

            echo json_encode([
                'upcoming'  => (int)($rows['upcoming']  ?? 0),
                'ongoing'   => (int)($rows['ongoing']   ?? 0),
                'completed' => (int)($rows['completed'] ?? 0),
            ]);
            break;

        // ── Gender-stacked attendance per last 8 events ───────────────────
        case 'gender_by_event':
            $events = $pdo->query("
                SELECT id, title FROM events
                ORDER BY event_date DESC
                LIMIT 8
            ")->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $male   = [];
            $female = [];

            foreach (array_reverse($events) as $ev) {
                $labels[] = strlen($ev['title']) > 20
                    ? substr($ev['title'], 0, 18) . '…'
                    : $ev['title'];

                $restrictedTables = getRestrictedTables();
                if ($restrictedTables !== null) {
                    if (empty($restrictedTables)) {
                        $g = [];
                    } else {
                        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
                        $params = array_merge([$ev['id']], $restrictedTables);
                        $genders = $pdo->prepare("
                            SELECT m.gender, COUNT(*) AS cnt
                            FROM attendance a
                            JOIN members m ON a.member_id = m.id
                            WHERE a.event_id = ? AND m.table_no IN ($inClause)
                            GROUP BY m.gender
                        ");
                        $genders->execute($params);
                        $g = $genders->fetchAll(PDO::FETCH_KEY_PAIR);
                    }
                } else {
                    $genders = $pdo->prepare("
                        SELECT m.gender, COUNT(*) AS cnt
                        FROM attendance a
                        JOIN members m ON a.member_id = m.id
                        WHERE a.event_id = ?
                        GROUP BY m.gender
                    ");
                    $genders->execute([$ev['id']]);
                    $g = $genders->fetchAll(PDO::FETCH_KEY_PAIR);
                }

                $male[]   = (int)($g['Male']   ?? 0);
                $female[] = (int)($g['Female'] ?? 0);
            }

            echo json_encode([
                'labels' => $labels,
                'male'   => $male,
                'female' => $female,
            ]);
            break;

        // ── Overall summary KPIs ──────────────────────────────────────────
        case 'summary_kpis':
            $restrictedTables = getRestrictedTables();
            if ($restrictedTables !== null) {
                if (empty($restrictedTables)) {
                    $totalMembers  = 0;
                    $activeMembers = 0;
                    $totalEvents   = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
                    $totalCheckIns = 0;
                    $avgTurnout    = 0;
                    $topRow        = ['title' => '—', 'cnt' => 0];
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
                    $evRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $avgTurnout = 0;
                    if ($totalMembers > 0 && count($evRows) > 0) {
                        $sumPct = 0;
                        foreach ($evRows as $r) {
                            $sumPct += ($r['attended'] / $totalMembers) * 100;
                        }
                        $avgTurnout = round($sumPct / count($evRows), 1);
                    }

                    $stmt = $pdo->prepare("
                        SELECT e.title, COUNT(a.id) AS cnt
                        FROM events e
                        LEFT JOIN attendance a ON e.id = a.event_id
                        LEFT JOIN members m ON a.member_id = m.id AND m.table_no IN ($inClause)
                        GROUP BY e.id, e.title
                        ORDER BY cnt DESC
                        LIMIT 1
                    ");
                    $stmt->execute($restrictedTables);
                    $topRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } else {
                $totalMembers  = (int) $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
                $activeMembers = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
                $totalEvents   = (int) $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
                $totalCheckIns = (int) $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();

                // Average turnout % per event
                $evRows = $pdo->query("
                    SELECT e.id, COUNT(a.id) AS attended
                    FROM events e
                    LEFT JOIN attendance a ON e.id = a.event_id
                    GROUP BY e.id
                ")->fetchAll(PDO::FETCH_ASSOC);

                $avgTurnout = 0;
                if ($totalMembers > 0 && count($evRows) > 0) {
                    $sumPct = 0;
                    foreach ($evRows as $r) {
                        $sumPct += ($r['attended'] / $totalMembers) * 100;
                    }
                    $avgTurnout = round($sumPct / count($evRows), 1);
                }

                // Top event by attendance
                $topRow = $pdo->query("
                    SELECT e.title, COUNT(a.id) AS cnt
                    FROM events e
                    LEFT JOIN attendance a ON e.id = a.event_id
                    GROUP BY e.id, e.title
                    ORDER BY cnt DESC
                    LIMIT 1
                ")->fetch(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'total_members'  => $totalMembers,
                'active_members' => $activeMembers,
                'total_events'   => $totalEvents,
                'total_checkins' => $totalCheckIns,
                'avg_turnout'    => $avgTurnout,
                'top_event_name' => $topRow['title'] ?? '—',
                'top_event_cnt'  => (int)($topRow['cnt'] ?? 0),
            ]);
            break;

        // ── Per-event stats for reports page ─────────────────────────────
        case 'event_stats':
            $restrictedTables = getRestrictedTables();
            if ($restrictedTables !== null) {
                if (empty($restrictedTables)) {
                    $totalActive = 0;
                    $totalMale = 0;
                    $totalFemale = 0;
                    $events = [];
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

                    $stmt = $pdo->prepare("
                        SELECT e.*,
                               COUNT(a.id)                                                  AS attended,
                               SUM(m.gender = 'Male'   AND a.id IS NOT NULL)                AS male_attended,
                               SUM(m.gender = 'Female' AND a.id IS NOT NULL)                AS female_attended
                        FROM events e
                        LEFT JOIN attendance a  ON e.id = a.event_id
                        LEFT JOIN members    m  ON a.member_id = m.id AND m.status = 'active' AND m.table_no IN ($inClause)
                        GROUP BY e.id
                        ORDER BY e.event_date DESC
                    ");
                    $stmt->execute($restrictedTables);
                    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $totalActive = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active'")->fetchColumn();
                $totalMale   = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active' AND gender='Male'")->fetchColumn();
                $totalFemale = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status='active' AND gender='Female'")->fetchColumn();

                $events = $pdo->query("
                    SELECT e.*,
                           COUNT(a.id)                                                  AS attended,
                           SUM(m.gender = 'Male'   AND a.id IS NOT NULL)                AS male_attended,
                           SUM(m.gender = 'Female' AND a.id IS NOT NULL)                AS female_attended
                    FROM events e
                    LEFT JOIN attendance a  ON e.id = a.event_id
                    LEFT JOIN members    m  ON a.member_id = m.id AND m.status = 'active'
                    GROUP BY e.id
                    ORDER BY e.event_date DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
            }

            $out = [];
            foreach ($events as $ev) {
                $attended = (int)$ev['attended'];
                $pct = $totalActive > 0 ? round(($attended / $totalActive) * 100, 1) : 0;
                $out[] = [
                    'id'              => $ev['id'],
                    'title'           => $ev['title'],
                    'event_date'      => $ev['event_date'],
                    'location'        => $ev['location'] ?? '',
                    'status'          => $ev['status'],
                    'total_members'   => $totalActive,
                    'attended'        => $attended,
                    'absent'          => $totalActive - $attended,
                    'turnout_pct'     => $pct,
                    'male_attended'   => (int)($ev['male_attended']   ?? 0),
                    'female_attended' => (int)($ev['female_attended'] ?? 0),
                    'total_male'      => $totalMale,
                    'total_female'    => $totalFemale,
                ];
            }

            echo json_encode([
                'events'       => $out,
                'total_active' => $totalActive,
                'total_male'   => $totalMale,
                'total_female' => $totalFemale,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown type: ' . htmlspecialchars($type)]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
