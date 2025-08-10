<?php
$pageTitle = 'Clientes';
require_once '../config/database.php';
requireLogin();

// Get customers with pagination
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
$countSql = "SELECT COUNT(*) as total FROM customers $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCustomers = $stmt->fetch()['total'];
$totalPages = ceil($totalCustomers / $limit);

// Get customers
$sql = "SELECT * FROM customers $whereClause ORDER BY name LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Clientes</h1>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Novo Cliente
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

<!-- Customers Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($customers)): ?>
            <div class="text-center py-4">
                <i class="bi bi-people fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhum cliente encontrado.</p>
                <a href="add.php" class="btn btn-primary">Adicionar Primeiro Cliente</a>
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
                            <th>Data Nascimento</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= $customer['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                        <?php if (strpos($customer['notes'] ?? '', 'catálogo online') !== false): ?>
                                            <span class="badge bg-success ms-2">Catálogo</span>
                                        <?php endif; ?>
                                        <?php if ($customer['address']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($customer['address']) ?></small>
                                        <?php endif; ?>
                                        <?php if ($customer['cep']): ?>
                                            <br><small class="text-muted">CEP: <?= htmlspecialchars($customer['cep']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($customer['phone']): ?>
                                    <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($customer['phone']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($customer['email']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $customer['birth_date'] ? date('d/m/Y', strtotime($customer['birth_date'])) : '' ?>
                            </td>
                            <td><?= formatDate($customer['created_at']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $customer['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este cliente?')">
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
