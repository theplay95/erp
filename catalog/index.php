<?php
$pageTitle = 'Catálogo - Mundo da Carne';
require_once '../config/database.php';

// Get catalog settings for colors and customization
$catalog_settings = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM catalog_settings LIMIT 1");
    $stmt->execute();
    $catalog_settings = $stmt->fetch();
} catch (Exception $e) {
    // Fallback colors if table doesn't exist
    $catalog_settings = [
        'primary_color' => '#dc3545',
        'secondary_color' => '#6c757d',
        'site_title' => 'Mundo da Carne'
    ];
}

// Handle logout FIRST (before any output)
if (isset($_GET['logout'])) {
    unset($_SESSION['customer_phone'], $_SESSION['cart']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// AJAX handler for adding products to cart
if (isset($_POST['ajax_add_to_cart'])) {
    header('Content-Type: application/json');
    
    // Ensure cart is initialized
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $response = ['success' => false, 'message' => '', 'cart_count' => 0];
    
    // Debug logging
    error_log("AJAX add to cart - Product ID: $product_id, Quantity: $quantity, Cart count: " . count($_SESSION['cart']));
    
    // Get product from database
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stock_quantity > 0");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product) {
        $item_index = null;
        
        // Check if product already in cart
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['id'] == $product_id) {
                $item_index = $index;
                break;
            }
        }
        
        // Update quantity if already in cart
        if ($item_index !== null) {
            $new_quantity = $_SESSION['cart'][$item_index]['quantity'] + $quantity;
            if ($new_quantity <= $product['stock_quantity']) {
                $_SESSION['cart'][$item_index]['quantity'] = $new_quantity;
                $response['success'] = true;
                $response['message'] = 'Quantidade atualizada no carrinho!';
            } else {
                $response['message'] = 'Estoque insuficiente!';
            }
        } else {
            // Add new item to cart
            if ($quantity <= $product['stock_quantity']) {
                $_SESSION['cart'][] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'stock' => $product['stock_quantity']
                ];
                $response['success'] = true;
                $response['message'] = 'Produto adicionado ao carrinho!';
            } else {
                $response['message'] = 'Estoque insuficiente!';
            }
        }
        
        if ($response['success']) {
            $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
            $response['cart_count'] = $cart_count;
        }
    } else {
        $response['message'] = 'Produto não encontrado ou esgotado!';
    }
    
    echo json_encode($response);
    exit();
}

// AJAX handler for removing items
if (isset($_POST['ajax_remove_item'])) {
    header('Content-Type: application/json');
    
    $product_id = (int)$_POST['product_id'];
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($product_id) {
            return $item['id'] !== $product_id;
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        
        $response['success'] = true;
        $response['message'] = 'Produto removido do carrinho!';
        
        $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
        $response['cart_count'] = $cart_count;
    }
    
    echo json_encode($response);
    exit();
}

// AJAX handler for clearing cart
if (isset($_POST['ajax_clear_cart'])) {
    header('Content-Type: application/json');
    
    $_SESSION['cart'] = [];
    
    echo json_encode([
        'success' => true,
        'message' => 'Carrinho limpo com sucesso!',
        'cart_count' => 0
    ]);
    exit();
}

// AJAX handler for updating quantity
if (isset($_POST['ajax_update_quantity'])) {
    header('Content-Type: application/json');
    
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $response = ['success' => false, 'message' => ''];
    
    if ($quantity > 0 && isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $stock = $stmt->fetchColumn();
                
                if ($quantity <= $stock) {
                    $item['quantity'] = $quantity;
                    $response['success'] = true;
                    $response['message'] = 'Quantidade atualizada!';
                } else {
                    $response['message'] = 'Estoque insuficiente!';
                }
                break;
            }
        }
        
        if ($response['success']) {
            $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
            $response['cart_count'] = $cart_count;
        }
    }
    
    echo json_encode($response);
    exit();
}

