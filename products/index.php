<?php
$pageTitle = 'Produtos';
require_once '../config/database.php';
requireLogin();

// Get products with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$whereClause = '';
$params = [];

if ($search) {
    $whereClause .= "WHERE name LIKE ?";
    $params[] = "%$search%";
}

if ($category) {
    $whereClause .= ($whereClause ? " AND " : "WHERE ") . "category_id = ?";
    $params[] = $category;
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM products $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalProducts = $stmt->fetch()['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products
$sql = "SELECT * FROM products $whereClause ORDER BY name LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories from categories table
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Produtos</h1>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Novo Produto
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome do produto...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoria</label>
                <select class="form-select" name="category">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($products)): ?>
            <div class="text-center py-4">
                <i class="bi bi-box-seam fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhum produto encontrado.</p>
                <a href="add.php" class="btn btn-primary">Adicionar Primeiro Produto</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= $product['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (isset($product['image']) && $product['image']): ?>
                                        <img src="/uploads/<?= htmlspecialchars($product['image']) ?>" alt="Produto" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <?php if ($product['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($product['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (isset($product['category']) && $product['category']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($product['category']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge bg-<?= $product['stock_quantity'] == 0 ? 'danger' : ($product['stock_quantity'] <= 10 ? 'warning' : 'success') ?>">
                                    <?= $product['stock_quantity'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $product['stock_quantity'] > 0 ? 'success' : 'danger' ?>">
                                    <?= $product['stock_quantity'] > 0 ? 'Disponível' : 'Esgotado' ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $product['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este produto?')">
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
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
