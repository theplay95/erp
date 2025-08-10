<?php
$pageTitle = 'Usuários';
require_once '../config/database.php';
requireLogin();

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: /dashboard.php');
    exit();
}

// Get users - use email since username column doesn't exist
$stmt = $pdo->query("SELECT * FROM users ORDER BY email");
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Gerenciar Usuários</h1>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus"></i> Novo Usuário
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-4">
                <i class="bi bi-person-gear fs-1 text-muted"></i>
                <p class="text-muted mt-2">Nenhum usuário encontrado.</p>
                <a href="add.php" class="btn btn-primary">Adicionar Primeiro Usuário</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Função</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Último Login</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : 'primary' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= isset($user['active']) && $user['active'] ? 'success' : 'secondary' ?>">
                                    <?= isset($user['active']) && $user['active'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td><?= formatDate($user['created_at']) ?></td>
                            <td><?= isset($user['last_login']) && $user['last_login'] ? formatDate($user['last_login']) : 'Nunca' ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="delete.php?id=<?= $user['id'] ?>" class="btn btn-outline-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
