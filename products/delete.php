<?php
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        // Get product image to delete
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Delete image file
            if ($product['image'] && file_exists('../uploads/' . $product['image'])) {
                unlink('../uploads/' . $product['image']);
            }
            
            // Delete product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
        }
    } catch (PDOException $e) {
        // Handle error - could add flash message here
    }
}

header('Location: index.php');
exit();
?>
