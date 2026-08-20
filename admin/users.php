<?php
// admin/users.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin(); // Administrator access required

// Fetch users
$stmt = $pdo->query("SELECT * FROM admin_users ORDER BY username ASC");
$users = $stmt->fetchAll();

// Fetch seating table assignments
$tableStmt = $pdo->query("SELECT user_id, table_no FROM user_tables ORDER BY table_no ASC");
$userTables = [];
while ($row = $tableStmt->fetch()) {
    $userTables[$row['user_id']][] = $row['table_no'];
}

// Count admins to prevent deleting the last admin
$adminCount = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') {
        $adminCount++;
    }
}

$pageTitle = 'User Management';
require_once '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold">User Management</h4>
    <a href="add_user.php" class="btn btn-primary-custom"><i class="fas fa-plus me-2"></i> Add User</a>
</div>

<div class="glass-panel p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Assigned Seating Tables</th>
                    <th>Created At</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($user['username']) ?></td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    <i class="fas fa-user-shield me-1"></i>Admin
                                </span>
                            <?php else: ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                    <i class="fas fa-user me-1"></i>Staff
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $assigned = $userTables[$user['id']] ?? [];
                            if (empty($assigned)): ?>
                                <span class="text-muted small"><i class="fas fa-globe me-1"></i>All Tables</span>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($assigned as $table): ?>
                                        <span class="badge bg-secondary text-white px-2 py-1" style="font-size: 0.72rem; font-weight: 500;">
                                            <i class="fas fa-chair me-1"></i><?= htmlspecialchars($table) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= date('M d, Y h:i A', strtotime($user['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php 
                                $isSelf = ((int)$user['id'] === (int)$_SESSION['admin_id']);
                                $isLastAdmin = ($user['role'] === 'admin' && $adminCount <= 1);
                                
                                if ($isSelf): ?>
                                    <button class="btn btn-sm btn-outline-secondary disabled" title="You cannot delete yourself" disabled>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php elseif ($isLastAdmin): ?>
                                    <button class="btn btn-sm btn-outline-secondary disabled" title="Cannot delete the last admin" disabled>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <form method="POST" action="../actions/delete_user_action.php" class="delete-user-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">No users registered.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Elegant SweetAlert2 delete confirmation
    $(document).on('click', '.btn-delete-user', function (e) {
        e.preventDefault();
        const form = $(this).closest('form');
        
        Swal.fire({
            title: 'Delete User?',
            text: 'Are you sure you want to delete this user? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash me-1"></i> Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
