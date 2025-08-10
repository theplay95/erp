<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$page_title = 'Caixa';

try {
    $pdo = Database::getInstance();
    
    // Check if user has an open cash register
    $stmt = $pdo->prepare("
        SELECT * FROM cash_registers 
        WHERE user_id = ? AND status = 'open' 
        ORDER BY opened_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $current_register = $stmt->fetch();
    
    // Get recent cash registers
    $stmt = $pdo->prepare("
        SELECT cr.*, u.name as user_name
        FROM cash_registers cr
        LEFT JOIN users u ON cr.user_id = u.id
        ORDER BY cr.opened_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recent_registers = $stmt->fetchAll();
    
    // If there's an open register, get movements
    $movements = [];
    if ($current_register) {
        $stmt = $pdo->prepare("
            SELECT * FROM cash_movements 
            WHERE register_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$current_register['id']]);
        $movements = $stmt->fetchAll();
    }
    
    // Statistics
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_registers,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_registers,
            SUM(CASE WHEN DATE(opened_at) = ? THEN 1 ELSE 0 END) as today_registers
        FROM cash_registers
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $current_register = null;
    $recent_registers = [];
    $movements = [];
    $stats = ['total_registers' => 0, 'open_registers' => 0, 'today_registers' => 0];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Controle de Caixa</h1>
    <div>
        <?php if ($current_register): ?>
            <a href="close.php?id=<?php echo $current_register['id']; ?>" class="btn btn-danger">
                <i class="fas fa-times me-2"></i>
                Fechar Caixa
            </a>
        <?php else: ?>
            <a href="open.php" class="btn btn-primary">
                <i class="fas fa-cash-register me-2"></i>
                Abrir Caixa
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value"><?php echo $stats['total_registers']; ?></div>
                    <div class="label">Total de Caixas</div>
                </div>
                <div class="icon">
                    <i class="fas fa-cash-register"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stats-card <?php echo $stats['open_registers'] > 0 ? 'success' : 'warning'; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value"><?php echo $stats['open_registers']; ?></div>
                    <div class="label">Caixas Abertos</div>
                </div>
                <div class="icon">
                    <i class="fas fa-unlock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stats-card info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="value"><?php echo $stats['today_registers']; ?></div>
                    <div class="label">Caixas Hoje</div>
                </div>
                <div class="icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($current_register): ?>
    <!-- Current Cash Register -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-cash-register me-2"></i>
                        Caixa Aberto - #<?php echo $current_register['id']; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-info">R$ <?php echo number_format($current_register['initial_amount'], 2, ',', '.'); ?></div>
                                <small class="text-muted">Valor Inicial</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-success">R$ <?php echo number_format($current_register['current_amount'], 2, ',', '.'); ?></div>
                                <small class="text-muted">Valor Atual</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h4 text-primary">
                                    R$ <?php echo number_format($current_register['current_amount'] - $current_register['initial_amount'], 2, ',', '.'); ?>
                                </div>
                                <small class="text-muted">Movimentação</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="h6">
                                    <?php echo date('d/m/Y H:i', strtotime($current_register['opened_at'])); ?>
                                </div>
                                <small class="text-muted">Aberto em</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($current_register['notes']): ?>
                        <hr>
                        <div class="alert alert-info">
                            <strong>Observações:</strong> <?php echo htmlspecialchars($current_register['notes']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="addMovement('in')">
                            <i class="fas fa-plus me-2"></i>
                            Entrada
                        </button>
                        <button type="button" class="btn btn-outline-danger me-2" onclick="addMovement('out')">
                            <i class="fas fa-minus me-2"></i>
                            Saída
                        </button>
                        <a href="close.php?id=<?php echo $current_register['id']; ?>" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>
                            Fechar Caixa
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cash Movements -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Movimentações
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($movements)): ?>
                        <p class="text-muted">Nenhuma movimentação ainda.</p>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($movements as $movement): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                    <div>
                                        <div class="fw-bold <?php echo $movement['type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $movement['type'] === 'in' ? '+' : '-'; ?>
                                            R$ <?php echo number_format($movement['amount'], 2, ',', '.'); ?>
                                        </div>
                                        <?php if ($movement['description']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($movement['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($movement['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- No Open Register -->
    <div class="alert alert-warning text-center">
        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
        <h5>Nenhum caixa aberto</h5>
        <p>Você precisa abrir um caixa para começar a trabalhar.</p>
        <a href="open.php" class="btn btn-primary">
            <i class="fas fa-cash-register me-2"></i>
            Abrir Caixa
        </a>
    </div>
<?php endif; ?>

<!-- Recent Cash Registers -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>
            Histórico de Caixas
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($recent_registers)): ?>
            <p class="text-muted">Nenhum caixa encontrado.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuário</th>
                            <th>Valor Inicial</th>
                            <th>Valor Final</th>
                            <th>Diferença</th>
                            <th>Status</th>
                            <th>Aberto em</th>
                            <th>Fechado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_registers as $register): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $register['id']; ?></td>
                                <td><?php echo htmlspecialchars($register['user_name']); ?></td>
                                <td>R$ <?php echo number_format($register['initial_amount'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php if ($register['status'] === 'closed'): ?>
                                        R$ <?php echo number_format($register['final_amount'], 2, ',', '.'); ?>
                                    <?php else: ?>
                                        R$ <?php echo number_format($register['current_amount'], 2, ',', '.'); ?>
                                        <small class="text-muted">(atual)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($register['status'] === 'closed' && $register['difference'] !== null): ?>
                                        <span class="fw-bold <?php echo $register['difference'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $register['difference'] >= 0 ? '+' : ''; ?>
                                            R$ <?php echo number_format($register['difference'], 2, ',', '.'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $register['status'] === 'open' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $register['status'] === 'open' ? 'Aberto' : 'Fechado'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($register['opened_at'])); ?>
                                </td>
                                <td>
                                    <?php if ($register['closed_at']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($register['closed_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Movement Modal -->
<div class="modal fade" id="movementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="movementModalTitle">Movimentação de Caixa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="movementForm">
                <div class="modal-body">
                    <input type="hidden" id="movementType" name="type">
                    <input type="hidden" name="register_id" value="<?php echo $current_register['id'] ?? ''; ?>">
                    
                    <div class="mb-3">
                        <label for="movementAmount" class="form-label">Valor (R$) *</label>
                        <input type="number" class="form-control" id="movementAmount" name="amount" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="movementDescription" class="form-label">Descrição</label>
                        <textarea class="form-control" id="movementDescription" name="description" 
                                  rows="3" placeholder="Motivo da movimentação..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addMovement(type) {
    const modal = new bootstrap.Modal(document.getElementById('movementModal'));
    const title = document.getElementById('movementModalTitle');
    const typeInput = document.getElementById('movementType');
    
    if (type === 'in') {
        title.textContent = 'Entrada de Dinheiro';
        typeInput.value = 'in';
    } else {
        title.textContent = 'Saída de Dinheiro';
        typeInput.value = 'out';
    }
    
    // Clear form
    document.getElementById('movementForm').reset();
    typeInput.value = type;
    
    modal.show();
}

document.getElementById('movementForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    fetch('../api/cash_register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add_movement',
            ...data
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erro de conexão');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
