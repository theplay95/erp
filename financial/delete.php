<?php
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        // Check if transaction is manual (can be deleted)
        $stmt = $pdo->prepare("SELECT * FROM financial_transactions WHERE id = ? AND reference_type = 'manual'");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Delete the transaction
            $stmt = $pdo->prepare("DELETE FROM financial_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Transação excluída com sucesso.';
        } else {
            $_SESSION['error'] = 'Transação não encontrada ou não pode ser excluída.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao excluir transação.';
    }
}

header('Location: index.php');
exit();
?>
