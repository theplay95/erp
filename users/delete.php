<?php
require_once '../config/database.php';
requireLogin();

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: /dashboard.php');
    exit();
}

$id = $_GET['id'] ?? 0;

if ($id) {
    // Don't allow users to delete themselves
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'Você não pode excluir sua própria conta.';
    } else {
        try {
            // Check if user has associated records that would prevent deletion
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE user_id = ?");
            $stmt->execute([$id]);
            $salesCount = $stmt->fetch()['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cash_registers WHERE user_id = ?");
            $stmt->execute([$id]);
            $cashCount = $stmt->fetch()['count'];
            
            if ($salesCount > 0 || $cashCount > 0) {
                // Don't delete, just deactivate
                $stmt = $pdo->prepare("UPDATE users SET active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Usuário desativado com sucesso (não pode ser excluído devido a registros associados).';
            } else {
                // Safe to delete
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Usuário excluído com sucesso.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao excluir usuário.';
        }
    }
}

header('Location: index.php');
exit();
?>
