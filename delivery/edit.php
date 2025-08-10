<?php
$pageTitle = 'Editar Entrega';
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit();
}

// Get delivery order
$stmt = $pdo->prepare("
    SELECT do.*, s.total_amount as sale_total, c.name as customer_name
    FROM delivery_orders do
    LEFT JOIN sales s ON do.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE do.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);
    $delivery_status = $_POST['status'] ?? '';
    $delivery_person = trim($_POST['delivery_person'] ?? '');
    $estimated_time = (int)($_POST['estimated_time'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($delivery_address)) {
        $error = 'Endereço de entrega é obrigatório.';
    } elseif ($delivery_fee < 0) {
        $error = 'Taxa de entrega não pode ser negativa.';
    } elseif (!in_array($delivery_status, ['pendente', 'preparando', 'enviado', 'entregue', 'cancelado'])) {
        $error = 'Status de entrega inválido.';
    } else {
        try {
            $oldValues = $order;
            
            // If status is changing to 'entregue', set delivered_at
            $delivered_at = null;
            if ($delivery_status === 'entregue' && $order['status'] !== 'entregue') {
                $delivered_at = date('Y-m-d H:i:s');
            } elseif ($delivery_status !== 'entregue') {
                $delivered_at = null; // Reset delivered_at if status changed away from 'entregue'
            }
            
            $stmt = $pdo->prepare("
                UPDATE delivery_orders SET
                    delivery_address = ?,
                    customer_phone = ?,
                    delivery_fee = ?,
                    status = ?,
                    delivery_person_id = ?,
                    estimated_time = ?,
                    notes = ?,
                    delivered_at = COALESCE(?, delivered_at)
                WHERE id = ?
            ");
            
            $stmt->execute([
                $delivery_address,
                $customer_phone ?: null,
                $delivery_fee,
                $delivery_status,
                $delivery_person ?: null,
                $estimated_time ?: null,
                $notes ?: null,
                $delivered_at,
                $id
            ]);
            
            // Log audit
            $newValues = [
                'delivery_address' => $delivery_address,
                'customer_phone' => $customer_phone,
                'delivery_fee' => $delivery_fee,
                'delivery_status' => $delivery_status,
                'delivery_person' => $delivery_person,
                'estimated_time' => $estimated_time,
                'notes' => $notes
            ];
            
            logAudit('UPDATE', 'delivery_orders', $id, $oldValues, $newValues);
            
            $success = 'Entrega atualizada com sucesso!';
            
            // Refresh order data
            $stmt = $pdo->prepare("
                SELECT do.*, s.total as sale_total, c.name as customer_name
                FROM delivery_orders do
                LEFT JOIN sales s ON do.sale_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE do.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar entrega: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Editar Entrega #<?= $order['id'] ?></h1>
        <p class="text-muted mb-0">Venda #<?= $order['sale_id'] ?> - <?= htmlspecialchars($order['customer_name'] ?? 'Cliente não identificado') ?></p>
    </div>
    <div>
        <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-outline-info me-2">
            <i class="bi bi-eye"></i> Ver Detalhes
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
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="delivery_address" class="form-label">Endereço de Entrega <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required><?= htmlspecialchars($order['delivery_address']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">Telefone do Cliente</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="delivery_fee" class="form-label">Taxa de Entrega</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="delivery_fee" name="delivery_fee" step="0.01" min="0" value="<?= $order['delivery_fee'] ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="delivery_status" class="form-label">Status da Entrega <span class="text-danger">*</span></label>
                                <select class="form-select" id="delivery_status" name="status" required>
                                    <option value="pendente" <?= $order['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="preparando" <?= $order['status'] === 'preparando' ? 'selected' : '' ?>>Preparando</option>
                                    <option value="enviado" <?= $order['status'] === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                                    <option value="entregue" <?= $order['status'] === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                                    <option value="cancelado" <?= $order['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="delivery_person" class="form-label">Entregador</label>
                                <input type="text" class="form-control" id="delivery_person" name="delivery_person" value="<?= htmlspecialchars($order['delivery_person'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="estimated_time" class="form-label">Tempo Estimado (min)</label>
                                <input type="number" class="form-control" id="estimated_time" name="estimated_time" min="0" value="<?= $order['estimated_time'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="index.php" class="btn btn-outline-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Informações da Venda</h6>
            </div>
            <div class="card-body">
                <p><strong>Venda:</strong> #<?= $order['sale_id'] ?></p>
                <p><strong>Cliente:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Não identificado') ?></p>
                <p><strong>Total da Venda:</strong> <?= formatCurrency($order['sale_total']) ?></p>
                <p><strong>Taxa de Entrega:</strong> <?= formatCurrency($order['delivery_fee']) ?></p>
                <hr>
                <p><strong>Total Geral:</strong> <span class="fs-5 text-success"><?= formatCurrency($order['sale_total'] + $order['delivery_fee']) ?></span></p>
                
                <div class="d-grid">
                    <a href="../sales/view.php?id=<?= $order['sale_id'] ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver Venda
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Histórico de Status</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Status Atual:</strong> 
                    <?php 
                    $statusTexts = [
                        'pendente' => 'Pendente',
                        'preparando' => 'Preparando',
                        'enviado' => 'Enviado', 
                        'entregue' => 'Entregue',
                        'cancelado' => 'Cancelado'
                    ];
                    $statusClasses = [
                        'pendente' => 'warning',
                        'preparando' => 'info', 
                        'enviado' => 'primary',
                        'entregue' => 'success',
                        'cancelado' => 'danger'
                    ];
                    ?>
                    <span class="badge bg-<?= $statusClasses[$order['status']] ?>">
                        <?= $statusTexts[$order['status']] ?>
                    </span>
                </div>
                
                <p><strong>Criado em:</strong><br><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                
                <?php if (isset($order['delivered_at']) && $order['delivered_at']): ?>
                <p><strong>Entregue em:</strong><br><?= date('d/m/Y H:i', strtotime($order['delivered_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('delivery_status').addEventListener('change', function() {
    const status = this.value;
    const deliveryPersonField = document.getElementById('delivery_person');
    
    if (status === 'enviado' || status === 'entregue') {
        deliveryPersonField.required = true;
        deliveryPersonField.closest('.mb-3').querySelector('.form-label').innerHTML = 'Entregador <span class="text-danger">*</span>';
    } else {
        deliveryPersonField.required = false;
        deliveryPersonField.closest('.mb-3').querySelector('.form-label').innerHTML = 'Entregador';
    }
});

// Trigger on page load
document.getElementById('delivery_status').dispatchEvent(new Event('change'));
</script>

<?php include '../includes/footer.php'; ?>