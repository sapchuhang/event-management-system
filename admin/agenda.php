<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$event_id = $_GET['event_id'] ?? null;
if (!$event_id) {
    // get the latest AGM
    $stmt = $pdo->query("SELECT id, title FROM events ORDER BY id DESC LIMIT 1");
    $event = $stmt->fetch();
    if($event) $event_id = $event['id'];
} else {
    $stmt = $pdo->prepare("SELECT id, title FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
}

// Fetch speakers for select list
$speakers = [];
try {
    $stmt = $pdo->query("SELECT id, name, title FROM speakers ORDER BY name ASC");
    $speakers = $stmt->fetchAll();
} catch (PDOException $e) {
    // speakers table doesn't exist
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event_id) {
    // Validate CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        setFlashMessage('error', 'Security check failed. Please try again.');
        header("Location: agenda.php?event_id=$event_id");
        exit;
    }

    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $speaker_id = !empty($_POST['speaker_id']) ? (int)$_POST['speaker_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO agendas (event_id, title, description, start_time, end_time, speaker_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$event_id, $title, $desc, $start_time, $end_time, $speaker_id]);
    setFlashMessage('success', 'Agenda added successfully!');
    header("Location: agenda.php?event_id=$event_id");
    exit;
}

$agendas = [];
if ($event_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, s.name as speaker_name, s.title as speaker_title, s.bio as speaker_bio, 
                   s.photo_path as speaker_photo, s.email as speaker_email, s.website as speaker_website 
            FROM agendas a 
            LEFT JOIN speakers s ON a.speaker_id = s.id 
            WHERE a.event_id = ? 
            ORDER BY a.start_time IS NULL ASC, a.start_time ASC, a.id ASC
        ");
        $stmt->execute([$event_id]);
        $agendas = $stmt->fetchAll();
    } catch(PDOException $e) {
        // Fallback to basic query if schema is not updated
        $stmt = $pdo->prepare("SELECT * FROM agendas WHERE event_id = ? ORDER BY id ASC");
        $stmt->execute([$event_id]);
        $agendas = $stmt->fetchAll();
    }
}

require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Agenda <?= $event ? '- ' . htmlspecialchars($event['title']) : '' ?></h4>
    <a href="events.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back to Events</a>
</div>

<?php if (!$event): ?>
<div class="alert alert-warning">No Events available.</div>
<?php else: ?>

