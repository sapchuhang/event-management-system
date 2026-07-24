<?php
// actions/update_schema_agendas.php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Allow running from command line OR if logged in
if (php_sapi_name() !== 'cli') {
    requireLogin();
}

try {
    // 1. Create speakers table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS speakers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            title VARCHAR(150),
            bio TEXT,
            photo_path VARCHAR(255),
            email VARCHAR(100),
            website VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'speakers' checked/created.<br>";

    // 2. Helper to check if a column exists
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

    // 3. Add columns to agendas table if they don't exist
    if (!columnExists($pdo, 'agendas', 'start_time')) {
        $pdo->exec("ALTER TABLE agendas ADD COLUMN start_time TIME NULL;");
        echo "Column 'start_time' added to 'agendas'.<br>";
    } else {
        echo "Column 'start_time' already exists in 'agendas'.<br>";
    }

    if (!columnExists($pdo, 'agendas', 'end_time')) {
        $pdo->exec("ALTER TABLE agendas ADD COLUMN end_time TIME NULL;");
        echo "Column 'end_time' added to 'agendas'.<br>";
    } else {
        echo "Column 'end_time' already exists in 'agendas'.<br>";
    }

    if (!columnExists($pdo, 'agendas', 'speaker_id')) {
        $pdo->exec("ALTER TABLE agendas ADD COLUMN speaker_id INT NULL;");
        echo "Column 'speaker_id' added to 'agendas'.<br>";
    } else {
        echo "Column 'speaker_id' already exists in 'agendas'.<br>";
    }

    // 4. Add foreign key constraint if it doesn't exist
    // Check if constraint exists in information_schema
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.table_constraints 
        WHERE table_schema = DATABASE() 
          AND table_name = 'agendas' 
          AND constraint_name = 'fk_agendas_speaker'
    ");
    $stmt->execute();
    $fkExists = $stmt->fetchColumn() > 0;

    if (!$fkExists) {
        try {
            $pdo->exec("
                ALTER TABLE agendas 
                ADD CONSTRAINT fk_agendas_speaker 
                FOREIGN KEY (speaker_id) REFERENCES speakers(id) 
                ON DELETE SET NULL;
            ");
            echo "Foreign key constraint 'fk_agendas_speaker' added to 'agendas'.<br>";
        } catch (PDOException $ex) {
            echo "Constraint warning (might already exist): " . htmlspecialchars($ex->getMessage()) . "<br>";
        }
    } else {
        echo "Foreign key constraint 'fk_agendas_speaker' already exists.<br>";
    }

    // Check/add gender column in members table
    if (!columnExists($pdo, 'members', 'gender')) {
        $pdo->exec("ALTER TABLE members ADD COLUMN gender ENUM('Male', 'Female', 'Other') DEFAULT 'Other' AFTER full_name");
        echo "Column 'gender' added to 'members' table.<br>";
    } else {
        echo "Column 'gender' already exists in 'members' table.<br>";
    }

    echo "<strong>Schema migration completed successfully!</strong>";

} catch (Exception $e) {
    die("Migration failed: " . htmlspecialchars($e->getMessage()));
}
?>
