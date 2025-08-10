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
        case 'get_history':
            getCustomerHistory($pdo);
            break;
        case 'search':
            searchCustomers($pdo);
            break;
        default:
            getAllCustomers($pdo);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createCustomer($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
}

function getAllCustomers($pdo) {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(s.id) as total_sales,
               COALESCE(SUM(s.total), 0) as total_spent
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id
        GROUP BY c.id
        ORDER BY c.name
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $customers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $customers
    ]);
}

function searchCustomers($pdo) {
    $query = $_GET['q'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM customers 
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
        ORDER BY name 
        LIMIT 10
    ");
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $customers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $customers
    ]);
}

function getCustomerHistory($pdo) {
    $customer_id = intval($_GET['id'] ?? 0);
    
    if ($customer_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do cliente inválido']);
        return;
    }
    
    // Get customer info
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        return;
    }
    
    // Get customer sales
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as user_name
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.customer_id = ?
        ORDER BY s.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$customer_id]);
    $sales = $stmt->fetchAll();
    
    // Get sales summary
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            SUM(total) as total_spent,
            AVG(total) as avg_sale,
            MAX(created_at) as last_sale
        FROM sales 
        WHERE customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $summary = $stmt->fetch();
    
    // Generate HTML
    $html = generateCustomerHistoryHtml($customer, $sales, $summary);
    
    echo json_encode([
        'success' => true,
        'data' => $customer,
        'sales' => $sales,
        'summary' => $summary,
        'html' => $html
    ]);
}

function createCustomer($pdo, $input) {
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
        return;
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO customers (name, phone, email, address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $phone ?: null,
            $email ?: null,
            $address ?: null
        ]);
        
        $customer_id = $pdo->lastInsertId();
        
        logAudit('create_customer_api', 'customers', $customer_id, null, [
            'name' => $name,
            'phone' => $phone,
            'email' => $email
        ]);
        
        // Get created customer
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cliente criado com sucesso',
            'data' => $customer
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao criar cliente']);
    }
}

function generateCustomerHistoryHtml($customer, $sales, $summary) {
    $html = '<div class="customer-history">';
    
    // Customer info
    $html .= '<div class="mb-4">';
    $html .= '<h6>' . htmlspecialchars($customer['name']) . '</h6>';
    if ($customer['phone']) {
        $html .= '<p class="mb-1"><i class="fas fa-phone me-2"></i>' . htmlspecialchars($customer['phone']) . '</p>';
    }
    if ($customer['email']) {
        $html .= '<p class="mb-1"><i class="fas fa-envelope me-2"></i>' . htmlspecialchars($customer['email']) . '</p>';
    }
    $html .= '<small class="text-muted">Cliente desde ' . date('d/m/Y', strtotime($customer['created_at'])) . '</small>';
    $html .= '</div>';
    
    // Summary
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-6 text-center">';
    $html .= '<div class="h5 text-primary">' . ($summary['total_sales'] ?? 0) . '</div>';
    $html .= '<small class="text-muted">Compras</small>';
    $html .= '</div>';
    $html .= '<div class="col-6 text-center">';
    $html .= '<div class="h5 text-success">R$ ' . number_format($summary['total_spent'] ?? 0, 2, ',', '.') . '</div>';
    $html .= '<small class="text-muted">Total Gasto</small>';
    $html .= '</div>';
    $html .= '</div>';
    
    if (!empty($sales)) {
        $html .= '<h6>Histórico de Compras</h6>';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-sm">';
        $html .= '<thead><tr><th>Data</th><th>Total</th><th>Pagamento</th><th>Vendedor</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($sales as $sale) {
            $html .= '<tr>';
            $html .= '<td>' . date('d/m/Y', strtotime($sale['created_at'])) . '</td>';
            $html .= '<td class="text-success fw-bold">R$ ' . number_format($sale['total'], 2, ',', '.') . '</td>';
            $html .= '<td>' . ucfirst($sale['payment_method']) . '</td>';
            $html .= '<td>' . htmlspecialchars($sale['user_name'] ?? 'Sistema') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
    } else {
        $html .= '<div class="alert alert-info">Nenhuma compra realizada ainda.</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
?>
