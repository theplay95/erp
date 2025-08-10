<?php
$pageTitle = 'Fechar Caixa';
require_once '../config/database.php';
requireLogin();

// Get user's active cash register
$stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$cashRegister = $stmt->fetch();

if (!$cashRegister) {
    header('Location: index.php');
    exit();
}

// Get cash movements summary
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out
    FROM cash_movements 
    WHERE register_id = ?
");
$stmt->execute([$cashRegister['id']]);
$movements = $stmt->fetch();

$total_in = $movements['total_in'] ?? 0;
$total_out = $movements['total_out'] ?? 0;
$expected_amount = $cashRegister['initial_amount'] + $total_in - $total_out;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $final_amount = $_POST['final_amount'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');
    
    if ($final_amount < 0) {
        $error = 'Valor final não pode ser negativo.';
    } else {
        try {
            $difference = $final_amount - $expected_amount;
            
            $stmt = $pdo->prepare("UPDATE cash_registers SET final_amount = ?, difference = ?, status = 'closed', notes = COALESCE(notes, '') || ? || ?, closed_at = datetime('now') WHERE id = ?");
            $stmt->execute([$final_amount, $difference, $notes ? "\n\nFechamento:\n" : '', $notes, $cashRegister['id']]);
            
            // Record difference as cash movement if exists
            if ($difference != 0) {
                $movement_type = $difference > 0 ? 'in' : 'out';
                $movement_amount = abs($difference);
                $movement_description = $difference > 0 ? 'Sobra no fechamento' : 'Falta no fechamento';
                
                $stmt = $pdo->prepare("INSERT INTO cash_movements (register_id, type, amount, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$cashRegister['id'], $movement_type, $movement_amount, $movement_description]);
            }
            
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $error = 'Erro ao fechar caixa: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Fechar Caixa</h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="bi bi-lock fs-1 text-danger"></i>
                    <h4 class="mt-2">Fechamento de Caixa</h4>
                    <p class="text-muted">Confira os valores e feche o caixa</p>
                </div>
                
                <!-- Cash Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h6 class="text-info">Valor Inicial</h6>
                                <h4><?= formatCurrency($cashRegister['initial_amount']) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6 class="text-success">Entradas</h6>
                                <h4><?= formatCurrency($total_in) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h6 class="text-danger">Saídas</h6>
                                <h4><?= formatCurrency($total_out) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="text-primary">Valor Esperado</h6>
                                <h4><?= formatCurrency($expected_amount) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-4">
                        <label for="final_amount" class="form-label">Valor Real Contado no Caixa *</label>
                        <div class="input-group">
                            <span class="input-group-text fs-4">R$</span>
                            <input type="number" class="form-control form-control-lg" id="final_amount" name="final_amount" step="0.01" min="0" value="<?= $expected_amount ?>" required>
                        </div>
                        <small class="form-text text-muted">Conte o dinheiro físico no caixa e informe o valor real</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Diferença</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control" id="difference" readonly value="0,00">
                        </div>
                        <small class="form-text text-muted">Diferença entre valor esperado e valor contado</small>
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label">Observações do Fechamento</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Observações sobre o fechamento do caixa..."><?= htmlspecialchars($notes ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="bi bi-lock"></i> Fechar Caixa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('final_amount').addEventListener('input', function() {
    const expectedAmount = <?= $expected_amount ?>;
    const finalAmount = parseFloat(this.value) || 0;
    const difference = finalAmount - expectedAmount;
    
    document.getElementById('difference').value = new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(difference);
    
    // Change color based on difference
    const diffField = document.getElementById('difference');
    diffField.className = 'form-control';
    if (difference > 0) {
        diffField.classList.add('text-success');
    } else if (difference < 0) {
        diffField.classList.add('text-danger');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
