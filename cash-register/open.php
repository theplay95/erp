<?php
$pageTitle = 'Abrir Caixa';
require_once '../config/database.php';
requireLogin();

// Check if user already has an open cash register
$stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open'");
$stmt->execute([$_SESSION['user_id']]);
$existingRegister = $stmt->fetch();

if ($existingRegister) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $initial_amount = $_POST['initial_amount'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');
    
    if ($initial_amount < 0) {
        $error = 'Valor inicial não pode ser negativo.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO cash_registers (user_id, initial_amount, current_amount, notes, opened_at) VALUES (?, ?, ?, ?, datetime('now'))");
            $stmt->execute([$_SESSION['user_id'], $initial_amount, $initial_amount, $notes ?: null]);
            
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $error = 'Erro ao abrir caixa: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Abrir Caixa</h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="bi bi-unlock fs-1 text-success"></i>
                    <h4 class="mt-2">Abertura de Caixa</h4>
                    <p class="text-muted">Informe o valor inicial para começar as operações</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-4">
                        <label for="initial_amount" class="form-label">Valor Inicial do Caixa *</label>
                        <div class="input-group">
                            <span class="input-group-text fs-4">R$</span>
                            <input type="number" class="form-control form-control-lg" id="initial_amount" name="initial_amount" step="0.01" min="0" value="<?= $initial_amount ?? '0.00' ?>" required>
                        </div>
                        <small class="form-text text-muted">Valor em dinheiro disponível no caixa no momento da abertura</small>
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Observações sobre a abertura do caixa..."><?= htmlspecialchars($notes ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-unlock"></i> Abrir Caixa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
