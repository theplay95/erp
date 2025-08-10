<?php
$pageTitle = 'Criar Entrega para Venda';
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

$sale_id = (int)($_GET['sale_id'] ?? 0);

if (!$sale_id) {
    header('Location: index.php');
    exit();
}

// Get sale details
$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.phone, c.address
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: index.php');
    exit();
}

// Check if delivery already exists
$stmt = $pdo->prepare("SELECT id FROM delivery_orders WHERE sale_id = ?");
$stmt->execute([$sale_id]);
if ($stmt->fetch()) {
    header('Location: ../delivery/index.php?error=Esta venda já possui uma entrega cadastrada');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);
    $delivery_person = trim($_POST['delivery_person'] ?? '');
    $estimated_time = (int)($_POST['estimated_time'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($delivery_address)) {
        $error = 'Endereço de entrega é obrigatório.';
    } elseif ($delivery_fee < 0) {
        $error = 'Taxa de entrega não pode ser negativa.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO delivery_orders (
                    sale_id, delivery_address, customer_phone, delivery_fee, 
                    delivery_person, estimated_time, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $sale_id,
                $delivery_address,
                $customer_phone ?: null,
                $delivery_fee,
                $delivery_person ?: null,
                $estimated_time ?: null,
                $notes ?: null
            ]);
            
            $deliveryId = $pdo->lastInsertId();
            
            // Log audit
            logAudit('CREATE', 'delivery_orders', $deliveryId, null, [
                'sale_id' => $sale_id,
                'delivery_address' => $delivery_address,
                'delivery_fee' => $delivery_fee
            ]);
            
            header('Location: ../delivery/view.php?id=' . $deliveryId . '&success=Entrega criada com sucesso');
            exit();
            
        } catch (PDOException $e) {
            $error = 'Erro ao criar entrega: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Criar Entrega</h1>
        <p class="text-muted mb-0">Venda #<?= $sale['id'] ?> - <?= htmlspecialchars($sale['customer_name'] ?? 'Cliente não identificado') ?></p>
    </div>
    <div>
        <a href="view.php?id=<?= $sale['id'] ?>" class="btn btn-outline-info me-2">
            <i class="bi bi-eye"></i> Ver Venda
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Informações da Entrega</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="delivery_address" class="form-label">Endereço de Entrega <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required><?= htmlspecialchars($_POST['delivery_address'] ?? $sale['address'] ?? '') ?></textarea>
                        <?php if ($sale['address']): ?>
                        <div class="form-text">
                            <strong>Endereço do cliente:</strong> <?= htmlspecialchars($sale['address']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">Telefone do Cliente</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($_POST['customer_phone'] ?? $sale['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="delivery_fee" class="form-label">Taxa de Entrega</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="delivery_fee" name="delivery_fee" step="0.01" min="0" value="<?= $_POST['delivery_fee'] ?? '5.00' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="delivery_person" class="form-label">Entregador</label>
                                <input type="text" class="form-control" id="delivery_person" name="delivery_person" value="<?= htmlspecialchars($_POST['delivery_person'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="estimated_time" class="form-label">Tempo Estimado (minutos)</label>
                                <input type="number" class="form-control" id="estimated_time" name="estimated_time" min="0" value="<?= $_POST['estimated_time'] ?? '30' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="view.php?id=<?= $sale['id'] ?>" class="btn btn-outline-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Criar Entrega
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Resumo da Venda</h6>
            </div>
            <div class="card-body">
                <p><strong>Venda:</strong> #<?= $sale['id'] ?></p>
                <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></p>
                <p><strong>Cliente:</strong> <?= htmlspecialchars($sale['customer_name'] ?? 'Não identificado') ?></p>
                <p><strong>Total da Venda:</strong> <?= formatCurrency($sale['total']) ?></p>
                <p><strong>Forma de Pagamento:</strong> <?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?></p>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">Valor da Venda</small>
                        <h5><?= formatCurrency($sale['total']) ?></h5>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Taxa de Entrega</small>
                        <h5 id="totalDeliveryFee"><?= formatCurrency(5.00) ?></h5>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <small class="text-muted">Total Geral</small>
                    <h4 class="text-success" id="grandTotal"><?= formatCurrency($sale['total'] + 5.00) ?></h4>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Informações</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Dica:</strong> Os dados do cliente serão preenchidos automaticamente se disponíveis.
                </div>
                
                <h6>Status do Delivery:</h6>
                <ul class="list-unstyled small">
                    <li><span class="badge bg-warning">Pendente</span> - Aguardando confirmação</li>
                    <li><span class="badge bg-info">Preparando</span> - Pedido sendo preparado</li>
                    <li><span class="badge bg-primary">Enviado</span> - Saiu para entrega</li>
                    <li><span class="badge bg-success">Entregue</span> - Entrega concluída</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Update total when delivery fee changes
document.getElementById('delivery_fee').addEventListener('input', function() {
    const saleTotal = <?= $sale['total'] ?>;
    const deliveryFee = parseFloat(this.value) || 0;
    const grandTotal = saleTotal + deliveryFee;
    
    document.getElementById('totalDeliveryFee').textContent = 'R$ ' + deliveryFee.toFixed(2).replace('.', ',');
    document.getElementById('grandTotal').textContent = 'R$ ' + grandTotal.toFixed(2).replace('.', ',');
});

// Auto-fill address button
<?php if ($sale['address']): ?>
document.addEventListener('DOMContentLoaded', function() {
    const addressField = document.getElementById('delivery_address');
    if (addressField.value === '') {
        addressField.value = <?= json_encode($sale['address']) ?>;
    }
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>