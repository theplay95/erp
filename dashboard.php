<?php
$pageTitle = 'Dashboard';
require_once 'config/database.php';
requireLogin();

// formatCurrency function is now in config/database.php

// Get dashboard statistics
$stats = [];

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE active = 1");
$stats['total_products'] = $stmt->fetch()['total'];

// Low stock products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity <= min_stock_level AND active = 1");
$stats['low_stock'] = $stmt->fetch()['total'];

// Today's sales (SQLite compatible)
$stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) = DATE('now') AND active = 1");
$today_sales = $stmt->fetch();
$stats['today_sales'] = $today_sales['total'];
$stats['today_revenue'] = $today_sales['revenue'];

// This month's sales (SQLite compatible)
$stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now') AND active = 1");
$month_sales = $stmt->fetch();
$stats['month_sales'] = $month_sales['total'];
$stats['month_revenue'] = $month_sales['revenue'];

// Delivery statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM delivery_orders WHERE DATE(created_at) = DATE('now')");
$stats['today_deliveries'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM delivery_orders WHERE DATE(created_at) = DATE('now') AND status = 'entregue'");
$stats['today_delivered'] = $stmt->fetch()['total'];

// Recent sales
$stmt = $pdo->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.active = 1 ORDER BY s.created_at DESC LIMIT 5");
$recent_sales = $stmt->fetchAll();

// Low stock products
$stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity <= min_stock_level AND active = 1 ORDER BY stock_quantity ASC LIMIT 5");
$low_stock_products = $stmt->fetchAll();

// Recent deliveries
$stmt = $pdo->query("SELECT * FROM delivery_orders ORDER BY created_at DESC LIMIT 5");
$recent_deliveries = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">Dashboard</h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Vendas Hoje</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['today_sales'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-receipt fs-1 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Receita Hoje</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= formatCurrency($stats['today_revenue']) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-dollar fs-1 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Deliveries Hoje</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['today_deliveries'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-truck fs-1 text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Entregas Concluídas</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['today_delivered'] ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Sales -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Vendas Recentes</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_sales)): ?>
                    <p class="text-muted">Nenhuma venda encontrada.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><?= $sale['id'] ?></td>
                                    <td><?= htmlspecialchars($sale['customer_name'] ?? 'Cliente Avulso') ?></td>
                                    <td><?= formatCurrency($sale['total_amount']) ?></td>
                                    <td><?= formatDate($sale['created_at']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Products -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-warning">Produtos com Estoque Baixo</h6>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock_products)): ?>
                    <p class="text-muted">Todos os produtos possuem estoque adequado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Estoque</th>
                                    <th>Categoria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $product['stock_quantity'] == 0 ? 'danger' : 'warning' ?>">
                                            <?= $product['stock_quantity'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($product['category'] ?? 'Sem categoria') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
