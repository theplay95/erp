// Checkout functionality
let currentStep = 1;
const totalSteps = 4;

// Initialize checkout
document.addEventListener('DOMContentLoaded', function() {
    // Start with step 1
    showStep(1);
    updateStepIndicator();
    updateNavigation();
    updateSummary();
    
    // Auto-fill customer data if available
    const customerName = document.querySelector('input[name="customer_name"]');
    const customerAddress = document.querySelector('textarea[name="customer_address"]');
    
    if (customerName && customerName.dataset.value) {
        customerName.value = customerName.dataset.value;
    }
    if (customerAddress && customerAddress.dataset.value) {
        customerAddress.value = customerAddress.dataset.value;
    }
    
    // Payment method change handler
    document.querySelectorAll('input[name="payment-method"]').forEach(input => {
        input.addEventListener('change', function() {
            const changeSection = document.getElementById('money-change-section');
            if (changeSection) {
                changeSection.style.display = this.value === 'dinheiro' ? 'block' : 'none';
            }
        });
    });
    
    // Delivery type change handler
    document.querySelectorAll('input[name="delivery-type"]').forEach(input => {
        input.addEventListener('change', function() {
            toggleDeliveryFields(this.value);
            updateDeliveryFeeForType(this.value);
        });
    });
});

function nextStep() {
    if (validateCurrentStep()) {
        if (currentStep < totalSteps) {
            hideStep(currentStep);
            currentStep++;
            showStep(currentStep);
            updateStepIndicator();
            updateNavigation();
        } else {
            submitOrder();
        }
    }
}

function previousStep() {
    if (currentStep > 1) {
        hideStep(currentStep);
        currentStep--;
        showStep(currentStep);
        updateStepIndicator();
        updateNavigation();
    }
}

function hideStep(step) {
    const sections = ['cart-section', 'customer-section', 'payment-section', 'confirmation-section'];
    sections.forEach(section => {
        const element = document.getElementById(section);
        if (element) element.style.display = 'none';
    });
}

function showStep(step) {
    hideStep(); // Hide all first
    
    let sectionId = '';
    switch(step) {
        case 1: sectionId = 'cart-section'; break;
        case 2: sectionId = 'customer-section'; break;
        case 3: sectionId = 'payment-section'; break;
        case 4: sectionId = 'confirmation-section'; break;
    }
    
    const stepElement = document.getElementById(sectionId);
    if (stepElement) {
        stepElement.style.display = 'block';
    }
}

function updateStepIndicator() {
    for (let i = 1; i <= totalSteps; i++) {
        const stepElement = document.querySelector(`.step:nth-child(${i})`);
        if (stepElement) {
            if (i < currentStep) {
                stepElement.classList.add('completed');
                stepElement.classList.remove('active');
            } else if (i === currentStep) {
                stepElement.classList.add('active');
                stepElement.classList.remove('completed');
            } else {
                stepElement.classList.remove('active', 'completed');
            }
        }
    }
}

function updateNavigation() {
    const nextBtn = document.getElementById('next-btn');
    const backBtn = document.getElementById('back-btn');
    
    if (nextBtn) {
        if (currentStep === totalSteps) {
            nextBtn.textContent = 'Finalizar Pedido';
            nextBtn.className = 'btn btn-success w-100';
        } else {
            nextBtn.textContent = 'Continuar';
            nextBtn.className = 'btn btn-primary w-100';
        }
    }
    
    if (backBtn) {
        backBtn.style.display = currentStep > 1 ? 'block' : 'none';
    }
}

