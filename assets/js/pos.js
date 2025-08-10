// POS System JavaScript
class POSSystem {
    constructor() {
        this.cart = [];
        this.init();
    }
    
    init() {
        this.loadProducts();
        this.bindEvents();
        this.updateCartDisplay();
    }
    
    bindEvents() {
        // Product search
        document.getElementById('productSearch').addEventListener('input', (e) => {
            this.loadProducts(e.target.value);
        });
        
        // Category filter
        document.querySelectorAll('[data-category]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('[data-category]').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.loadProducts('', e.target.dataset.category);
            });
        });
        
        // Finalize sale
        document.getElementById('finalizeSale').addEventListener('click', () => {
            this.finalizeSale();
        });
        
        // Clear cart
        document.getElementById('clearCart').addEventListener('click', () => {
            this.clearCart();
        });
    }
    
    loadProducts(search = '', category = '') {
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (category) params.append('category', category);
        
        fetch(`/api/get_product.php?${params}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('productsGrid').innerHTML = html;
                this.bindProductEvents();
            });
    }
    
    bindProductEvents() {
        document.querySelectorAll('.add-to-cart').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const productData = {
                    id: parseInt(e.target.dataset.id),
                    name: e.target.dataset.name,
                    price: parseFloat(e.target.dataset.price)
                };
                this.addToCart(productData);
            });
        });
    }
    
    addToCart(product) {
        const existingItem = this.cart.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            this.cart.push({
                ...product,
                quantity: 1
            });
        }
        
        this.updateCartDisplay();
        showToast(`${product.name} adicionado ao carrinho!`);
    }
    
    removeFromCart(productId) {
        this.cart = this.cart.filter(item => item.id !== productId);
        this.updateCartDisplay();
    }
    
    updateQuantity(productId, newQuantity) {
        if (newQuantity <= 0) {
            this.removeFromCart(productId);
            return;
        }
        
        const item = this.cart.find(item => item.id === productId);
        if (item) {
            item.quantity = newQuantity;
            this.updateCartDisplay();
        }
    }
    
    updateCartDisplay() {
        const cartItems = document.getElementById('cartItems');
        const subtotalElement = document.getElementById('subtotal');
        const totalElement = document.getElementById('total');
        const finalizeSaleBtn = document.getElementById('finalizeSale');
        
        if (this.cart.length === 0) {
            cartItems.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="bi bi-cart fs-1"></i>
                    <p>Carrinho vazio</p>
                </div>
            `;
            finalizeSaleBtn.disabled = true;
        } else {
            let html = '';
            let subtotal = 0;
            
            this.cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div class="flex-grow-1">
                            <strong>${item.name}</strong><br>
                            <small class="text-muted">${formatCurrency(item.price)} x ${item.quantity}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="pos.updateQuantity(${item.id}, ${item.quantity - 1})">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <span class="mx-2">${item.quantity}</span>
                                <button class="quantity-btn" onclick="pos.updateQuantity(${item.id}, ${item.quantity + 1})">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="pos.removeFromCart(${item.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            cartItems.innerHTML = html;
            subtotalElement.textContent = formatCurrency(subtotal);
            totalElement.textContent = formatCurrency(subtotal);
            finalizeSaleBtn.disabled = false;
        }
    }
    
    clearCart() {
        this.cart = [];
        this.updateCartDisplay();
        showToast('Carrinho limpo!', 'info');
    }
    
    finalizeSale() {
        const customerId = document.getElementById('customerId').value || null;
        const paymentMethod = document.getElementById('paymentMethod').value;
        
        if (this.cart.length === 0) {
            showToast('Carrinho vazio!', 'error');
            return;
        }
        
        const saleData = {
            items: this.cart,
            customer_id: customerId,
            payment_method: paymentMethod,
            discount: 0
        };
        
        fetch('/api/process_sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(saleData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('saleId').textContent = data.sale_id;
                document.getElementById('saleTotal').textContent = formatCurrency(data.total);
                
                const modal = new bootstrap.Modal(document.getElementById('saleSuccessModal'));
                modal.show();
                
                this.clearCart();
                
                // Print receipt button
                document.getElementById('printReceipt').onclick = () => {
                    window.open(`/sales/receipt.php?id=${data.sale_id}`, '_blank');
                };
                
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Erro ao processar venda!', 'danger');
            console.error('Error:', error);
        });
    }
}

// Initialize POS system
let pos;
document.addEventListener('DOMContentLoaded', function() {
    pos = new POSSystem();
});
