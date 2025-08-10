<?php
$pageTitle = 'Vendas';
require_once '../config/database.php';
requireLogin();

// Get sales with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$whereClause = '';
$params = [];

if ($dateFrom) {
    $whereClause .= "WHERE DATE(s.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereClause .= ($whereClause ? " AND " : "WHERE ") . "DATE(s.created_at) <= ?";
    $params[] = $dateTo;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM sales s $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalSales = $stmt->fetch()['total'];
$totalPages = ceil($totalSales / $limit);

// Get sales - use email instead of username
$sql = "SELECT s.*, c.name as customer_name, u.email as user_email 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN users u ON s.user_id = u.id 
        $whereClause 
        ORDER BY s.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Vendas</h1>
    <a href="pos.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Nova Venda
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
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
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($sales)): ?>
            <div class="text-center py-4">
                <i class="bi bi-receipt fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhuma venda encontrada.</p>
                <a href="pos.php" class="btn btn-primary">Realizar Primeira Venda</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Vendedor</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>#<?= $sale['id'] ?></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Cliente Avulso') ?></td>
                            <td><?= formatCurrency($sale['total']) ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($sale['username']) ?></td>
                            <td><?= formatDate($sale['created_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="receipt.php?id=<?= $sale['id'] ?>" class="btn btn-outline-primary" title="Ver Recibo">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                    <button class="btn btn-outline-info" onclick="viewSaleDetails(<?= $sale['id'] ?>)" title="Detalhes">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Navegação da página">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes da Venda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function viewSaleDetails(saleId) {
    fetch(`/api/get_sale_details.php?id=${saleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('saleDetailsContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('saleDetailsModal')).show();
            }
        });
}
</script>

<?php include '../includes/footer.php'; ?>
