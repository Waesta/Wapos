/**
 * M-Pesa STK Push Payment Handler
 * 
 * Usage:
 * const mpesa = new MpesaPayment();
 * mpesa.initiatePayment({
 *     phone: '0712345678',
 *     amount: 1000,
 *     reference: 'INV-001',
 *     saleId: 123
 * });
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

class MpesaPayment {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || '/wapos/api/payments';
        this.pollInterval = options.pollInterval || 5000; // 5 seconds
        this.maxPolls = options.maxPolls || 24; // 2 minutes max
        this.onStatusChange = options.onStatusChange || (() => {});
        this.onSuccess = options.onSuccess || (() => {});
        this.onError = options.onError || (() => {});
        
        this.currentPoll = 0;
        this.pollTimer = null;
    }

    /**
     * Initiate STK Push payment
     */
    async initiatePayment({ phone, amount, reference = '', description = '', saleId = 0 }) {
        try {
            this.onStatusChange('initiating', 'Sending payment request...');
            
            const response = await fetch(`${this.baseUrl}/initiate-stk.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone,
                    amount,
                    reference,
                    description,
                    sale_id: saleId
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to initiate payment');
            }

            this.onStatusChange('pending', data.message);
            
            // Start polling for status
            this.startPolling(data.checkout_request_id);

            return data;

        } catch (error) {
            this.onError(error.message);
            throw error;
        }
    }

    /**
     * Start polling for payment status
     */
    startPolling(checkoutRequestId) {
        this.currentPoll = 0;
        this.stopPolling(); // Clear any existing timer
        
        const poll = async () => {
            this.currentPoll++;
            
            if (this.currentPoll > this.maxPolls) {
                this.stopPolling();
                this.onStatusChange('timeout', 'Payment verification timed out. Please check your M-Pesa messages.');
                return;
            }

            try {
                const status = await this.checkStatus(checkoutRequestId);
                
                if (status.status === 'completed') {
                    this.stopPolling();
                    this.onStatusChange('completed', status.message);
                    this.onSuccess(status);
                    return;
                }
                
                if (status.status === 'failed' || status.status === 'cancelled') {
                    this.stopPolling();
                    this.onStatusChange('failed', status.message);
                    this.onError(status.message);
                    return;
                }

                // Still pending, continue polling
                this.onStatusChange('pending', `Waiting for payment confirmation... (${this.currentPoll}/${this.maxPolls})`);
                this.pollTimer = setTimeout(poll, this.pollInterval);

            } catch (error) {
                console.error('Poll error:', error);
                // Continue polling even on error
                this.pollTimer = setTimeout(poll, this.pollInterval);
            }
        };

        // Start first poll after a short delay
        this.pollTimer = setTimeout(poll, 3000);
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
    }

    /**
     * Check payment status
     */
    async checkStatus(checkoutRequestId) {
        const response = await fetch(`${this.baseUrl}/check-stk-status.php?checkout_request_id=${encodeURIComponent(checkoutRequestId)}`);
        return await response.json();
    }

    /**
     * Format phone number for display
     */
    static formatPhone(phone) {
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length === 12 && cleaned.startsWith('254')) {
            return `+${cleaned.slice(0, 3)} ${cleaned.slice(3, 6)} ${cleaned.slice(6, 9)} ${cleaned.slice(9)}`;
        }
        return phone;
    }

    /**
     * Validate Kenyan phone number
     */
    static isValidPhone(phone) {
        const cleaned = phone.replace(/\D/g, '');
        // Accept: 07XXXXXXXX, 01XXXXXXXX, 2547XXXXXXXX, +2547XXXXXXXX
        return /^(0[17]\d{8}|254[17]\d{8}|\+254[17]\d{8})$/.test(cleaned) ||
               /^(0[17]\d{8}|254[17]\d{8})$/.test(cleaned);
    }
}

/**
 * M-Pesa Payment Modal Component
 */
class MpesaPaymentModal {
    constructor(options = {}) {
        this.modalId = options.modalId || 'mpesaPaymentModal';
        this.onComplete = options.onComplete || (() => {});
        this.mpesa = new MpesaPayment({
            onStatusChange: (status, message) => this.updateStatus(status, message),
            onSuccess: (data) => this.handleSuccess(data),
            onError: (message) => this.handleError(message),
        });
        
        this.createModal();
    }

    createModal() {
        // Check if modal already exists
        if (document.getElementById(this.modalId)) {
            return;
        }

        const modalHtml = `
            <div class="modal fade" id="${this.modalId}" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-phone me-2"></i>M-Pesa Payment
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="${this.modalId}-form">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+254</span>
                                        <input type="tel" class="form-control form-control-lg" 
                                               id="${this.modalId}-phone" 
                                               placeholder="7XX XXX XXX"
                                               maxlength="10">
                                    </div>
                                    <div class="form-text">Enter Safaricom number to receive payment prompt</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (KES)</label>
                                    <input type="text" class="form-control form-control-lg text-end" 
                                           id="${this.modalId}-amount" readonly>
                                </div>
                            </div>
                            <div id="${this.modalId}-status" class="text-center d-none">
                                <div class="spinner-border text-success mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p id="${this.modalId}-message" class="mb-0"></p>
                            </div>
                            <div id="${this.modalId}-result" class="text-center d-none">
                                <div id="${this.modalId}-result-icon" class="mb-3"></div>
                                <p id="${this.modalId}-result-message" class="mb-0"></p>
                            </div>
                        </div>
                        <div class="modal-footer" id="${this.modalId}-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" id="${this.modalId}-submit">
                                <i class="bi bi-send me-1"></i>Send Payment Request
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Bind events
        document.getElementById(`${this.modalId}-submit`).addEventListener('click', () => this.submit());
        
        // Format phone input
        document.getElementById(`${this.modalId}-phone`).addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 9);
        });
    }

    show(amount, reference = '', saleId = 0) {
        this.amount = amount;
        this.reference = reference;
        this.saleId = saleId;

        // Reset modal state
        document.getElementById(`${this.modalId}-form`).classList.remove('d-none');
        document.getElementById(`${this.modalId}-status`).classList.add('d-none');
        document.getElementById(`${this.modalId}-result`).classList.add('d-none');
        document.getElementById(`${this.modalId}-footer`).classList.remove('d-none');
        document.getElementById(`${this.modalId}-phone`).value = '';
        document.getElementById(`${this.modalId}-amount`).value = new Intl.NumberFormat('en-KE').format(amount);
        document.getElementById(`${this.modalId}-submit`).disabled = false;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById(this.modalId));
        modal.show();
    }

    async submit() {
        const phoneInput = document.getElementById(`${this.modalId}-phone`);
        let phone = phoneInput.value.replace(/\D/g, '');
        
        // Validate
        if (phone.length < 9) {
            phoneInput.classList.add('is-invalid');
            return;
        }
        phoneInput.classList.remove('is-invalid');

        // Format phone
        if (phone.length === 9) {
            phone = '0' + phone;
        }

        // Show status
        document.getElementById(`${this.modalId}-form`).classList.add('d-none');
        document.getElementById(`${this.modalId}-status`).classList.remove('d-none');
        document.getElementById(`${this.modalId}-footer`).classList.add('d-none');

        try {
            await this.mpesa.initiatePayment({
                phone,
                amount: this.amount,
                reference: this.reference,
                saleId: this.saleId
            });
        } catch (error) {
            this.handleError(error.message);
        }
    }

    updateStatus(status, message) {
        document.getElementById(`${this.modalId}-message`).textContent = message;
    }

    handleSuccess(data) {
        document.getElementById(`${this.modalId}-status`).classList.add('d-none');
        document.getElementById(`${this.modalId}-result`).classList.remove('d-none');
        document.getElementById(`${this.modalId}-result-icon`).innerHTML = 
            '<i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>';
        document.getElementById(`${this.modalId}-result-message`).innerHTML = 
            `<strong class="text-success">Payment Successful!</strong><br>${data.message || ''}`;
        
        // Auto close after 3 seconds
        setTimeout(() => {
            bootstrap.Modal.getInstance(document.getElementById(this.modalId))?.hide();
            this.onComplete(true, data);
        }, 3000);
    }

    handleError(message) {
        document.getElementById(`${this.modalId}-status`).classList.add('d-none');
        document.getElementById(`${this.modalId}-result`).classList.remove('d-none');
        document.getElementById(`${this.modalId}-result-icon`).innerHTML = 
            '<i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>';
        document.getElementById(`${this.modalId}-result-message`).innerHTML = 
            `<strong class="text-danger">Payment Failed</strong><br>${message}`;
        
        // Show footer with retry option
        document.getElementById(`${this.modalId}-footer`).classList.remove('d-none');
        document.getElementById(`${this.modalId}-footer`).innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('${this.modalId}-form').classList.remove('d-none'); document.getElementById('${this.modalId}-result').classList.add('d-none');">
                <i class="bi bi-arrow-repeat me-1"></i>Try Again
            </button>
        `;
    }
}

// Export for use
window.MpesaPayment = MpesaPayment;
window.MpesaPaymentModal = MpesaPaymentModal;
