<?php
// Fix admin password in SQLite database

$db_path = __DIR__ . '/../database/erp_system.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generate new password hash for 'admin123'
    $password = 'admin123';
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    // Update admin user password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashed]);
    
    echo "Admin password updated successfully!\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    
    // Verify the password works
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        echo "Password verification: SUCCESS\n";
    } else {
        echo "Password verification: FAILED\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>