<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
$events = $stmt->fetchAll();

require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Events</h4>
    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addEventModal"><i
            class="fas fa-plus me-2"></i> New Event</button>
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
                        <td class="fw-medium"><?= htmlspecialchars($event['title']) ?></td>
                        <td><?= htmlspecialchars($event['event_date']) ?></td>
                        <td><?= htmlspecialchars($event['location']) ?></td>
                        <td>
                            <?php
                            $badge = 'secondary';
                            if ($event['status'] == 'upcoming')
                                $badge = 'primary';
                            if ($event['status'] == 'ongoing')
                                $badge = 'success';
                            if ($event['status'] == 'completed')
                                $badge = 'dark';
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($event['status']) ?></span>
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
                                data-status="<?= htmlspecialchars($event['status']) ?>" title="Edit Event">
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
                        <input type="text" name="title" class="form-control" required
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