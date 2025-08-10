<?php
$pageTitle = 'Finalizar Pedido - Mundo da Carne';
require_once '../config/database.php';

// Get catalog settings for colors
$catalog_settings = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM catalog_settings LIMIT 1");
    $stmt->execute();
    $catalog_settings = $stmt->fetch();
} catch (Exception $e) {
    $catalog_settings = [
        'primary_color' => '#dc3545',
        'secondary_color' => '#6c757d'
    ];
}

// Check if user is identified and has items in cart
if (!isset($_SESSION['customer_phone']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit();
}

// Get customer data if exists with full address breakdown
$phone = $_SESSION['customer_phone'];
$existing_customer = null;
$latest_address = null;
$address_parts = [
    'street' => '',
    'number' => '',
    'neighborhood' => '',
    'city' => '',
    'state' => '',
    'complement' => '',
    'cep' => ''
];

// Try to find existing customer
$stmt = $pdo->prepare("SELECT * FROM customers WHERE phone LIKE ?");
$stmt->execute(["%$phone%"]);
$existing_customer = $stmt->fetch();

// Check delivery orders for latest address first
$stmt = $pdo->prepare("SELECT delivery_address FROM delivery_orders WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$phone]);
$delivery_data = $stmt->fetch(PDO::FETCH_ASSOC);

// If customer exists or has delivery orders, use their data for autofill
if ($existing_customer || $delivery_data) {
    $latest_address = [
        'customer_name' => $existing_customer['name'] ?? '',
        'customer_address' => $delivery_data['delivery_address'] ?? ($existing_customer['address'] ?? '')
    ];
    
    // Parse the address - try different formats
    $address_to_parse = $delivery_data['delivery_address'] ?? ($existing_customer['address'] ?? '');
    if (!empty($address_to_parse)) {
        // Pattern 1: "Avenida Mamoré, 3180 - Tancredo Neves, Porto Velho - RO"
        if (preg_match('/^([^,]+),\s*([^-]+)\s*-\s*([^,]+),\s*([^-]+)\s*-\s*([A-Z]{2})/', $address_to_parse, $matches)) {
            $address_parts['street'] = trim($matches[1] ?? '');
            $address_parts['number'] = trim($matches[2] ?? '');
            $address_parts['neighborhood'] = trim($matches[3] ?? '');
            $address_parts['city'] = trim($matches[4] ?? '');
            $address_parts['state'] = trim($matches[5] ?? '');
        }
        // Pattern 2: "av mamore, 3180" (simpler format from delivery_orders)
        elseif (preg_match('/^([^,]+),\s*(\d+)/', $address_to_parse, $matches)) {
            $address_parts['street'] = trim($matches[1] ?? '');
            $address_parts['number'] = trim($matches[2] ?? '');
        }
    }
    
    // Prefer customer table data for complete address if available
    if ($existing_customer && !empty($existing_customer['address'])) {
        $customer_address = $existing_customer['address'];
        if (preg_match('/^([^,]+),\s*([^-]+)\s*-\s*([^,]+),\s*([^-]+)\s*-\s*([A-Z]{2})/', $customer_address, $matches)) {
            $address_parts['street'] = $address_parts['street'] ?: trim($matches[1] ?? '');
            $address_parts['number'] = $address_parts['number'] ?: trim($matches[2] ?? '');
            $address_parts['neighborhood'] = trim($matches[3] ?? '');
            $address_parts['city'] = trim($matches[4] ?? '');
            $address_parts['state'] = trim($matches[5] ?? '');
        }
    }
    
    // Use CEP from customer record if available
    if ($existing_customer && !empty($existing_customer['cep'])) {
        $address_parts['cep'] = $existing_customer['cep'];
    }
}

// Calculate totals
$subtotal = array_sum(array_map(function($item) {
    return $item['price'] * $item['quantity'];
}, $_SESSION['cart']));

$delivery_fee = 5.00; // Default fallback, will be updated dynamically
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $catalog_settings['primary_color'] ?? '#dc3545' ?>;
            --secondary-color: <?= $catalog_settings['secondary_color'] ?? '#6c757d' ?>;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            position: relative;
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            right: -30px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.completed::after {
            background: #28a745;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand text-danger fw-bold" href="index.php">
                <i class="bi bi-arrow-left"></i> Mundo da Carne
            </a>
            <span class="text-muted">Finalizar Pedido</span>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">1</div>
            <div class="step" id="step2">2</div>
            <div class="step" id="step3">3</div>
            <div class="step" id="step4">4</div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Step 1: Cart Review -->
                <div class="card mb-4" id="cart-section">
                    <div class="card-header">
                        <h5><i class="bi bi-cart3"></i> Revisão do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                <small class="text-muted">R$ <?= number_format($item['price'], 2, ',', '.') ?> × <?= $item['quantity'] ?></small>
                            </div>
                            <div class="text-end">
                                <strong>R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Customer Info -->
                <div class="card mb-4" id="customer-section" style="display: none;">
                    <div class="card-header">
                        <h5><i class="bi bi-person"></i> Dados do Cliente</h5>
                    </div>
                    <div class="card-body">
                        <!-- Delivery Type Selection -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-bold mb-3">Tipo de Pedido</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery-type" id="delivery-option" value="delivery" checked>
                                            <label class="form-check-label" for="delivery-option">
                                                <i class="bi bi-truck"></i> <strong>Entrega</strong>
                                                <div class="small text-muted">Receber em casa com taxa de entrega</div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery-type" id="pickup-option" value="pickup">
                                            <label class="form-check-label" for="pickup-option">
                                                <i class="bi bi-shop"></i> <strong>Retirada</strong>
                                                <div class="small text-muted">Buscar na loja - sem taxa de entrega</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                            </div>
                        </div>
                        
                        <!-- Store Address (shown for pickup) -->
                        <div id="pickup-address" class="alert alert-info" style="display: none;">
                            <h6><i class="bi bi-geo-alt"></i> Endereço da Loja:</h6>
                            <p class="mb-0">
                                <strong>Mundo da Carne</strong><br>
                                Av Mamoré, 3180 - Centro, Porto Velho - RO<br>
                                Horário: Seg-Sex: 8h-18h | Sáb: 8h-16h
                            </p>
                        </div>
                        
                        <form id="customer-form">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                Telefone identificado: <strong><?= htmlspecialchars($phone) ?></strong>
                                <?php if ($latest_address): ?>
                                <br><small>Dados do último pedido carregados automaticamente</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nome Completo *</label>
                                        <input type="text" class="form-control" id="customer-name" name="customer_name" required 
                                               value="<?= htmlspecialchars($latest_address['customer_name'] ?? $existing_customer['name'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Telefone *</label>
                                        <input type="tel" class="form-control" id="customer-phone" name="customer_phone" required 
                                               value="<?= htmlspecialchars($phone) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Address Fields (only for delivery) -->
                            <div id="delivery-address-fields">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">CEP *</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customer-cep" name="customer_cep" 
                                                   required placeholder="00000-000" maxlength="9" pattern="[0-9]{5}-?[0-9]{3}"
                                                   value="<?= htmlspecialchars($address_parts['cep'] ?? '') ?>">
                                            <button type="button" class="btn btn-outline-secondary" id="search-cep-btn">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Digite o CEP ou preencha rua/número/bairro/cidade para buscar automaticamente</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Número *</label>
                                        <input type="text" class="form-control" id="customer-number" name="customer_number" 
                                               required placeholder="123" maxlength="10"
                                               value="<?= htmlspecialchars($address_parts['number'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Complemento</label>
                                        <input type="text" class="form-control" id="customer-complement" name="customer_complement" 
                                               placeholder="Apto, bloco..." 
                                               value="<?= htmlspecialchars($address_parts['complement'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Rua</label>
                                        <input type="text" class="form-control" id="customer-street" name="customer_street" 
                                               placeholder="Digite ou será preenchido pelo CEP"
                                               value="<?= htmlspecialchars($address_parts['street'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Bairro</label>
                                        <input type="text" class="form-control" id="customer-neighborhood" name="customer_neighborhood" 
                                               placeholder="Digite ou será preenchido pelo CEP"
                                               value="<?= htmlspecialchars($address_parts['neighborhood'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" class="form-control" id="customer-city" name="customer_city" 
                                               placeholder="Digite ou será preenchido pelo CEP"
                                               value="<?= htmlspecialchars($address_parts['city'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Estado</label>
                                        <input type="text" class="form-control" id="customer-state" name="customer_state" 
                                               placeholder="UF" maxlength="2" style="text-transform: uppercase;"
                                               value="<?= htmlspecialchars($address_parts['state'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" id="customer-address" name="customer_address">
                            </div>
                            <!-- End of delivery address fields -->
                        </form>
                    </div>
                </div>

                <!-- Step 3: Payment -->
                <div class="card mb-4" id="payment-section" style="display: none;">
                    <div class="card-header">
                        <h5><i class="bi bi-credit-card"></i> Forma de Pagamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment-method" id="payment-money" value="dinheiro">
                                    <label class="form-check-label" for="payment-money">
                                        <i class="bi bi-cash-stack"></i> Dinheiro
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment-method" id="payment-pix" value="pix">
                                    <label class="form-check-label" for="payment-pix">
                                        <i class="bi bi-qr-code"></i> PIX
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment-method" id="payment-credit" value="cartao_credito">
                                    <label class="form-check-label" for="payment-credit">
                                        <i class="bi bi-credit-card"></i> Cartão de Crédito
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment-method" id="payment-debit" value="cartao_debito">
                                    <label class="form-check-label" for="payment-debit">
                                        <i class="bi bi-credit-card-2-front"></i> Cartão de Débito
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="money-change-section" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Troco para quanto? (opcional)</label>
                                <input type="number" class="form-control" id="change-for" name="change_for" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea class="form-control" id="observations" name="observations" rows="2" placeholder="Alguma observação sobre o pedido..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Confirmation -->
                <div class="card mb-4" id="confirmation-section" style="display: none;">
                    <div class="card-header">
                        <h5><i class="bi bi-check-circle"></i> Confirmação do Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div id="order-summary"></div>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-lg-4">
                <div class="card sticky-top">
                    <div class="card-header">
                        <h6><i class="bi bi-receipt"></i> Resumo do Pedido</h6>
                    </div>
                    <div class="card-body">
                        <div id="summary-items">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= $item['quantity'] ?>× <?= htmlspecialchars($item['name']) ?></span>
                                <span>R$ <?= number_format($item['price'] * $item['quantity'], 2, ',', '.') ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span id="order-subtotal">R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Taxa de Entrega:</span>
                            <span id="delivery-fee">R$ <?= number_format($delivery_fee, 2, ',', '.') ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total:</span>
                            <span id="order-total">R$ <?= number_format($subtotal + $delivery_fee, 2, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary w-100" id="next-btn" onclick="nextStep()">
                            Continuar
                        </button>
                        <button class="btn btn-outline-secondary w-100 mt-2" id="back-btn" onclick="previousStep()" style="display: none;">
                            Voltar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Processando seu pedido...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <i class="bi bi-check-circle-fill text-success display-3"></i>
                    <h4 class="mt-3">Pedido Realizado com Sucesso!</h4>
                    <p class="text-muted mb-4">
                        Seu pedido foi recebido e será preparado em breve.<br>
                        Número do pedido: <strong id="order-number"></strong>
                    </p>
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-primary">Fazer Novo Pedido</a>
                        <button type="button" class="btn btn-outline-primary" onclick="printReceipt()">
                            <i class="bi bi-printer"></i> Imprimir Cupom
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/checkout.js"></script>
</body>
</html>