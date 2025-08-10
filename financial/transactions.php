<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Transações Financeiras';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $reference_type = $_POST['reference_type'] ?? null;
    $reference_id = intval($_POST['reference_id'] ?? 0) ?: null;
    
    // Validation
    if (!in_array($type, ['income', 'expense'])) {
        $errors[] = 'Tipo de transação inválido.';
    }
    
    if (empty($category)) {
        $errors[] = 'Categoria é obrigatória.';
    }
    
    if ($amount <= 0) {
        $errors[] = 'Valor deve ser maior que zero.';
    }
    
    if (empty($description)) {
        $errors[] = 'Descrição é obrigatória.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance();
            
            $stmt = $pdo->prepare("
                INSERT INTO financial_transactions (type, category, amount, description, reference_type, reference_id, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $type,
                $category,
                $amount,
                $description,
                $reference_type,
                $reference_id,
                $_SESSION['user_id']
            ]);
            
            $transaction_id = $pdo->lastInsertId();
            
            logAudit('create_transaction', 'financial_transactions', $transaction_id, null, [
                'type' => $type,
                'category' => $category,
                'amount' => $amount
            ]);
            
            $success = true;
            $_POST = []; // Clear form
            
        } catch (Exception $e) {
            $errors[] = 'Erro ao salvar transação: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

try {
    $pdo = Database::getInstance();
    
    // Get all transactions
    $stmt = $pdo->prepare("
        SELECT ft.*, u.name as user_name
        FROM financial_transactions ft
        LEFT JOIN users u ON ft.user_id = u.id
        ORDER BY ft.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll();
    
    // Get common categories
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as usage_count
        FROM financial_transactions 
        WHERE category IS NOT NULL 
        GROUP BY category 
        ORDER BY usage_count DESC 
        LIMIT 10
    ");
    $common_categories = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $transactions = [];
    $common_categories = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Transações Financeiras</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-chart-line me-2"></i>
        Dashboard Financeiro
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        Transação registrada com sucesso!
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

<div class="row">
    <!-- Transaction Form -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Nova Transação
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="type" class="form-label">Tipo *</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Selecione o tipo</option>
                            <option value="income" <?php echo ($_POST['type'] ?? '') === 'income' ? 'selected' : ''; ?>>
                                Receita
                            </option>
                            <option value="expense" <?php echo ($_POST['type'] ?? '') === 'expense' ? 'selected' : ''; ?>>
                                Despesa
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Categoria *</label>
                        <input type="text" class="form-control" id="category" name="category" 
                               value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" 
                               required maxlength="100" list="categoryList">
                        <datalist id="categoryList">
                            <?php foreach ($common_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                            <?php endforeach; ?>
                            <option value="Vendas">
                            <option value="Compras">
                            <option value="Fornecedores">
                            <option value="Salários">
                            <option value="Impostos">
                            <option value="Aluguel">
                            <option value="Energia">
                            <option value="Telefone">
                            <option value="Marketing">
                            <option value="Manutenção">
                        </datalist>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Valor (R$) *</label>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               value="<?php echo $_POST['amount'] ?? ''; ?>" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição *</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" required maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reference_type" class="form-label">Referência</label>
                                <select class="form-select" id="reference_type" name="reference_type">
                                    <option value="">Nenhuma</option>
                                    <option value="sale" <?php echo ($_POST['reference_type'] ?? '') === 'sale' ? 'selected' : ''; ?>>
                                        Venda
                                    </option>
                                    <option value="purchase" <?php echo ($_POST['reference_type'] ?? '') === 'purchase' ? 'selected' : ''; ?>>
                                        Compra
                                    </option>
                                    <option value="salary" <?php echo ($_POST['reference_type'] ?? '') === 'salary' ? 'selected' : ''; ?>>
                                        Salário
                                    </option>
                                    <option value="tax" <?php echo ($_POST['reference_type'] ?? '') === 'tax' ? 'selected' : ''; ?>>
                                        Imposto
                                    </option>
                                    <option value="other" <?php echo ($_POST['reference_type'] ?? '') === 'other' ? 'selected' : ''; ?>>
                                        Outro
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reference_id" class="form-label">ID Referência</label>
                                <input type="number" class="form-control" id="reference_id" name="reference_id" 
                                       value="<?php echo $_POST['reference_id'] ?? ''; ?>" min="1">
                                <div class="form-text">Opcional</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>
                        Registrar Transação
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Transactions List -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Transações Recentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma transação encontrada</h5>
                        <p class="text-muted">Registre sua primeira transação financeira.</p>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="searchTransaction" placeholder="Buscar transação...">
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Categoria</th>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                    <th>Usuário</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($transaction['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $transaction['type'] === 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                                <i class="fas <?php echo $transaction['type'] === 'income' ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i>
                                                <?php echo $transaction['type'] === 'income' ? 'Receita' : 'Despesa'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($transaction['category'] ?? 'Sem categoria'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($transaction['description']); ?></div>
                                            <?php if ($transaction['reference_type']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-link me-1"></i>
                                                    <?php echo ucfirst($transaction['reference_type']); ?>
                                                    <?php if ($transaction['reference_id']): ?>
                                                        #<?php echo $transaction['reference_id']; ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold <?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>
                                            R$ <?php echo number_format($transaction['amount'], 2, ',', '.'); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($transaction['user_name'] ?? 'Sistema'); ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchTransaction');
    const table = document.getElementById('transactionsTable');
    const rows = table ? table.querySelectorAll('tbody tr') : [];
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
    }
    
    // Type selection styling
    const typeSelect = document.getElementById('type');
    typeSelect.addEventListener('change', function() {
        const form = this.closest('.card');
        if (this.value === 'income') {
            form.classList.remove('border-danger');
            form.classList.add('border-success');
        } else if (this.value === 'expense') {
            form.classList.remove('border-success');
            form.classList.add('border-danger');
        } else {
            form.classList.remove('border-success', 'border-danger');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
