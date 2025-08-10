<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php');
    exit();
}

$delivery_id = (int)($_POST['delivery_id'] ?? 0);
$status = $_POST['status'] ?? '';

if (!$delivery_id || !$status) {
    header('Location: index.php');
    exit();
}

if (!in_array($status, ['pendente', 'preparando', 'enviado', 'entregue', 'cancelado'])) {
    header('Location: view.php?id=' . $delivery_id . '&error=Status inválido');
    exit();
}

try {
    // Get current order data for audit
    $stmt = $pdo->prepare("SELECT * FROM delivery_orders WHERE id = ?");
    $stmt->execute([$delivery_id]);
    $oldOrder = $stmt->fetch();
    
    if (!$oldOrder) {
        header('Location: index.php?error=Entrega não encontrada');
        exit();
    }
    
    // Set delivered_at if status is changing to 'entregue'
    $delivered_at = null;
    if ($status === 'entregue' && $oldOrder['status'] !== 'entregue') {
        $delivered_at = date('Y-m-d H:i:s');
    } elseif ($status !== 'entregue') {
        $delivered_at = null; // Reset delivered_at if status changed away from 'entregue'
    }
    
    $stmt = $pdo->prepare("
        UPDATE delivery_orders SET 
            status = ?,
            delivered_at = COALESCE(?, delivered_at)
        WHERE id = ?
    ");
    
    $stmt->execute([$status, $delivered_at, $delivery_id]);
    
    // Log audit
    logAudit('UPDATE', 'delivery_orders', $delivery_id, 
        ['status' => $oldOrder['status']], 
        ['status' => $status]
    );
    
    header('Location: view.php?id=' . $delivery_id . '&success=Status atualizado com sucesso');
    
} catch (PDOException $e) {
    header('Location: view.php?id=' . $delivery_id . '&error=' . urlencode('Erro ao atualizar status: ' . $e->getMessage()));
}
exit();
?>