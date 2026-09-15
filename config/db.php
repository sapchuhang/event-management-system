<?php
// config/db.php
$host = 'localhost';
$dbname = 'event_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error securely in a real app, don't expose to user
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}

// ── Auto-migration: ensure guest_allowances table exists + all columns present ─
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guest_allowances (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            event_id    INT NOT NULL,
            token_no    VARCHAR(50)  NULL,
            full_name   VARCHAR(150) NOT NULL,
            contact     VARCHAR(50)  NULL,
            organization VARCHAR(150) NULL,
            category    VARCHAR(80)  NOT NULL DEFAULT 'Media / Reporter',
            table_no    VARCHAR(50)  NULL,
            reason_remarks TEXT NULL,
            allowance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            marked_by   INT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id)  REFERENCES events(id)       ON DELETE CASCADE,
            FOREIGN KEY (marked_by) REFERENCES admin_users(id)  ON DELETE SET NULL,
            INDEX idx_guest_event    (event_id),
            INDEX idx_guest_markedby (marked_by),
            INDEX idx_guest_table    (table_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Patch older installations: add any missing columns safely
    $existingCols = $pdo->query("DESCRIBE guest_allowances")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('organization', $existingCols)) {
        $pdo->exec("ALTER TABLE guest_allowances ADD COLUMN organization VARCHAR(150) NULL AFTER contact");
    }
    if (!in_array('table_no', $existingCols)) {
        $pdo->exec("ALTER TABLE guest_allowances ADD COLUMN table_no VARCHAR(50) NULL AFTER category");
    }
    if (!in_array('token_no', $existingCols)) {
        $pdo->exec("ALTER TABLE guest_allowances ADD COLUMN token_no VARCHAR(50) NULL AFTER event_id");
    }
    if (!in_array('reason_remarks', $existingCols)) {
        $pdo->exec("ALTER TABLE guest_allowances ADD COLUMN reason_remarks TEXT NULL AFTER table_no");
    }
} catch (PDOException $e) {
    // Non-fatal: log but don't crash if migration fails
    error_log("Auto-migration guest_allowances failed: " . $e->getMessage());
}

?>
