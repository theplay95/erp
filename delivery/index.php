<?php
$pageTitle = 'Gestão de Delivery';
require_once '../config/database.php';
requireLogin();

// Get filter parameters
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereClause = "WHERE DATE(do.created_at) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];

if ($status && $status !== 'all') {
    $whereClause .= " AND do.status = ?";
    $params[] = $status;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM delivery_orders do $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalOrders = $stmt->fetch()['total'];
$totalPages = ceil($totalOrders / $limit);

// Get delivery orders
$sql = "SELECT do.*, s.total_amount as sale_total, c.name as customer_name, s.created_at as sale_date
        FROM delivery_orders do 
        LEFT JOIN sales s ON do.sale_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        $whereClause 
        ORDER BY do.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get statistics
$statsParams = [$dateFrom, $dateTo];
$statsWhere = "WHERE DATE(do.created_at) BETWEEN ? AND ?";

$statsSql = "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'preparando' THEN 1 END) as preparing,
                COUNT(CASE WHEN status = 'enviado' THEN 1 END) as sent,
                COUNT(CASE WHEN status = 'entregue' THEN 1 END) as delivered,
                COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelled,
                COALESCE(SUM(delivery_fee), 0) as total_fees
             FROM delivery_orders do $statsWhere";

$stmt = $pdo->prepare($statsSql);
$stmt->execute($statsParams);
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Gestão de Delivery</h1>
        <p class="text-muted mb-0">Controle completo dos pedidos de entrega</p>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> Nova Entrega
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?= $stats['total_orders'] ?></h5>
                <p class="card-text small">Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning"><?= $stats['pending'] ?></h5>
                <p class="card-text small">Pendente</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info"><?= $stats['preparing'] ?></h5>
                <p class="card-text small">Preparando</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?= $stats['sent'] ?></h5>
                <p class="card-text small">Enviado</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success"><?= $stats['delivered'] ?></h5>
                <p class="card-text small">Entregue</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-secondary"><?= formatCurrency($stats['total_fees']) ?></h5>
                <p class="card-text small">Taxa Total</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="preparando" <?= $status === 'preparando' ? 'selected' : '' ?>>Preparando</option>
                    <option value="enviado" <?= $status === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                    <option value="entregue" <?= $status === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                    <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Data Final</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="?" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Pedidos de Entrega</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Endereço</th>
                    <th>Valor</th>
                    <th>Taxa</th>
                    <th>Status</th>
                    <th>Entregador</th>
                    <th>Data Pedido</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-truck fs-1 d-block mb-2"></i>
                        Nenhum pedido de entrega encontrado
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong>#<?= $order['id'] ?></strong></td>
                    <td>
                        <?= htmlspecialchars($order['customer_name'] ?? 'Cliente não identificado') ?>
                        <?php if ($order['customer_phone']): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small><?= htmlspecialchars(substr($order['delivery_address'], 0, 50)) ?><?= strlen($order['delivery_address']) > 50 ? '...' : '' ?></small>
                    </td>
                    <td><?= formatCurrency($order['sale_total']) ?></td>
                    <td><?= formatCurrency($order['delivery_fee']) ?></td>
                    <td>
                        <?php 
                        $statusClasses = [
                            'pendente' => 'bg-warning',
                            'preparando' => 'bg-info', 
                            'enviado' => 'bg-primary',
                            'entregue' => 'bg-success',
                            'cancelado' => 'bg-danger'
                        ];
                        $statusTexts = [
                            'pendente' => 'Pendente',
                            'preparando' => 'Preparando',
                            'enviado' => 'Enviado', 
                            'entregue' => 'Entregue',
                            'cancelado' => 'Cancelado'
                        ];
                        ?>
                        <span class="badge <?= $statusClasses[$order['status']] ?>">
                            <?= $statusTexts[$order['status']] ?>
                        </span>
                    </td>
                    <td>
                        <?= htmlspecialchars($order['delivery_person'] ?? 'Não atribuído') ?>
                        <?php if ($order['estimated_time']): ?>
                        <br><small class="text-muted"><?= $order['estimated_time'] ?> min</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                        <br><small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary" title="Ver detalhes">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-outline-secondary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Anterior</a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Próximo</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>