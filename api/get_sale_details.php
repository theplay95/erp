<?php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

try {
    // Get sale details
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, 
               u.username as seller_name
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Venda não encontrada']);
        exit();
    }

    // Get sale items
    $stmt = $pdo->prepare("
        SELECT si.*, p.name as product_name, p.category
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        WHERE si.sale_id = ?
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();

    // Generate HTML
    $html = '
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Informações da Venda</h6>
                <p><strong>ID:</strong> #' . $sale['id'] . '</p>
                <p><strong>Data:</strong> ' . formatDate($sale['created_at']) . '</p>
                <p><strong>Vendedor:</strong> ' . htmlspecialchars($sale['seller_name']) . '</p>
                <p><strong>Pagamento:</strong> ' . ucfirst(str_replace('_', ' ', $sale['payment_method'])) . '</p>
            </div>
            <div class="col-md-6">
                <h6>Cliente</h6>
                <p><strong>Nome:</strong> ' . htmlspecialchars($sale['customer_name'] ?? 'Cliente Avulso') . '</p>
                ' . ($sale['customer_phone'] ? '<p><strong>Telefone:</strong> ' . htmlspecialchars($sale['customer_phone']) . '</p>' : '') . '
            </div>
        </div>
        
        <h6>Itens da Venda</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Qtd</th>
                        <th>Preço Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($items as $item) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['product_name']) . '</td>
                        <td><span class="badge bg-secondary">' . htmlspecialchars($item['category']) . '</span></td>
                        <td>' . $item['quantity'] . '</td>
                        <td>' . formatCurrency($item['price']) . '</td>
                        <td>' . formatCurrency($item['quantity'] * $item['price']) . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="row">
            <div class="col-md-6 ms-auto">
                <table class="table">';
    
    if ($sale['discount'] > 0) {
        $html .= '
                    <tr>
                        <th>Subtotal:</th>
                        <td>' . formatCurrency($sale['subtotal']) . '</td>
                    </tr>
                    <tr>
                        <th>Desconto:</th>
                        <td class="text-danger">-' . formatCurrency($sale['discount']) . '</td>
                    </tr>';
    }
    
    $html .= '
                    <tr class="table-success">
                        <th>Total:</th>
                        <th>' . formatCurrency($sale['total']) . '</th>
                    </tr>
                </table>
            </div>
        </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar detalhes da venda']);
}
?>
