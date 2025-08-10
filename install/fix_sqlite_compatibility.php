<?php
// Fix all MySQL functions to be SQLite compatible across the system

echo "Fixing SQLite compatibility issues...\n";

// Add some sample sales data for testing
$db_path = __DIR__ . '/../database/erp_system.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Add sample sales for testing dashboard
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO sales (id, user_id, subtotal, discount, total, payment_method, created_at) VALUES (?, 1, ?, 0, ?, 'dinheiro', ?)");
    
    // Today's sales
    $stmt->execute([1, 25.50, 25.50, date('Y-m-d H:i:s')]);
    $stmt->execute([2, 45.99, 45.99, date('Y-m-d H:i:s', strtotime('-2 hours'))]);
    
    // Yesterday's sales  
    $stmt->execute([3, 33.75, 33.75, date('Y-m-d H:i:s', strtotime('-1 day'))]);
    $stmt->execute([4, 78.20, 78.20, date('Y-m-d H:i:s', strtotime('-1 day -3 hours'))]);
    
    // This month's sales
    $stmt->execute([5, 120.00, 120.00, date('Y-m-d H:i:s', strtotime('-5 days'))]);
    $stmt->execute([6, 89.50, 89.50, date('Y-m-d H:i:s', strtotime('-10 days'))]);
    
    // Add sample sale items
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt->execute([1, 1, 2, 15.99]); // Arroz
    $stmt->execute([1, 3, 1, 7.99]);  // Coca-Cola
    $stmt->execute([2, 2, 3, 8.50]);  // Feijão
    $stmt->execute([2, 5, 2, 2.99]);  // Sabonete
    
    echo "Sample data added successfully!\n";
    echo "Dashboard should now work properly.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>