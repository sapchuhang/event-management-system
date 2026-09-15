<?php
// actions/update_schema_guest_allowance.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Allow running from command line OR if logged in as admin
if (php_sapi_name() !== 'cli') {
    requireLogin();
    if (!isAdmin()) {
        die("Unauthorized. Admin access required.");
    }
}

try {
    // Create guest_allowances table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guest_allowances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            token_no VARCHAR(50) NULL,
            full_name VARCHAR(150) NOT NULL,
            contact VARCHAR(50) NULL,
            organization VARCHAR(150) NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'Media / Reporter',
            table_no VARCHAR(50) NULL,
            reason_remarks TEXT NULL,
            allowance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            marked_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (marked_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_guest_event (event_id),
            INDEX idx_guest_marked_by (marked_by),
            INDEX idx_guest_table (table_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Table 'guest_allowances' created/verified successfully.\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
