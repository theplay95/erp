<?php
require_once '../config/database.php';
requireLogin();

$id = $_GET['id'] ?? 0;

// Get sale details
$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
           u.username as seller_name
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: index.php');
    exit();
}

// Get sale items
$stmt = $pdo->prepare("
    SELECT si.*, p.name as product_name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Check if this is for print (remove styling)
$print = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo - Venda #<?= $sale['id'] ?></title>
    <?php if (!$print): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        @media print {
            body { font-size: 12px; }
            .no-print { display: none !important; }
        }
        .receipt {
            max-width: 400px;
            margin: 0 auto;
            font-family: monospace;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .receipt-total {
            border-top: 2px solid #000;
            padding-top: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body class="<?= $print ? '' : 'bg-light p-4' ?>">
    <?php if (!$print): ?>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1 class="h3">Recibo da Venda</h1>
            <div>
                <button onclick="window.print()" class="btn btn-primary me-2">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="receipt">
        <div class="receipt-header">
            <h3>SISTEMA ERP</h3>
            <p>Recibo de Venda</p>
            <p>Data: <?= formatDate($sale['created_at']) ?></p>
            <p>Venda #<?= $sale['id'] ?></p>
        </div>
        
        <?php if ($sale['customer_name']): ?>
        <div class="mb-3">
            <strong>Cliente:</strong><br>
            <?= htmlspecialchars($sale['customer_name']) ?><br>
            <?php if ($sale['customer_phone']): ?>
                Tel: <?= htmlspecialchars($sale['customer_phone']) ?><br>
            <?php endif; ?>
            <?php if ($sale['customer_address']): ?>
                <?= htmlspecialchars($sale['customer_address']) ?><br>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <strong>Vendedor:</strong> <?= htmlspecialchars($sale['seller_name']) ?>
        </div>
        
        <div class="mb-3">
            <strong>Itens:</strong>
            <?php foreach ($items as $item): ?>
            <div class="receipt-item">
                <span><?= htmlspecialchars($item['product_name']) ?></span>
            </div>
            <div class="receipt-item">
                <span><?= $item['quantity'] ?>x <?= formatCurrency($item['price']) ?></span>
                <span><?= formatCurrency($item['quantity'] * $item['price']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="receipt-total">
            <?php if ($sale['discount'] > 0): ?>
            <div class="receipt-item">
                <span>Subtotal:</span>
                <span><?= formatCurrency($sale['subtotal']) ?></span>
            </div>
            <div class="receipt-item">
                <span>Desconto:</span>
                <span>-<?= formatCurrency($sale['discount']) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-item">
                <span>TOTAL:</span>
                <span><?= formatCurrency($sale['total']) ?></span>
            </div>
        </div>
        
        <div class="mt-3 text-center">
            <p>Pagamento: <?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?></p>
            <p>Obrigado pela preferência!</p>
        </div>
    </div>
    
    <?php if (!$print): ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html>
