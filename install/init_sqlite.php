<?php
// Initialize SQLite database for ERP system

$db_path = __DIR__ . '/../database/erp_system.sqlite';
$sql_path = __DIR__ . '/sqlite_database.sql';

// Create database directory
$dir = dirname($db_path);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

try {
    // Connect to SQLite database (will create if doesn't exist)
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign keys
    $pdo->exec("PRAGMA foreign_keys = ON");
    
    // Read and execute SQL file
    if (file_exists($sql_path)) {
        $sql = file_get_contents($sql_path);
        
        // Split SQL statements and execute them
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        echo "Database initialized successfully!\n";
        echo "Default admin user: admin / admin123\n";
        
    } else {
        echo "SQL file not found: $sql_path\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>