<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Novo Produto';
$errors = [];
$success = false;

try {
    // Connection is already established in database.php as global $pdo
    
    // Get categories
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validation
        if (empty($name)) {
            $errors[] = 'Nome do produto é obrigatório.';
        }
        
        if ($price <= 0) {
            $errors[] = 'Preço deve ser maior que zero.';
        }
        
        if ($stock < 0) {
            $errors[] = 'Estoque não pode ser negativo.';
        }
        
        // Handle image upload
        $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $image_name = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $image_name;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $errors[] = 'Erro ao fazer upload da imagem.';
                    $image_name = null;
                }
            } else {
                $errors[] = 'Formato de imagem inválido. Use JPG, PNG ou GIF.';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, price, stock, category, description, image)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $price, $stock, $category, $description, $image_name]);
                
                $product_id = $pdo->lastInsertId();
                
                logAudit('create_product', 'products', $product_id, null, [
                    'name' => $name,
                    'price' => $price,
                    'stock' => $stock,
                    'category' => $category
                ]);
                
                $success = true;
                
                // Clear form data
                $_POST = [];
                
            } catch (Exception $e) {
                $errors[] = 'Erro ao salvar produto: ' . $e->getMessage();
                error_log($e->getMessage());
            }
        }
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $categories = [];
    $errors[] = 'Erro interno do servidor.';
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Novo Produto</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>
        Voltar
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        Produto criado com sucesso!
        <a href="index.php" class="btn btn-sm btn-outline-success ms-2">Ver Produtos</a>
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
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-box me-2"></i>
                    Informações do Produto
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome do Produto *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                       required maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category" class="form-label">Categoria</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Selecione uma categoria</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"
                                                <?php echo (($_POST['category'] ?? '') === $cat['name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Preço (R$) *</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo $_POST['price'] ?? ''; ?>" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stock" class="form-label">Estoque Inicial *</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                       value="<?php echo $_POST['stock'] ?? ''; ?>" 
                                       min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" maxlength="500"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="form-text">Máximo 500 caracteres</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Imagem do Produto</label>
                        <input type="file" class="form-control" id="image" name="image" 
                               accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB</div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Salvar Produto
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Dicas
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Use nomes descritivos para os produtos
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Adicione uma categoria para melhor organização
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Inclua uma descrição detalhada
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Use imagens de boa qualidade
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-check text-success me-2"></i>
                        Mantenha o estoque sempre atualizado
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('image');
    const maxSize = 2 * 1024 * 1024; // 2MB
    
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file && file.size > maxSize) {
            alert('Arquivo muito grande! O tamanho máximo é 2MB.');
            e.target.value = '';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
