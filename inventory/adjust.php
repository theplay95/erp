<?php
$pageTitle = 'Ajustar Estoque';
require_once '../config/database.php';
requireLogin();

$product_id = $_GET['product_id'] ?? 0;
$error = '';
$success = '';

// Get product if specified
$product = null;
if ($product_id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'] ?? 0;
    $adjustment_type = $_POST['adjustment_type'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$product_id || !$adjustment_type || !$quantity) {
        $error = 'Todos os campos são obrigatórios.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get current stock
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $current_stock = $stmt->fetch()['stock'] ?? 0;
            
            // Calculate new stock
            if ($adjustment_type == 'add') {
                $new_stock = $current_stock + $quantity;
            } else {
                $new_stock = max(0, $current_stock - $quantity);
            }
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $product_id]);
            
            // Record adjustment
            $stmt = $pdo->prepare("INSERT INTO inventory_adjustments (product_id, old_quantity, new_quantity, reason, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $current_stock, $new_stock, $reason, $_SESSION['user_id']]);
            
            // Record financial transaction if it's a loss
            if ($adjustment_type == 'remove') {
                $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product_price = $stmt->fetch()['price'];
                
                $loss_amount = $quantity * $product_price;
                $stmt = $pdo->prepare("INSERT INTO financial_transactions (type, category, amount, description, reference_type, user_id) VALUES ('expense', 'ajuste_estoque', ?, ?, 'manual', ?)");
                $stmt->execute([$loss_amount, "Ajuste de estoque - Produto #$product_id", $_SESSION['user_id']]);
            }
            
            $pdo->commit();
            $success = 'Estoque ajustado com sucesso!';
            
            // Clear form
            if (!$product_id) {
                $product_id = $adjustment_type = $quantity = $reason = '';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Erro ao ajustar estoque: ' . $e->getMessage();
        }
    }
}

// Get all products for dropdown
$stmt = $pdo->query("SELECT id, name, stock FROM products ORDER BY name");
$products = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Ajustar Estoque</h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="product_id" class="form-label">Produto *</label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Selecione um produto</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id'] ?>" <?= ($product && $product['id'] == $prod['id']) || $product_id == $prod['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prod['name']) ?> (Estoque atual: <?= $prod['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="adjustment_type" class="form-label">Tipo de Ajuste *</label>
                                <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="add">Adicionar ao Estoque</option>
                                    <option value="remove">Remover do Estoque</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantidade *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo do Ajuste *</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Descreva o motivo do ajuste de estoque..." required></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Confirmar Ajuste
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Últimos Ajustes</h6>
            </div>
            <div class="card-body">
                <?php
                // Get recent adjustments
                $stmt = $pdo->query("
                    SELECT ia.*, p.name as product_name, u.username 
                    FROM inventory_adjustments ia 
                    JOIN products p ON ia.product_id = p.id 
                    JOIN users u ON ia.user_id = u.id 
                    ORDER BY ia.created_at DESC 
                    LIMIT 10
                ");
                $adjustments = $stmt->fetchAll();
                ?>
                
                <?php if (empty($adjustments)): ?>
                    <p class="text-muted">Nenhum ajuste realizado ainda.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($adjustments as $adj): ?>
                                <tr>
                                    <td>
                                        <small>
                                            <strong><?= htmlspecialchars($adj['product_name']) ?></strong><br>
                                            <?= $adj['old_quantity'] ?> → <?= $adj['new_quantity'] ?><br>
                                            <span class="text-muted"><?= formatDate($adj['created_at']) ?></span>
                                        </small>
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

<?php include '../includes/footer.php'; ?>
