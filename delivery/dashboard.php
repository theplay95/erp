<?php
$pageTitle = 'Dashboard de Delivery';
require_once '../config/database.php';
requireLogin();

// Get today's statistics
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'preparando' THEN 1 END) as preparing,
        COUNT(CASE WHEN status = 'enviado' THEN 1 END) as sent,
        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as delivered,
        COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelled,
        COALESCE(SUM(delivery_fee), 0) as total_fees,
        COALESCE(AVG(delivery_fee), 0) as avg_fee
    FROM delivery_orders 
    WHERE DATE(created_at) = ?
");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

// Get this week's statistics
$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(delivery_fee), 0) as total_fees
    FROM delivery_orders 
    WHERE DATE(created_at) >= ?
");
$stmt->execute([$weekStart]);
$weekStats = $stmt->fetch();

// Get recent deliveries needing attention
$stmt = $pdo->prepare("
    SELECT do.id, do.status as delivery_status, do.delivery_person_id as delivery_person, do.estimated_time, do.created_at,
           c.name as customer_name, s.total_amount as sale_total
    FROM delivery_orders do
    LEFT JOIN sales s ON do.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE do.status IN ('pendente', 'preparando', 'enviado')
    ORDER BY 
        CASE do.status 
            WHEN 'enviado' THEN 1
            WHEN 'preparando' THEN 2
            WHEN 'pendente' THEN 3
        END,
        do.created_at ASC
    LIMIT 10
");
$stmt->execute();
$urgentDeliveries = $stmt->fetchAll();

// Get delivery person performance
$stmt = $pdo->prepare("
    SELECT 
        delivery_person_id as delivery_person,
        COUNT(*) as total_deliveries,
        COUNT(CASE WHEN status = 'entregue' THEN 1 END) as completed,
        COALESCE(AVG(CASE WHEN updated_at IS NOT NULL AND created_at IS NOT NULL 
            THEN (julianday(updated_at) - julianday(created_at)) * 24 * 60 END), 0) as avg_time_minutes
    FROM delivery_orders 
    WHERE delivery_person_id IS NOT NULL 
        AND DATE(created_at) >= ?
    GROUP BY delivery_person_id
    ORDER BY completed DESC
    LIMIT 5
");
$stmt->execute([$weekStart]);
$deliveryPerformance = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Dashboard de Delivery</h1>
        <p class="text-muted mb-0">Visão geral das operações de entrega</p>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> Nova Entrega
        </a>
    </div>
</div>

<!-- Today's Statistics -->
<div class="row mb-4">
    <div class="col-12">
        <h5>Estatísticas de Hoje (<?= date('d/m/Y') ?>)</h5>
    </div>
</div>
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-truck fs-1 text-primary mb-2"></i>
                <h4 class="card-title"><?= $todayStats['total_orders'] ?></h4>
                <p class="card-text small text-muted">Total de Pedidos</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-hourglass fs-1 text-warning mb-2"></i>
                <h4 class="card-title"><?= $todayStats['pending'] ?></h4>
                <p class="card-text small text-muted">Pendentes</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-arrow-clockwise fs-1 text-info mb-2"></i>
                <h4 class="card-title"><?= $todayStats['preparing'] ?></h4>
                <p class="card-text small text-muted">Preparando</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-send fs-1 text-primary mb-2"></i>
                <h4 class="card-title"><?= $todayStats['sent'] ?></h4>
                <p class="card-text small text-muted">Enviados</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-check-circle fs-1 text-success mb-2"></i>
                <h4 class="card-title"><?= $todayStats['delivered'] ?></h4>
                <p class="card-text small text-muted">Entregues</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-currency-dollar fs-1 text-success mb-2"></i>
                <h4 class="card-title"><?= formatCurrency($todayStats['total_fees']) ?></h4>
                <p class="card-text small text-muted">Taxas Hoje</p>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Summary -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Resumo da Semana</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary"><?= $weekStats['total_orders'] ?></h4>
                        <small class="text-muted">Pedidos Totais</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?= formatCurrency($weekStats['total_fees']) ?></h4>
                        <small class="text-muted">Taxa de Entrega</small>
                    </div>
                </div>
                <?php if ($todayStats['total_orders'] > 0): ?>
                <hr>
                <div class="row text-center">
                    <div class="col-12">
                        <small class="text-muted">Taxa Média Hoje: <?= formatCurrency($todayStats['avg_fee']) ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Taxa de Sucesso Hoje</h6>
            </div>
            <div class="card-body">
                <?php 
                $successRate = $todayStats['total_orders'] > 0 ? 
                    ($todayStats['delivered'] / $todayStats['total_orders']) * 100 : 0;
                ?>
                <div class="text-center">
                    <h2 class="text-success"><?= number_format($successRate, 1) ?>%</h2>
                    <small class="text-muted">
                        <?= $todayStats['delivered'] ?> de <?= $todayStats['total_orders'] ?> pedidos entregues
                    </small>
                </div>
                <div class="progress mt-3">
                    <div class="progress-bar bg-success" style="width: <?= $successRate ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Urgent Deliveries -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Entregas que Precisam de Atenção</h6>
                <a href="index.php?status=pendente" class="btn btn-sm btn-outline-primary">Ver Todas</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Entregador</th>
                            <th>Tempo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($urgentDeliveries)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                                Nenhuma entrega pendente!
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($urgentDeliveries as $delivery): ?>
                        <tr>
                            <td><strong>#<?= $delivery['id'] ?></strong></td>
                            <td><?= htmlspecialchars($delivery['customer_name'] ?? 'Não identificado') ?></td>
                            <td>
                                <?php 
                                $statusClasses = [
                                    'pendente' => 'bg-warning',
                                    'preparando' => 'bg-info',
                                    'enviado' => 'bg-primary'
                                ];
                                $statusTexts = [
                                    'pendente' => 'Pendente',
                                    'preparando' => 'Preparando',
                                    'enviado' => 'Enviado'
                                ];
                                ?>
                                <span class="badge <?= $statusClasses[$delivery['delivery_status']] ?>">
                                    <?= $statusTexts[$delivery['delivery_status']] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($delivery['delivery_person'] ?? 'Não atribuído') ?></td>
                            <td>
                                <?php
                                $createdMinutesAgo = (strtotime('now') - strtotime($delivery['created_at'])) / 60;
                                $estimatedTime = $delivery['estimated_time'] ?? 30;
                                
                                if ($createdMinutesAgo > $estimatedTime) {
                                    echo '<span class="text-danger">' . number_format($createdMinutesAgo) . ' min (atrasado)</span>';
                                } else {
                                    echo '<span class="text-muted">' . number_format($createdMinutesAgo) . ' min</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?= $delivery['id'] ?>" class="btn btn-outline-primary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $delivery['id'] ?>" class="btn btn-outline-secondary" title="Editar">
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
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Delivery Person Performance -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Performance dos Entregadores (Esta Semana)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($deliveryPerformance)): ?>
                <div class="text-center text-muted">
                    <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                    <small>Nenhum entregador ativo esta semana</small>
                </div>
                <?php else: ?>
                <?php foreach ($deliveryPerformance as $performer): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong><?= htmlspecialchars($performer['delivery_person']) ?></strong>
                        <br>
                        <small class="text-muted">
                            <?= $performer['completed'] ?>/<?= $performer['total_deliveries'] ?> entregas
                            <?php if ($performer['avg_time_minutes'] > 0): ?>
                                (<?= number_format($performer['avg_time_minutes']) ?> min médio)
                            <?php endif; ?>
                        </small>
                    </div>
                    <div>
                        <?php 
                        $completionRate = $performer['total_deliveries'] > 0 ? 
                            ($performer['completed'] / $performer['total_deliveries']) * 100 : 0;
                        ?>
                        <span class="badge <?= $completionRate >= 90 ? 'bg-success' : ($completionRate >= 70 ? 'bg-warning' : 'bg-danger') ?>">
                            <?= number_format($completionRate) ?>%
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Ações Rápidas</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Nova Entrega
                    </a>
                    <a href="index.php?status=pendente" class="btn btn-outline-warning">
                        <i class="bi bi-hourglass"></i> Ver Pendentes
                    </a>
                    <a href="index.php?status=enviado" class="btn btn-outline-primary">
                        <i class="bi bi-send"></i> Ver Enviados
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-list"></i> Todas as Entregas
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>