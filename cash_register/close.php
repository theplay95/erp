<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Fechar Caixa';
$errors = [];
$success = false;
$register = null;

$register_id = intval($_GET['id'] ?? 0);

if ($register_id <= 0) {
    header('Location: index.php');
    exit();
}

try {
    $pdo = Database::getInstance();
    
    // Get the cash register
    $stmt = $pdo->prepare("
        SELECT * FROM cash_registers 
        WHERE id = ? AND user_id = ? AND status = 'open'
    ");
    $stmt->execute([$register_id, $_SESSION['user_id']]);
    $register = $stmt->fetch();
    
    if (!$register) {
        header('Location: index.php');
        exit();
    }
    
    // Get movements for this register
    $stmt = $pdo->prepare("
        SELECT * FROM cash_movements 
        WHERE register_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$register_id]);
    $movements = $stmt->fetchAll();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $final_amount = floatval($_POST['final_amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if ($final_amount < 0) {
            $errors[] = 'Valor final não pode ser negativo.';
        }
        
        if (empty($errors)) {
            try {
                $difference = $final_amount - $register['current_amount'];
                
                $stmt = $pdo->prepare("
                    UPDATE cash_registers 
                    SET final_amount = ?, difference = ?, status = 'closed', 
                        notes = CONCAT(COALESCE(notes, ''), ?, ''), closed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $final_amount,
                    $difference,
                    $notes ? "\n\nFechamento: " . $notes : '',
                    $register_id
                ]);
                
                logAudit('close_cash_register', 'cash_registers', $register_id, $register, [
                    'final_amount' => $final_amount,
                    'difference' => $difference,
                    'status' => 'closed'
                ]);
                
                $success = true;
                
                // Redirect to cash register page after 2 seconds
                header("refresh:2;url=index.php");
                
            } catch (Exception $e) {
                $errors[] = 'Erro ao fechar caixa: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $errors[] = 'Erro interno do servidor.';
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Fechar Caixa</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>
        Voltar
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        Caixa fechado com sucesso! Redirecionando...
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Erro(s) encontrado(s):</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$success && $register): ?>
<div class="row">
    <div class="col-lg-8">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-times-circle me-2"></i>
                    Fechamento do Caixa #<?php echo $register['id']; ?>
                </h5>
            </div>
            <div class="card-body">
                <!-- Current Status -->
                <div class="row mb-4">
                    <div class="col-md-3 text-center">
                        <div class="h5 text-info">R$ <?php echo number_format($register['initial_amount'], 2, ',', '.'); ?></div>
                        <small class="text-muted">Valor Inicial</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="h5 text-primary">R$ <?php echo number_format($register['current_amount'], 2, ',', '.'); ?></div>
                        <small class="text-muted">Valor Sistema</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="h5 text-warning">
                            R$ <?php echo number_format($register['current_amount'] - $register['initial_amount'], 2, ',', '.'); ?>
                        </div>
                        <small class="text-muted">Movimentação</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="h6">
                            <?php 
                            $duration = time() - strtotime($register['opened_at']);
                            $hours = floor($duration / 3600);
                            $minutes = floor(($duration % 3600) / 60);
                            echo $hours . 'h ' . $minutes . 'm';
                            ?>
                        </div>
                        <small class="text-muted">Tempo Aberto</small>
                    </div>
                </div>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="final_amount" class="form-label">Valor Real do Caixa (R$) *</label>
                                <input type="number" class="form-control form-control-lg text-center" 
                                       id="final_amount" name="final_amount" 
                                       value="<?php echo $_POST['final_amount'] ?? $register['current_amount']; ?>" 
                                       step="0.01" min="0" required>
                                <div class="form-text">
                                    Conte o dinheiro físico e digite o valor exato encontrado
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Diferença</label>
                                <div class="form-control form-control-lg text-center" id="differenceDisplay">
                                    R$ 0,00
                                </div>
                                <div class="form-text" id="differenceText">
                                    Diferença entre valor real e sistema
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações do Fechamento</label>
                        <textarea class="form-control" id="notes" name="notes" 
                                  rows="3" placeholder="Observações sobre o fechamento, justificativa para diferenças..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Após fechar o caixa, não será mais possível fazer alterações.
                        Confira os valores antes de confirmar.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button type="submit" class="btn btn-danger btn-lg me-md-2" id="closeBtn">
                            <i class="fas fa-times-circle me-2"></i>
                            Fechar Caixa
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Movements Summary -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Resumo de Movimentações
                </h5>
            </div>
            <div class="card-body">
                <?php
                $total_in = 0;
                $total_out = 0;
                $count_in = 0;
                $count_out = 0;
                
                foreach ($movements as $movement) {
                    if ($movement['type'] === 'in') {
                        $total_in += $movement['amount'];
                        $count_in++;
                    } else {
                        $total_out += $movement['amount'];
                        $count_out++;
                    }
                }
                ?>
                
                <div class="row">
                    <div class="col-6 text-center">
                        <div class="text-success">
                            <div class="h5">R$ <?php echo number_format($total_in, 2, ',', '.'); ?></div>
                            <small><?php echo $count_in; ?> entradas</small>
                        </div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="text-danger">
                            <div class="h5">R$ <?php echo number_format($total_out, 2, ',', '.'); ?></div>
                            <small><?php echo $count_out; ?> saídas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Movements -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Movimentações
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($movements)): ?>
                    <p class="text-muted">Nenhuma movimentação registrada.</p>
                <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($movements as $movement): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                <div>
                                    <div class="fw-bold <?php echo $movement['type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $movement['type'] === 'in' ? '+' : '-'; ?>
                                        R$ <?php echo number_format($movement['amount'], 2, ',', '.'); ?>
                                    </div>
                                    <?php if ($movement['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($movement['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <?php echo date('H:i', strtotime($movement['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const finalAmountInput = document.getElementById('final_amount');
    const differenceDisplay = document.getElementById('differenceDisplay');
    const differenceText = document.getElementById('differenceText');
    const closeBtn = document.getElementById('closeBtn');
    const systemAmount = <?php echo $register['current_amount']; ?>;
    
    function updateDifference() {
        const finalAmount = parseFloat(finalAmountInput.value) || 0;
        const difference = finalAmount - systemAmount;
        
        // Format difference
        const formattedDifference = 'R$ ' + Math.abs(difference).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        if (difference > 0) {
            differenceDisplay.textContent = '+' + formattedDifference;
            differenceDisplay.className = 'form-control form-control-lg text-center text-success';
            differenceText.textContent = 'Sobra de caixa';
            differenceText.className = 'form-text text-success';
        } else if (difference < 0) {
            differenceDisplay.textContent = '-' + formattedDifference;
            differenceDisplay.className = 'form-control form-control-lg text-center text-danger';
            differenceText.textContent = 'Falta no caixa';
            differenceText.className = 'form-text text-danger';
        } else {
            differenceDisplay.textContent = formattedDifference;
            differenceDisplay.className = 'form-control form-control-lg text-center';
            differenceText.textContent = 'Caixa conferido';
            differenceText.className = 'form-text text-success';
        }
        
        // Update button text based on difference
        if (Math.abs(difference) > 0.01) {
            closeBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Fechar com Diferença';
        } else {
            closeBtn.innerHTML = '<i class="fas fa-check me-2"></i>Fechar Caixa';
        }
    }
    
    finalAmountInput.addEventListener('input', updateDifference);
    
    // Initial calculation
    updateDifference();
    
    // Focus and select the final amount input
    finalAmountInput.focus();
    finalAmountInput.select();
    
    // Confirmation for closing with difference
    document.querySelector('form').addEventListener('submit', function(e) {
        const finalAmount = parseFloat(finalAmountInput.value) || 0;
        const difference = Math.abs(finalAmount - systemAmount);
        
        if (difference > 0.01) {
            if (!confirm('Há uma diferença de R$ ' + difference.toFixed(2) + ' no caixa. Deseja continuar com o fechamento?')) {
                e.preventDefault();
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
