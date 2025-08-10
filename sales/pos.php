<?php
$pageTitle = 'PDV - Ponto de Venda';
require_once '../config/database.php';
requireLogin();

// Get categories for filter - using categories table
$stmt = $pdo->query("SELECT DISTINCT c.id, c.name as category FROM categories c 
                     INNER JOIN products p ON c.id = p.category_id 
                     WHERE p.stock_quantity > 0 ORDER BY c.name");
$categories = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <!-- Products Section -->
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Produtos</h4>
            <div class="input-group" style="max-width: 300px;">
                <input type="text" class="form-control" id="productSearch" placeholder="Buscar produtos...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
        
        <!-- Category Filter -->
        <div class="mb-3">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" data-category="">Todos</button>
                <?php foreach ($categories as $category): ?>
                    <button type="button" class="btn btn-outline-primary" data-category="<?= htmlspecialchars($category['id']) ?>">
                        <?= htmlspecialchars($category['category']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div id="productsGrid" class="row">
            <!-- Products will be loaded here via AJAX -->
        </div>
    </div>
    
    <!-- Cart Section -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Carrinho de Compras</h5>
            </div>
            <div class="card-body">
                <div id="cartItems">
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-cart fs-1"></i>
                        <p>Carrinho vazio</p>
                    </div>
                </div>
                
                <hr>
                
                <!-- Customer Selection -->
                <div class="mb-3">
                    <label class="form-label">Cliente (Opcional)</label>
                    <select class="form-select" id="customerId">
                        <option value="">Cliente Avulso</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, name FROM customers ORDER BY name");
                        while ($customer = $stmt->fetch()):
                        ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Payment Method -->
                <div class="mb-3">
                    <label class="form-label">Método de Pagamento</label>
                    <select class="form-select" id="paymentMethod" required>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="cartao_credito">Cartão de Crédito</option>
                        <option value="cartao_debito">Cartão de Débito</option>
                        <option value="pix">PIX</option>
                    </select>
                </div>
                
                <!-- Totals -->
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal">R$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Desconto:</span>
                        <span id="discount">R$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between fs-5 fw-bold">
                        <span>Total:</span>
                        <span id="total">R$ 0,00</span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-success btn-lg" id="finalizeSale" disabled>
                        <i class="bi bi-check-circle"></i> Finalizar Venda
                    </button>
                    <button class="btn btn-outline-secondary" id="clearCart">
                        <i class="bi bi-trash"></i> Limpar Carrinho
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sale Success Modal -->
<div class="modal fade" id="saleSuccessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Venda Realizada com Sucesso!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-check-circle-fill text-success fs-1"></i>
                <h4 class="mt-3">Venda #<span id="saleId"></span></h4>
                <p class="text-muted">Total: <span id="saleTotal"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="printReceipt">
                    <i class="bi bi-printer"></i> Imprimir Recibo
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/pos.js"></script>

<?php include '../includes/footer.php'; ?>
