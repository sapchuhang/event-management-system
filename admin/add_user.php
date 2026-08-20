<?php
// admin/add_user.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin(); // Administrator access required

$stmtTables = $pdo->query("SELECT DISTINCT table_no FROM members WHERE table_no IS NOT NULL AND table_no != '' ORDER BY table_no ASC");
$allTables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Add User';
require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">Add User</h4>
    <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Back</a>
</div>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="glass-panel p-4 p-md-5">
            <form action="../actions/add_user_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="Enter username" maxlength="50" autocomplete="username">
                    <div class="form-text text-muted">Must be unique, alphanumeric and under 50 characters.</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required placeholder="Enter secure password" autocomplete="new-password">
                    <div class="form-text text-muted">A strong, secure password is recommended. Minimum 6 characters.</div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-medium">System Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="staff" selected>Staff (Limited Access)</option>
                        <option value="admin">Administrator (Full Access)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium">Assigned Seating Tables</label>
                    <div class="row g-2 border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                        <?php if (empty($allTables)): ?>
                            <div class="col-12 text-muted text-center py-2 fs-7" id="noTablesMsg">No Seating Tables found in Member records.</div>
                        <?php else: ?>
                            <?php foreach ($allTables as $table): ?>
                                <div class="col-6 col-sm-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_tables[]" value="<?= htmlspecialchars($table) ?>" id="table_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $table)) ?>">
                                        <label class="form-check-label" for="table_<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $table)) ?>">
                                            <?= htmlspecialchars($table) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text text-muted">Select the tables this user is responsible for. If none are selected, the user will have access to all tables.</div>
                    
                    <div class="mt-3">
                        <label class="form-label small fw-medium text-secondary">Add Custom Table Number</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="customTableInput" class="form-control" placeholder="e.g. T-12">
                            <button type="button" class="btn btn-outline-secondary" id="btnAddCustomTable"><i class="fas fa-plus"></i> Add</button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100"><i class="fas fa-save me-2"></i> Create User Account</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnAdd = document.getElementById('btnAddCustomTable');
    if (btnAdd) {
        btnAdd.addEventListener('click', function() {
            const input = document.getElementById('customTableInput');
            const tableVal = input.value.trim();
            if (!tableVal) return;
            
            // Check if checkbox already exists
            const safeId = 'table_' + tableVal.replace(/[^a-zA-Z0-9_-]/g, '_');
            const existing = document.getElementById(safeId);
            if (existing) {
                existing.checked = true;
                input.value = '';
                return;
            }
            
            const row = document.querySelector('.row.g-2.border');
            
            // Remove "No tables found" if it exists
            const noTablesMsg = document.getElementById('noTablesMsg');
            if (noTablesMsg) {
                noTablesMsg.remove();
            }
            
            const colDiv = document.createElement('div');
            colDiv.className = 'col-6 col-sm-4';
            
            const checkDiv = document.createElement('div');
            checkDiv.className = 'form-check';
            
            const checkInput = document.createElement('input');
            checkInput.className = 'form-check-input';
            checkInput.type = 'checkbox';
            checkInput.name = 'assigned_tables[]';
            checkInput.value = tableVal;
            checkInput.id = safeId;
            checkInput.checked = true;
            
            const checkLabel = document.createElement('label');
            checkLabel.className = 'form-check-label';
            checkLabel.htmlFor = safeId;
            checkLabel.textContent = tableVal;
            
            checkDiv.appendChild(checkInput);
            checkDiv.appendChild(checkLabel);
            colDiv.appendChild(checkDiv);
            row.appendChild(colDiv);
            
            input.value = '';
        });

        document.getElementById('customTableInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                btnAdd.click();
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