function validateCurrentStep() {
    let isValid = true;
    let errorMessage = '';
    
    // Step 1: Cart (always valid if we got here)
    if (currentStep === 1) {
        return true;
    }
    
    // Step 2: Customer info
    if (currentStep === 2) {
        // Build complete address first
        buildCompleteAddress();
        
        const name = document.getElementById('customer-name');
        const phone = document.getElementById('customer-phone');
        const cep = document.getElementById('customer-cep');
        const number = document.getElementById('customer-number');
        const street = document.getElementById('customer-street');
        const address = document.getElementById('customer-address');
        
        if (!name || !name.value.trim()) {
            if (name) name.classList.add('is-invalid');
            isValid = false;
            errorMessage = 'Nome é obrigatório';
        } else if (name) {
            name.classList.remove('is-invalid');
        }
        
        if (!phone || !phone.value.trim()) {
            if (phone) phone.classList.add('is-invalid');
            isValid = false;
            errorMessage = 'Telefone é obrigatório';
        } else if (phone) {
            phone.classList.remove('is-invalid');
        }
        
        // Validate CEP
        if (!cep || !cep.value.trim() || cep.value.replace(/\D/g, '').length !== 8) {
            if (cep) cep.classList.add('is-invalid');
            isValid = false;
            errorMessage = 'CEP válido é obrigatório (8 dígitos)';
        } else if (cep) {
            cep.classList.remove('is-invalid');
        }
        
        // Validate number
        if (!number || !number.value.trim()) {
            if (number) number.classList.add('is-invalid');
            isValid = false;
            errorMessage = 'Número da residência é obrigatório';
        } else if (number) {
            number.classList.remove('is-invalid');
        }
        
        // Validate street (should be filled automatically by CEP search)
        if (!street || !street.value.trim()) {
            if (street) street.classList.add('is-invalid');
            isValid = false;
            errorMessage = 'Busque o endereço pelo CEP primeiro';
        } else if (street) {
            street.classList.remove('is-invalid');
        }
        
        // Validate final address
        if (!address || !address.value.trim()) {
            if (address) address.classList.add('is-invalid');
            isValid = false;
            errorMessage = 'Endereço completo é obrigatório';
        } else if (address) {
            address.classList.remove('is-invalid');
        }
    }
    
    // Step 3: Payment method
    if (currentStep === 3) {
        const paymentMethod = document.querySelector('input[name="payment-method"]:checked');
        if (!paymentMethod) {
            isValid = false;
            errorMessage = 'Selecione uma forma de pagamento';
            
            // Highlight payment section
            document.querySelectorAll('input[name="payment-method"]').forEach(input => {
                input.classList.add('is-invalid');
            });
        } else {
            document.querySelectorAll('input[name="payment-method"]').forEach(input => {
                input.classList.remove('is-invalid');
            });
        }
    }
    
    if (!isValid && errorMessage) {
        showToast(errorMessage, 'error');
    }
    
    return isValid;
}

function updateSummary() {
    // This function would normally update the summary from cart data
    // Since we're using session-based cart, the summary is already rendered by PHP
    console.log('Summary updated');
    
    // Update delivery fee based on neighborhood
    updateDeliveryFee();
}

function updateDeliveryFee() {
    const neighborhoodField = document.getElementById('customer-neighborhood');
    
    if (!neighborhoodField || !neighborhoodField.value.trim()) {
        return;
    }
    
    const neighborhood = neighborhoodField.value.trim();
    
    fetch('/catalog/api/get_delivery_fee.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            neighborhood: neighborhood
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Delivery fee response:', data);
        
        const deliveryFeeElement = document.getElementById('delivery-fee');
        const totalElement = document.getElementById('order-total');
        const subtotalElement = document.getElementById('order-subtotal');
        
        if (deliveryFeeElement && totalElement && subtotalElement) {
            const fee = parseFloat(data.fee) || 5.00;
            
            // Update delivery fee display
            deliveryFeeElement.textContent = `R$ ${fee.toFixed(2).replace('.', ',')}`;
            
            // Calculate new total
            const subtotalText = subtotalElement.textContent || 'R$ 0,00';
            const subtotal = parseFloat(subtotalText.replace('R$ ', '').replace(',', '.')) || 0;
            const newTotal = subtotal + fee;
            
            totalElement.textContent = `R$ ${newTotal.toFixed(2).replace('.', ',')}`;
            
            console.log('Updated delivery fee:', fee, 'New total:', newTotal);
            
            // Show message about delivery fee
            if (data.success) {
                showToast(data.message, 'success');
            } else if (data.suggestions && data.suggestions.length > 0) {
                showToast(`${data.message}. Bairros disponíveis: ${data.suggestions.join(', ')}`, 'warning');
            }
        } else {
            console.error('Could not find elements:', {
                deliveryFee: deliveryFeeElement,
                total: totalElement,
                subtotal: subtotalElement
            });
        }
    })
    .catch(error => {
        console.error('Error fetching delivery fee:', error);
    });
}

