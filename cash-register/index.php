<?php
$pageTitle = 'Controle de Caixa';
require_once '../config/database.php';
requireLogin();

// Get current user's active cash register
$stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$activeCashRegister = $stmt->fetch();

// Get recent cash registers
$stmt = $pdo->prepare("
    SELECT cr.*, u.email as username, 
           (SELECT SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) FROM cash_movements WHERE cash_register_id = cr.id) as movement_total
    FROM cash_registers cr 
    LEFT JOIN users u ON cr.user_id = u.id 
    ORDER BY cr.opened_at DESC 
    LIMIT 10
");
$stmt->execute();
$cashRegisters = $stmt->fetchAll();

// Get today's cash movements if register is open
$todayMovements = [];
if ($activeCashRegister) {
    $stmt = $pdo->prepare("
        SELECT * FROM cash_movements 
        WHERE cash_register_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$activeCashRegister['id']]);
    $todayMovements = $stmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Controle de Caixa</h1>
    <?php if (!$activeCashRegister): ?>
        <a href="open.php" class="btn btn-success">
            <i class="bi bi-unlock"></i> Abrir Caixa
        </a>
    <?php else: ?>
        <div>
            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addMovementModal">
                <i class="bi bi-plus"></i> Movimentação
            </button>
            <a href="close.php" class="btn btn-danger">
                <i class="bi bi-lock"></i> Fechar Caixa
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($activeCashRegister): ?>
<!-- Active Cash Register -->
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Caixa Aberto</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h6>Valor Inicial</h6>
                <p class="h4 text-success"><?= formatCurrency($activeCashRegister['initial_amount']) ?></p>
            </div>
            <div class="col-md-3">
                <h6>Valor Atual</h6>
                <p class="h4 text-primary"><?= formatCurrency($activeCashRegister['current_amount']) ?></p>
            </div>
            <div class="col-md-3">
                <h6>Aberto em</h6>
                <p><?= formatDate($activeCashRegister['opened_at']) ?></p>
            </div>
            <div class="col-md-3">
                <h6>Tempo Aberto</h6>
                <?php
                $openTime = new DateTime($activeCashRegister['opened_at']);
                $now = new DateTime();
                $diff = $now->diff($openTime);
                ?>
                <p><?= $diff->format('%H:%I:%S') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Today's Movements -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Movimentações de Hoje</h6>
    </div>
    <div class="card-body">
        <?php if (empty($todayMovements)): ?>
            <p class="text-muted">Nenhuma movimentação registrada hoje.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayMovements as $movement): ?>
                        <tr>
                            <td><?= date('H:i:s', strtotime($movement['created_at'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $movement['type'] == 'in' ? 'success' : 'danger' ?>">
                                    <?= $movement['type'] == 'in' ? 'Entrada' : 'Saída' ?>
                                </span>
                            </td>
                            <td class="text-<?= $movement['type'] == 'in' ? 'success' : 'danger' ?>">
                                <?= $movement['type'] == 'in' ? '+' : '-' ?><?= formatCurrency($movement['amount']) ?>
                            </td>
                            <td><?= htmlspecialchars($movement['description']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Cash Registers -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Histórico de Caixas</h6>
    </div>
    <div class="card-body">
        <?php if (empty($cashRegisters)): ?>
            <p class="text-muted">Nenhum caixa encontrado.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Valor Inicial</th>
                            <th>Valor Final</th>
                            <th>Diferença</th>
                            <th>Status</th>
                            <th>Período</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashRegisters as $register): ?>
                        <tr>
                            <td><?= htmlspecialchars($register['username']) ?></td>
                            <td><?= formatCurrency($register['initial_amount']) ?></td>
                            <td>
                                <?= $register['final_amount'] ? formatCurrency($register['final_amount']) : '-' ?>
                            </td>
                            <td>
                                <?php if ($register['difference'] !== null): ?>
                                    <span class="text-<?= $register['difference'] >= 0 ? 'success' : 'danger' ?>">
                                        <?= formatCurrency($register['difference']) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $register['status'] == 'open' ? 'success' : 'secondary' ?>">
                                    <?= $register['status'] == 'open' ? 'Aberto' : 'Fechado' ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    Aberto: <?= formatDate($register['opened_at']) ?><br>
                                    <?= $register['closed_at'] ? 'Fechado: ' . formatDate($register['closed_at']) : 'Em aberto' ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Movement Modal -->
<?php if ($activeCashRegister): ?>
<div class="modal fade" id="addMovementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/api/add_cash_movement.php">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Movimentação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="register_id" value="<?= $activeCashRegister['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo *</label>
                        <select class="form-select" name="type" required>
                            <option value="">Selecione</option>
                            <option value="in">Entrada</option>
                            <option value="out">Saída</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Valor *</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição *</label>
                        <textarea class="form-control" name="description" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
