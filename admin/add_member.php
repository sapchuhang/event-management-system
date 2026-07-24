<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Add Member';

require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold">Add Member</h2>
    <a href="members.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back</a>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="glass-panel p-4 p-md-5">
            <form action="../actions/add_member_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Member Number</label>
                        <input type="text" name="member_no" class="form-control" placeholder="e.g. 001" required
                            maxlength="50">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-medium">Contact Number</label>
                        <input type="text" name="contact" class="form-control" maxlength="50">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-medium">Register Page No.</label>
                        <input type="text" name="page_number" class="form-control" placeholder="e.g. 15">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-medium">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-medium">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">Table No.</label>
                        <input type="text" name="table_no" class="form-control" placeholder="e.g. T-01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-medium">File Number</label>
                        <input type="text" name="file_number" class="form-control" placeholder="e.g. F-102">
                    </div>
                    <div class="col-md-12 mb-4">
                        <label class="form-label fw-medium">Address</label>
                        <textarea name="address" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary-custom w-100"><i class="fas fa-save me-2"></i> Save
                            Member</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>