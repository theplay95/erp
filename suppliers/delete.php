<?php
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        // Check if supplier has purchase orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as orders_count FROM purchase_orders WHERE supplier_id = ?");
        $stmt->execute([$id]);
        $ordersCount = $stmt->fetch()['orders_count'];
        
        if ($ordersCount > 0) {
            $_SESSION['error'] = 'Não é possível excluir fornecedor com pedidos associados.';
        } else {
            // Delete supplier
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Fornecedor excluído com sucesso.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao excluir fornecedor.';
    }
}

header('Location: index.php');
exit();
?>
