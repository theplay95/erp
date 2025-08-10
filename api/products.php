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
        case 'PUT':
            handlePut($pdo, $input);
            break;
        case 'DELETE':
            handleDelete($pdo);
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
        case 'search':
            searchProducts($pdo);
            break;
        case 'low_stock':
            getLowStockProducts($pdo);
            break;
        case 'categories':
            getCategories($pdo);
            break;
        default:
            getAllProducts($pdo);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createProduct($pdo, $input);
            break;
        case 'update_stock':
            updateStock($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
}

function getAllProducts($pdo) {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category = c.name 
        ORDER BY p.name 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $products = $stmt->fetchAll();
    
    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total = $stmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $products,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function searchProducts($pdo) {
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category = c.name WHERE 1=1";
    $params = [];
    
    if ($query) {
        $sql .= " AND p.name LIKE ?";
        $params[] = '%' . $query . '%';
    }
    
    if ($category) {
        $sql .= " AND p.category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY p.name LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
}

function getLowStockProducts($pdo) {
    $threshold = intval($_GET['threshold'] ?? 10);
    
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE stock <= ? 
        ORDER BY stock ASC, name ASC
    ");
    $stmt->execute([$threshold]);
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $products
    ]);
}

function getCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
}

function createProduct($pdo, $input) {
    $name = trim($input['name'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    $category = trim($input['category'] ?? '');
    $description = trim($input['description'] ?? '');
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nome do produto é obrigatório']);
        return;
    }
    
    if ($price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Preço deve ser maior que zero']);
        return;
    }
    
    if ($stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Estoque não pode ser negativo']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (name, price, stock, category, description)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $price,
            $stock,
            $category ?: null,
            $description ?: null
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        logAudit('create_product_api', 'products', $product_id, null, [
            'name' => $name,
            'price' => $price,
            'stock' => $stock
        ]);
        
        // Get created product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produto criado com sucesso',
            'data' => $product
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao criar produto']);
    }
}

function updateStock($pdo, $input) {
    $product_id = intval($input['product_id'] ?? 0);
    $new_stock = intval($input['new_stock'] ?? 0);
    $reason = trim($input['reason'] ?? '');
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do produto inválido']);
        return;
    }
    
    if ($new_stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Estoque não pode ser negativo']);
        return;
    }
    
    try {
        // Get current product
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
            return;
        }
        
        $old_stock = $product['stock'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Record adjustment
        $stmt = $pdo->prepare("
            INSERT INTO inventory_adjustments (product_id, old_quantity, new_quantity, reason, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $product_id,
            $old_stock,
            $new_stock,
            $reason ?: 'Ajuste via API',
            $_SESSION['user_id']
        ]);
        
        logAudit('update_stock_api', 'products', $product_id, 
                ['stock' => $old_stock], 
                ['stock' => $new_stock]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Estoque atualizado com sucesso',
            'data' => [
                'product_id' => $product_id,
                'old_stock' => $old_stock,
                'new_stock' => $new_stock
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar estoque']);
    }
}
?>