// AJAX handler for getting cart content
if (isset($_POST['ajax_get_cart'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => true, 'cart_html' => '', 'total_formatted' => '0,00', 'cart_count' => 0];
    
    if (empty($_SESSION['cart'])) {
        $response['cart_html'] = '
            <div class="text-center py-5">
                <i class="bi bi-cart-x display-4 text-muted"></i>
                <p class="mt-3 text-muted">Seu carrinho está vazio</p>
                <small class="text-muted">Clique nos produtos para adicionar</small>
            </div>';
    } else {
        $cart_html = '';
        $subtotal = 0;
        
        foreach ($_SESSION['cart'] as $item) {
            $item_total = $item['price'] * $item['quantity'];
            $subtotal += $item_total;
            
            $cart_html .= '
            <div class="cart-item border-bottom py-3" data-product-id="' . $item['id'] . '">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">' . htmlspecialchars($item['name']) . '</h6>
                        <small class="text-muted">R$ ' . number_format($item['price'], 2, ',', '.') . ' cada</small>
                    </div>
                    <div class="text-end">
                        <div class="d-flex align-items-center justify-content-end mb-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateSessionQuantity(' . $item['id'] . ', ' . ($item['quantity'] - 1) . ')">
                                <i class="bi bi-dash"></i>
                            </button>
                            <span class="mx-3 fw-bold">' . $item['quantity'] . '</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateSessionQuantity(' . $item['id'] . ', ' . ($item['quantity'] + 1) . ')" ' . ($item['quantity'] >= $item['stock'] ? 'disabled title="Estoque esgotado"' : '') . '>
                                <i class="bi bi-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeSessionItem(' . $item['id'] . ')" title="Remover item">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="fw-bold text-success">
                            R$ ' . number_format($item_total, 2, ',', '.') . '
                        </div>';
            
            if ($item['quantity'] >= $item['stock']) {
                $cart_html .= '
                        <small class="text-warning">
                            <i class="bi bi-exclamation-triangle"></i> Estoque limitado
                        </small>';
            }
            
            $cart_html .= '
                    </div>
                </div>
            </div>';
        }
        
        $response['cart_html'] = $cart_html;
        $response['total_formatted'] = number_format($subtotal, 2, ',', '.');
        $response['cart_count'] = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    
    echo json_encode($response);
    exit();
}

// AJAX handler for clearing cart
if (isset($_POST['ajax_clear_cart'])) {
    header('Content-Type: application/json');
    
    $_SESSION['cart'] = [];
    
    $response = [
        'success' => true,
        'message' => 'Carrinho limpo com sucesso!',
        'cart_count' => 0
    ];
    
    echo json_encode($response);
    exit();
}

// Customer identification logic
$show_phone_form = true;
$existing_customer = null;
$customer_orders = [];
$phone = '';
$step = 'identify';

// Check if customer already identified
if (isset($_SESSION['customer_phone'])) {
    $phone = $_SESSION['customer_phone'];
    $show_phone_form = false;
    $step = 'products';
}

// Process phone identification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['customer_phone']) && !isset($_POST['ajax_add_to_cart'])) {
    $phone = preg_replace('/[^0-9]/', '', $_POST['customer_phone']);
    $_SESSION['customer_phone'] = $phone;
    
    // Look for existing customer
    $stmt = $pdo->prepare("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE c.phone LIKE ? ORDER BY s.created_at DESC LIMIT 1");
    $stmt->execute(["%$phone%"]);
    $existing_customer = $stmt->fetch();
    
    if ($existing_customer) {
        // Get last order items
        $stmt = $pdo->prepare("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
        $stmt->execute([$existing_customer['id']]);
        $customer_orders = $stmt->fetchAll();
        
        // If requested to repeat last order
        if (isset($_POST['repeat_order'])) {
            $_SESSION['cart'] = [];
            foreach ($customer_orders as $item) {
                // Verify stock before adding
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stock_quantity >= ?");
                $stmt->execute([$item['product_id'], $item['quantity']]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $_SESSION['cart'][] = [
                        'id' => $item['product_id'],
                        'name' => $item['product_name'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'stock' => $product['stock_quantity']
                    ];
                }
            }
        }
        
        // If requested to start a new order
        if (isset($_POST['new_order'])) {
            $_SESSION['cart'] = [];
        }
    }
    
    $show_phone_form = false;
    $step = 'products';
}

// Process new order (clear cart for new orders)
if (isset($_POST['new_order'])) {
    $_SESSION['cart'] = [];
}

// Get categories and products
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

$category_filter = $_GET['category'] ?? '';
$search_filter = $_GET['search'] ?? '';

$sql = "SELECT p.*, c.name as category_name FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.stock_quantity > 0";
$params = [];

if ($category_filter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if ($search_filter) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$search_filter%";
}

$sql .= " ORDER BY c.name, p.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Calculate cart totals (safe check for null)
$cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
$subtotal = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? array_sum(array_map(function($item) {
    return $item['price'] * $item['quantity'];
}, $_SESSION['cart'])) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $catalog_settings['primary_color'] ?? '#dc3545' ?>;
            --secondary-color: <?= $catalog_settings['secondary_color'] ?? '#6c757d' ?>;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--primary-color) !important;
        }
        
        .product-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        
        .product-image {
            height: 200px;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--secondary-color);
        }
        
        .price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .cart-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .floating-cart {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .category-filter {
            background: var(--primary-color);
            color: white;
        }
        
        .hero-section {
            background: linear-gradient(rgba(220, 53, 69, 0.8), rgba(220, 53, 69, 0.8)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%23f8f9fa" width="1200" height="600"/></svg>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shop"></i> Mundo da Carne
            </a>
            
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['customer_phone'])): ?>
                <span class="me-3 text-muted">
                    <i class="bi bi-person"></i> <?= htmlspecialchars($_SESSION['customer_phone']) ?>
                    <a href="?logout=1" class="text-decoration-none ms-2" onclick="return confirm('Desconectar?')">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </span>
                <?php endif; ?>
                
                <button class="btn btn-outline-primary position-relative me-3" data-bs-toggle="modal" data-bs-target="#cartModal">
                    <i class="bi bi-cart3"></i> Carrinho
                    <?php if ($cart_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count" style="display: inline;">
                        <?= $cart_count ?>
                    </span>
                    <?php else: ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count" style="display: none;">
                        0
                    </span>
                    <?php endif; ?>
                </button>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#produtos">Produtos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contato">Contato</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Customer Identification -->
    <?php if ($show_phone_form): ?>
    <div class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <h1 class="display-4 mb-4">Mundo da Carne</h1>
                    <p class="lead mb-4">Para começar, informe seu telefone</p>
                    
                    <form method="POST" class="mb-4">
                        <div class="input-group input-group-lg">
                            <input type="tel" name="customer_phone" class="form-control" 
                                   placeholder="(00) 00000-0000" required 
                                   pattern="[0-9\(\)\-\s]+" maxlength="15">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-right"></i> Continuar
                            </button>
                        </div>
                    </form>
                    
                    <small class="text-muted">
                        Usamos seu telefone para identificar pedidos anteriores e facilitar futuras compras
                    </small>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Customer Recognition -->
    <?php if ($existing_customer && !empty($customer_orders)): ?>
    <div class="bg-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h4><i class="bi bi-person-check"></i> Olá, <?= htmlspecialchars($existing_customer['customer_name'] ?? 'Cliente') ?>!</h4>
                    <p class="mb-0">Encontramos seu último pedido. Deseja repetir ou fazer um novo?</p>
                </div>
                <div class="col-md-4 text-end">
                    <form method="POST" class="d-inline me-2">
                        <input type="hidden" name="repeat_order" value="1">
                        <input type="hidden" name="customer_phone" value="<?= htmlspecialchars($phone) ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-repeat"></i> Repetir Último Pedido
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="new_order" value="1">
                        <input type="hidden" name="customer_phone" value="<?= htmlspecialchars($phone) ?>">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-plus"></i> Novo Pedido
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($customer_orders)): ?>
            <div class="mt-3">
                <small class="text-muted">
                    <strong>Último pedido:</strong> 
                    <?php foreach ($customer_orders as $i => $item): ?>
                        <?= htmlspecialchars($item['product_name']) ?> (<?= $item['quantity'] ?>x)<?= $i < count($customer_orders) - 1 ? ', ' : '' ?>
                    <?php endforeach; ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Hero Section -->
    <div class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 mb-4">Mundo da Carne</h1>
            <p class="lead mb-4">Os melhores cortes com qualidade garantida e entrega rápida</p>
            <a href="#produtos" class="btn btn-light btn-lg">
                <i class="bi bi-arrow-down"></i> Ver Produtos
            </a>
        </div>
    </div>
    <?php endif; ?>
    


    <!-- Filters -->
    <div class="bg-light py-4" id="produtos">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Buscar produtos..." value="<?= htmlspecialchars($search_filter) ?>">
                                <button type="submit" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary fs-6"><?= count($products) ?> produtos encontrados</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Products -->
    <?php if (!$show_phone_form): ?>
    <div class="container my-5">
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-search display-1 text-muted"></i>
                <h3 class="mt-3">Nenhum produto encontrado</h3>
                <p class="text-muted">Tente ajustar os filtros de busca</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card product-card h-100">
                        <div class="product-image">
                            <i class="bi bi-image"></i>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?= htmlspecialchars($product['description'] ?? 'Produto de qualidade') ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary"><?= htmlspecialchars($product['category_name'] ?? 'Sem categoria') ?></span>
                                <small class="text-muted">Estoque: <?= $product['stock_quantity'] ?></small>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="price">
                                    R$ <?= number_format($product['price'], 2, ',', '.') ?>
                                </div>
                                <div class="d-flex align-items-center">
                                    <input type="number" class="form-control form-control-sm me-2 quantity-selector" 
                                           style="width: 60px;" value="1" min="1" max="<?= $product['stock_quantity'] ?>"
                                           data-product-id="<?= $product['id'] ?>">
                                    <button class="btn btn-primary btn-sm add-to-cart-ajax" 
                                            data-id="<?= $product['id'] ?>"
                                            data-name="<?= htmlspecialchars($product['name']) ?>"
                                            data-price="<?= $product['price'] ?>"
                                            data-stock="<?= $product['stock_quantity'] ?>">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-cart3"></i> Meu Pedido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="session-cart-items">
                        <?php if (empty($_SESSION['cart'])): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-cart-x display-4 text-muted"></i>
                                <p class="mt-3 text-muted">Seu carrinho está vazio</p>
                                <small class="text-muted">Clique nos produtos para adicionar</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                            <div class="cart-item border-bottom py-3" data-product-id="<?= $item['id'] ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                        <small class="text-muted">R$ <?= number_format($item['price'], 2, ',', '.') ?> cada</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="d-flex align-items-center justify-content-end mb-2">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateSessionQuantity(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <span class="mx-3 fw-bold"><?= $item['quantity'] ?></span>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateSessionQuantity(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)" <?= $item['quantity'] >= $item['stock'] ? 'disabled title="Estoque esgotado"' : '' ?>>
                                                <i class="bi bi-plus"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeSessionItem(<?= $item['id'] ?>)" title="Remover item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <div class="fw-bold text-success">
                                            R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?>
                                        </div>
                                        <?php if ($item['quantity'] >= $item['stock']): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i> Estoque limitado
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <strong>Total: R$ <span id="session-cart-total"><?= number_format($subtotal, 2, ',', '.') ?></span></strong>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="clearSessionCart()">Limpar</button>
                        <button type="button" class="btn btn-primary" onclick="window.location.href='/catalog/checkout.php'" id="checkout-btn" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                            Finalizar Pedido
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <div class="bg-light py-5" id="contato">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h3><i class="bi bi-geo-alt"></i> Localização</h3>
                    <p><strong>Endereço:</strong> Av Mamoré, 3180 - Centro</p>
                    <p><strong>Telefone:</strong> (00) 0000-0000</p>
                    <p><strong>CNPJ:</strong> 00.000.000/0001-00</p>
                </div>
                <div class="col-md-6">
                    <h3><i class="bi bi-clock"></i> Horário de Funcionamento</h3>
                    <p><strong>Segunda a Sexta:</strong> 08:00 às 18:00</p>
                    <p><strong>Sábado:</strong> 08:00 às 16:00</p>
                    <p><strong>Domingo:</strong> Fechado</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container text-center">
            <p>&copy; 2025 Mundo da Carne. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/catalog/js/catalog.js"></script>
</body>
</html>