function toggleDeliveryFields(deliveryType) {
    const deliveryFields = document.getElementById('delivery-address-fields');
    const pickupAddress = document.getElementById('pickup-address');
    
    if (deliveryType === 'pickup') {
        // Hide delivery address fields
        if (deliveryFields) deliveryFields.style.display = 'none';
        if (pickupAddress) pickupAddress.style.display = 'block';
        
        // Remove required attributes from address fields
        const addressInputs = ['customer-cep', 'customer-number', 'customer-street', 'customer-neighborhood', 'customer-city', 'customer-state'];
        addressInputs.forEach(id => {
            const field = document.getElementById(id);
            if (field) field.removeAttribute('required');
        });
    } else {
        // Show delivery address fields
        if (deliveryFields) deliveryFields.style.display = 'block';
        if (pickupAddress) pickupAddress.style.display = 'none';
        
        // Add required attributes back to address fields
        const addressInputs = ['customer-cep', 'customer-number'];
        addressInputs.forEach(id => {
            const field = document.getElementById(id);
            if (field) field.setAttribute('required', 'required');
        });
    }
}

function updateDeliveryFeeForType(deliveryType) {
    const deliveryFeeElement = document.getElementById('delivery-fee');
    const totalElement = document.getElementById('order-total');
    const subtotalElement = document.getElementById('order-subtotal');
    
    if (!deliveryFeeElement || !totalElement || !subtotalElement) return;
    
    if (deliveryType === 'pickup') {
        // Set delivery fee to 0 for pickup
        const subtotalText = subtotalElement.textContent || 'R$ 0,00';
        const subtotal = parseFloat(subtotalText.replace('R$ ', '').replace(',', '.')) || 0;
        
        deliveryFeeElement.textContent = 'R$ 0,00';
        totalElement.textContent = `R$ ${subtotal.toFixed(2).replace('.', ',')}`;
        
        showToast('Retirada selecionada - sem taxa de entrega!', 'success');
    } else {
        // Update delivery fee based on neighborhood if available
        updateDeliveryFee();
    }
}

function submitOrder() {
    // Show loading modal
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        const modal = new bootstrap.Modal(loadingModal);
        modal.show();
    }
    
    // Disable form to prevent double submission
    const submitBtn = document.getElementById('next-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
    }
    
    // Collect form data
    const customerName = document.getElementById('customer-name')?.value || '';
    const customerPhone = document.getElementById('customer-phone')?.value || '';
    const customerAddress = document.getElementById('customer-address')?.value || '';
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked')?.value || '';
    const changeFor = document.getElementById('change-for')?.value || '';
    const observations = document.getElementById('observations')?.value || '';
    
    // Get delivery type
    const deliveryType = document.querySelector('input[name="delivery-type"]:checked')?.value || 'delivery';
    
    const formData = new FormData();
    formData.append('customer_name', customerName);
    formData.append('customer_phone', customerPhone);
    formData.append('customer_address', customerAddress);
    formData.append('payment_method', paymentMethod);
    formData.append('change_for', changeFor);
    formData.append('observations', observations);
    formData.append('delivery_type', deliveryType);
    
    // Add delivery fee from current display
    const deliveryFeeText = document.getElementById('delivery-fee')?.textContent || 'R$ 5,00';
    const deliveryFee = parseFloat(deliveryFeeText.replace('R$ ', '').replace(',', '.')) || 5.00;
    formData.append('delivery_fee', deliveryFee);
    
    fetch('submit_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Order response:', data);
        if (data.success) {
            showSuccessModal(data.order_id);
            // Session cart is already cleared on server side
            console.log('Order completed successfully');
        } else {
            showToast(data.error || data.message || 'Erro ao processar pedido', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erro ao processar pedido', 'error');
    })
    .finally(() => {
        // Hide loading modal
        if (loadingModal) {
            const modal = bootstrap.Modal.getInstance(loadingModal);
            if (modal) modal.hide();
        }
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Finalizar Pedido';
        }
    });
}

