<?php
$pageTitle = 'Adicionar Usuário';
require_once '../config/database.php';
requireLogin();

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: /dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (empty($username) || empty($password)) {
        $error = 'Nome de usuário e senha são obrigatórios.';
    } elseif ($password != $confirm_password) {
        $error = 'Senhas não coincidem.';
    } elseif (strlen($password) < 6) {
        $error = 'Senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Nome de usuário já existe.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $role, $active]);
                
                $success = 'Usuário criado com sucesso!';
                
                // Clear form
                $username = $password = $confirm_password = '';
                $role = 'employee';
                $active = 1;
            }
        } catch (PDOException $e) {
            $error = 'Erro ao criar usuário: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Adicionar Usuário</h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nome de Usuário *</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha *</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                        <small class="form-text text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Senha *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Função</label>
                        <select class="form-select" id="role" name="role">
                            <option value="employee" <?= ($role ?? 'employee') == 'employee' ? 'selected' : '' ?>>Funcionário</option>
                            <option value="admin" <?= ($role ?? '') == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="active" name="active" <?= ($active ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="active">
                                Usuário Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Criar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Informações sobre Funções</h6>
            </div>
            <div class="card-body">
                <dl>
                    <dt>Funcionário</dt>
                    <dd>Pode realizar vendas, gerenciar produtos, clientes e estoque. Não pode gerenciar usuários.</dd>
                    
                    <dt>Administrador</dt>
                    <dd>Acesso completo ao sistema, incluindo gerenciamento de usuários e configurações avançadas.</dd>
                </dl>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Cuidado:</strong> Administradores têm acesso total ao sistema.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
