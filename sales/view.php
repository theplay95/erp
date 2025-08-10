<?php
$pageTitle = 'Detalhes da Venda';
require_once '../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit();
}

// Get sale with customer data
$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
           u.email as seller_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: index.php');
    exit();
}

// Get sale items
$stmt = $pdo->prepare("
    SELECT si.*, p.name as product_name
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Check if has delivery
$stmt = $pdo->prepare("SELECT id, status as delivery_status FROM delivery_orders WHERE sale_id = ?");
$stmt->execute([$id]);
$delivery = $stmt->fetch();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Venda #<?= $sale['id'] ?></h1>
        <p class="text-muted mb-0">
            <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?> - 
            <?= htmlspecialchars($sale['customer_name'] ?? 'Cliente não identificado') ?>
        </p>
    </div>
    <div>
        <?php if (!$delivery): ?>
        <a href="create_delivery.php?sale_id=<?= $sale['id'] ?>" class="btn btn-success me-2">
            <i class="bi bi-truck"></i> Criar Entrega
        </a>
        <?php endif; ?>
        <a href="receipt.php?id=<?= $sale['id'] ?>" class="btn btn-outline-primary me-2" target="_blank">
            <i class="bi bi-printer"></i> Cupom
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Sale Items -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-bag"></i> Itens da Venda
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
                            <td><?= formatCurrency($item['unit_price'] ?? 0) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= formatCurrency(($item['unit_price'] ?? 0) * $item['quantity']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <?php if (isset($sale['discount']) && $sale['discount'] > 0): ?>
                        <tr>
                            <th colspan="3">Subtotal:</th>
                            <th><?= formatCurrency($sale['subtotal'] ?? 0) ?></th>
                        </tr>
                        <tr>
                            <th colspan="3">Desconto:</th>
                            <th>-<?= formatCurrency($sale['discount']) ?></th>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-success">
                            <th colspan="3">Total:</th>
                            <th><?= formatCurrency($sale['total_amount']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Sale Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-receipt"></i> Informações da Venda
                </h6>
            </div>
            <div class="card-body">
                <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></p>
                <p><strong>Vendedor:</strong> <?= htmlspecialchars($sale['seller_name']) ?></p>
                <p><strong>Forma de Pagamento:</strong> 
                    <?php
                    $payment_methods = [
                        'dinheiro' => 'Dinheiro',
                        'cartao_credito' => 'Cartão de Crédito',
                        'cartao_debito' => 'Cartão de Débito',
                        'pix' => 'PIX'
                    ];
                    echo $payment_methods[$sale['payment_method']] ?? ucfirst($sale['payment_method']);
                    ?>
                </p>
                
                <?php if (isset($sale['discount']) && $sale['discount'] > 0): ?>
                <hr>
                <p><strong>Subtotal:</strong> <?= formatCurrency($sale['subtotal'] ?? 0) ?></p>
                <p><strong>Desconto:</strong> <?= formatCurrency($sale['discount']) ?></p>
                <?php endif; ?>
                <hr>
                <p class="mb-0"><strong>Total:</strong> <span class="fs-5 text-success"><?= formatCurrency($sale['total_amount']) ?></span></p>
            </div>
        </div>

        <!-- Customer Information -->
        <?php if ($sale['customer_name']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-person"></i> Informações do Cliente
                </h6>
            </div>
            <div class="card-body">
                <p><strong>Nome:</strong> <?= htmlspecialchars($sale['customer_name']) ?></p>
                <?php if ($sale['customer_phone']): ?>
                <p><strong>Telefone:</strong> <a href="tel:<?= $sale['customer_phone'] ?>"><?= htmlspecialchars($sale['customer_phone']) ?></a></p>
                <?php endif; ?>
                <?php if ($sale['customer_address']): ?>
                <p><strong>Endereço:</strong><br><?= nl2br(htmlspecialchars($sale['customer_address'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Delivery Information -->
        <?php if ($delivery): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-truck"></i> Informações de Entrega
                </h6>
            </div>
            <div class="card-body">
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
                <p>
                    <strong>Status:</strong>
                    <span class="badge <?= $statusClasses[$delivery['delivery_status']] ?>">
                        <?= $statusTexts[$delivery['delivery_status']] ?>
                    </span>
                </p>
                
                <div class="d-grid">
                    <a href="../delivery/view.php?id=<?= $delivery['id'] ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver Detalhes da Entrega
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-truck"></i> Entrega
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted">Esta venda não possui entrega cadastrada.</p>
                
                <div class="d-grid">
                    <a href="create_delivery.php?sale_id=<?= $sale['id'] ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-plus"></i> Criar Entrega
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>