function showSuccessModal(orderId) {
    // Create success modal if it doesn't exist
    let successModal = document.getElementById('successModal');
    
    if (!successModal) {
        const modalHTML = `
            <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-check-circle me-2"></i>Pedido Realizado!
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <div class="mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h4>Sucesso!</h4>
                            <p class="mb-3">Seu pedido foi realizado com sucesso!</p>
                            <div class="alert alert-info">
                                <strong>Número do Pedido:</strong> <span id="order-number">#${orderId}</span>
                            </div>
                            <p class="text-muted">
                                <i class="bi bi-truck"></i> 
                                Estimativa de entrega: 45 minutos
                            </p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-outline-primary" onclick="printReceipt()">
                                <i class="bi bi-printer"></i> Imprimir Comprovante
                            </button>
                            <button type="button" class="btn btn-success" onclick="window.location.href='index.php'">
                                <i class="bi bi-house"></i> Voltar ao Catálogo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        successModal = document.getElementById('successModal');
    } else {
        const orderNumber = document.getElementById('order-number');
        if (orderNumber) {
            orderNumber.textContent = `#${orderId}`;
        }
    }
    
    const modal = new bootstrap.Modal(successModal);
    modal.show();
}

function printReceipt() {
    // Get order data
    const orderNumber = document.getElementById('order-number')?.textContent || '';
    const customerName = document.getElementById('customer-name')?.value || '';
    const customerAddress = document.getElementById('customer-address')?.value || '';
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked')?.parentElement?.textContent?.trim() || '';
    
    // Get items from summary
    let itemsHTML = '';
    let subtotal = 0;
    const summaryItems = document.querySelectorAll('#summary-items .d-flex');
    
    summaryItems.forEach(item => {
        const text = item.textContent.trim();
        itemsHTML += text + '<br>';
    });
    
    // Get totals from summary
    const summarySubtotal = document.getElementById('summary-subtotal')?.textContent || 'R$ 0,00';
    const summaryDelivery = document.getElementById('summary-delivery')?.textContent || 'R$ 5,00';
    const summaryTotal = document.getElementById('summary-total')?.textContent || 'R$ 0,00';
    
    const printContent = `
        <div style="max-width: 400px; margin: 0 auto; font-family: monospace; padding: 20px;">
            <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px;">
                <h2 style="margin: 0; font-size: 18px;">COMPROVANTE DE PEDIDO</h2>
                <p style="margin: 5px 0; font-weight: bold;">Pedido ${orderNumber}</p>
                <p style="margin: 5px 0; font-size: 12px;">${new Date().toLocaleString('pt-BR')}</p>
            </div>
            
            <div style="margin-bottom: 15px;">
                <strong>CLIENTE:</strong><br>
                ${customerName}<br>
                ${customerAddress}
            </div>
            
            <div style="border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 10px 0; margin: 15px 0;">
                <strong>ITENS DO PEDIDO:</strong><br><br>
                ${itemsHTML}
                <div style="border-top: 1px dashed #000; margin-top: 10px; padding-top: 5px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                        <span>Subtotal:</span>
                        <span>${summarySubtotal}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                        <span>Taxa de Entrega:</span>
                        <span>${summaryDelivery}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid #000; padding-top: 3px;">
                        <span>TOTAL:</span>
                        <span>${summaryTotal}</span>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <strong>PAGAMENTO:</strong> ${paymentMethod}<br>
                <strong>ENTREGA:</strong> 45 minutos (estimado)
            </div>
            
            <div style="text-align: center; margin-top: 20px; font-size: 12px; border-top: 1px solid #000; padding-top: 10px;">
                Obrigado pela preferência!<br>
                Acompanhe seu pedido pelo telefone informado.
            </div>
        </div>
    `;
    
    const printWindow = window.open('', '_blank', 'width=600,height=800');
    
    if (printWindow) {
        printWindow.document.open();
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
                <head>
                    <title>Comprovante - ${orderNumber}</title>
                    <meta charset="utf-8">
                    <style>
                        body { 
                            margin: 0; 
                            padding: 0;
                            font-family: 'Courier New', monospace;
                            font-size: 12px;
                            line-height: 1.4;
                        }
                        @media print {
                            body { margin: 0; }
                            @page { 
                                margin: 0.5in; 
                                size: A4;
                            }
                        }
                        @media screen {
                            body { padding: 20px; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
            </html>
        `);
        printWindow.document.close();
        
        // Wait for content to load, then print
        printWindow.onload = function() {
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                
                // Close window after printing (optional)
                printWindow.onafterprint = function() {
                    setTimeout(() => {
                        printWindow.close();
                    }, 500);
                };
            }, 250);
        };
    } else {
        // Fallback if popup was blocked
        alert('Pop-up bloqueado! Permita pop-ups para imprimir o comprovante.');
    }
}

// Toast notification function
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

// CEP search functionality
function searchCEP() {
    const cepInput = document.getElementById('customer-cep');
    const cep = cepInput.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        showToast('CEP deve ter 8 dígitos', 'error');
        return;
    }
    
    const searchBtn = document.getElementById('search-cep-btn');
    if (searchBtn) {
        searchBtn.disabled = true;
        searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    
    // Clear previous data
    document.getElementById('customer-street').value = '';
    document.getElementById('customer-neighborhood').value = '';
    document.getElementById('customer-city').value = '';
    document.getElementById('customer-state').value = '';
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (data.erro) {
                showToast('CEP não encontrado', 'error');
            } else {
                // Fill address fields
                document.getElementById('customer-street').value = data.logradouro || '';
                document.getElementById('customer-neighborhood').value = data.bairro || '';
                document.getElementById('customer-city').value = data.localidade || '';
                document.getElementById('customer-state').value = data.uf || '';
                
                // Focus on number field
                document.getElementById('customer-number').focus();
                
                showToast('Endereço encontrado!', 'success');
                buildCompleteAddress();
                
                // Update delivery fee based on neighborhood
                updateDeliveryFee();
            }
        })
        .catch(error => {
            console.error('Erro ao buscar CEP:', error);
            showToast('Erro ao buscar CEP. Tente novamente.', 'error');
        })
        .finally(() => {
            if (searchBtn) {
                searchBtn.disabled = false;
                searchBtn.innerHTML = '<i class="bi bi-search"></i>';
            }
        });
}

// Reverse CEP search - find CEP from address components
function reverseCEPSearch() {
    const state = document.getElementById('customer-state').value.trim();
    const city = document.getElementById('customer-city').value.trim();
    const street = document.getElementById('customer-street').value.trim();
    const neighborhood = document.getElementById('customer-neighborhood').value.trim();
    const number = document.getElementById('customer-number').value.trim();
    
    // Debug log
    console.log('Reverse CEP Search triggered:', { state, city, street, neighborhood, number });
    
    if (!state || !city || !street) {
        console.log('Not enough data for reverse CEP search');
        return; // Need at least state, city, and street
    }
    
    // Don't search if CEP is already filled
    const cepField = document.getElementById('customer-cep');
    if (cepField && cepField.value.trim().length >= 8) {
        console.log('CEP already filled, skipping reverse search');
        return;
    }
    
    let searchUrl = '';
    let searchType = '';
    
    // Try different search combinations, from most specific to least specific
    if (street) {
        // Start with just street name (more reliable than street+number)
        searchUrl = `https://viacep.com.br/ws/${state}/${encodeURIComponent(city)}/${encodeURIComponent(street)}/json/`;
        searchType = 'street';
        
        console.log('Searching with URL:', searchUrl);
    } else {
        console.log('No street provided, cannot search');
        return;
    }
    
    fetch(searchUrl)
        .then(response => {
            console.log('API Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API Response data:', data);
            
            if (Array.isArray(data) && data.length > 0) {
                let bestMatch = data[0];
                console.log('Found', data.length, 'results, initial best match:', bestMatch);
                
                // Smart matching algorithm considering all available information
                if (data.length > 1 && neighborhood) {
                    console.log('Multiple results found, applying neighborhood matching...');
                    
                    // Score each result based on how well it matches our input
                    const scoredResults = data.map(item => {
                        let score = 0;
                        
                        // Neighborhood match (highest priority)
                        if (neighborhood && item.bairro) {
                            const itemNeighborhood = item.bairro.toLowerCase();
                            const inputNeighborhood = neighborhood.toLowerCase();
                            
                            if (itemNeighborhood === inputNeighborhood) {
                                score += 100;
                            } else if (itemNeighborhood.includes(inputNeighborhood) || 
                                     inputNeighborhood.includes(itemNeighborhood)) {
                                score += 50;
                            }
                        }
                        
                        // Street match
                        if (street && item.logradouro) {
                            const itemStreet = item.logradouro.toLowerCase();
                            const inputStreet = street.toLowerCase();
                            
                            if (itemStreet.includes(inputStreet) || inputStreet.includes(itemStreet)) {
                                score += 30;
                            }
                        }
                        
                        return { ...item, matchScore: score };
                    });
                    
                    // Sort by score and pick the best match
                    scoredResults.sort((a, b) => b.matchScore - a.matchScore);
                    console.log('Scored results:', scoredResults);
                    
                    if (scoredResults[0].matchScore > 0) {
                        bestMatch = scoredResults[0];
                        console.log('Selected best match based on score:', bestMatch);
                    }
                }
                
                const cepField = document.getElementById('customer-cep');
                if (cepField && bestMatch.cep) {
                    console.log('Setting CEP:', bestMatch.cep);
                    cepField.value = bestMatch.cep;
                    formatCEP(cepField);
                    
                    // Also fill any missing fields from the API result
                    if (!neighborhood && bestMatch.bairro) {
                        console.log('Auto-filling neighborhood:', bestMatch.bairro);
                        document.getElementById('customer-neighborhood').value = bestMatch.bairro;
                    }
                    if (!street && bestMatch.logradouro) {
                        console.log('Auto-filling street:', bestMatch.logradouro);
                        document.getElementById('customer-street').value = bestMatch.logradouro;
                    }
                    
                    buildCompleteAddress();
                    showToast('CEP encontrado automaticamente!', 'success');
                }
            } else {
                console.log('No results found or invalid data format:', data);
                if (data && data.erro) {
                    console.log('API returned error:', data);
                }
            }
        })
        .catch(error => {
            console.error('Erro na busca reversa de CEP:', error);
            // Don't show error to user - reverse search is optional
        });
}

// Build complete address string from components
function buildCompleteAddress() {
    const street = document.getElementById('customer-street').value.trim();
    const number = document.getElementById('customer-number').value.trim();
    const complement = document.getElementById('customer-complement').value.trim();
    const neighborhood = document.getElementById('customer-neighborhood').value.trim();
    const city = document.getElementById('customer-city').value.trim();
    const state = document.getElementById('customer-state').value.trim();
    
    let address = '';
    
    if (street && number) {
        address = `${street}, ${number}`;
        if (complement) address += `, ${complement}`;
        if (neighborhood) address += ` - ${neighborhood}`;
        if (city && state) address += `, ${city} - ${state}`;
    }
    
    document.getElementById('customer-address').value = address;
}

// Format CEP input
function formatCEP(input) {
    let value = input.value.replace(/\D/g, '');
    value = value.replace(/^(\d{5})(\d{3})$/, '$1-$2');
    input.value = value;
}

// Phone formatting  
document.addEventListener('DOMContentLoaded', function() {
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
    
    // CEP input formatting and search
    const cepInput = document.getElementById('customer-cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            formatCEP(e.target);
            // Auto-search when 8 digits are entered
            const cleanCEP = e.target.value.replace(/\D/g, '');
            if (cleanCEP.length === 8) {
                setTimeout(() => searchCEP(), 300); // Small delay for better UX
            }
        });
        
        cepInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchCEP();
            }
        });
    }
    
    // CEP search button
    const searchCepBtn = document.getElementById('search-cep-btn');
    if (searchCepBtn) {
        searchCepBtn.addEventListener('click', searchCEP);
    }
    
    // Auto-build address when components change
    ['customer-number', 'customer-complement', 'customer-street', 'customer-neighborhood', 'customer-city', 'customer-state'].forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', buildCompleteAddress);
            
            // Add reverse CEP search for address fields
            if (['customer-street', 'customer-number', 'customer-neighborhood', 'customer-city', 'customer-state'].includes(fieldId)) {
                field.addEventListener('blur', () => {
                    console.log(`Field ${fieldId} lost focus, scheduling reverse CEP search...`);
                    setTimeout(reverseCEPSearch, 1200); // Increased delay to allow all fields to be filled
                    
                    // Update delivery fee when neighborhood changes
                    if (fieldId === 'customer-neighborhood') {
                        setTimeout(updateDeliveryFee, 500);
                    }
                });
            }
        }
    });
    
    // State field auto-uppercase
    const stateField = document.getElementById('customer-state');
    if (stateField) {
        stateField.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    }
    
    // Build initial address if fields are already populated
    setTimeout(() => {
        const street = document.getElementById('customer-street');
        const number = document.getElementById('customer-number');
        if (street && street.value.trim() && number && number.value.trim()) {
            buildCompleteAddress();
        }
    }, 100);
});