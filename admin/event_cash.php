<?php
// admin/event_cash.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin(); // Restricted to administrators

$event_id = $_GET['event_id'] ?? null;
if (!$event_id || !is_numeric($event_id)) {
    setFlashMessage('error', 'Invalid event ID.');
    header('Location: events.php');
    exit;
}

$event_id = (int)$event_id;

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if ($event && isAgmEvent($event['title'])) {
    $event['allowance_amount'] = 500.00;
}

if (!$event) {
    setFlashMessage('error', 'Event not found.');
    header('Location: events.php');
    exit;
}

// Handle POST to update allocations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        setFlashMessage('error', 'Security check failed. Please try again.');
        header('Location: event_cash.php?event_id=' . $event_id);
        exit;
    }

    $allocations = $_POST['allocations'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $stmtInsert = $pdo->prepare("
            INSERT INTO staff_event_cash (event_id, user_id, allocated_amount) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE allocated_amount = VALUES(allocated_amount)
        ");
        
        foreach ($allocations as $user_id => $amount) {
            if (!is_numeric($user_id)) continue;
            $amt = floatval($amount);
            if ($amt < 0) $amt = 0.00;
            
            $stmtInsert->execute([$event_id, (int)$user_id, $amt]);
        }
        
        $pdo->commit();
        setFlashMessage('success', 'Staff cash allocations updated successfully!');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Database error setting staff cash: " . $e->getMessage());
        setFlashMessage('error', 'A database error occurred while saving allocations.');
    }
    
    header('Location: event_cash.php?event_id=' . $event_id);
    exit;
}

// Fetch all users with their cash floats and payouts
$sql = "
    SELECT u.id, u.username, u.role, 
           COALESCE(sec.allocated_amount, 0.00) AS allocated_amount,
           COALESCE(payouts.total_paid, 0.00) AS paid_amount
    FROM admin_users u
    LEFT JOIN staff_event_cash sec ON u.id = sec.user_id AND sec.event_id = :event_id
    LEFT JOIN (
        SELECT marked_by, SUM(allowance_paid) AS total_paid
        FROM attendance 
        WHERE event_id = :event_id2
        GROUP BY marked_by
    ) payouts ON u.id = payouts.marked_by
    ORDER BY u.username ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['event_id' => $event_id, 'event_id2' => $event_id]);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Staff Cash – ' . $event['title'];
require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Staff Cash Floats</h4>
        <p class="text-muted mb-0"><?= htmlspecialchars($event['title']) ?> — <?= htmlspecialchars($event['event_date']) ?></p>
    </div>
    <a href="events.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back to Events</a>
</div>

<!-- Event Info Card -->
<div class="glass-panel p-4 mb-4" style="background-color: var(--bs-primary-bg-subtle);">
    <div class="row align-items-center text-center text-md-start">
        <div class="col-md-8">
            <h5 class="fw-bold mb-1"><i class="fas fa-info-circle me-2 text-primary"></i>Allowance Configurations</h5>
            <p class="mb-0 text-secondary">Members checked in will be paid a transportation allowance of <strong>NPR <?= number_format($event['allowance_amount'], 2) ?></strong>. Staff members require allocated cash floats to mark attendance.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="fs-4 fw-bold text-primary font-monospace">NPR <?= number_format($event['allowance_amount'], 2) ?></span>
            <small class="d-block text-muted">Per Member</small>
        </div>
    </div>
</div>

<div class="glass-panel p-4">
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
        
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Allocated Cash Float</th>
                        <th>Amount Paid Out</th>
                        <th>Remaining Float</th>
                        <th style="width: 200px;">New Allocation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $remaining = $user['allocated_amount'] - $user['paid_amount'];
                        $badgeClass = ($remaining >= $event['allowance_amount']) ? 'success' : 'danger';
                    ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary text-capitalize border border-secondary-subtle px-2 py-1">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                            <td class="font-monospace text-secondary">NPR <?= number_format($user['allocated_amount'], 2) ?></td>
                            <td class="font-monospace text-danger">NPR <?= number_format($user['paid_amount'], 2) ?></td>
                            <td class="font-monospace fw-bold text-<?= $badgeClass ?>">NPR <?= number_format($remaining, 2) ?></td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">NPR</span>
                                    <input type="number" step="0.01" min="0" name="allocations[<?= $user['id'] ?>]" 
                                           class="form-control font-monospace" 
                                           value="<?= htmlspecialchars(number_format($user['allocated_amount'], 2, '.', '')) ?>" required>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary-custom px-4"><i class="fas fa-save me-2"></i> Save All Allocations</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