<div class="row">
    <div class="col-md-4">  
        <div class="glass-panel p-4 mb-4">
            <h5 class="fw-bold mb-3">Add Agenda Item</h5>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Opening Remarks" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief session summary..."></textarea>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label fw-semibold text-secondary">Start Time</label>
                        <input type="time" name="start_time" class="form-control">
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold text-secondary">End Time</label>
                        <input type="time" name="end_time" class="form-control">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary">Speaker</label>
                    <select name="speaker_id" class="form-select">
                        <option value="">-- No Speaker --</option>
                        <?php foreach ($speakers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['title'] ?? 'N/A') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary-custom w-100">Add Agenda Item</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="glass-panel p-4">
            <h5 class="fw-bold mb-4">Agenda List</h5>
            <?php if(empty($agendas)): ?>
                <p class="text-muted text-center py-4">No agenda items added yet.</p>
            <?php else: ?>
                <div class="timeline mt-3">
                    <?php foreach($agendas as $i => $agenda): ?>
                    <div class="timeline-item">
                        <!-- Marker -->
                        <div class="timeline-marker">
                            <i class="far fa-clock"></i>
                        </div>
                        
                        <!-- Content Card -->
                        <div class="timeline-card p-4">
                            <!-- Time and Delete Row -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <?php if ($agenda['start_time']): ?>
                                        <div class="timeline-time">
                                            <i class="far fa-clock me-1"></i>
                                            <?= date('h:i A', strtotime($agenda['start_time'])) ?>
                                            <?php if ($agenda['end_time']): ?>
                                                - <?= date('h:i A', strtotime($agenda['end_time'])) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="timeline-time bg-secondary-subtle text-secondary">
                                            <i class="far fa-clock me-1"></i> Unscheduled
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" action="../actions/delete_agenda_action.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this agenda item?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= $agenda['id'] ?>">
                                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Agenda Item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Agenda Details -->
                            <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($agenda['title']) ?></h5>
                            <?php if ($agenda['description']): ?>
                                <p class="text-secondary small mb-3"><?= nl2br(htmlspecialchars($agenda['description'])) ?></p>
                            <?php endif; ?>
                            
                            <!-- Speaker Profile Card if linked -->
                            <?php if (isset($agenda['speaker_id']) && $agenda['speaker_id']): ?>
                                <div class="mt-3 border-top pt-2">
                                    <button type="button" class="speaker-badge-btn"
                                            data-name="<?= htmlspecialchars($agenda['speaker_name']) ?>"
                                            data-title="<?= htmlspecialchars($agenda['speaker_title'] ?? '') ?>"
                                            data-bio="<?= htmlspecialchars($agenda['speaker_bio'] ?? 'No biography details.') ?>"
                                            data-email="<?= htmlspecialchars($agenda['speaker_email'] ?? '') ?>"
                                            data-website="<?= htmlspecialchars($agenda['speaker_website'] ?? '') ?>"
                                            data-photo="<?= htmlspecialchars($agenda['speaker_photo'] ? BASE_URL . $agenda['speaker_photo'] : '') ?>">
                                        <?php if ($agenda['speaker_photo'] && file_exists('../' . $agenda['speaker_photo'])): ?>
                                            <img src="<?= BASE_URL . htmlspecialchars($agenda['speaker_photo']) ?>" alt="Speaker Avatar" class="rounded-circle speaker-avatar-mini">
                                        <?php else: ?>
                                            <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center speaker-avatar-mini"><i class="fas fa-user" style="font-size: 0.75rem;"></i></span>
                                        <?php endif; ?>
                                        <span>
                                            <strong class="text-dark"><?= htmlspecialchars($agenda['speaker_name']) ?></strong>
                                            <?php if ($agenda['speaker_title']): ?>
                                                <small class="text-muted d-block" style="font-size: 0.7rem;"><?= htmlspecialchars($agenda['speaker_title']) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    $(document).on('click', '.speaker-badge-btn', function () {
        const name = $(this).data('name');
        const title = $(this).data('title');
        const bio = $(this).data('bio');
        const email = $(this).data('email');
        const website = $(this).data('website');
        const photo = $(this).data('photo');

        let photoHtml = `
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center shadow-sm mb-3" style="width: 90px; height: 90px; border: 3px solid var(--secondary);">
                <i class="fas fa-user-tie text-secondary fa-3x"></i>
            </div>
        `;
        if (photo) {
            photoHtml = `<img src="${photo}" class="rounded-circle shadow-sm mb-3" style="width: 90px; height: 90px; object-fit: cover; border: 3px solid var(--secondary);" />`;
        }

        let linksHtml = '';
        if (email || website) {
            linksHtml = `
                <div class="border-top pt-3 text-start small mb-3">
                    ${email ? `<div class="mb-1"><i class="far fa-envelope text-primary me-2"></i> <a href="mailto:${email}" class="text-decoration-none text-secondary">${email}</a></div>` : ''}
                    ${website ? `<div><i class="fas fa-globe text-success me-2"></i> <a href="${website}" target="_blank" class="text-decoration-none text-secondary">${website}</a></div>` : ''}
                </div>
            `;
        }

        Swal.fire({
            title: 'Speaker Profile',
            html: `
                <div class="text-center">
                    ${photoHtml}
                    <h4 class="fw-bold mb-1">${name}</h4>
                    ${title ? `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-3 px-3 py-1">${title}</span>` : ''}
                </div>
                <div class="text-start mt-2">
                    <p class="text-muted small" style="line-height: 1.5; white-space: pre-line;">${bio}</p>
                    ${linksHtml}
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'Close',
            confirmButtonColor: '#083844'
        });
    });
});
</script>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
