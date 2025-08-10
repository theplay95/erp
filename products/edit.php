<?php
$pageTitle = 'Editar Produto';
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = $_POST['price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name) || $price <= 0) {
        $error = 'Nome e preço são obrigatórios.';
    } else {
        try {
            $image = $product['image'];
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['image']['name'];
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($extension, $allowed)) {
                    // Delete old image
                    if ($image && file_exists('../uploads/' . $image)) {
                        unlink('../uploads/' . $image);
                    }
                    
                    $image = uniqid() . '.' . $extension;
                    move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ?, category = ?, description = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $price, $stock, $category, $description, $image, $id]);
            
            $success = 'Produto atualizado com sucesso!';
            
            // Refresh product data
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar produto: ' . $e->getMessage();
        }
    }
}

// Get existing categories
// Get categories - check if column exists first
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
} catch (PDOException $e) {
    // Column doesn't exist, create empty array
    $stmt = null;
}
$categories = $stmt ? $stmt->fetchAll() : [];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Editar Produto</h1>
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
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome do Produto *</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Preço *</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?= $product['price'] ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Categoria</label>
                                <input type="text" class="form-control" id="category" name="category" value="<?= htmlspecialchars($product['category'] ?? '') ?>" list="categories">
                                <datalist id="categories">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['category']) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stock" class="form-label">Estoque</label>
                                <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?= $product['stock_quantity'] ?? 0 ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Imagem do Produto</label>
                        <?php if (isset($product['image']) && $product['image']): ?>
                            <div class="mb-2">
                                <img src="/uploads/<?= htmlspecialchars($product['image']) ?>" alt="Produto atual" style="max-width: 200px; height: auto;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="form-text text-muted">Formatos aceitos: JPG, JPEG, PNG, GIF. Deixe em branco para manter a imagem atual.</small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Atualizar Produto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
