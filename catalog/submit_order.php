<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    // Accept both JSON and form data
    $input = [];
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }
    
    // Log for debugging
    error_log("Content-Type: " . $content_type);
    error_log("POST data: " . print_r($_POST, true));
    error_log("Input data: " . print_r($input, true));
    
    if (empty($input)) {
        throw new Exception('Dados inválidos - nenhum dado recebido');
    }
    
    // Check session cart
    if (empty($_SESSION['cart'])) {
        throw new Exception('Carrinho vazio');
    }
    
    if (!isset($_SESSION['customer_phone'])) {
        throw new Exception('Cliente não identificado');
    }
    
    // Validate required fields
    $required_fields = ['customer_name', 'customer_phone', 'customer_address', 'payment_method'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo obrigatório: $field");
        }
    }
    
    // Validate phone format (basic Brazilian phone validation)
    $clean_phone = preg_replace('/\D/', '', $input['customer_phone']);
    if (strlen($clean_phone) < 10 || strlen($clean_phone) > 11) {
        throw new Exception('Telefone deve ter 10 ou 11 dígitos');
    }
    
    // Extract CEP from address (first 8-10 digits pattern)
    $cep = '';
    if (preg_match('/(\d{5}-?\d{3})/', $input['customer_address'], $matches)) {
        $cep = $matches[1];
        // Remove dashes for consistent storage
        $cep = str_replace('-', '', $cep);
        // Format with dash
        if (strlen($cep) === 8) {
            $cep = substr($cep, 0, 5) . '-' . substr($cep, 5);
        }
    }
    
    $pdo->beginTransaction();
    
    // Check/create customer
    $customer_id = null;
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
    $stmt->execute([$input['customer_phone']]);
    $existing_customer = $stmt->fetch();
    
    if ($existing_customer) {
        $customer_id = $existing_customer['id'];
        
        // Update customer info
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET name = ?, cep = ?, address = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([
            $input['customer_name'],
            null, // CEP handled separately 
            $input['customer_address'],
            $customer_id
        ]);
    } else {
        // Create new customer automatically from online catalog
        $stmt = $pdo->prepare("
            INSERT INTO customers (name, phone, cep, address, notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $input['customer_name'],
            $input['customer_phone'],
            null, // CEP handled separately
            $input['customer_address'],
            'Cliente cadastrado automaticamente pelo catálogo online'
        ]);
        $customer_id = $pdo->lastInsertId();
    }
    
    // Use session cart items
    $validated_items = [];
    $subtotal = 0;
    
    foreach ($_SESSION['cart'] as $item) {
        // Re-validate stock from database
        $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item['id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Produto não encontrado: " . $item['name']);
        }
        
        if ($product['stock_quantity'] < $item['quantity']) {
            throw new Exception("Estoque insuficiente para: " . $product['name']);
        }
        
        $validated_items[] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $item['quantity'],
            'total' => $product['price'] * $item['quantity']
        ];
        
        $subtotal += $product['price'] * $item['quantity'];
    }
    
    // Create sale
    // Get delivery type (pickup vs delivery)  
    $delivery_type = $_POST['delivery_type'] ?? 'delivery';
    
    // Calculate delivery fee based on type and address
    $delivery_fee = 5.00; // Default
    
    if ($delivery_type === 'pickup') {
        $delivery_fee = 0.00; // No delivery fee for pickup
    } elseif (!empty($customer_address)) {
        // Extract neighborhood from address
        if (preg_match('/-\s*([^,]+),/', $customer_address, $matches)) {
            $neighborhood = trim($matches[1]);
            
            // Query neighborhoods table for delivery fee
            $stmt = $pdo->prepare("SELECT delivery_fee FROM neighborhoods WHERE LOWER(name) = LOWER(?) AND active = 1");
            $stmt->execute([$neighborhood]);
            $result = $stmt->fetch();
            
            if ($result) {
                $delivery_fee = (float)$result['delivery_fee'];
            }
        }
    }
    
    // Allow override from form if provided (when sent from frontend)
    if (isset($_POST['delivery_fee']) && $_POST['delivery_fee'] > 0) {
        $delivery_fee = (float)$_POST['delivery_fee'];
    }
    $total = $subtotal + $delivery_fee;
    
    $stmt = $pdo->prepare("
        INSERT INTO sales (
            customer_id, user_id, total_amount, discount_amount, 
            payment_method, notes, created_at
        ) VALUES (?, 1, ?, 0, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $customer_id,
        $total,
        $input['payment_method'],
        $input['observations'] ?? ''
    ]);
    
    $sale_id = $pdo->lastInsertId();
    
    // Create sale items and update stock
    foreach ($validated_items as $item) {
        // Insert sale item - using correct column names including total_price
        $stmt = $pdo->prepare("
            INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sale_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['total'] // quantity * unit_price
        ]);
        
        // Update stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Create delivery order
    // Only create delivery order for delivery type, not pickup
    if ($delivery_type === 'delivery') {
        $stmt = $pdo->prepare("
            INSERT INTO delivery_orders (
                sale_id, customer_name, customer_phone, delivery_address, 
                delivery_fee, status, estimated_time, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pendente', 45, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $sale_id,
            $input['customer_name'],
            $input['customer_phone'],
            $input['customer_address'],
            $delivery_fee
        ]);
    }
    
    // Log audit
    if (function_exists('logAudit')) {
        logAudit(1, 'CREATE', 'sales', $sale_id, json_encode([
            'customer_name' => $input['customer_name'],
            'total' => $total,
            'items_count' => count($validated_items),
            'source' => 'online_catalog'
        ]));
    }
    
    $pdo->commit();
    
    // Clear session cart after successful order
    unset($_SESSION['cart']);
    
    echo json_encode([
        'success' => true,
        'order_id' => $sale_id,
        'message' => 'Pedido realizado com sucesso!',
        'customer_name' => $input['customer_name'],
        'customer_address' => $input['customer_address'],
        'payment_method' => $input['payment_method'],
        'subtotal' => $subtotal,
        'delivery_fee' => $delivery_fee,
        'total' => $total,
        'items' => $validated_items
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log('Order submission error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>