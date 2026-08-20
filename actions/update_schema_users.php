<?php
// actions/update_schema_users.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Allow running from command line OR if logged in as admin
if (php_sapi_name() !== 'cli') {
    requireLogin();
}

try {
    // Helper to check if a column exists
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
              AND table_name = ? 
              AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() > 0;
    }

    // Add role column to admin_users if it doesn't exist
    if (!columnExists($pdo, 'admin_users', 'role')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' AFTER password;");
        echo "Column 'role' added to 'admin_users' table.<br>\n";
    } else {
        echo "Column 'role' already exists in 'admin_users' table.<br>\n";
    }

    echo "<strong>Schema migration for users completed successfully!</strong>";

} catch (Exception $e) {
    die("Migration failed: " . htmlspecialchars($e->getMessage()));
}
?>
