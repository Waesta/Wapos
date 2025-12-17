/**
 * WAPOS PWA Install Manager
 * Handles app installation prompts and provides a native-like install experience
 */

class PWAInstallManager {
    constructor() {
        this.deferredPrompt = null;
        this.installButton = null;
        this.installBanner = null;
        this.isInstalled = false;
        this.dismissedUntil = null;
        
        this.init();
    }
    
    init() {
        // Check if already installed
        this.checkInstallState();
        
        // Listen for install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallOption();
        });
        
        // Listen for successful install
        window.addEventListener('appinstalled', () => {
            this.isInstalled = true;
            this.hideInstallOption();
            this.showInstallSuccess();
            localStorage.setItem('wapos_pwa_installed', 'true');
        });
        
        // Create install UI elements
        this.createInstallUI();
        
        // Check if we should show the banner
        this.checkShowBanner();
    }
    
    checkInstallState() {
        // Check if running as installed PWA
        if (window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;
            return;
        }
        
        // Check iOS standalone
        if (window.navigator.standalone === true) {
            this.isInstalled = true;
            return;
        }
        
        // Check localStorage flag
        if (localStorage.getItem('wapos_pwa_installed') === 'true') {
            this.isInstalled = true;
        }
    }
    
    checkShowBanner() {
        // Don't show if already installed
        if (this.isInstalled) return;
        
        // Check if user dismissed recently
        const dismissedUntil = localStorage.getItem('wapos_install_dismissed');
        if (dismissedUntil && new Date(dismissedUntil) > new Date()) {
            return;
        }
        
        // Show banner after a delay (let user explore first)
        setTimeout(() => {
            if (this.deferredPrompt && !this.isInstalled) {
                this.showInstallBanner();
            }
        }, 30000); // 30 seconds
    }
    
    createInstallUI() {
        // Create floating install button (always visible when installable)
        this.installButton = document.createElement('button');
        this.installButton.id = 'pwa-install-btn';
        this.installButton.className = 'pwa-install-btn';
        this.installButton.innerHTML = `
            <i class="bi bi-download"></i>
            <span>Install App</span>
        `;
        this.installButton.style.cssText = `
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
            z-index: 9998;
            transition: all 0.3s ease;
            align-items: center;
            gap: 8px;
        `;
        this.installButton.addEventListener('click', () => this.promptInstall());
        this.installButton.addEventListener('mouseenter', () => {
            this.installButton.style.transform = 'scale(1.05)';
            this.installButton.style.boxShadow = '0 6px 20px rgba(37, 99, 235, 0.5)';
        });
        this.installButton.addEventListener('mouseleave', () => {
            this.installButton.style.transform = 'scale(1)';
            this.installButton.style.boxShadow = '0 4px 15px rgba(37, 99, 235, 0.4)';
        });
        document.body.appendChild(this.installButton);
        
        // Create install banner
        this.installBanner = document.createElement('div');
        this.installBanner.id = 'pwa-install-banner';
        this.installBanner.innerHTML = `
            <div class="pwa-banner-content">
                <div class="pwa-banner-icon">
                    <img src="${window.APP_URL || ''}/assets/images/icons/icon-96.png" alt="WAPOS" onerror="this.onerror=null; this.src='${window.APP_URL || ''}/assets/images/logo.png'">
                </div>
                <div class="pwa-banner-text">
                    <strong>Install WAPOS</strong>
                    <p>Add to your device for quick access & offline use</p>
                </div>
                <div class="pwa-banner-actions">
                    <button class="pwa-btn-install">Install</button>
                    <button class="pwa-btn-dismiss">Not now</button>
                </div>
            </div>
        `;
        this.installBanner.style.cssText = `
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 16px 20px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideUp 0.3s ease;
        `;
        
        // Add banner styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideUp {
                from { transform: translateY(100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: scale(0.9); }
                to { opacity: 1; transform: scale(1); }
            }
            .pwa-banner-content {
                display: flex;
                align-items: center;
                gap: 16px;
                max-width: 600px;
                margin: 0 auto;
            }
            .pwa-banner-icon img {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .pwa-banner-text {
                flex: 1;
            }
            .pwa-banner-text strong {
                display: block;
                font-size: 16px;
                color: #1e293b;
            }
            .pwa-banner-text p {
                margin: 4px 0 0;
                font-size: 13px;
                color: #64748b;
            }
            .pwa-banner-actions {
                display: flex;
                gap: 8px;
            }
            .pwa-btn-install {
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .pwa-btn-install:hover {
                background: linear-gradient(135deg, #1d4ed8, #1e40af);
                transform: translateY(-1px);
            }
            .pwa-btn-dismiss {
                background: transparent;
                color: #64748b;
                border: 1px solid #e2e8f0;
                padding: 10px 16px;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .pwa-btn-dismiss:hover {
                background: #f8fafc;
                color: #475569;
            }
            .pwa-success-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #059669, #047857);
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(5, 150, 105, 0.4);
                z-index: 10000;
                animation: fadeIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .pwa-success-toast i {
                font-size: 24px;
            }
            @media (max-width: 480px) {
                .pwa-banner-content {
                    flex-wrap: wrap;
                }
                .pwa-banner-actions {
                    width: 100%;
                    justify-content: stretch;
                }
                .pwa-btn-install, .pwa-btn-dismiss {
                    flex: 1;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Add event listeners to banner buttons
        this.installBanner.querySelector('.pwa-btn-install').addEventListener('click', () => this.promptInstall());
        this.installBanner.querySelector('.pwa-btn-dismiss').addEventListener('click', () => this.dismissBanner());
        
        document.body.appendChild(this.installBanner);
    }
    
    showInstallOption() {
        if (this.installButton && !this.isInstalled) {
            this.installButton.style.display = 'flex';
        }
    }
    
    hideInstallOption() {
        if (this.installButton) {
            this.installButton.style.display = 'none';
        }
        if (this.installBanner) {
            this.installBanner.style.display = 'none';
        }
    }
    
    showInstallBanner() {
        if (this.installBanner && !this.isInstalled) {
            this.installBanner.style.display = 'block';
        }
    }
    
    dismissBanner() {
        this.installBanner.style.display = 'none';
        // Don't show again for 7 days
        const dismissUntil = new Date();
        dismissUntil.setDate(dismissUntil.getDate() + 7);
        localStorage.setItem('wapos_install_dismissed', dismissUntil.toISOString());
    }
    
    async promptInstall() {
        if (!this.deferredPrompt) {
            // Show manual install instructions
            this.showManualInstallInstructions();
            return;
        }
        
        // Show the install prompt
        this.deferredPrompt.prompt();
        
        // Wait for user response
        const { outcome } = await this.deferredPrompt.userChoice;
        
        if (outcome === 'accepted') {
            console.log('[PWA] User accepted install');
        } else {
            console.log('[PWA] User dismissed install');
        }
        
        // Clear the prompt
        this.deferredPrompt = null;
        this.hideInstallOption();
    }
    
    showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        
        let instructions = '';
        
        if (isIOS || isSafari) {
            instructions = `
                <h5>Install on iOS/Safari</h5>
                <ol>
                    <li>Tap the <strong>Share</strong> button <i class="bi bi-box-arrow-up"></i></li>
                    <li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>
                    <li>Tap <strong>"Add"</strong> to confirm</li>
                </ol>
            `;
        } else {
            instructions = `
                <h5>Install WAPOS</h5>
                <ol>
                    <li>Click the <strong>menu icon</strong> (⋮) in your browser</li>
                    <li>Select <strong>"Install WAPOS"</strong> or <strong>"Add to Home Screen"</strong></li>
                    <li>Click <strong>"Install"</strong> to confirm</li>
                </ol>
                <p class="text-muted small mt-3">
                    <i class="bi bi-info-circle"></i> 
                    In Chrome/Edge, look for the install icon (⊕) in the address bar.
                </p>
            `;
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'modal fade show';
        modal.style.cssText = 'display: block; background: rgba(0,0,0,0.5);';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-download me-2"></i>Install WAPOS</h5>
                        <button type="button" class="btn-close" onclick="this.closest('.modal').remove()"></button>
                    </div>
                    <div class="modal-body">
                        ${instructions}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    showInstallSuccess() {
        const toast = document.createElement('div');
        toast.className = 'pwa-success-toast';
        toast.innerHTML = `
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>WAPOS Installed!</strong>
                <p style="margin:0;font-size:13px;opacity:0.9;">You can now access it from your home screen</p>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}

// Initialize PWA Install Manager
let pwaInstallManager;
document.addEventListener('DOMContentLoaded', () => {
    pwaInstallManager = new PWAInstallManager();
});

// Export for manual triggering
window.showInstallPrompt = () => {
    if (pwaInstallManager) {
        pwaInstallManager.promptInstall();
    }
};
