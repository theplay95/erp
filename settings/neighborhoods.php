<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
checkAdminAccess();

$pageTitle = 'Bairros e Taxas de Entrega';

// Processar ações (adicionar/editar/excluir)
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $pdo->prepare("INSERT INTO neighborhoods (name, delivery_fee, delivery_time_min, delivery_time_max, active) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['delivery_fee'],
                        $_POST['delivery_time_min'],
                        $_POST['delivery_time_max'],
                        isset($_POST['active']) ? 1 : 0
                    ]);
                    $success = "Bairro adicionado com sucesso!";
                    break;
                    
                case 'edit':
                    $stmt = $pdo->prepare("UPDATE neighborhoods SET name = ?, delivery_fee = ?, delivery_time_min = ?, delivery_time_max = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['delivery_fee'],
                        $_POST['delivery_time_min'],
                        $_POST['delivery_time_max'],
                        isset($_POST['active']) ? 1 : 0,
                        $_POST['neighborhood_id']
                    ]);
                    $success = "Bairro atualizado com sucesso!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM neighborhoods WHERE id = ?");
                    $stmt->execute([$_POST['neighborhood_id']]);
                    $success = "Bairro removido com sucesso!";
                    break;
            }
            
            logAudit($_SESSION['user_id'], $_POST['action'], 'neighborhoods', $_POST['neighborhood_id'] ?? 0, 
                     json_encode($_POST), $_SERVER['REMOTE_ADDR']);
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar bairros
$stmt = $pdo->prepare("SELECT * FROM neighborhoods ORDER BY name");
$stmt->execute();
$neighborhoods = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-geo-alt"></i> <?= $pageTitle ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addNeighborhoodModal">
                        <i class="bi bi-plus-lg"></i> Adicionar Bairro
                    </button>
                    <a href="/settings/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Bairros Cadastrados</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Bairro</th>
                                            <th>Taxa de Entrega</th>
                                            <th>Tempo de Entrega</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($neighborhoods as $neighborhood): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($neighborhood['name']) ?></td>
                                            <td>R$ <?= number_format($neighborhood['delivery_fee'], 2, ',', '.') ?></td>
                                            <td><?= $neighborhood['delivery_time_min'] ?>-<?= $neighborhood['delivery_time_max'] ?> min</td>
                                            <td>
                                                <?php if ($neighborhood['active']): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editNeighborhood(<?= htmlspecialchars(json_encode($neighborhood)) ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteNeighborhood(<?= $neighborhood['id'] ?>, '<?= htmlspecialchars($neighborhood['name']) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <?php if (empty($neighborhoods)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-geo-alt" style="font-size: 3rem; color: #dee2e6;"></i>
                                        <p class="text-muted mt-2">Nenhum bairro cadastrado ainda.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Adicionar/Editar Bairro -->
<div class="modal fade" id="addNeighborhoodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="neighborhoodForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Adicionar Bairro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="neighborhood_id" id="neighborhoodId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome do Bairro *</label>
                        <input type="text" class="form-control" name="name" id="neighborhoodName" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Taxa de Entrega *</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" name="delivery_fee" 
                                           id="deliveryFee" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tempo Mín. (min)</label>
                                <input type="number" class="form-control" name="delivery_time_min" 
                                       id="deliveryTimeMin" min="10" max="120" value="30">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tempo Máx. (min)</label>
                                <input type="number" class="form-control" name="delivery_time_max" 
                                       id="deliveryTimeMax" min="15" max="180" value="60">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" 
                                   id="neighborhoodActive" checked>
                            <label class="form-check-label" for="neighborhoodActive">
                                Bairro ativo para entregas
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="neighborhood_id" id="deleteNeighborhoodId">
                    <p>Tem certeza que deseja excluir o bairro <strong id="deleteNeighborhoodName"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Esta ação não pode ser desfeita.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editNeighborhood(neighborhood) {
    document.getElementById('modalTitle').textContent = 'Editar Bairro';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('neighborhoodId').value = neighborhood.id;
    document.getElementById('neighborhoodName').value = neighborhood.name;
    document.getElementById('deliveryFee').value = neighborhood.delivery_fee;
    document.getElementById('deliveryTimeMin').value = neighborhood.delivery_time_min;
    document.getElementById('deliveryTimeMax').value = neighborhood.delivery_time_max;
    document.getElementById('neighborhoodActive').checked = neighborhood.active == 1;
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Atualizar';
    
    new bootstrap.Modal(document.getElementById('addNeighborhoodModal')).show();
}

function deleteNeighborhood(id, name) {
    document.getElementById('deleteNeighborhoodId').value = id;
    document.getElementById('deleteNeighborhoodName').textContent = name;
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Reset modal quando fechar
document.getElementById('addNeighborhoodModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('neighborhoodForm').reset();
    document.getElementById('modalTitle').textContent = 'Adicionar Bairro';
    document.getElementById('formAction').value = 'add';
    document.getElementById('neighborhoodId').value = '';
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Salvar';
    document.getElementById('neighborhoodActive').checked = true;
});
</script>

<?php include '../includes/footer.php'; ?>