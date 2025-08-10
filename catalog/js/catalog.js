// Session-based cart functionality
function addToCartAjax(productId, quantity = 1) {
    console.log('Adding to cart:', productId, quantity);
    
    const formData = new FormData();
    formData.append('ajax_add_to_cart', '1');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Cart response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Cart response:', data);
        if (data.success) {
            showToast(data.message, 'success');
            updateCartCount(data.cart_count);
            
            // Update cart modal content dynamically
            updateCartModal();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erro ao adicionar produto', 'error');
    });
}

// Function to update quantity in cart
function updateSessionQuantity(productId, newQuantity) {
    if (newQuantity < 1) {
        removeSessionItem(productId);
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax_update_quantity', '1');
    formData.append('product_id', productId);
    formData.append('quantity', newQuantity);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            updateCartCount(data.cart_count);
            updateCartModal();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erro ao atualizar quantidade', 'error');
    });
}

// Function to clear cart
function clearSessionCart() {
    if (!confirm('Deseja limpar todo o carrinho?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax_clear_cart', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            updateCartCount(0);
            updateCartModal();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erro ao limpar carrinho', 'error');
    });
}

// Function to proceed to checkout
function checkout() {
    window.location.href = 'checkout.php';
}

function removeSessionItem(productId) {
    const formData = new FormData();
    formData.append('ajax_remove_item', '1');
    formData.append('product_id', productId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            updateCartCount(data.cart_count);
            updateCartModal();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erro ao remover produto', 'error');
    });
}

// Function removed - duplicate exists above

function updateCartCount(count) {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        cartCount.textContent = count;
        cartCount.style.display = count > 0 ? 'inline' : 'none';
    }
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.style.position = 'fixed';
    toast.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove element after hiding
    toast.addEventListener('hidden.bs.toast', () => {
        if (document.body.contains(toast)) {
            document.body.removeChild(toast);
        }
    });
}

// Functions moved to avoid duplication

// Filter products by category
function filterByCategory(category) {
    const url = new URL(window.location);
    if (category) {
        url.searchParams.set('category', category);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

// Clear all filters
function clearFilters() {
    const url = new URL(window.location);
    url.search = '';
    window.location.href = url.toString();
}

// Search products
function searchProducts() {
    const searchInput = document.getElementById('search-input');
    const searchTerm = searchInput ? searchInput.value.trim() : '';
    
    const url = new URL(window.location);
    if (searchTerm) {
        url.searchParams.set('search', searchTerm);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add to cart AJAX buttons
    document.querySelectorAll('.add-to-cart-ajax').forEach(button => {
        button.addEventListener('click', function() {
            const id = parseInt(this.dataset.id);
            const quantityInput = document.querySelector(`.quantity-selector[data-product-id="${id}"]`);
            const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
            
            if (quantity <= 0) {
                showToast('Quantidade deve ser maior que zero', 'error');
                return;
            }
            
            addToCartAjax(id, quantity);
        });
    });
    
    // Phone input formatting
    const phoneInput = document.querySelector('input[name="customer_phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 11) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length >= 7) {
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            } else if (value.length >= 1) {
                value = value.replace(/^(\d{0,2})/, '($1');
            }
            e.target.value = value;
        });
    }
    
    // Search functionality
    const searchButton = document.getElementById('search-button');
    const searchInput = document.getElementById('search-input');
    
    if (searchButton) {
        searchButton.addEventListener('click', searchProducts);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchProducts();
            }
        });
    }
    
    // Category filter buttons
    document.querySelectorAll('.category-filter').forEach(button => {
        button.addEventListener('click', function() {
            const category = this.dataset.category;
            filterByCategory(category);
        });
    });
    
    // Quantity selectors validation
    document.querySelectorAll('.quantity-selector').forEach(input => {
        input.addEventListener('change', function() {
            const max = parseInt(this.getAttribute('max'));
            const value = parseInt(this.value);
            
            if (value > max) {
                this.value = max;
                showToast(`Quantidade máxima disponível: ${max}`, 'error');
            } else if (value < 1) {
                this.value = 1;
            }
        });
    });
    
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Initialize tooltips if Bootstrap is loaded
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Loading state management
function setLoading(element, loading = true) {
    if (!element) return;
    
    if (loading) {
        element.disabled = true;
        element.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Carregando...';
    } else {
        element.disabled = false;
        element.innerHTML = element.dataset.originalText || 'Adicionar';
    }
}

// Utility functions
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Function removed - duplicate exists above

// Update cart modal content
function updateCartModal() {
    const formData = new FormData();
    formData.append('ajax_get_cart', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart items container
            const cartItemsContainer = document.getElementById('session-cart-items');
            if (cartItemsContainer) {
                cartItemsContainer.innerHTML = data.cart_html;
            }
            
            // Update total
            const cartTotal = document.getElementById('session-cart-total');
            if (cartTotal) {
                cartTotal.textContent = data.total_formatted;
            }
            
            // Update checkout button
            const checkoutBtn = document.getElementById('checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.disabled = data.cart_count === 0;
            }
            
            console.log('Cart modal updated successfully');
        }
    })
    .catch(error => {
        console.error('Error updating cart modal:', error);
    });
}