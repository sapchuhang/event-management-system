<?php
// actions/update_schema_allowance.php
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

    // 1. Add allowance_amount to events table
    if (!columnExists($pdo, 'events', 'allowance_amount')) {
        $pdo->exec("ALTER TABLE events ADD COLUMN allowance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER status;");
        echo "Column 'allowance_amount' added to 'events' table.<br>\n";
    } else {
        echo "Column 'allowance_amount' already exists in 'events' table.<br>\n";
    }

    // 2. Add columns to attendance table
    if (!columnExists($pdo, 'attendance', 'marked_by')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN marked_by INT NULL AFTER member_id;");
        echo "Column 'marked_by' added to 'attendance' table.<br>\n";
    } else {
        echo "Column 'marked_by' already exists in 'attendance' table.<br>\n";
    }

    if (!columnExists($pdo, 'attendance', 'allowance_paid')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN allowance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER marked_by;");
        echo "Column 'allowance_paid' added to 'attendance' table.<br>\n";
    } else {
        echo "Column 'allowance_paid' already exists in 'attendance' table.<br>\n";
    }

    // 3. Add foreign key for marked_by
    // Check if constraint exists in information_schema
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.table_constraints 
        WHERE table_schema = DATABASE() 
          AND table_name = 'attendance' 
          AND constraint_name = 'fk_attendance_marked_by'
    ");
    $stmt->execute();
    $fkExists = $stmt->fetchColumn() > 0;

    if (!$fkExists) {
        try {
            $pdo->exec("
                ALTER TABLE attendance 
                ADD CONSTRAINT fk_attendance_marked_by 
                FOREIGN KEY (marked_by) REFERENCES admin_users(id) 
                ON DELETE SET NULL;
            ");
            echo "Foreign key constraint 'fk_attendance_marked_by' added to 'attendance'.<br>\n";
        } catch (PDOException $ex) {
            echo "Constraint warning (might already exist): " . htmlspecialchars($ex->getMessage()) . "<br>\n";
        }
    } else {
        echo "Foreign key constraint 'fk_attendance_marked_by' already exists.<br>\n";
    }

    // 4. Create staff_event_cash table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_event_cash (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            allocated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_staff_event (event_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'staff_event_cash' checked/created.<br>\n";

    echo "<strong>Schema migration for transportation allowance completed successfully!</strong>";

} catch (Exception $e) {
    die("Migration failed: " . htmlspecialchars($e->getMessage()));
}
?>
