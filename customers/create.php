<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Novo Cliente';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Nome é obrigatório.';
    }
    
    if (empty($phone)) {
        $errors[] = 'Telefone é obrigatório.';
    } else {
        // Check if phone already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = 'Este número de telefone já está cadastrado.';
        }
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido.';
    }
    
    if ($birth_date && !DateTime::createFromFormat('Y-m-d', $birth_date)) {
        $errors[] = 'Data de nascimento inválida.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = Database::getInstance();
            
            $stmt = $pdo->prepare("
                INSERT INTO customers (name, phone, email, cep, address, birth_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $phone,
                $email ?: null,
                $cep ?: null,
                $address ?: null,
                $birth_date ?: null,
                $notes ?: null
            ]);
            
            $customer_id = $pdo->lastInsertId();
            
            logAudit('create_customer', 'customers', $customer_id, null, [
                'name' => $name,
                'phone' => $phone,
                'email' => $email
            ]);
            
            $success = true;
            $_POST = []; // Clear form
            
        } catch (Exception $e) {
            $errors[] = 'Erro ao salvar cliente: ' . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Novo Cliente</h1>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>
        Voltar
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        Cliente criado com sucesso!
        <a href="index.php" class="btn btn-sm btn-outline-success ms-2">Ver Clientes</a>
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
                    <i class="fas fa-user me-2"></i>
                    Informações do Cliente
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                       required maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="birth_date" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                       value="<?php echo $_POST['birth_date'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                       maxlength="20" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       maxlength="255">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep" name="cep" 
                                       placeholder="00000-000" maxlength="9"
                                       value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="mb-3">
                                <label for="address" class="form-label">Endereço Completo</label>
                                <textarea class="form-control" id="address" name="address" 
                                          rows="3" placeholder="Rua, número, bairro, cidade - estado"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" 
                                  rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Salvar Cliente
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
                        Nome completo é obrigatório
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Telefone ajuda no atendimento
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Email para promoções e contato
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Endereço para entregas
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-check text-success me-2"></i>
                        Data de nascimento para campanhas
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
