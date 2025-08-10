<?php
require_once '../config/database.php';
requireLogin();

$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$whereClause = "WHERE stock_quantity > 0";
$params = [];

if ($category) {
    $whereClause .= " AND category_id = ?";
    $params[] = $category;
}

if ($search) {
    $whereClause .= " AND name LIKE ?";
    $params[] = "%$search%";
}

$sql = "SELECT * FROM products $whereClause ORDER BY name LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$html = '';
foreach ($products as $product) {
    $html .= '
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card product-card h-100" data-product-id="' . $product['id'] . '">
            <div class="card-body">
                ' . (isset($product['image']) && $product['image'] ? '<img src="/uploads/' . htmlspecialchars($product['image']) . '" class="card-img-top mb-2" style="height: 100px; object-fit: cover;">' : '') . '
                <h6 class="card-title">' . htmlspecialchars($product['name']) . '</h6>
                <p class="card-text">
                    <strong class="text-success">R$ ' . number_format($product['price'], 2, ',', '.') . '</strong><br>
                    <small>Estoque: ' . $product['stock_quantity'] . '</small>
                </p>
                <button class="btn btn-primary btn-sm w-100 add-to-cart" 
                        data-id="' . $product['id'] . '" 
                        data-name="' . htmlspecialchars($product['name']) . '" 
                        data-price="' . $product['price'] . '">
                    <i class="bi bi-cart-plus"></i> Adicionar
                </button>
            </div>
        </div>
    </div>';
}

echo $html;
?>
