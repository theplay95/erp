<?php
$pageTitle = 'Relatórios';
require_once '../config/database.php';
requireLogin();

// Default date range (current month)
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Sales summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as average_sale,
        MIN(total_amount) as min_sale,
        MAX(total_amount) as max_sale
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$salesSummary = $stmt->fetch();

// Top products
$stmt = $pdo->prepare("
    SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.quantity * si.unit_price) as total_revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute([$dateFrom, $dateTo]);
$topProducts = $stmt->fetchAll();

// Payment methods
$stmt = $pdo->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$paymentMethods = $stmt->fetchAll();

// Daily sales
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as sales_count, SUM(total_amount) as daily_total
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
");
$stmt->execute([$dateFrom, $dateTo]);
$dailySales = $stmt->fetchAll();

// Financial summary
$stmt = $pdo->prepare("
    SELECT 
        type,
        SUM(amount) as total
    FROM financial_transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY type
");
$stmt->execute([$dateFrom, $dateTo]);
$financial = $stmt->fetchAll();
$income = 0;
$expense = 0;
foreach ($financial as $f) {
    if ($f['type'] == 'income') $income = $f['total'];
    if ($f['type'] == 'expense') $expense = $f['total'];
}

// Low stock products
$stmt = $pdo->query("SELECT name, stock_quantity as stock FROM products WHERE stock_quantity <= 10 ORDER BY stock_quantity ASC");
$lowStockProducts = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Relatórios</h1>
    <button onclick="window.print()" class="btn btn-outline-primary">
        <i class="bi bi-printer"></i> Imprimir
    </button>
</div>

<!-- Date Filter -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Data Inicial</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data Final</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Atualizar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-primary text-uppercase">Vendas</h6>
                        <h4 class="font-weight-bold"><?= $salesSummary['total_sales'] ?? 0 ?></h4>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-receipt fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-success text-uppercase">Receita</h6>
                        <h4 class="font-weight-bold"><?= formatCurrency($salesSummary['total_revenue'] ?? 0) ?></h4>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-currency-dollar fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-info text-uppercase">Ticket Médio</h6>
                        <h4 class="font-weight-bold"><?= formatCurrency($salesSummary['average_sale'] ?? 0) ?></h4>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-graph-up fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-warning text-uppercase">Lucro Líquido</h6>
                        <h4 class="font-weight-bold"><?= formatCurrency($income - $expense) ?></h4>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-piggy-bank fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Products -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Produtos Mais Vendidos</h6>
            </div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted">Nenhuma venda no período.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th>Receita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= $product['total_sold'] ?></td>
                                    <td><?= formatCurrency($product['total_revenue']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Métodos de Pagamento</h6>
            </div>
            <div class="card-body">
                <?php if (empty($paymentMethods)): ?>
                    <p class="text-muted">Nenhuma venda no período.</p>
                <?php else: ?>
                    <canvas id="paymentMethodsChart" width="400" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Daily Sales -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Vendas Diárias</h6>
            </div>
            <div class="card-body">
                <?php if (empty($dailySales)): ?>
                    <p class="text-muted">Nenhuma venda no período.</p>
                <?php else: ?>
                    <canvas id="dailySalesChart" width="400" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Low Stock -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 text-warning">Produtos com Estoque Baixo</h6>
            </div>
            <div class="card-body">
                <?php if (empty($lowStockProducts)): ?>
                    <p class="text-muted">Todos os produtos com estoque adequado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Estoque</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $product['stock'] == 0 ? 'danger' : 'warning' ?>">
                                            <?= $product['stock'] ?>
                                        </span>
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
// Payment Methods Chart
<?php if (!empty($paymentMethods)): ?>
const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
new Chart(paymentCtx, {
    type: 'pie',
    data: {
        labels: [<?php foreach ($paymentMethods as $pm): ?>'<?= ucfirst(str_replace("_", " ", $pm["payment_method"])) ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($paymentMethods as $pm): ?><?= $pm['total'] ?>,<?php endforeach; ?>],
            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

// Daily Sales Chart
<?php if (!empty($dailySales)): ?>
const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: [<?php foreach (array_reverse($dailySales) as $ds): ?>'<?= date('d/m', strtotime($ds['date'])) ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Vendas (R$)',
            data: [<?php foreach (array_reverse($dailySales) as $ds): ?><?= $ds['daily_total'] ?>,<?php endforeach; ?>],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
