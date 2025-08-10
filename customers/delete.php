<?php
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        // Check if customer has sales
        $stmt = $pdo->prepare("SELECT COUNT(*) as sales_count FROM sales WHERE customer_id = ?");
        $stmt->execute([$id]);
        $salesCount = $stmt->fetch()['sales_count'];
        
        if ($salesCount > 0) {
            // Don't delete, just redirect with error message
            $_SESSION['error'] = 'Não é possível excluir cliente com vendas associadas.';
        } else {
            // Delete customer
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Cliente excluído com sucesso.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao excluir cliente.';
    }
}

header('Location: index.php');
exit();
?>
