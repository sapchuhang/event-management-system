<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
$events = $stmt->fetchAll();
foreach ($events as &$e) {
    if (isAgmEvent($e['title'])) {
        $e['allowance_amount'] = 500.00;
    }
}
unset($e);

require_once '../includes/header.php';
?>
<div class="page-header mb-4">
    <h4>Events</h4>
    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="fas fa-plus"></i> New Event
    </button>
</div>

<div class="glass-panel p-4">


    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="fw-medium">
                            <?= htmlspecialchars($event['title']) ?>
                            <?php if ((float)$event['allowance_amount'] > 0): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-money-bill-wave me-1"></i>NPR <?= number_format($event['allowance_amount'], 2) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($event['event_date']) ?></td>
                        <td><?= htmlspecialchars($event['location']) ?></td>
                        <td>
                            <?php
                            $statusClass = match($event['status']) {
                                'upcoming'  => 'badge-upcoming',
                                'ongoing'   => 'badge-ongoing',
                                'completed' => 'badge-completed',
                                default     => 'badge-secondary',
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($event['status']) ?></span>
                        </td>
                        <td>
                            <a href="attendance.php?event_id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-success me-1"
                                title="Attendance">
                                <i class="fas fa-clipboard-check me-1"></i>Attendance
                            </a>
                            <a href="agenda.php?event_id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-info me-1"
                                title="Agenda">
                                <i class="fas fa-list me-1"></i>Agenda
                            </a>
                            <a href="event_report.php?event_id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-primary me-1"
                                title="Print Report" target="_blank">
                                <i class="fas fa-print me-1"></i>Report
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit-event"
                                data-bs-toggle="modal" data-bs-target="#editEventModal" data-id="<?= $event['id'] ?>"
                                data-title="<?= htmlspecialchars($event['title']) ?>"
                                data-date="<?= htmlspecialchars($event['event_date']) ?>"
                                data-location="<?= htmlspecialchars($event['location']) ?>"
                                data-status="<?= htmlspecialchars($event['status']) ?>" 
                                data-allowance="<?= htmlspecialchars($event['allowance_amount']) ?>" title="Edit Event">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <form method="POST" action="../actions/delete_event_action.php" class="d-inline"
                                onsubmit="return confirm('Are you sure you want to delete this Event? All related attendance and agenda records will also be permanently deleted.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Event">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No Events scheduled yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom border-light">
                <h5 class="modal-title fw-bold">Schedule New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../actions/add_event_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Title</label>
                        <input type="text" id="add_event_title" name="title" class="form-control" required
                            placeholder="e.g. 5th Annual General Meeting">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Date</label>
                        <input type="text" id="nepali-datepicker" name="event_date" class="form-control"
                            placeholder="Click to select B.S. date" required readonly
                            style="cursor:pointer; background:#fff;">

                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Location</label>
                        <input type="text" name="location" class="form-control" required>
                    </div>
                    <!-- Toggle switch for Has Allowance -->
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="add_has_allowance" name="has_allowance" value="1">
                        <label class="form-check-label fw-medium text-dark" for="add_has_allowance">Provide Transportation Allowance</label>
                    </div>
                    <div class="mb-3" id="add_allowance_container" style="display: none;">
                        <label class="form-label fw-medium">Transportation Allowance (per member)</label>
                        <div class="input-group">
                            <span class="input-group-text">NPR</span>
                            <input type="number" step="0.01" min="0" id="add_event_allowance_amount" name="allowance_amount" class="form-control" value="0.00" required>
                        </div>
                        <div class="form-text text-muted">Set the amount paid to members who attend this event.</div>
                    </div>
                </div>
                <div class="modal-footer border-top border-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom border-light">
                <h5 class="modal-title fw-bold">Edit Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../actions/edit_event_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" id="edit_event_id" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Title</label>
                        <input type="text" id="edit_event_title" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Date</label>
                        <input type="text" id="edit-nepali-datepicker" name="event_date" class="form-control"
                            placeholder="Click to select B.S. date" required readonly
                            style="cursor:pointer; background:#fff;">
                        <input type="hidden" id="edit_event_date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Location</label>
                        <input type="text" id="edit_event_location" name="location" class="form-control" required>
                    </div>
                    <!-- Toggle switch for Has Allowance -->
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit_has_allowance" name="has_allowance" value="1">
                        <label class="form-check-label fw-medium text-dark" for="edit_has_allowance">Provide Transportation Allowance</label>
                    </div>
                    <div class="mb-3" id="edit_allowance_container" style="display: none;">
                        <label class="form-label fw-medium">Transportation Allowance (per member)</label>
                        <div class="input-group">
                            <span class="input-group-text">NPR</span>
                            <input type="number" step="0.01" min="0" id="edit_event_allowance_amount" name="allowance_amount" class="form-control" required>
                        </div>
                        <div class="form-text text-muted">Set the amount paid to members who attend this event.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Status</label>
                        <select id="edit_event_status" name="status" class="form-select" required>
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top border-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>