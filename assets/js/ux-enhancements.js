/**
 * WAPOS UX Enhancements
 * International standards compliant, user-friendly utilities
 * 
 * Features:
 * - Toast notifications with auto-dismiss
 * - Confirmation dialogs with undo support
 * - Form validation with friendly messages
 * - Loading states and progress indicators
 * - Keyboard shortcuts
 * - Auto-save functionality
 * - Accessibility improvements
 * - Smart defaults
 */

const WAPOS_UX = (function() {
    'use strict';

    // ============================================
    // CONFIGURATION
    // ============================================
    const config = {
        toastDuration: 4000,
        autoSaveDelay: 30000,
        undoDuration: 8000,
        animationDuration: 300,
        debounceDelay: 300,
    };

    // ============================================
    // TOAST NOTIFICATION SYSTEM
    // ============================================
    const Toast = {
        container: null,

        init() {
            if (this.container) return;
            
            this.container = document.createElement('div');
            this.container.id = 'wapos-toast-container';
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-atomic', 'true');
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 380px;
                pointer-events: none;
            `;
            document.body.appendChild(this.container);
        },

        show(message, type = 'info', options = {}) {
            this.init();

            const {
                duration = config.toastDuration,
                action = null,
                actionLabel = 'Undo',
                icon = null,
                persistent = false
            } = options;

            const icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-exclamation-triangle-fill',
                warning: 'bi-exclamation-circle-fill',
                info: 'bi-info-circle-fill',
            };

            const colors = {
                success: { bg: '#d1e7dd', border: '#badbcc', text: '#0f5132', icon: '#198754' },
                error: { bg: '#f8d7da', border: '#f5c2c7', text: '#842029', icon: '#dc3545' },
                warning: { bg: '#fff3cd', border: '#ffecb5', text: '#664d03', icon: '#ffc107' },
                info: { bg: '#cff4fc', border: '#b6effb', text: '#055160', icon: '#0dcaf0' },
            };

            const color = colors[type] || colors.info;
            const iconClass = icon || icons[type] || icons.info;

            const toast = document.createElement('div');
            toast.className = 'wapos-toast';
            toast.setAttribute('role', 'alert');
            toast.style.cssText = `
                background: ${color.bg};
                border: 1px solid ${color.border};
                border-radius: 8px;
                padding: 12px 16px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                pointer-events: auto;
                animation: slideInRight 0.3s ease;
                color: ${color.text};
            `;

            toast.innerHTML = `
                <i class="bi ${iconClass}" style="font-size: 1.25rem; color: ${color.icon}; flex-shrink: 0; margin-top: 2px;"></i>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 500; line-height: 1.4;">${this.escapeHtml(message)}</div>
                    ${action ? `<button class="toast-action-btn" style="
                        background: none;
                        border: none;
                        color: ${color.icon};
                        font-weight: 600;
                        padding: 4px 0;
                        cursor: pointer;
                        text-decoration: underline;
                        font-size: 0.9rem;
                    ">${actionLabel}</button>` : ''}
                </div>
                <button class="toast-close-btn" aria-label="Close" style="
                    background: none;
                    border: none;
                    color: ${color.text};
                    opacity: 0.6;
                    cursor: pointer;
                    padding: 0;
                    font-size: 1.25rem;
                    line-height: 1;
                ">&times;</button>
            `;

            // Event listeners
            const closeBtn = toast.querySelector('.toast-close-btn');
            closeBtn.addEventListener('click', () => this.dismiss(toast));

            if (action) {
                const actionBtn = toast.querySelector('.toast-action-btn');
                actionBtn.addEventListener('click', () => {
                    action();
                    this.dismiss(toast);
                });
            }

            this.container.appendChild(toast);

            // Auto dismiss
            if (!persistent && duration > 0) {
                setTimeout(() => this.dismiss(toast), duration);
            }

            return toast;
        },

        dismiss(toast) {
            if (!toast || !toast.parentNode) return;
            
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        },

        success(message, options = {}) {
            return this.show(message, 'success', options);
        },

        error(message, options = {}) {
            return this.show(message, 'error', { duration: 6000, ...options });
        },

        warning(message, options = {}) {
            return this.show(message, 'warning', options);
        },

        info(message, options = {}) {
            return this.show(message, 'info', options);
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ============================================
    // CONFIRMATION DIALOG SYSTEM
    // ============================================
    const Confirm = {
        show(options) {
            return new Promise((resolve) => {
                const {
                    title = 'Confirm Action',
                    message = 'Are you sure you want to proceed?',
                    confirmText = 'Confirm',
                    cancelText = 'Cancel',
                    type = 'warning', // warning, danger, info
                    icon = null
                } = options;

                const icons = {
                    warning: 'bi-exclamation-triangle',
                    danger: 'bi-trash',
                    info: 'bi-question-circle',
                };

                const colors = {
                    warning: '#ffc107',
                    danger: '#dc3545',
                    info: '#0d6efd',
                };

                const overlay = document.createElement('div');
                overlay.className = 'wapos-confirm-overlay';
                overlay.style.cssText = `
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    animation: fadeIn 0.2s ease;
                `;

                overlay.innerHTML = `
                    <div class="wapos-confirm-dialog" style="
                        background: white;
                        border-radius: 12px;
                        padding: 24px;
                        max-width: 400px;
                        width: 90%;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                        animation: scaleIn 0.2s ease;
                    ">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="
                                width: 64px;
                                height: 64px;
                                border-radius: 50%;
                                background: ${colors[type]}20;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin: 0 auto 16px;
                            ">
                                <i class="bi ${icon || icons[type]}" style="font-size: 2rem; color: ${colors[type]};"></i>
                            </div>
                            <h5 style="margin: 0 0 8px; font-weight: 600;">${this.escapeHtml(title)}</h5>
                            <p style="margin: 0; color: #6c757d; line-height: 1.5;">${this.escapeHtml(message)}</p>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <button class="confirm-cancel-btn" style="
                                flex: 1;
                                padding: 10px 16px;
                                border: 1px solid #dee2e6;
                                background: white;
                                border-radius: 8px;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s;
                            ">${cancelText}</button>
                            <button class="confirm-ok-btn" style="
                                flex: 1;
                                padding: 10px 16px;
                                border: none;
                                background: ${type === 'danger' ? '#dc3545' : '#0d6efd'};
                                color: white;
                                border-radius: 8px;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s;
                            ">${confirmText}</button>
                        </div>
                    </div>
                `;

                const close = (result) => {
                    overlay.style.animation = 'fadeOut 0.2s ease forwards';
                    setTimeout(() => {
                        document.body.removeChild(overlay);
                        resolve(result);
                    }, 200);
                };

                overlay.querySelector('.confirm-cancel-btn').addEventListener('click', () => close(false));
                overlay.querySelector('.confirm-ok-btn').addEventListener('click', () => close(true));
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) close(false);
                });

                // Keyboard support
                const handleKeydown = (e) => {
                    if (e.key === 'Escape') close(false);
                    if (e.key === 'Enter') close(true);
                };
                document.addEventListener('keydown', handleKeydown);
                overlay.addEventListener('remove', () => document.removeEventListener('keydown', handleKeydown));

                document.body.appendChild(overlay);
                overlay.querySelector('.confirm-ok-btn').focus();
            });
        },

        delete(itemName) {
            return this.show({
                title: 'Delete Item',
                message: `Are you sure you want to delete "${itemName}"? This action cannot be undone.`,
                confirmText: 'Delete',
                cancelText: 'Keep',
                type: 'danger',
                icon: 'bi-trash'
            });
        },

        unsavedChanges() {
            return this.show({
                title: 'Unsaved Changes',
                message: 'You have unsaved changes. Do you want to leave without saving?',
                confirmText: 'Leave',
                cancelText: 'Stay',
                type: 'warning',
                icon: 'bi-exclamation-triangle'
            });
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ============================================
    // FORM VALIDATION SYSTEM
    // ============================================
    const Validation = {
        messages: {
            required: 'This field is required',
            email: 'Please enter a valid email address',
            phone: 'Please enter a valid phone number',
            number: 'Please enter a valid number',
            min: 'Value must be at least {min}',
            max: 'Value must not exceed {max}',
            minLength: 'Must be at least {min} characters',
            maxLength: 'Must not exceed {max} characters',
            pattern: 'Please enter a valid format',
            match: 'Fields do not match',
            url: 'Please enter a valid URL',
            date: 'Please enter a valid date',
            currency: 'Please enter a valid amount',
        },

        rules: {
            required: (value) => value !== null && value !== undefined && String(value).trim() !== '',
            email: (value) => !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            phone: (value) => !value || /^[\d\s\-+()]{7,20}$/.test(value),
            number: (value) => !value || !isNaN(parseFloat(value)),
            min: (value, min) => !value || parseFloat(value) >= min,
            max: (value, max) => !value || parseFloat(value) <= max,
            minLength: (value, min) => !value || String(value).length >= min,
            maxLength: (value, max) => !value || String(value).length <= max,
            url: (value) => !value || /^https?:\/\/.+/.test(value),
            date: (value) => !value || !isNaN(Date.parse(value)),
            currency: (value) => !value || /^\d+(\.\d{1,2})?$/.test(value),
        },

        validateField(input) {
            const value = input.value;
            const errors = [];

            // Check required
            if (input.required && !this.rules.required(value)) {
                errors.push(this.messages.required);
            }

            // Check type-specific validation
            if (input.type === 'email' && !this.rules.email(value)) {
                errors.push(this.messages.email);
            }

            if (input.type === 'tel' && !this.rules.phone(value)) {
                errors.push(this.messages.phone);
            }

            if (input.type === 'number') {
                if (!this.rules.number(value)) {
                    errors.push(this.messages.number);
                }
                if (input.min && !this.rules.min(value, parseFloat(input.min))) {
                    errors.push(this.messages.min.replace('{min}', input.min));
                }
                if (input.max && !this.rules.max(value, parseFloat(input.max))) {
                    errors.push(this.messages.max.replace('{max}', input.max));
                }
            }

            if (input.type === 'url' && !this.rules.url(value)) {
                errors.push(this.messages.url);
            }

            // Check minlength/maxlength
            if (input.minLength > 0 && !this.rules.minLength(value, input.minLength)) {
                errors.push(this.messages.minLength.replace('{min}', input.minLength));
            }
            if (input.maxLength > 0 && input.maxLength < 524288 && !this.rules.maxLength(value, input.maxLength)) {
                errors.push(this.messages.maxLength.replace('{max}', input.maxLength));
            }

            // Check pattern
            if (input.pattern && value && !new RegExp(input.pattern).test(value)) {
                errors.push(input.title || this.messages.pattern);
            }

            return errors;
        },

        showError(input, message) {
            this.clearError(input);
            
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.style.display = 'block';
            feedback.textContent = message;

            input.parentNode.appendChild(feedback);
        },

        clearError(input) {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');

            const feedback = input.parentNode.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.remove();
            }
        },

        showSuccess(input) {
            this.clearError(input);
            input.classList.add('is-valid');
        },

        validateForm(form) {
            let isValid = true;
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                const errors = this.validateField(input);
                if (errors.length > 0) {
                    this.showError(input, errors[0]);
                    isValid = false;
                } else if (input.value) {
                    this.showSuccess(input);
                }
            });

            if (!isValid) {
                // Focus first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }

            return isValid;
        },

        initRealTimeValidation(form) {
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    const errors = this.validateField(input);
                    if (errors.length > 0) {
                        this.showError(input, errors[0]);
                    } else {
                        this.clearError(input);
                        if (input.value) {
                            this.showSuccess(input);
                        }
                    }
                });

                input.addEventListener('input', () => {
                    if (input.classList.contains('is-invalid')) {
                        const errors = this.validateField(input);
                        if (errors.length === 0) {
                            this.clearError(input);
                        }
                    }
                });
            });
        }
    };

    // ============================================
    // LOADING STATE SYSTEM
    // ============================================
    const Loading = {
        show(element, text = 'Loading...') {
            if (!element) return;

            const originalContent = element.innerHTML;
            element.dataset.originalContent = originalContent;
            element.disabled = true;
            element.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                ${text}
            `;
        },

        hide(element) {
            if (!element || !element.dataset.originalContent) return;

            element.innerHTML = element.dataset.originalContent;
            element.disabled = false;
            delete element.dataset.originalContent;
        },

        overlay(container, text = 'Loading...') {
            const overlay = document.createElement('div');
            overlay.className = 'wapos-loading-overlay';
            overlay.style.cssText = `
                position: absolute;
                inset: 0;
                background: rgba(255,255,255,0.9);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 100;
                border-radius: inherit;
            `;
            overlay.innerHTML = `
                <div class="spinner-border text-primary mb-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="text-muted">${text}</span>
            `;

            container.style.position = 'relative';
            container.appendChild(overlay);

            return () => overlay.remove();
        },

        progress(container, percent, text = '') {
            let progressBar = container.querySelector('.wapos-progress-bar');
            
            if (!progressBar) {
                progressBar = document.createElement('div');
                progressBar.className = 'wapos-progress-bar';
                progressBar.style.cssText = `
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: #e9ecef;
                    border-radius: 0 0 inherit inherit;
                    overflow: hidden;
                `;
                progressBar.innerHTML = `
                    <div class="progress-fill" style="
                        height: 100%;
                        background: #0d6efd;
                        transition: width 0.3s ease;
                        width: 0%;
                    "></div>
                `;
                container.style.position = 'relative';
                container.appendChild(progressBar);
            }

            const fill = progressBar.querySelector('.progress-fill');
            fill.style.width = `${Math.min(100, Math.max(0, percent))}%`;

            if (percent >= 100) {
                setTimeout(() => progressBar.remove(), 500);
            }
        }
    };

    // ============================================
    // KEYBOARD SHORTCUTS SYSTEM
    // ============================================
    const Shortcuts = {
        registered: new Map(),
        helpModal: null,

        init() {
            document.addEventListener('keydown', (e) => {
                // Don't trigger shortcuts when typing in inputs
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                    // Allow Escape to blur inputs
                    if (e.key === 'Escape') {
                        e.target.blur();
                    }
                    return;
                }

                const key = this.getKeyCombo(e);
                const handler = this.registered.get(key);
                
                if (handler) {
                    e.preventDefault();
                    handler.callback();
                }
            });

            // Register help shortcut
            this.register('?', 'Show keyboard shortcuts', () => this.showHelp());
        },

        getKeyCombo(e) {
            const parts = [];
            if (e.ctrlKey || e.metaKey) parts.push('Ctrl');
            if (e.altKey) parts.push('Alt');
            if (e.shiftKey) parts.push('Shift');
            
            let key = e.key;
            if (key === ' ') key = 'Space';
            if (key.length === 1) key = key.toUpperCase();
            
            parts.push(key);
            return parts.join('+');
        },

        register(shortcut, description, callback) {
            this.registered.set(shortcut, { description, callback });
        },

        showHelp() {
            if (this.helpModal) {
                this.helpModal.remove();
            }

            const shortcuts = Array.from(this.registered.entries())
                .map(([key, { description }]) => `
                    <tr>
                        <td><kbd style="
                            background: #f1f3f5;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-family: monospace;
                            border: 1px solid #dee2e6;
                        ">${key}</kbd></td>
                        <td>${description}</td>
                    </tr>
                `).join('');

            this.helpModal = document.createElement('div');
            this.helpModal.className = 'wapos-shortcuts-modal';
            this.helpModal.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;
            this.helpModal.innerHTML = `
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
                        <h5 style="margin: 0;"><i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts</h5>
                        <button class="btn-close" aria-label="Close"></button>
                    </div>
                    <table class="table table-sm">
                        <tbody>${shortcuts}</tbody>
                    </table>
                </div>
            `;

            this.helpModal.querySelector('.btn-close').addEventListener('click', () => {
                this.helpModal.remove();
                this.helpModal = null;
            });

            this.helpModal.addEventListener('click', (e) => {
                if (e.target === this.helpModal) {
                    this.helpModal.remove();
                    this.helpModal = null;
                }
            });

            document.body.appendChild(this.helpModal);
        }
    };

    // ============================================
    // AUTO-SAVE SYSTEM
    // ============================================
    const AutoSave = {
        timers: new Map(),
        indicators: new Map(),

        init(form, saveCallback, options = {}) {
            const {
                delay = config.autoSaveDelay,
                storageKey = null,
                showIndicator = true
            } = options;

            if (showIndicator) {
                this.createIndicator(form);
            }

            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    this.scheduleAutoSave(form, saveCallback, delay, storageKey);
                });
            });

            // Restore from localStorage if available
            if (storageKey) {
                this.restore(form, storageKey);
            }

            // Save before leaving
            window.addEventListener('beforeunload', () => {
                if (storageKey) {
                    this.saveToStorage(form, storageKey);
                }
            });
        },

        scheduleAutoSave(form, callback, delay, storageKey) {
            const formId = form.id || 'default';
            
            // Clear existing timer
            if (this.timers.has(formId)) {
                clearTimeout(this.timers.get(formId));
            }

            this.updateIndicator(form, 'pending');

            // Save to localStorage immediately
            if (storageKey) {
                this.saveToStorage(form, storageKey);
            }

            // Schedule server save
            const timer = setTimeout(async () => {
                this.updateIndicator(form, 'saving');
                try {
                    await callback(new FormData(form));
                    this.updateIndicator(form, 'saved');
                    
                    // Clear localStorage after successful save
                    if (storageKey) {
                        localStorage.removeItem(storageKey);
                    }
                } catch (error) {
                    this.updateIndicator(form, 'error');
                    Toast.error('Auto-save failed. Your changes are saved locally.');
                }
            }, delay);

            this.timers.set(formId, timer);
        },

        createIndicator(form) {
            const indicator = document.createElement('div');
            indicator.className = 'autosave-indicator';
            indicator.style.cssText = `
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: white;
                padding: 8px 16px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                font-size: 0.875rem;
                display: none;
                align-items: center;
                gap: 8px;
                z-index: 1000;
            `;
            document.body.appendChild(indicator);
            this.indicators.set(form.id || 'default', indicator);
        },

        updateIndicator(form, status) {
            const indicator = this.indicators.get(form.id || 'default');
            if (!indicator) return;

            const states = {
                pending: { icon: 'bi-circle', text: 'Unsaved changes', color: '#ffc107' },
                saving: { icon: 'bi-arrow-repeat', text: 'Saving...', color: '#0d6efd' },
                saved: { icon: 'bi-check-circle', text: 'All changes saved', color: '#198754' },
                error: { icon: 'bi-exclamation-circle', text: 'Save failed', color: '#dc3545' },
            };

            const state = states[status];
            indicator.style.display = 'flex';
            indicator.innerHTML = `
                <i class="bi ${state.icon}" style="color: ${state.color};"></i>
                <span>${state.text}</span>
            `;

            if (status === 'saved') {
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 3000);
            }
        },

        saveToStorage(form, key) {
            const data = {};
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                if (input.name) {
                    if (input.type === 'checkbox') {
                        data[input.name] = input.checked;
                    } else if (input.type === 'radio') {
                        if (input.checked) {
                            data[input.name] = input.value;
                        }
                    } else {
                        data[input.name] = input.value;
                    }
                }
            });

            localStorage.setItem(key, JSON.stringify(data));
        },

        restore(form, key) {
            const saved = localStorage.getItem(key);
            if (!saved) return;

            try {
                const data = JSON.parse(saved);
                Object.entries(data).forEach(([name, value]) => {
                    const input = form.querySelector(`[name="${name}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = value;
                        } else if (input.type === 'radio') {
                            const radio = form.querySelector(`[name="${name}"][value="${value}"]`);
                            if (radio) radio.checked = true;
                        } else {
                            input.value = value;
                        }
                    }
                });

                Toast.info('Restored unsaved changes from your last session.', {
                    action: () => {
                        localStorage.removeItem(key);
                        location.reload();
                    },
                    actionLabel: 'Discard'
                });
            } catch (e) {
                localStorage.removeItem(key);
            }
        }
    };

    // ============================================
    // ACCESSIBILITY HELPERS
    // ============================================
    const A11y = {
        init() {
            // Skip link
            this.addSkipLink();
            
            // Focus trap for modals
            this.initFocusTrap();
            
            // Announce dynamic content
            this.createLiveRegion();
        },

        addSkipLink() {
            if (document.querySelector('.skip-link')) return;

            const skipLink = document.createElement('a');
            skipLink.className = 'skip-link';
            skipLink.href = '#main-content';
            skipLink.textContent = 'Skip to main content';
            skipLink.style.cssText = `
                position: absolute;
                top: -40px;
                left: 0;
                background: #0d6efd;
                color: white;
                padding: 8px 16px;
                z-index: 10000;
                transition: top 0.3s;
            `;
            skipLink.addEventListener('focus', () => {
                skipLink.style.top = '0';
            });
            skipLink.addEventListener('blur', () => {
                skipLink.style.top = '-40px';
            });

            document.body.insertBefore(skipLink, document.body.firstChild);
        },

        initFocusTrap() {
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Tab') return;

                const modal = document.querySelector('.modal.show, [role="dialog"][aria-modal="true"]');
                if (!modal) return;

                const focusable = modal.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                
                if (focusable.length === 0) return;

                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });
        },

        createLiveRegion() {
            if (document.getElementById('wapos-live-region')) return;

            const region = document.createElement('div');
            region.id = 'wapos-live-region';
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            region.className = 'visually-hidden';
            document.body.appendChild(region);
        },

        announce(message) {
            const region = document.getElementById('wapos-live-region');
            if (region) {
                region.textContent = message;
                setTimeout(() => {
                    region.textContent = '';
                }, 1000);
            }
        }
    };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    const Utils = {
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        throttle(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        formatCurrency(amount, currency = 'USD') {
            return new Intl.NumberFormat(undefined, {
                style: 'currency',
                currency: currency
            }).format(amount);
        },

        formatDate(date, options = {}) {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
                ...options
            }).format(new Date(date));
        },

        formatRelativeTime(date) {
            const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
            const diff = Date.now() - new Date(date).getTime();
            
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);

            if (days > 0) return rtf.format(-days, 'day');
            if (hours > 0) return rtf.format(-hours, 'hour');
            if (minutes > 0) return rtf.format(-minutes, 'minute');
            return rtf.format(-seconds, 'second');
        },

        copyToClipboard(text) {
            return navigator.clipboard.writeText(text).then(() => {
                Toast.success('Copied to clipboard');
            }).catch(() => {
                Toast.error('Failed to copy');
            });
        }
    };

    // ============================================
    // CSS ANIMATIONS
    // ============================================
    const injectStyles = () => {
        if (document.getElementById('wapos-ux-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'wapos-ux-styles';
        styles.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            @keyframes scaleIn {
                from { transform: scale(0.9); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            .wapos-toast .bi-arrow-repeat {
                animation: spin 1s linear infinite;
            }
            
            /* Enhanced form styles */
            .form-control:focus, .form-select:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
            }
            .form-control.is-valid {
                border-color: #198754;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right calc(0.375em + 0.1875rem) center;
                background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
                padding-right: calc(1.5em + 0.75rem);
            }
            
            /* Button loading state */
            .btn:disabled {
                cursor: not-allowed;
                opacity: 0.7;
            }
            
            /* Smooth transitions */
            .form-control, .form-select, .btn {
                transition: all 0.2s ease;
            }
        `;
        document.head.appendChild(styles);
    };

    // ============================================
    // INITIALIZATION
    // ============================================
    const init = () => {
        injectStyles();
        Toast.init();
        Shortcuts.init();
        A11y.init();

        // Register common shortcuts
        Shortcuts.register('Ctrl+S', 'Save', () => {
            const form = document.querySelector('form[data-autosave], form#settingsForm, form.main-form');
            if (form) {
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });

        Shortcuts.register('Escape', 'Close modal / Cancel', () => {
            const modal = document.querySelector('.modal.show');
            if (modal) {
                const closeBtn = modal.querySelector('[data-bs-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
            }
        });

        Shortcuts.register('/', 'Focus search', () => {
            const search = document.querySelector('input[type="search"], input[name="search"], #searchInput, .search-input');
            if (search) {
                search.focus();
                search.select();
            }
        });

        // Initialize real-time validation on all forms
        document.querySelectorAll('form').forEach(form => {
            Validation.initRealTimeValidation(form);
        });

        console.log('WAPOS UX Enhancements initialized');
    };

    // Auto-init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ============================================
    // PUBLIC API
    // ============================================
    return {
        Toast,
        Confirm,
        Validation,
        Loading,
        Shortcuts,
        AutoSave,
        A11y,
        Utils,
        config
    };
})();

// Global shortcuts for convenience
const showToast = WAPOS_UX.Toast.show.bind(WAPOS_UX.Toast);
const showConfirm = WAPOS_UX.Confirm.show.bind(WAPOS_UX.Confirm);
