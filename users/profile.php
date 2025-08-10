<?php
$pageTitle = 'Meu Perfil';
require_once '../config/database.php';
requireLogin();

$error = '';
$success = '';

// Get current user data
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password)) {
        $error = 'Senha atual é obrigatória.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Senha atual incorreta.';
    } elseif (empty($new_password)) {
        $error = 'Nova senha é obrigatória.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($new_password != $confirm_password) {
        $error = 'Confirmação de senha não confere.';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $success = 'Senha alterada com sucesso!';
            
        } catch (PDOException $e) {
            $error = 'Erro ao alterar senha: ' . $e->getMessage();
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue FROM sales WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userStats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) as total_registers FROM cash_registers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cashStats = $stmt->fetch();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Meu Perfil</h1>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Alterar Senha</h6>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Senha Atual *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nova Senha *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                        <small class="form-text text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nova Senha *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key"></i> Alterar Senha
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- User Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Informações da Conta</h6>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-5">Usuário:</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars($user['email']) ?></dd>
                    
                    <dt class="col-sm-5">Função:</dt>
                    <dd class="col-sm-7">
                        <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : 'primary' ?>">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-5">Membro desde:</dt>
                    <dd class="col-sm-7"><?= date('d/m/Y', strtotime($user['created_at'])) ?></dd>
                    
                    <dt class="col-sm-5">Último login:</dt>
                    <dd class="col-sm-7"><?= isset($user['last_login']) && $user['last_login'] ? formatDate($user['last_login']) : 'Primeiro acesso' ?></dd>
                </dl>
            </div>
        </div>
        
        <!-- User Statistics -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Suas Estatísticas</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?= $userStats['total_sales'] ?></h4>
                            <small class="text-muted">Vendas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?= formatCurrency($userStats['total_revenue']) ?></h4>
                        <small class="text-muted">Receita</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <h5 class="text-info"><?= $cashStats['total_registers'] ?></h5>
                    <small class="text-muted">Operações de Caixa</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
