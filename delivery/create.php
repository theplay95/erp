<?php
$pageTitle = 'Nova Entrega';
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

$error = '';
$success = '';

// Get recent sales without delivery
$stmt = $pdo->query("
    SELECT s.id, s.total_amount as total, s.created_at, c.name as customer_name, c.phone, c.address
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN delivery_orders do ON s.id = do.sale_id
    WHERE do.id IS NULL AND s.created_at >= datetime('now', '-7 days')
    ORDER BY s.created_at DESC
    LIMIT 50
");
$availableSales = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sale_id = (int)$_POST['sale_id'];
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);
    $delivery_person = trim($_POST['delivery_person'] ?? '');
    $estimated_time = (int)($_POST['estimated_time'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$sale_id) {
        $error = 'Selecione uma venda válida.';
    } elseif (empty($delivery_address)) {
        $error = 'Endereço de entrega é obrigatório.';
    } elseif ($delivery_fee < 0) {
        $error = 'Taxa de entrega não pode ser negativa.';
    } else {
        try {
            // Check if sale exists and doesn't have delivery yet
            $stmt = $pdo->prepare("
                SELECT s.id FROM sales s 
                LEFT JOIN delivery_orders do ON s.id = do.sale_id
                WHERE s.id = ? AND do.id IS NULL
            ");
            $stmt->execute([$sale_id]);
            
            if (!$stmt->fetch()) {
                $error = 'Venda não encontrada ou já possui entrega cadastrada.';
            } else {
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
                
                $success = 'Entrega criada com sucesso!';
                
                // Clear form
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = 'Erro ao criar entrega: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Nova Entrega</h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
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
                        <label for="sale_id" class="form-label">Venda <span class="text-danger">*</span></label>
                        <select class="form-select" id="sale_id" name="sale_id" required>
                            <option value="">Selecione uma venda...</option>
                            <?php foreach ($availableSales as $sale): ?>
                            <option value="<?= $sale['id'] ?>" <?= (($_POST['sale_id'] ?? '') == $sale['id']) ? 'selected' : '' ?>>
                                #<?= $sale['id'] ?> - <?= htmlspecialchars($sale['customer_name'] ?? 'Cliente não identificado') ?> - <?= formatCurrency($sale['total']) ?> (<?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($availableSales)): ?>
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle"></i> Nenhuma venda recente sem entrega encontrada.
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="delivery_address" class="form-label">Endereço de Entrega <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required><?= htmlspecialchars($_POST['delivery_address'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_phone" class="form-label">Telefone do Cliente</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
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
                        <a href="index.php" class="btn btn-outline-secondary me-2">Cancelar</a>
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
                <h6 class="mb-0">Informações</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Dica:</strong> Selecione uma venda recente que ainda não possui entrega cadastrada.
                </div>
                
                <h6>Status do Pedido:</h6>
                <ul class="list-unstyled small">
                    <li><span class="badge bg-warning">Pendente</span> - Aguardando confirmação</li>
                    <li><span class="badge bg-info">Preparando</span> - Pedido sendo preparado</li>
                    <li><span class="badge bg-primary">Enviado</span> - Saiu para entrega</li>
                    <li><span class="badge bg-success">Entregue</span> - Entrega concluída</li>
                    <li><span class="badge bg-danger">Cancelado</span> - Pedido cancelado</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('sale_id').addEventListener('change', function() {
    // Auto-populate fields based on sale selection if needed
    const saleId = this.value;
    if (saleId) {
        // Here you could make an AJAX call to get sale details
        // and populate address/phone fields automatically
    }
});
</script>

<?php include '../includes/footer.php'; ?>