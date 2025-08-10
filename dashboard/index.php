<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Dashboard';

try {
    $pdo = Database::getInstance();
    
    // Get today's stats
    $today = date('Y-m-d');
    
    // Today's sales
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM sales WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $today_sales = $stmt->fetch();
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $total_products = $stmt->fetch()['count'];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
    $total_customers = $stmt->fetch()['count'];
    
    // Low stock products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock <= 10");
    $low_stock = $stmt->fetch()['count'];
    
    // Recent sales
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recent_sales = $stmt->fetchAll();
    
    // Sales chart data (last 7 days)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count, SUM(total) as total
        FROM sales 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute();
    $sales_chart_data = $stmt->fetchAll();
    
    // Top selling products
    $stmt = $pdo->prepare("
        SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.subtotal) as revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_products = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $today_sales = ['count' => 0, 'total' => 0];
    $total_products = 0;
    $total_customers = 0;
    $low_stock = 0;
    $recent_sales = [];
    $sales_chart_data = [];
    $top_products = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <div class="text-muted">
        <i class="fas fa-calendar me-2"></i>
        <?php echo strftime('%d de %B de %Y', time()); ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value"><?php echo $today_sales['count']; ?></div>
                    <div class="label">Vendas Hoje</div>
                </div>
                <div class="icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value">R$ <?php echo number_format($today_sales['total'], 2, ',', '.'); ?></div>
                    <div class="label">Faturamento Hoje</div>
                </div>
                <div class="icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value"><?php echo $total_products; ?></div>
                    <div class="label">Total de Produtos</div>
                </div>
                <div class="icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stats-card <?php echo $low_stock > 0 ? 'danger' : 'success'; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value"><?php echo $low_stock; ?></div>
                    <div class="label">Produtos com Baixo Estoque</div>
                </div>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables Row -->
<div class="row">
    <!-- Sales Chart -->
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Vendas dos Últimos 7 Dias
                </h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Produtos Mais Vendidos
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_products)): ?>
                    <p class="text-muted">Nenhum dado disponível</p>
                <?php else: ?>
                    <?php foreach ($top_products as $product): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                <small class="text-muted"><?php echo $product['total_sold']; ?> unidades</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">
                                    R$ <?php echo number_format($product['revenue'], 2, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Sales -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Vendas Recentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_sales)): ?>
                    <p class="text-muted">Nenhuma venda encontrada</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td>#<?php echo $sale['id']; ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Cliente Avulso'); ?></td>
                                        <td class="fw-bold text-success">
                                            R$ <?php echo number_format($sale['total'], 2, ',', '.'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Concluída</span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></td>
                                        <td>
                                            <a href="../sales/receipt.php?id=<?php echo $sale['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        </td>
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

<script>
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesData = <?php echo json_encode($sales_chart_data); ?>;

const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: salesData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
            label: 'Vendas',
            data: salesData.map(item => item.count),
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }, {
            label: 'Faturamento (R$)',
            data: salesData.map(item => item.total),
            backgroundColor: 'rgba(39, 174, 96, 0.1)',
            borderColor: 'rgba(39, 174, 96, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
