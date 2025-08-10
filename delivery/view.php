<?php
$pageTitle = 'Detalhes da Entrega';
require_once '../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit();
}

// Get delivery order with related data
$stmt = $pdo->prepare("
    SELECT do.*, s.total_amount as sale_total, s.payment_method, s.created_at as sale_date,
           c.name as customer_name, c.phone as customer_default_phone, c.address as customer_default_address,
           u.email as sale_user
    FROM delivery_orders do
    LEFT JOIN sales s ON do.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE do.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit();
}

// Get sale items
$stmt = $pdo->prepare("
    SELECT si.*, p.name as product_name, p.price as product_price
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$stmt->execute([$order['sale_id']]);
$items = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Entrega #<?= $order['id'] ?></h1>
        <p class="text-muted mb-0">
            <?php 
            $statusTexts = [
                'pendente' => 'Pendente',
                'preparando' => 'Preparando',
                'enviado' => 'Enviado', 
                'entregue' => 'Entregue',
                'cancelado' => 'Cancelado'
            ];
            ?>
            Status: <strong><?= $statusTexts[$order['status']] ?></strong>
        </p>
    </div>
    <div>
        <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-primary me-2">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Delivery Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-truck"></i> Informações da Entrega
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Status da Entrega</h6>
                        <?php 
                        $statusClasses = [
                            'pendente' => 'bg-warning',
                            'preparando' => 'bg-info', 
                            'enviado' => 'bg-primary',
                            'entregue' => 'bg-success',
                            'cancelado' => 'bg-danger'
                        ];
                        ?>
                        <span class="badge <?= $statusClasses[$order['delivery_status']] ?> mb-3">
                            <?= $statusTexts[$order['delivery_status']] ?>
                        </span>
                        
                        <h6>Endereço de Entrega</h6>
                        <p><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
                        
                        <?php if ($order['customer_phone']): ?>
                        <h6>Telefone de Contato</h6>
                        <p><a href="tel:<?= $order['customer_phone'] ?>"><?= htmlspecialchars($order['customer_phone']) ?></a></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6>Taxa de Entrega</h6>
                        <p class="fs-5 text-success"><?= formatCurrency($order['delivery_fee']) ?></p>
                        
                        <?php if ($order['delivery_person']): ?>
                        <h6>Entregador</h6>
                        <p><?= htmlspecialchars($order['delivery_person']) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($order['estimated_time']): ?>
                        <h6>Tempo Estimado</h6>
                        <p><?= $order['estimated_time'] ?> minutos</p>
                        <?php endif; ?>
                        
                        <h6>Data do Pedido</h6>
                        <p><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                        
                        <?php if ($order['delivered_at']): ?>
                        <h6>Data da Entrega</h6>
                        <p><?= date('d/m/Y H:i', strtotime($order['delivered_at'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($order['notes']): ?>
                <div class="mt-3">
                    <h6>Observações</h6>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sale Items -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-bag"></i> Itens do Pedido
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produto</th>
                            <th>Preço Unit.</th>
                            <th>Qtd</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= formatCurrency($item['price']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= formatCurrency($item['price'] * $item['quantity']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3">Subtotal dos Produtos:</th>
                            <th><?= formatCurrency($order['sale_total']) ?></th>
                        </tr>
                        <tr>
                            <th colspan="3">Taxa de Entrega:</th>
                            <th><?= formatCurrency($order['delivery_fee']) ?></th>
                        </tr>
                        <tr class="table-success">
                            <th colspan="3">Total Geral:</th>
                            <th><?= formatCurrency($order['sale_total'] + $order['delivery_fee']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-person"></i> Informações do Cliente
                </h6>
            </div>
            <div class="card-body">
                <?php if ($order['customer_name']): ?>
                <p><strong>Nome:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                <?php endif; ?>
                
                <?php if ($order['customer_default_phone']): ?>
                <p><strong>Telefone:</strong> <a href="tel:<?= $order['customer_default_phone'] ?>"><?= htmlspecialchars($order['customer_default_phone']) ?></a></p>
                <?php endif; ?>
                
                <?php if ($order['customer_default_address']): ?>
                <p><strong>Endereço Padrão:</strong><br><?= nl2br(htmlspecialchars($order['customer_default_address'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sale Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-receipt"></i> Informações da Venda
                </h6>
            </div>
            <div class="card-body">
                <p><strong>Venda #<?= $order['sale_id'] ?></strong></p>
                <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($order['sale_date'])) ?></p>
                <p><strong>Vendedor:</strong> <?= htmlspecialchars($order['sale_user']) ?></p>
                <p><strong>Forma de Pagamento:</strong> <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></p>
                <p><strong>Valor:</strong> <?= formatCurrency($order['sale_total']) ?></p>
                
                <div class="d-grid">
                    <a href="../sales/view.php?id=<?= $order['sale_id'] ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver Venda Completa
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Status Update -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-lightning"></i> Atualização Rápida
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="update_status.php">
                    <input type="hidden" name="delivery_id" value="<?= $order['id'] ?>">
                    <div class="mb-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">Alterar status...</option>
                            <option value="pendente" <?= $order['delivery_status'] === 'pendente' ? 'disabled' : '' ?>>Pendente</option>
                            <option value="preparando" <?= $order['delivery_status'] === 'preparando' ? 'disabled' : '' ?>>Preparando</option>
                            <option value="enviado" <?= $order['delivery_status'] === 'enviado' ? 'disabled' : '' ?>>Enviado</option>
                            <option value="entregue" <?= $order['delivery_status'] === 'entregue' ? 'disabled' : '' ?>>Entregue</option>
                            <option value="cancelado" <?= $order['delivery_status'] === 'cancelado' ? 'disabled' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                </form>
                
                <small class="text-muted">Selecione um status para atualizar rapidamente</small>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>