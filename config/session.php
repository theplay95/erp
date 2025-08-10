<?php
// Audit logging function only
// All other session functions are in database.php

// Function already declared in database.php, removing duplicate
if (!function_exists('logAudit')) {
function logAudit($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    if (!isset($_SESSION['user_id'])) return;
    
    try {
        global $pdo;
        if (!$pdo) return; // Skip if no database connection
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
}
?>
