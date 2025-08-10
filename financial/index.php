<?php
$pageTitle = 'Financeiro';
require_once '../config/database.php';
requireLogin();

// Get financial summary
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM financial_transactions 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

$totalIncome = $summary['total_income'] ?? 0;
$totalExpense = $summary['total_expense'] ?? 0;
$netProfit = $totalIncome - $totalExpense;

// Get transactions with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$type = $_GET['type'] ?? '';
$category = $_GET['category'] ?? '';

$whereClause = "WHERE DATE(ft.created_at) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];

if ($type) {
    $whereClause .= " AND ft.type = ?";
    $params[] = $type;
}

if ($category) {
    $whereClause .= " AND ft.category = ?";
    $params[] = $category;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM financial_transactions ft $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalTransactions = $stmt->fetch()['total'];
$totalPages = ceil($totalTransactions / $limit);

// Get transactions - users table uses email as identifier
$sql = "SELECT ft.*, u.email as username 
        FROM financial_transactions ft 
        LEFT JOIN users u ON ft.user_id = u.id 
        $whereClause 
        ORDER BY ft.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get categories
$stmt = $pdo->query("SELECT DISTINCT category FROM financial_transactions WHERE category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Financeiro</h1>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Nova Transação
    </a>
</div>

<!-- Financial Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-success text-uppercase">Receitas</h6>
                        <h4 class="font-weight-bold text-success"><?= formatCurrency($totalIncome) ?></h4>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-arrow-up-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-danger text-uppercase">Despesas</h6>
                        <h4 class="font-weight-bold text-danger"><?= formatCurrency($totalExpense) ?></h4>
                    </div>
                    <div class="text-danger">
                        <i class="bi bi-arrow-down-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-<?= $netProfit >= 0 ? 'success' : 'danger' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-<?= $netProfit >= 0 ? 'success' : 'danger' ?> text-uppercase">Lucro Líquido</h6>
                        <h4 class="font-weight-bold text-<?= $netProfit >= 0 ? 'success' : 'danger' ?>"><?= formatCurrency($netProfit) ?></h4>
                    </div>
                    <div class="text-<?= $netProfit >= 0 ? 'success' : 'danger' ?>">
                        <i class="bi bi-graph-<?= $netProfit >= 0 ? 'up' : 'down' ?> fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-info text-uppercase">Transações</h6>
                        <h4 class="font-weight-bold text-info"><?= $totalTransactions ?></h4>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-list-ul fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Data Inicial</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Final</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="type">
                    <option value="">Todos</option>
                    <option value="income" <?= $type == 'income' ? 'selected' : '' ?>>Receitas</option>
                    <option value="expense" <?= $type == 'expense' ? 'selected' : '' ?>>Despesas</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoria</label>
                <select class="form-select" name="category">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category == $cat['category'] ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($cat['category']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-4">
                <i class="bi bi-graph-up fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhuma transação encontrada.</p>
                <a href="add.php" class="btn btn-primary">Adicionar Primeira Transação</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Usuário</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?= formatDate($transaction['created_at']) ?></td>
                            <td>
                                <span class="badge bg-<?= $transaction['type'] == 'income' ? 'success' : 'danger' ?>">
                                    <?= $transaction['type'] == 'income' ? 'Receita' : 'Despesa' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($transaction['category']): ?>
                                    <span class="badge bg-secondary">
                                        <?= ucfirst(str_replace('_', ' ', htmlspecialchars($transaction['category']))) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($transaction['description']) ?></td>
                            <td class="text-<?= $transaction['type'] == 'income' ? 'success' : 'danger' ?>">
                                <?= $transaction['type'] == 'income' ? '+' : '-' ?><?= formatCurrency($transaction['amount']) ?>
                            </td>
                            <td><?= htmlspecialchars($transaction['username']) ?></td>
                            <td>
                                <?php if ($transaction['reference_type'] == 'manual'): ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteTransaction(<?= $transaction['id'] ?>)" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Navegação da página">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&type=<?= urlencode($type) ?>&category=<?= urlencode($category) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteTransaction(id) {
    if (confirm('Tem certeza que deseja excluir esta transação?')) {
        window.location.href = `/financial/delete.php?id=${id}`;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
