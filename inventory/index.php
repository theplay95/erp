<?php
$pageTitle = 'Controle de Estoque';
require_once '../config/database.php';
requireLogin();

// Get products with stock information
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';

$whereClause = 'WHERE 1=1';
$params = [];

if ($search) {
    $whereClause .= " AND name LIKE ?";
    $params[] = "%$search%";
}

if ($category) {
    $whereClause .= " AND category_id = ?";
    $params[] = $category;
}

if ($stock_filter) {
    switch ($stock_filter) {
        case 'low':
            $whereClause .= " AND stock_quantity <= 10";
            break;
        case 'zero':
            $whereClause .= " AND stock_quantity = 0";
            break;
        case 'high':
            $whereClause .= " AND stock_quantity > 100";
            break;
    }
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM products $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalProducts = $stmt->fetch()['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products
$sql = "SELECT * FROM products $whereClause ORDER BY stock_quantity ASC, name LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories from categories table
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get stock summary
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN stock_quantity <= 10 THEN 1 ELSE 0 END) as low_stock, SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock FROM products");
$stockSummary = $stmt->fetch();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Controle de Estoque</h1>
    <a href="adjust.php" class="btn btn-primary">
        <i class="bi bi-plus-minus"></i> Ajustar Estoque
    </a>
</div>

<!-- Stock Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-info text-uppercase">Total de Produtos</h6>
                        <h4 class="font-weight-bold"><?= $stockSummary['total'] ?></h4>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-box-seam fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-left-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-warning text-uppercase">Estoque Baixo</h6>
                        <h4 class="font-weight-bold"><?= $stockSummary['low_stock'] ?></h4>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-left-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-danger text-uppercase">Sem Estoque</h6>
                        <h4 class="font-weight-bold"><?= $stockSummary['out_of_stock'] ?></h4>
                    </div>
                    <div class="text-danger">
                        <i class="bi bi-x-circle fs-1"></i>
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
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label class="form-label">Filtrar por Estoque</label>
                <select class="form-select" name="stock_filter">
                    <option value="">Todos</option>
                    <option value="low" <?= $stock_filter == 'low' ? 'selected' : '' ?>>Estoque Baixo (≤10)</option>
                    <option value="zero" <?= $stock_filter == 'zero' ? 'selected' : '' ?>>Sem Estoque</option>
                    <option value="high" <?= $stock_filter == 'high' ? 'selected' : '' ?>>Estoque Alto (>100)</option>
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

<!-- Products Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($products)): ?>
            <div class="text-center py-4">
                <i class="bi bi-boxes fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhum produto encontrado.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Estoque Atual</th>
                            <th>Status</th>
                            <th>Preço</th>
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
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (isset($product['category']) && $product['category']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($product['category']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">Sem categoria</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge fs-6 bg-<?= $product['stock_quantity'] == 0 ? 'danger' : ($product['stock_quantity'] <= 10 ? 'warning' : 'success') ?>">
                                    <?= $product['stock_quantity'] ?> unidades
                                </span>
                            </td>
                            <td>
                                <?php if ($product['stock_quantity'] == 0): ?>
                                    <span class="badge bg-danger">Sem Estoque</span>
                                <?php elseif ($product['stock_quantity'] <= 10): ?>
                                    <span class="badge bg-warning">Estoque Baixo</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Disponível</span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($product['price'], 2, ',', '.') ?></td>
                            <td>
                                <a href="adjust.php?product_id=<?= $product['id'] ?>" class="btn btn-outline-primary btn-sm" title="Ajustar Estoque">
                                    <i class="bi bi-plus-minus"></i>
                                </a>
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
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&stock_filter=<?= urlencode($stock_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
