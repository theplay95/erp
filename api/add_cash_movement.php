<?php
require_once '../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cash-register/index.php');
    exit();
}

$register_id = $_POST['register_id'] ?? 0;
$type = $_POST['type'] ?? '';
$amount = $_POST['amount'] ?? 0;
$description = trim($_POST['description'] ?? '');

$error = '';

// Validate input
if (!$register_id || !$type || !$amount || !$description) {
    $error = 'Todos os campos são obrigatórios.';
} elseif (!in_array($type, ['in', 'out'])) {
    $error = 'Tipo de movimentação inválido.';
} elseif ($amount <= 0) {
    $error = 'Valor deve ser maior que zero.';
} else {
    try {
        $pdo->beginTransaction();
        
        // Verify register belongs to user and is open
        $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE id = ? AND user_id = ? AND status = 'open'");
        $stmt->execute([$register_id, $_SESSION['user_id']]);
        $register = $stmt->fetch();
        
        if (!$register) {
            $error = 'Caixa não encontrado ou não pertence ao usuário.';
        } else {
            // Add movement
            $stmt = $pdo->prepare("INSERT INTO cash_movements (register_id, type, amount, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$register_id, $type, $amount, $description]);
            
            // Update register current amount
            $new_amount = $type == 'in' 
                ? $register['current_amount'] + $amount 
                : $register['current_amount'] - $amount;
                
            $stmt = $pdo->prepare("UPDATE cash_registers SET current_amount = ? WHERE id = ?");
            $stmt->execute([$new_amount, $register_id]);
            
            // Add financial transaction
            $transaction_type = $type == 'in' ? 'income' : 'expense';
            $stmt = $pdo->prepare("INSERT INTO financial_transactions (type, category, amount, description, reference_type, user_id) VALUES (?, 'caixa', ?, ?, 'manual', ?)");
            $stmt->execute([$transaction_type, $amount, $description, $_SESSION['user_id']]);
            
            $pdo->commit();
            $_SESSION['success'] = 'Movimentação registrada com sucesso!';
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Erro ao registrar movimentação: ' . $e->getMessage();
    }
}

if ($error) {
    $_SESSION['error'] = $error;
}

header('Location: /cash-register/index.php');
exit();
?>
