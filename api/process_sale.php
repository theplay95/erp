<?php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];
$customerId = $data['customer_id'] ?? null;
$paymentMethod = $data['payment_method'] ?? 'dinheiro';
$discount = $data['discount'] ?? 0;

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Carrinho vazio']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Calculate total
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $total = $subtotal - $discount;
    
    // Create sale
    $stmt = $pdo->prepare("INSERT INTO sales (customer_id, user_id, subtotal, discount, total, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$customerId, $_SESSION['user_id'], $subtotal, $discount, $total, $paymentMethod]);
    $saleId = $pdo->lastInsertId();
    
    // Add sale items and update stock
    foreach ($items as $item) {
        // Add sale item
        $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$saleId, $item['id'], $item['quantity'], $item['price']]);
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['id']]);
    }
    
    // Add financial transaction
    $stmt = $pdo->prepare("INSERT INTO financial_transactions (type, category, amount, description, reference_type, reference_id, user_id) VALUES ('income', 'venda', ?, ?, 'sale', ?, ?)");
    $stmt->execute([$total, "Venda #$saleId", $saleId, $_SESSION['user_id']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'sale_id' => $saleId,
        'total' => $total,
        'message' => 'Venda realizada com sucesso!'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro ao processar venda: ' . $e->getMessage()]);
}
?>
