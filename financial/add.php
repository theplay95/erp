<?php
$pageTitle = 'Nova Transação Financeira';
require_once '../config/database.php';
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $amount = $_POST['amount'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    
    if (empty($type) || empty($description) || $amount <= 0) {
        $error = 'Todos os campos são obrigatórios.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO financial_transactions (type, category, amount, description, reference_type, user_id) VALUES (?, ?, ?, ?, 'manual', ?)");
            $stmt->execute([$type, $category ?: null, $amount, $description, $_SESSION['user_id']]);
            
            $success = 'Transação adicionada com sucesso!';
            
            // Clear form
            $type = $category = $amount = $description = '';
            
        } catch (PDOException $e) {
            $error = 'Erro ao salvar transação: ' . $e->getMessage();
        }
    }
}

// Get existing categories
$stmt = $pdo->query("SELECT DISTINCT category FROM financial_transactions WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Nova Transação Financeira</h1>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Tipo de Transação *</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="income" <?= ($type ?? '') == 'income' ? 'selected' : '' ?>>Receita</option>
                                    <option value="expense" <?= ($type ?? '') == 'expense' ? 'selected' : '' ?>>Despesa</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Valor *</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" value="<?= $amount ?? '' ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Categoria</label>
                        <input type="text" class="form-control" id="category" name="category" value="<?= htmlspecialchars($category ?? '') ?>" list="categories" placeholder="Ex: Despesas operacionais, Salários, Vendas...">
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>">
                            <?php endforeach; ?>
                            <option value="salarios">
                            <option value="aluguel">
                            <option value="energia">
                            <option value="internet">
                            <option value="marketing">
                            <option value="despesas_operacionais">
                            <option value="impostos">
                            <option value="manutencao">
                            <option value="combustivel">
                            <option value="outros">
                        </datalist>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Descreva a transação..." required><?= htmlspecialchars($description ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Transação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Dicas</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use categorias consistentes</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Seja específico na descrição</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Registre todas as movimentações</li>
                    <li><i class="bi bi-check-circle text-success me-2"></i>Mantenha comprovantes</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
