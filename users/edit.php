<?php
$pageTitle = 'Editar Usuário';
require_once '../config/database.php';
requireLogin();

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: /dashboard.php');
    exit();
}

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (empty($username)) {
        $error = 'Nome de usuário é obrigatório.';
    } elseif ($password && $password != $confirm_password) {
        $error = 'Senhas não coincidem.';
    } elseif ($password && strlen($password) < 6) {
        $error = 'Senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            // Check if username already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                $error = 'Nome de usuário já existe.';
            } else {
                // Update user
                if ($password) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, active = ? WHERE id = ?");
                    $stmt->execute([$username, $hashed_password, $role, $active, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, active = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $active, $id]);
                }
                
                $success = 'Usuário atualizado com sucesso!';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar usuário: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Editar Usuário</h1>
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
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6">
                        <small class="form-text text-muted">Deixe em branco para manter a senha atual</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Função</label>
                        <select class="form-select" id="role" name="role" <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="employee" <?= $user['role'] == 'employee' ? 'selected' : '' ?>>Funcionário</option>
                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                            <input type="hidden" name="role" value="<?= $user['role'] ?>">
                            <small class="form-text text-muted">Você não pode alterar sua própria função</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="active" name="active" <?= $user['active'] ? 'checked' : '' ?> <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="active">
                                Usuário Ativo
                            </label>
                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <input type="hidden" name="active" value="1">
                                <br><small class="form-text text-muted">Você não pode desativar sua própria conta</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Atualizar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Informações do Usuário</h6>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">ID:</dt>
                    <dd class="col-sm-8"><?= $user['id'] ?></dd>
                    
                    <dt class="col-sm-4">Criado em:</dt>
                    <dd class="col-sm-8"><?= formatDate($user['created_at']) ?></dd>
                    
                    <dt class="col-sm-4">Último login:</dt>
                    <dd class="col-sm-8"><?= $user['last_login'] ? formatDate($user['last_login']) : 'Nunca' ?></dd>
                </dl>
                
                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Nota:</strong> Você está editando sua própria conta. Algumas opções estão desabilitadas por segurança.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
