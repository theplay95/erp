<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Set JSON header
header('Content-Type: application/json');

// Require login for all API calls
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo, $input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function handleGet($pdo) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'current_register':
            getCurrentRegister($pdo);
            break;
        case 'movements':
            getMovements($pdo);
            break;
        case 'daily_summary':
            getDailySummary($pdo);
            break;
        default:
            getCashRegisters($pdo);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add_movement':
            addMovement($pdo, $input);
            break;
        case 'open_register':
            openRegister($pdo, $input);
            break;
        case 'close_register':
            closeRegister($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
}

function getCurrentRegister($pdo) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT * FROM cash_registers 
        WHERE user_id = ? AND status = 'open' 
        ORDER BY opened_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $register = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => $register
    ]);
}

function getMovements($pdo) {
    $register_id = intval($_GET['register_id'] ?? 0);
    
    if ($register_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do caixa inválido']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM cash_movements 
        WHERE register_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$register_id]);
    $movements = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $movements
    ]);
}

function getDailySummary($pdo) {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_registers,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_registers,
            SUM(initial_amount) as total_initial,
            SUM(CASE WHEN status = 'closed' THEN final_amount ELSE current_amount END) as total_current
        FROM cash_registers
        WHERE DATE(opened_at) = ?
    ");
    $stmt->execute([$date]);
    $summary = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => $summary
    ]);
}

function getCashRegisters($pdo) {
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT cr.*, u.name as user_name
        FROM cash_registers cr
        LEFT JOIN users u ON cr.user_id = u.id
        ORDER BY cr.opened_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $registers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $registers
    ]);
}

function addMovement($pdo, $input) {
    $register_id = intval($input['register_id'] ?? 0);
    $type = $input['type'] ?? '';
    $amount = floatval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');
    
    // Validation
    if ($register_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do caixa inválido']);
        return;
    }
    
    if (!in_array($type, ['in', 'out'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo de movimentação inválido']);
        return;
    }
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor deve ser maior que zero']);
        return;
    }
    
    try {
        // Check if register belongs to user and is open
        $stmt = $pdo->prepare("
            SELECT * FROM cash_registers 
            WHERE id = ? AND user_id = ? AND status = 'open'
        ");
        $stmt->execute([$register_id, $_SESSION['user_id']]);
        $register = $stmt->fetch();
        
        if (!$register) {
            echo json_encode(['success' => false, 'message' => 'Caixa não encontrado ou já fechado']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Add movement
        $stmt = $pdo->prepare("
            INSERT INTO cash_movements (register_id, type, amount, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$register_id, $type, $amount, $description]);
        
        // Update register current amount
        $new_amount = $register['current_amount'];
        if ($type === 'in') {
            $new_amount += $amount;
        } else {
            $new_amount -= $amount;
        }
        
        $stmt = $pdo->prepare("UPDATE cash_registers SET current_amount = ? WHERE id = ?");
        $stmt->execute([$new_amount, $register_id]);
        
        logAudit('cash_movement', 'cash_movements', null, null, [
            'register_id' => $register_id,
            'type' => $type,
            'amount' => $amount
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Movimentação registrada com sucesso',
            'new_amount' => $new_amount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao registrar movimentação']);
    }
}

function openRegister($pdo, $input) {
    $initial_amount = floatval($input['initial_amount'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if ($initial_amount < 0) {
        echo json_encode(['success' => false, 'message' => 'Valor inicial não pode ser negativo']);
        return;
    }
    
    try {
        // Check if user already has an open register
        $stmt = $pdo->prepare("
            SELECT id FROM cash_registers 
            WHERE user_id = ? AND status = 'open'
        ");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Você já possui um caixa aberto']);
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO cash_registers (user_id, initial_amount, current_amount, notes, opened_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $initial_amount, $initial_amount, $notes]);
        
        $register_id = $pdo->lastInsertId();
        
        logAudit('open_cash_register_api', 'cash_registers', $register_id, null, [
            'initial_amount' => $initial_amount
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Caixa aberto com sucesso',
            'register_id' => $register_id
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao abrir caixa']);
    }
}

function closeRegister($pdo, $input) {
    $register_id = intval($input['register_id'] ?? 0);
    $final_amount = floatval($input['final_amount'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    
    // Validation
    if ($register_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID do caixa inválido']);
        return;
    }
    
    if ($final_amount < 0) {
        echo json_encode(['success' => false, 'message' => 'Valor final não pode ser negativo']);
        return;
    }
    
    try {
        // Get register
        $stmt = $pdo->prepare("
            SELECT * FROM cash_registers 
            WHERE id = ? AND user_id = ? AND status = 'open'
        ");
        $stmt->execute([$register_id, $_SESSION['user_id']]);
        $register = $stmt->fetch();
        
        if (!$register) {
            echo json_encode(['success' => false, 'message' => 'Caixa não encontrado ou já fechado']);
            return;
        }
        
        $difference = $final_amount - $register['current_amount'];
        
        $stmt = $pdo->prepare("
            UPDATE cash_registers 
            SET final_amount = ?, difference = ?, status = 'closed', 
                notes = CONCAT(COALESCE(notes, ''), ?), closed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $final_amount,
            $difference,
            $notes ? "\n\nFechamento: " . $notes : '',
            $register_id
        ]);
        
        logAudit('close_cash_register_api', 'cash_registers', $register_id, $register, [
            'final_amount' => $final_amount,
            'difference' => $difference
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Caixa fechado com sucesso',
            'difference' => $difference
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao fechar caixa']);
    }
}
?>
