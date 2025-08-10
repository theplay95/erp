<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Set JSON header
header('Content-Type: application/json');

// Require login for all API calls
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo, $input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function handleGet($pdo) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_sale_details':
            getSaleDetails($pdo);
            break;
        case 'recent_sales':
            getRecentSales($pdo);
            break;
        case 'daily_sales':
            getDailySales($pdo);
            break;
        default:
            getAllSales($pdo);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_sale':
            createSale($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
}

function getAllSales($pdo) {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    $sql = "
        SELECT s.*, c.name as customer_name, u.name as user_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN users u ON s.user_id = u.id
    ";
    $params = [];
    
    if ($start_date && $end_date) {
        $sql .= " WHERE DATE(s.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    $sql .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $sales
    ]);
}

function getSaleDetails($pdo) {
    $sale_id = intval($_GET['id'] ?? 0);
    
    if ($sale_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID da venda inválido']);
        return;
    }
    
    // Get sale details
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as user_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
    
    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Venda não encontrada']);
        return;
    }
    
    // Get sale items
    $stmt = $pdo->prepare("
        SELECT si.*, p.name as product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY p.name
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll();
    
    // Generate HTML for modal
    $html = generateSaleDetailsHtml($sale, $sale_items);
    
    echo json_encode([
        'success' => true,
        'data' => $sale,
        'items' => $sale_items,
        'html' => $html
    ]);
}

function getRecentSales($pdo) {
    $limit = intval($_GET['limit'] ?? 10);
    
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        ORDER BY s.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $sales = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $sales
    ]);
}

function getDailySales($pdo) {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE DATE(s.created_at) = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$date]);
    $sales = $stmt->fetchAll();
    
    // Get daily summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            SUM(total) as total_amount
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$date]);
    $summary = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => $sales,
        'summary' => $summary
    ]);
}

function createSale($pdo, $input) {
    $items = $input['items'] ?? [];
    $total = floatval($input['total'] ?? 0);
    $payment_method = $input['payment_method'] ?? '';
    $customer_id = intval($input['customer_id'] ?? 0) ?: null;
    $discount = floatval($input['discount'] ?? 0);
    
    // Validation
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Carrinho está vazio']);
        return;
    }
    
    if ($total <= 0) {
        echo json_encode(['success' => false, 'message' => 'Total da venda deve ser maior que zero']);
        return;
    }
    
    if (empty($payment_method)) {
        echo json_encode(['success' => false, 'message' => 'Método de pagamento é obrigatório']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Create sale
        $stmt = $pdo->prepare("
            INSERT INTO sales (customer_id, user_id, total, discount, payment_method, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $customer_id,
            $_SESSION['user_id'],
            $total,
            $discount,
            $payment_method
        ]);
        
        $sale_id = $pdo->lastInsertId();
        
        // Add sale items and update stock
        foreach ($items as $item) {
            $product_id = intval($item['id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            $subtotal = $price * $quantity;
            
            // Check product exists and has enough stock
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Produto ID $product_id não encontrado");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Estoque insuficiente para o produto ID $product_id");
            }
            
            // Insert sale item
            $stmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sale_id, $product_id, $quantity, $price, $subtotal]);
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);
        }
        
        // Log audit
        logAudit('create_sale_api', 'sales', $sale_id, null, [
            'total' => $total,
            'payment_method' => $payment_method,
            'items_count' => count($items)
        ]);
        
        // Create financial transaction
        $stmt = $pdo->prepare("
            INSERT INTO financial_transactions (type, category, amount, description, reference_type, reference_id, user_id)
            VALUES ('income', 'Vendas', ?, ?, 'sale', ?, ?)
        ");
        $stmt->execute([
            $total,
            "Venda #$sale_id - " . ucfirst($payment_method),
            $sale_id,
            $_SESSION['user_id']
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Venda realizada com sucesso',
            'sale_id' => $sale_id,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generateSaleDetailsHtml($sale, $sale_items) {
    $html = '<div class="sale-details">';
    
    // Sale info
    $html .= '<div class="row mb-3">';
    $html .= '<div class="col-md-6">';
    $html .= '<strong>Venda #' . $sale['id'] . '</strong><br>';
    $html .= 'Data: ' . date('d/m/Y H:i', strtotime($sale['created_at'])) . '<br>';
    if ($sale['customer_name']) {
        $html .= 'Cliente: ' . htmlspecialchars($sale['customer_name']) . '<br>';
    }
    $html .= 'Vendedor: ' . htmlspecialchars($sale['user_name'] ?? 'Sistema');
    $html .= '</div>';
    $html .= '<div class="col-md-6 text-end">';
    $html .= '<div class="h4 text-success">R$ ' . number_format($sale['total'], 2, ',', '.') . '</div>';
    $html .= '<small>Pagamento: ' . ucfirst($sale['payment_method']) . '</small>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Items table
    $html .= '<div class="table-responsive">';
    $html .= '<table class="table table-sm">';
    $html .= '<thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Subtotal</th></tr></thead>';
    $html .= '<tbody>';
    
    foreach ($sale_items as $item) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($item['product_name']) . '</td>';
        $html .= '<td>' . $item['quantity'] . '</td>';
        $html .= '<td>R$ ' . number_format($item['price'], 2, ',', '.') . '</td>';
        $html .= '<td>R$ ' . number_format($item['subtotal'], 2, ',', '.') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}
?>
