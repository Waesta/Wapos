/**
 * WAPOS Smart Helpers
 * Intelligent assistance, guided workflows, and contextual help
 */

const WAPOS_Smart = (function() {
    'use strict';

    // ============================================
    // CONTEXTUAL HELP SYSTEM
    // ============================================
    const Help = {
        tips: {
            // POS Tips
            'pos-search': {
                title: 'Quick Product Search',
                content: 'Type product name, SKU, or scan barcode. Press Enter to add first match.',
                shortcut: '/'
            },
            'pos-payment': {
                title: 'Payment Options',
                content: 'Split payments across multiple methods. Click "Split" to divide the total.',
            },
            'pos-hold': {
                title: 'Hold Order',
                content: 'Save current cart to serve another customer. Retrieve later from "Held Orders".',
                shortcut: 'Ctrl+H'
            },
            'pos-discount': {
                title: 'Apply Discount',
                content: 'Enter percentage or fixed amount. Manager approval may be required for large discounts.',
            },
            
            // Restaurant Tips
            'table-status': {
                title: 'Table Colors',
                content: '🟢 Green = Available | 🔴 Red = Occupied | 🟡 Yellow = Reserved',
            },
            'order-status': {
                title: 'Order Flow',
                content: 'New → In Kitchen → Ready → Served → Paid',
            },
            'split-bill': {
                title: 'Split Bill',
                content: 'Divide bill equally or by items. Select items to move to a new bill.',
            },

            // Inventory Tips
            'low-stock': {
                title: 'Low Stock Alert',
                content: 'Items below reorder point are highlighted. Click to create purchase order.',
            },
            'stock-adjustment': {
                title: 'Stock Adjustment',
                content: 'Always select a reason for adjustments. This maintains accurate audit trails.',
            },

            // Settings Tips
            'backup-schedule': {
                title: 'Automatic Backups',
                content: 'Backups run automatically based on your schedule. No cron jobs needed!',
            },
        },

        init() {
            this.createHelpButton();
            this.attachTooltips();
        },

        createHelpButton() {
            // Floating help button
            const helpBtn = document.createElement('button');
            helpBtn.id = 'wapos-help-btn';
            helpBtn.className = 'btn btn-primary rounded-circle shadow';
            helpBtn.setAttribute('aria-label', 'Help');
            helpBtn.style.cssText = `
                position: fixed;
                bottom: 80px;
                right: 20px;
                width: 50px;
                height: 50px;
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
            `;
            helpBtn.innerHTML = '<i class="bi bi-question-lg"></i>';
            helpBtn.addEventListener('click', () => this.showContextualHelp());
            
            document.body.appendChild(helpBtn);
        },

        attachTooltips() {
            // Find elements with data-help attribute
            document.querySelectorAll('[data-help]').forEach(el => {
                const tipKey = el.dataset.help;
                const tip = this.tips[tipKey];
                
                if (tip) {
                    el.setAttribute('title', tip.content);
                    el.style.cursor = 'help';
                    
                    // Add help icon
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-question-circle ms-1 text-muted';
                    icon.style.fontSize = '0.875rem';
                    el.appendChild(icon);
                }
            });
        },

        showContextualHelp() {
            const page = this.detectCurrentPage();
            const tips = this.getTipsForPage(page);

            const modal = document.createElement('div');
            modal.className = 'wapos-help-modal';
            modal.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;

            modal.innerHTML = `
                <div style="
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h5 style="margin: 0;"><i class="bi bi-lightbulb me-2 text-warning"></i>Quick Tips: ${page}</h5>
                        <button class="btn-close" aria-label="Close"></button>
                    </div>
                    <div class="help-tips-list">
                        ${tips.map(tip => `
                            <div style="
                                padding: 12px;
                                background: #f8f9fa;
                                border-radius: 8px;
                                margin-bottom: 12px;
                            ">
                                <strong>${tip.title}</strong>
                                ${tip.shortcut ? `<kbd style="float: right; background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem;">${tip.shortcut}</kbd>` : ''}
                                <p style="margin: 8px 0 0; color: #6c757d; font-size: 0.9rem;">${tip.content}</p>
                            </div>
                        `).join('')}
                    </div>
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #dee2e6;">
                        <a href="user-manual.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-book me-1"></i>Full User Manual
                        </a>
                        <button class="btn btn-link btn-sm text-muted" onclick="WAPOS_UX.Shortcuts.showHelp()">
                            <i class="bi bi-keyboard me-1"></i>Keyboard Shortcuts
                        </button>
                    </div>
                </div>
            `;

            modal.querySelector('.btn-close').addEventListener('click', () => modal.remove());
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.remove();
            });

            document.body.appendChild(modal);
        },

        detectCurrentPage() {
            const path = window.location.pathname;
            if (path.includes('pos.php')) return 'Point of Sale';
            if (path.includes('restaurant')) return 'Restaurant';
            if (path.includes('bar-pos')) return 'Bar POS';
            if (path.includes('products') || path.includes('inventory')) return 'Inventory';
            if (path.includes('settings')) return 'Settings';
            if (path.includes('customers')) return 'Customers';
            if (path.includes('reports') || path.includes('sales')) return 'Reports';
            if (path.includes('rooms') || path.includes('booking')) return 'Property';
            return 'General';
        },

        getTipsForPage(page) {
            const pageTips = {
                'Point of Sale': ['pos-search', 'pos-payment', 'pos-hold', 'pos-discount'],
                'Restaurant': ['table-status', 'order-status', 'split-bill'],
                'Bar POS': ['pos-search', 'pos-payment'],
                'Inventory': ['low-stock', 'stock-adjustment'],
                'Settings': ['backup-schedule'],
                'General': ['pos-search'],
            };

            const tipKeys = pageTips[page] || pageTips['General'];
            return tipKeys.map(key => this.tips[key]).filter(Boolean);
        }
    };

    // ============================================
    // SMART DEFAULTS SYSTEM
    // ============================================
    const SmartDefaults = {
        apply() {
            this.setDateDefaults();
            this.setQuantityDefaults();
            this.setSearchFocus();
            this.rememberLastUsed();
        },

        setDateDefaults() {
            // Set date inputs to today by default
            document.querySelectorAll('input[type="date"]:not([value])').forEach(input => {
                if (!input.value && !input.dataset.noDefault) {
                    input.value = new Date().toISOString().split('T')[0];
                }
            });

            // Set datetime-local to now
            document.querySelectorAll('input[type="datetime-local"]:not([value])').forEach(input => {
                if (!input.value && !input.dataset.noDefault) {
                    const now = new Date();
                    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                    input.value = now.toISOString().slice(0, 16);
                }
            });
        },

        setQuantityDefaults() {
            // Set quantity inputs to 1 by default
            document.querySelectorAll('input[type="number"][name*="quantity"], input[type="number"][name*="qty"]').forEach(input => {
                if (!input.value && input.min !== '0') {
                    input.value = '1';
                }
            });
        },

        setSearchFocus() {
            // Auto-focus search on list pages
            const searchInput = document.querySelector('input[type="search"], input[name="search"], #searchInput');
            if (searchInput && !document.querySelector('.modal.show')) {
                // Delay to not interfere with other focus handlers
                setTimeout(() => {
                    if (document.activeElement.tagName !== 'INPUT') {
                        searchInput.focus();
                    }
                }, 100);
            }
        },

        rememberLastUsed() {
            // Remember last used payment method
            const paymentSelect = document.querySelector('select[name="payment_method"]');
            if (paymentSelect) {
                const lastPayment = localStorage.getItem('lastPaymentMethod');
                if (lastPayment) {
                    const option = paymentSelect.querySelector(`option[value="${lastPayment}"]`);
                    if (option) {
                        paymentSelect.value = lastPayment;
                    }
                }

                paymentSelect.addEventListener('change', () => {
                    localStorage.setItem('lastPaymentMethod', paymentSelect.value);
                });
            }

            // Remember last used category filter
            const categorySelect = document.querySelector('select[name="category"], #categoryFilter');
            if (categorySelect) {
                const lastCategory = sessionStorage.getItem('lastCategory');
                if (lastCategory) {
                    const option = categorySelect.querySelector(`option[value="${lastCategory}"]`);
                    if (option) {
                        categorySelect.value = lastCategory;
                        categorySelect.dispatchEvent(new Event('change'));
                    }
                }

                categorySelect.addEventListener('change', () => {
                    sessionStorage.setItem('lastCategory', categorySelect.value);
                });
            }
        }
    };

    // ============================================
    // GUIDED WORKFLOWS
    // ============================================
    const Workflows = {
        steps: [],
        currentStep: 0,
        overlay: null,

        start(workflowName) {
            const workflows = {
                'first-sale': [
                    { element: '#searchInput, .search-input', title: 'Search Products', content: 'Start by searching for a product or scanning a barcode' },
                    { element: '.product-card, .product-item', title: 'Add to Cart', content: 'Click a product to add it to the cart' },
                    { element: '.cart-items, #cartItems', title: 'Review Cart', content: 'Your selected items appear here. Adjust quantities as needed.' },
                    { element: '.checkout-btn, #checkoutBtn', title: 'Checkout', content: 'Click to proceed to payment when ready' },
                ],
                'new-product': [
                    { element: '#productName, input[name="name"]', title: 'Product Name', content: 'Enter a clear, descriptive name' },
                    { element: '#productSku, input[name="sku"]', title: 'SKU/Barcode', content: 'Unique identifier for inventory tracking' },
                    { element: '#productPrice, input[name="price"]', title: 'Selling Price', content: 'The price customers will pay' },
                    { element: '#productStock, input[name="stock"]', title: 'Initial Stock', content: 'How many units you have in stock' },
                ],
                'table-order': [
                    { element: '.table-card[data-status="available"]', title: 'Select Table', content: 'Click an available (green) table to start an order' },
                    { element: '.menu-categories, #categoryTabs', title: 'Browse Menu', content: 'Select a category to view items' },
                    { element: '.menu-item, .product-card', title: 'Add Items', content: 'Click items to add to the order' },
                    { element: '.send-to-kitchen, #sendToKitchen', title: 'Send to Kitchen', content: 'Send the order to the kitchen when ready' },
                ],
            };

            this.steps = workflows[workflowName] || [];
            if (this.steps.length === 0) return;

            this.currentStep = 0;
            this.showStep();
        },

        showStep() {
            if (this.currentStep >= this.steps.length) {
                this.end();
                return;
            }

            const step = this.steps[this.currentStep];
            const element = document.querySelector(step.element);

            if (!element) {
                this.currentStep++;
                this.showStep();
                return;
            }

            this.createOverlay(element, step);
        },

        createOverlay(element, step) {
            this.removeOverlay();

            const rect = element.getBoundingClientRect();
            
            // Highlight element
            element.style.position = 'relative';
            element.style.zIndex = '10001';
            element.style.boxShadow = '0 0 0 4px #0d6efd, 0 0 20px rgba(13, 110, 253, 0.5)';
            element.style.borderRadius = '8px';

            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'workflow-overlay';
            this.overlay.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.6);
                z-index: 10000;
            `;

            // Create tooltip
            const tooltip = document.createElement('div');
            tooltip.className = 'workflow-tooltip';
            tooltip.style.cssText = `
                position: fixed;
                background: white;
                border-radius: 12px;
                padding: 20px;
                max-width: 320px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                z-index: 10002;
            `;

            // Position tooltip
            const tooltipTop = rect.bottom + 16;
            const tooltipLeft = Math.max(16, Math.min(rect.left, window.innerWidth - 336));
            tooltip.style.top = `${tooltipTop}px`;
            tooltip.style.left = `${tooltipLeft}px`;

            tooltip.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <h6 style="margin: 0; color: #0d6efd;">
                        <i class="bi bi-cursor-fill me-2"></i>${step.title}
                    </h6>
                    <span style="color: #6c757d; font-size: 0.875rem;">${this.currentStep + 1}/${this.steps.length}</span>
                </div>
                <p style="margin: 0 0 16px; color: #495057;">${step.content}</p>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button class="btn btn-link btn-sm text-muted workflow-skip">Skip Tour</button>
                    ${this.currentStep > 0 ? '<button class="btn btn-outline-secondary btn-sm workflow-prev">Back</button>' : ''}
                    <button class="btn btn-primary btn-sm workflow-next">${this.currentStep === this.steps.length - 1 ? 'Finish' : 'Next'}</button>
                </div>
            `;

            document.body.appendChild(this.overlay);
            document.body.appendChild(tooltip);

            // Event listeners
            tooltip.querySelector('.workflow-skip').addEventListener('click', () => this.end());
            tooltip.querySelector('.workflow-next').addEventListener('click', () => {
                this.currentStep++;
                this.showStep();
            });
            
            const prevBtn = tooltip.querySelector('.workflow-prev');
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    this.currentStep--;
                    this.showStep();
                });
            }

            // Store tooltip reference
            this.tooltip = tooltip;
            this.highlightedElement = element;
        },

        removeOverlay() {
            if (this.overlay) {
                this.overlay.remove();
                this.overlay = null;
            }
            if (this.tooltip) {
                this.tooltip.remove();
                this.tooltip = null;
            }
            if (this.highlightedElement) {
                this.highlightedElement.style.boxShadow = '';
                this.highlightedElement.style.zIndex = '';
                this.highlightedElement = null;
            }
        },

        end() {
            this.removeOverlay();
            this.steps = [];
            this.currentStep = 0;
            
            WAPOS_UX.Toast.success('Tour completed! Need help? Click the ? button anytime.');
        }
    };

    // ============================================
    // SMART SEARCH
    // ============================================
    const SmartSearch = {
        init(inputSelector, options = {}) {
            const input = document.querySelector(inputSelector);
            if (!input) return;

            const {
                minChars = 2,
                debounceMs = 300,
                onSearch = null,
                placeholder = 'Search...',
                showRecent = true
            } = options;

            input.placeholder = placeholder;
            
            // Add search icon
            const wrapper = document.createElement('div');
            wrapper.className = 'smart-search-wrapper';
            wrapper.style.cssText = 'position: relative;';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            const icon = document.createElement('i');
            icon.className = 'bi bi-search';
            icon.style.cssText = `
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #6c757d;
                pointer-events: none;
            `;
            wrapper.appendChild(icon);
            input.style.paddingLeft = '36px';

            // Clear button
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'smart-search-clear';
            clearBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            clearBtn.style.cssText = `
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #6c757d;
                cursor: pointer;
                padding: 4px;
                display: none;
            `;
            wrapper.appendChild(clearBtn);

            clearBtn.addEventListener('click', () => {
                input.value = '';
                clearBtn.style.display = 'none';
                input.focus();
                if (onSearch) onSearch('');
            });

            // Debounced search
            let timeout;
            input.addEventListener('input', () => {
                clearBtn.style.display = input.value ? 'block' : 'none';
                
                clearTimeout(timeout);
                if (input.value.length >= minChars || input.value.length === 0) {
                    timeout = setTimeout(() => {
                        if (onSearch) onSearch(input.value);
                        
                        // Save to recent searches
                        if (showRecent && input.value.length >= minChars) {
                            this.saveRecentSearch(input.value);
                        }
                    }, debounceMs);
                }
            });

            // Show recent searches on focus
            if (showRecent) {
                input.addEventListener('focus', () => {
                    if (!input.value) {
                        this.showRecentSearches(input, wrapper, onSearch);
                    }
                });
            }
        },

        saveRecentSearch(query) {
            const key = 'wapos_recent_searches';
            let recent = JSON.parse(localStorage.getItem(key) || '[]');
            
            // Remove if exists, add to front
            recent = recent.filter(q => q.toLowerCase() !== query.toLowerCase());
            recent.unshift(query);
            recent = recent.slice(0, 5); // Keep only 5 recent
            
            localStorage.setItem(key, JSON.stringify(recent));
        },

        showRecentSearches(input, wrapper, onSearch) {
            const key = 'wapos_recent_searches';
            const recent = JSON.parse(localStorage.getItem(key) || '[]');
            
            if (recent.length === 0) return;

            // Remove existing dropdown
            const existing = wrapper.querySelector('.recent-searches');
            if (existing) existing.remove();

            const dropdown = document.createElement('div');
            dropdown.className = 'recent-searches';
            dropdown.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                z-index: 100;
                margin-top: 4px;
            `;

            dropdown.innerHTML = `
                <div style="padding: 8px 12px; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #dee2e6;">
                    Recent Searches
                </div>
                ${recent.map(q => `
                    <div class="recent-search-item" style="
                        padding: 10px 12px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    ">
                        <i class="bi bi-clock-history text-muted"></i>
                        <span>${this.escapeHtml(q)}</span>
                    </div>
                `).join('')}
            `;

            wrapper.appendChild(dropdown);

            // Click handlers
            dropdown.querySelectorAll('.recent-search-item').forEach((item, index) => {
                item.addEventListener('click', () => {
                    input.value = recent[index];
                    dropdown.remove();
                    if (onSearch) onSearch(recent[index]);
                });

                item.addEventListener('mouseenter', () => {
                    item.style.background = '#f8f9fa';
                });
                item.addEventListener('mouseleave', () => {
                    item.style.background = '';
                });
            });

            // Close on blur
            const closeDropdown = (e) => {
                if (!wrapper.contains(e.target)) {
                    dropdown.remove();
                    document.removeEventListener('click', closeDropdown);
                }
            };
            setTimeout(() => document.addEventListener('click', closeDropdown), 0);
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ============================================
    // ERROR RECOVERY
    // ============================================
    const ErrorRecovery = {
        init() {
            // Global error handler
            window.addEventListener('error', (e) => {
                console.error('Global error:', e.error);
                this.showRecoveryOption(e.message);
            });

            // Unhandled promise rejections
            window.addEventListener('unhandledrejection', (e) => {
                console.error('Unhandled rejection:', e.reason);
                if (e.reason?.message?.includes('fetch') || e.reason?.message?.includes('network')) {
                    this.showNetworkError();
                }
            });

            // Form submission errors
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (form.tagName === 'FORM') {
                    this.backupFormData(form);
                }
            });
        },

        showRecoveryOption(message) {
            // Don't show for minor errors
            if (message?.includes('Script error') || message?.includes('ResizeObserver')) {
                return;
            }

            WAPOS_UX.Toast.error('Something went wrong. Your data has been saved locally.', {
                action: () => location.reload(),
                actionLabel: 'Refresh Page',
                duration: 10000
            });
        },

        showNetworkError() {
            WAPOS_UX.Toast.warning('Network connection issue. Changes will sync when connection is restored.', {
                persistent: true,
                icon: 'bi-wifi-off'
            });
        },

        backupFormData(form) {
            const formId = form.id || form.action || 'unknown-form';
            const data = new FormData(form);
            const backup = {};
            
            for (const [key, value] of data.entries()) {
                if (typeof value === 'string') {
                    backup[key] = value;
                }
            }

            sessionStorage.setItem(`form_backup_${formId}`, JSON.stringify(backup));
        },

        restoreFormData(form) {
            const formId = form.id || form.action || 'unknown-form';
            const backup = sessionStorage.getItem(`form_backup_${formId}`);
            
            if (!backup) return false;

            try {
                const data = JSON.parse(backup);
                Object.entries(data).forEach(([name, value]) => {
                    const input = form.querySelector(`[name="${name}"]`);
                    if (input && input.type !== 'password') {
                        input.value = value;
                    }
                });
                
                sessionStorage.removeItem(`form_backup_${formId}`);
                return true;
            } catch (e) {
                return false;
            }
        }
    };

    // ============================================
    // INITIALIZATION
    // ============================================
    const init = () => {
        Help.init();
        SmartDefaults.apply();
        ErrorRecovery.init();

        // Check if first visit
        if (!localStorage.getItem('wapos_visited')) {
            localStorage.setItem('wapos_visited', 'true');
            
            // Show welcome message after a short delay
            setTimeout(() => {
                WAPOS_UX.Toast.info('Welcome! Click the ? button for help anytime.', {
                    duration: 6000,
                    action: () => Help.showContextualHelp(),
                    actionLabel: 'Show Tips'
                });
            }, 2000);
        }

        console.log('WAPOS Smart Helpers initialized');
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ============================================
    // PUBLIC API
    // ============================================
    return {
        Help,
        SmartDefaults,
        Workflows,
        SmartSearch,
        ErrorRecovery
    };
})();
