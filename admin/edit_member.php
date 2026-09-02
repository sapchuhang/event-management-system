<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: members.php');
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    setFlashMessage('error', 'Member not found.');
    header('Location: members.php');
    exit;
}

$pageTitle = 'Edit Member – ' . $member['full_name'];
require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Edit Member</h2>
    <a href="members.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back</a>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="glass-panel p-4 p-md-5">
            <form action="../actions/edit_member_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($member['id']) ?>">
                <div class="row g-3">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-medium">S.N. <span class="text-muted fw-normal">(Serial No.)</span></label>
                        <input type="number" name="sn" class="form-control" value="<?= htmlspecialchars($member['sn'] ?? '') ?>" placeholder="e.g. 1" min="1">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-medium">Member Number</label>
                        <input type="text" name="member_no" class="form-control" value="<?= htmlspecialchars($member['member_no']) ?>" required maxlength="50">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($member['full_name']) ?>" required maxlength="100">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-medium">Contact Number</label>
                        <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($member['contact']) ?>" maxlength="50">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-medium">Register Page No.</label>
                        <input type="text" name="page_number" class="form-control" value="<?= htmlspecialchars($member['page_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-medium">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Male" <?= ($member['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($member['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($member['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-medium">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Table No.</label>
                        <input type="text" name="table_no" class="form-control" value="<?= htmlspecialchars($member['table_no'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">File Number</label>
                        <input type="text" name="file_number" class="form-control" value="<?= htmlspecialchars($member['file_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-12 mb-4">
                        <label class="form-label fw-medium">Address</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($member['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary-custom w-100"><i class="fas fa-save me-2"></i> Update Member</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
