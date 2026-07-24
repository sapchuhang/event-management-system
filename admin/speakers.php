<?php
// admin/speakers.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

// Fetch speakers
$stmt = $pdo->query("SELECT * FROM speakers ORDER BY name ASC");
$speakers = $stmt->fetchAll();

$pageTitle = 'Speakers';
require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Speakers</h4>
    <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSpeakerModal">
        <i class="fas fa-plus me-2"></i> Add Speaker
    </button>
</div>

<!-- Grid of Speaker Cards -->
<div class="row g-4">
    <?php if (empty($speakers)): ?>
        <div class="col-12">
            <div class="glass-panel p-5 text-center">
                <i class="fas fa-user-tie text-muted fa-3x mb-3"></i>
                <h5 class="fw-bold">No Speakers Registered</h5>
                <p class="text-muted mb-3">Add speaker profiles first to link them with event agenda items.</p>
                <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSpeakerModal">
                    <i class="fas fa-plus me-2"></i> Add Speaker
                </button>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($speakers as $speaker): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 stat-card p-4 d-flex flex-column align-items-center text-center">
                    <!-- Profile Picture -->
                    <div class="mb-3 position-relative">
                        <?php if ($speaker['photo_path'] && file_exists('../' . $speaker['photo_path'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($speaker['photo_path']) ?>" alt="Photo" 
                                 class="rounded-circle shadow-sm" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid var(--secondary);">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center shadow-sm" 
                                 style="width: 100px; height: 100px; border: 3px solid #ccc;">
                                <i class="fas fa-user-tie text-secondary fa-3x"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Speaker Info -->
                    <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($speaker['name']) ?></h5>
                    <?php if ($speaker['title']): ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-3 px-3 py-1"><?= htmlspecialchars($speaker['title']) ?></span>
                    <?php endif; ?>

                    <!-- Biography Summary -->
                    <p class="text-muted small mb-4 flex-grow-1 text-start" style="display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;">
                        <?= htmlspecialchars($speaker['bio'] ?: 'No biography provided.') ?>
                    </p>

                    <!-- Contact Details -->
                    <div class="w-100 border-top pt-3 text-start small mb-3">
                        <?php if ($speaker['email']): ?>
                            <div class="text-truncate text-muted mb-1">
                                <i class="far fa-envelope text-primary me-2"></i> 
                                <a href="mailto:<?= htmlspecialchars($speaker['email']) ?>" class="text-decoration-none text-secondary"><?= htmlspecialchars($speaker['email']) ?></a>
                            </div>
                        <?php endif; ?>
                        <?php if ($speaker['website']): ?>
                            <div class="text-truncate text-muted">
                                <i class="fas fa-globe text-success me-2"></i> 
                                <a href="<?= htmlspecialchars($speaker['website']) ?>" target="_blank" class="text-decoration-none text-secondary"><?= htmlspecialchars($speaker['website']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex w-100 gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-outline-primary w-50 btn-edit-speaker" 
                                data-id="<?= $speaker['id'] ?>"
                                data-name="<?= htmlspecialchars($speaker['name']) ?>"
                                data-title="<?= htmlspecialchars($speaker['title'] ?? '') ?>"
                                data-bio="<?= htmlspecialchars($speaker['bio'] ?? '') ?>"
                                data-email="<?= htmlspecialchars($speaker['email'] ?? '') ?>"
                                data-website="<?= htmlspecialchars($speaker['website'] ?? '') ?>"
                                data-photo="<?= htmlspecialchars($speaker['photo_path'] ? BASE_URL . $speaker['photo_path'] : '') ?>"
                                data-bs-toggle="modal" data-bs-target="#editSpeakerModal">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        
                        <form method="POST" action="../actions/delete_speaker_action.php" class="w-50" 
                              onsubmit="return confirm('Are you sure you want to delete this speaker profile? Agendas linked to this speaker will be set to no speaker.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $speaker['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- Modal: Add Speaker -->
<!-- ========================================== -->
<div class="modal fade" id="addSpeakerModal" tabindex="-1" aria-labelledby="addSpeakerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="../actions/add_speaker_action.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold" id="addSpeakerModalLabel"><i class="fas fa-user-plus me-2"></i>Add Speaker Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Dr. John Doe" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Job Title / Company</label>
                        <input type="text" name="title" class="form-control" placeholder="Lead Architect at Acme Corp">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Biography</label>
                        <textarea name="bio" class="form-control" rows="4" placeholder="Brief details about the speaker's background, achievements..."></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold text-secondary">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="john.doe@example.com">
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold text-secondary">Website URL</label>
                            <input type="url" name="website" class="form-control" placeholder="https://example.com">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Profile Image</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <div class="form-text text-muted">Max file size: 2MB. Formats: JPG, PNG, GIF.</div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- Modal: Edit Speaker -->
<!-- ========================================== -->
<div class="modal fade" id="editSpeakerModal" tabindex="-1" aria-labelledby="editSpeakerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="../actions/edit_speaker_action.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="id" id="edit_speaker_id">
                
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold" id="editSpeakerModalLabel"><i class="fas fa-user-edit me-2"></i>Edit Speaker Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- Current Photo Preview -->
                    <div class="text-center mb-3">
                        <img id="edit_photo_preview" src="" alt="Preview" class="rounded-circle shadow-sm d-none" 
                             style="width: 80px; height: 80px; object-fit: cover; border: 2px solid var(--secondary);">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_speaker_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Job Title / Company</label>
                        <input type="text" name="title" id="edit_speaker_title" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Biography</label>
                        <textarea name="bio" id="edit_speaker_bio" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold text-secondary">Email Address</label>
                            <input type="email" name="email" id="edit_speaker_email" class="form-control">
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold text-secondary">Website URL</label>
                            <input type="url" name="website" id="edit_speaker_website" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary">Replace Profile Image</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <div class="form-text text-muted">Leave empty to keep current photo. Max: 2MB.</div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Register jQuery handler once document is ready (included in footer)
    document.addEventListener('DOMContentLoaded', function () {
        // Event delegation for dynamically added or loaded elements
        $(document).on('click', '.btn-edit-speaker', function () {
            $('#edit_speaker_id').val($(this).data('id'));
            $('#edit_speaker_name').val($(this).data('name'));
            $('#edit_speaker_title').val($(this).data('title'));
            $('#edit_speaker_bio').val($(this).data('bio'));
            $('#edit_speaker_email').val($(this).data('email'));
            $('#edit_speaker_website').val($(this).data('website'));
            
            const photoUrl = $(this).data('photo');
            const previewImg = $('#edit_photo_preview');
            if (photoUrl) {
                previewImg.attr('src', photoUrl).removeClass('d-none');
            } else {
                previewImg.addClass('d-none').attr('src', '');
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
