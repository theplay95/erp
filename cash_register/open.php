<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Abrir Caixa';
$errors = [];
$success = false;

try {
    $pdo = Database::getInstance();
    
    // Check if user already has an open cash register
    $stmt = $pdo->prepare("
        SELECT * FROM cash_registers 
        WHERE user_id = ? AND status = 'open'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $existing_register = $stmt->fetch();
    
    if ($existing_register) {
        header('Location: index.php');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $initial_amount = floatval($_POST['initial_amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if ($initial_amount < 0) {
            $errors[] = 'Valor inicial não pode ser negativo.';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO cash_registers (user_id, initial_amount, current_amount, notes, opened_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $initial_amount,
                    $initial_amount,
                    $notes ?: null
                ]);
                
                $register_id = $pdo->lastInsertId();
                
                logAudit('open_cash_register', 'cash_registers', $register_id, null, [
                    'initial_amount' => $initial_amount
                ]);
                
                $success = true;
                
                // Redirect to cash register page after 2 seconds
                header("refresh:2;url=index.php");
                
            } catch (Exception $e) {
                $errors[] = 'Erro ao abrir caixa: ' . $e->getMessage();
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
    <h1 class="h3 mb-0">Abrir Caixa</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>
        Voltar
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        Caixa aberto com sucesso! Redirecionando...
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

<?php if (!$success): ?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cash-register me-2"></i>
                    Abrir Novo Caixa
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-4">
                        <label for="initial_amount" class="form-label">Valor Inicial do Caixa (R$) *</label>
                        <input type="number" class="form-control form-control-lg text-center" 
                               id="initial_amount" name="initial_amount" 
                               value="<?php echo $_POST['initial_amount'] ?? '0.00'; ?>" 
                               step="0.01" min="0" required>
                        <div class="form-text">
                            Valor em dinheiro que está sendo colocado no caixa para início do expediente
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" 
                                  rows="3" placeholder="Observações sobre a abertura do caixa..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Importante:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Confira o valor inicial antes de confirmar</li>
                            <li>Este valor será usado como base para conferência no fechamento</li>
                            <li>Anote observações importantes para o controle</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <button type="submit" class="btn btn-success btn-lg me-md-2">
                            <i class="fas fa-cash-register me-2"></i>
                            Abrir Caixa
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Dicas para Abertura do Caixa
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-3">
                        <i class="fas fa-check text-success me-2"></i>
                        <strong>Conte o dinheiro físico</strong><br>
                        <small class="text-muted">Verifique todas as notas e moedas disponíveis</small>
                    </li>
                    <li class="mb-3">
                        <i class="fas fa-check text-success me-2"></i>
                        <strong>Registre o valor exato</strong><br>
                        <small class="text-muted">Digite exatamente o valor contado</small>
                    </li>
                    <li class="mb-3">
                        <i class="fas fa-check text-success me-2"></i>
                        <strong>Anote observações</strong><br>
                        <small class="text-muted">Registre informações importantes</small>
                    </li>
                    <li class="mb-3">
                        <i class="fas fa-check text-success me-2"></i>
                        <strong>Confira antes de confirmar</strong><br>
                        <small class="text-muted">Uma vez aberto, só pode ser alterado no fechamento</small>
                    </li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atenção:</strong> Você só pode ter um caixa aberto por vez.
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const initialAmountInput = document.getElementById('initial_amount');
    
    // Format currency input
    initialAmountInput.addEventListener('input', function(e) {
        let value = e.target.value;
        // Remove any non-numeric characters except dots
        value = value.replace(/[^\d.]/g, '');
        
        // Ensure only one decimal point
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        
        e.target.value = value;
    });
    
    // Auto-focus on the amount input
    initialAmountInput.focus();
    initialAmountInput.select();
});
</script>

<?php include '../includes/footer.php'; ?>
