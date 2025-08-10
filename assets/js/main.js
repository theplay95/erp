// Main JavaScript file for ERP System

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        });
    }
    
    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Format currency inputs
    const currencyInputs = document.querySelectorAll('.currency-input');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            e.target.value = value;
        });
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este item?')) {
                e.preventDefault();
            }
        });
    });
});

// Utility functions
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('pt-BR').format(new Date(date));
}

function showAlert(message, type = 'info') {
    const alertContainer = document.querySelector('.content-wrapper');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.insertBefore(alert, alertContainer.firstChild);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 300);
    }, 5000);
}

// AJAX helper function
function ajaxRequest(url, data = {}, method = 'POST') {
    return fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: method === 'GET' ? null : JSON.stringify(data)
    })
    .then(response => response.json())
    .catch(error => {
        console.error('AJAX Error:', error);
        throw error;
    });
}

// POS System Functions
class POSSystem {
    constructor() {
        this.cart = [];
        this.total = 0;
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        // Product selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.product-card')) {
                const productCard = e.target.closest('.product-card');
                const productId = productCard.dataset.productId;
                const productName = productCard.querySelector('.product-name').textContent;
                const productPrice = parseFloat(productCard.dataset.price);
                
                this.addToCart(productId, productName, productPrice);
            }
        });
        
        // Quantity change
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('cart-quantity')) {
                const productId = e.target.dataset.productId;
                const newQuantity = parseInt(e.target.value);
                this.updateQuantity(productId, newQuantity);
            }
        });
        
        // Remove item
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item')) {
                const productId = e.target.dataset.productId;
                this.removeFromCart(productId);
            }
        });
    }
    
    addToCart(productId, productName, productPrice) {
        const existingItem = this.cart.find(item => item.id === productId);
        
        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            this.cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: 1
            });
        }
        
        this.updateCartDisplay();
    }
    
    updateQuantity(productId, quantity) {
        const item = this.cart.find(item => item.id === productId);
        if (item) {
            if (quantity <= 0) {
                this.removeFromCart(productId);
            } else {
                item.quantity = quantity;
                this.updateCartDisplay();
            }
        }
    }
    
    removeFromCart(productId) {
        this.cart = this.cart.filter(item => item.id !== productId);
        this.updateCartDisplay();
    }
    
    updateCartDisplay() {
        const cartItems = document.querySelector('.cart-items');
        const cartTotal = document.querySelector('.cart-total .total-amount');
        
        if (!cartItems) return;
        
        cartItems.innerHTML = '';
        this.total = 0;
        
        this.cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            this.total += itemTotal;
            
            const cartItem = document.createElement('div');
            cartItem.className = 'cart-item';
            cartItem.innerHTML = `
                <div>
                    <div class="fw-bold">${item.name}</div>
                    <small class="text-muted">${formatCurrency(item.price)} cada</small>
                </div>
                <div class="text-end">
                    <input type="number" class="form-control form-control-sm cart-quantity" 
                           value="${item.quantity}" min="1" style="width: 60px; display: inline-block;"
                           data-product-id="${item.id}">
                    <button class="btn btn-sm btn-danger remove-item ms-1" data-product-id="${item.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                    <div class="fw-bold">${formatCurrency(itemTotal)}</div>
                </div>
            `;
            cartItems.appendChild(cartItem);
        });
        
        if (cartTotal) {
            cartTotal.textContent = formatCurrency(this.total);
        }
    }
    
    clearCart() {
        this.cart = [];
        this.total = 0;
        this.updateCartDisplay();
    }
    
    processPayment() {
        if (this.cart.length === 0) {
            showAlert('Carrinho está vazio!', 'warning');
            return;
        }
        
        // Process the sale via AJAX
        ajaxRequest('../api/sales.php', {
            action: 'create_sale',
            items: this.cart,
            total: this.total
        })
        .then(response => {
            if (response.success) {
                showAlert('Venda realizada com sucesso!', 'success');
                this.clearCart();
                if (response.sale_id) {
                    window.open(`receipt.php?id=${response.sale_id}`, '_blank');
                }
            } else {
                showAlert(response.message || 'Erro ao processar venda', 'danger');
            }
        })
        .catch(error => {
            showAlert('Erro de conexão', 'danger');
        });
    }
}

// Initialize POS system if on POS page
if (document.querySelector('.pos-container')) {
    window.posSystem = new POSSystem();
}

// Data tables initialization
function initializeDataTable(tableId, options = {}) {
    const defaultOptions = {
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
        },
        responsive: true
    };
    
    return $(tableId).DataTable({ ...defaultOptions, ...options });
}

// Chart.js default configuration
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.color = '#495057';

function createChart(ctx, type, data, options = {}) {
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        }
    };
    
    return new Chart(ctx, {
        type: type,
        data: data,
        options: { ...defaultOptions, ...options }
    });
}
