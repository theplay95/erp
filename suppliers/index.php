<?php
$pageTitle = 'Fornecedores';
require_once '../config/database.php';
requireLogin();

// Get suppliers with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';

$whereClause = '';
$params = [];

if ($search) {
    $whereClause = "WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM suppliers $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalSuppliers = $stmt->fetch()['total'];
$totalPages = ceil($totalSuppliers / $limit);

// Get suppliers
$sql = "SELECT * FROM suppliers $whereClause ORDER BY name LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Fornecedores</h1>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Novo Fornecedor
    </a>
</div>

<!-- Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome, telefone ou email...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($suppliers)): ?>
            <div class="text-center py-4">
                <i class="bi bi-truck fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhum fornecedor encontrado.</p>
                <a href="add.php" class="btn btn-primary">Adicionar Primeiro Fornecedor</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>CNPJ</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?= $supplier['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($supplier['name']) ?></strong>
                                <?php if ($supplier['contact_person']): ?>
                                    <br><small class="text-muted">Contato: <?= htmlspecialchars($supplier['contact_person']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($supplier['phone']): ?>
                                    <a href="tel:<?= htmlspecialchars($supplier['phone']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($supplier['phone']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($supplier['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($supplier['email']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($supplier['cnpj']) ?></td>
                            <td><?= formatDate($supplier['created_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este fornecedor?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
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
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
