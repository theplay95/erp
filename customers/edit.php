<?php
$pageTitle = 'Editar Cliente';
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get customer
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birth_date = $_POST['birth_date'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name)) {
        $error = 'Nome é obrigatório.';
    } elseif (empty($phone)) {
        $error = 'Telefone é obrigatório.';
    } else {
        // Check if phone already exists for another customer
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $id]);
        if ($stmt->fetch()) {
            $error = 'Este número de telefone já está cadastrado.';
        }
    }
    
    if (!$error) {
        try {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, cep = ?, address = ?, birth_date = ?, notes = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $email ?: null, $cep ?: null, $address ?: null, $birth_date ?: null, $notes ?: null, $id]);
            
            $success = 'Cliente atualizado com sucesso!';
            
            // Refresh customer data
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar cliente: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Editar Cliente</h1>
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
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="birth_date" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= $customer['birth_date'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep" name="cep" 
                                       placeholder="00000-000" maxlength="9"
                                       value="<?= htmlspecialchars($customer['cep'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="mb-3">
                                <label for="address" class="form-label">Endereço Completo</label>
                                <textarea class="form-control" id="address" name="address" rows="2" 
                                          placeholder="Rua, número, bairro, cidade - estado"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Atualizar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
