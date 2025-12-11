/**
 * WAPOS Offline Manager
 * Handles offline functionality, data caching, and synchronization
 */

class OfflineManager {
    constructor() {
        this.dbName = 'wapos-offline';
        this.dbVersion = 3;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        this.checkInterval = null;
        
        this.init();
    }
    
    async init() {
        // Initialize IndexedDB
        await this.openDatabase();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Check actual connectivity (not just network adapter)
        await this.checkRealConnectivity();
        
        // Update status indicator immediately
        this.updateOnlineStatus();
        
        // Start periodic connectivity check
        this.startConnectivityCheck();
        
        // Initial sync if online
        if (this.isOnline) {
            this.syncWhenOnline();
        }
        
        console.log('[OfflineManager] Initialized, online:', this.isOnline);
    }
    
    /**
     * Check real internet connectivity
     * For localhost: checks external connectivity
     * For production: pings the server
     */
    async checkRealConnectivity() {
        const isLocalhost = window.location.hostname === 'localhost' || 
                           window.location.hostname === '127.0.0.1';
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            if (isLocalhost) {
                // On localhost, check external internet connectivity
                // Try multiple reliable endpoints
                const checks = [
                    this.checkEndpoint('https://www.google.com/generate_204', controller.signal),
                    this.checkEndpoint('https://connectivitycheck.gstatic.com/generate_204', controller.signal),
                    this.checkEndpoint('https://clients3.google.com/generate_204', controller.signal)
                ];
                
                // If any check succeeds, we're online
                const results = await Promise.allSettled(checks);
                this.isOnline = results.some(r => r.status === 'fulfilled' && r.value === true);
            } else {
                // On production server, ping our own endpoint
                const response = await fetch('/api/ping.php?t=' + Date.now(), {
                    method: 'HEAD',
                    cache: 'no-store',
                    signal: controller.signal
                });
                this.isOnline = response.ok;
            }
            
            clearTimeout(timeoutId);
        } catch (error) {
            // Network error or timeout - we're offline
            this.isOnline = false;
        }
        return this.isOnline;
    }
    
    /**
     * Check a single endpoint for connectivity
     */
    async checkEndpoint(url, signal) {
        try {
            const response = await fetch(url, {
                method: 'HEAD',
                mode: 'no-cors', // Allows checking external URLs
                cache: 'no-store',
                signal: signal
            });
            // With no-cors, we can't read response but if fetch succeeds, we're online
            return true;
        } catch (error) {
            return false;
        }
    }
    
    /**
     * Start periodic connectivity check
     */
    startConnectivityCheck() {
        // Check every 10 seconds
        this.checkInterval = setInterval(async () => {
            const wasOnline = this.isOnline;
            await this.checkRealConnectivity();
            
            if (wasOnline !== this.isOnline) {
                console.log('[OfflineManager] Connectivity changed:', this.isOnline ? 'Online' : 'Offline');
                this.updateOnlineStatus();
                
                if (this.isOnline) {
                    this.syncWhenOnline();
                }
            }
        }, 10000);
    }
    
    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create stores if they don't exist
                const stores = [
                    'pending-sales',
                    'pending-orders',
                    'pending-customers',
                    'products',
                    'customers',
                    'categories',
                    'settings'
                ];
                
                stores.forEach(storeName => {
                    if (!db.objectStoreNames.contains(storeName)) {
                        const store = db.createObjectStore(storeName, { 
                            keyPath: 'id', 
                            autoIncrement: true 
                        });
                        
                        if (storeName.startsWith('pending-')) {
                            store.createIndex('timestamp', 'timestamp');
                        }
                    }
                });
            };
        });
    }
    
    setupEventListeners() {
        // Online/offline events from browser
        window.addEventListener('online', async () => {
            console.log('[OfflineManager] Browser reports online');
            // Verify with actual ping
            await this.checkRealConnectivity();
            this.updateOnlineStatus();
            if (this.isOnline) {
                this.syncWhenOnline();
            }
        });
        
        window.addEventListener('offline', () => {
            console.log('[OfflineManager] Browser reports offline');
            this.isOnline = false;
            this.updateOnlineStatus();
        });
        
        // Service worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event.data);
            });
        }
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                console.log('[OfflineManager] Service Worker registered:', registration);
                
                // Handle updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateAvailable();
                        }
                    });
                });
                
            } catch (error) {
                console.error('[OfflineManager] Service Worker registration failed:', error);
            }
        }
    }
    
    updateOnlineStatus() {
        const statusElement = document.getElementById('online-status');
        const syncButton = document.getElementById('sync-button');
        
        if (statusElement) {
            if (this.isOnline) {
                statusElement.innerHTML = '<i class="bi bi-wifi"></i> <span>Online</span>';
                statusElement.className = 'badge bg-success d-flex align-items-center gap-1';
            } else {
                statusElement.innerHTML = '<i class="bi bi-wifi-off"></i> <span>Offline</span>';
                statusElement.className = 'badge bg-warning text-dark d-flex align-items-center gap-1';
            }
        }
        
        if (syncButton) {
            syncButton.disabled = !this.isOnline || this.syncInProgress;
        }
        
        // Show/hide offline indicator
        this.toggleOfflineIndicator(!this.isOnline);
    }
    
    toggleOfflineIndicator(show) {
        let indicator = document.getElementById('offline-indicator');
        
        if (show && !indicator) {
            indicator = document.createElement('div');
            indicator.id = 'offline-indicator';
            indicator.className = 'alert alert-warning position-fixed top-0 start-50 translate-middle-x mt-2';
            indicator.style.zIndex = '9999';
            indicator.innerHTML = `
                <i class="bi bi-wifi-off me-2"></i>
                Working offline - changes will sync when online
                <button class="btn btn-sm btn-outline-warning ms-2" onclick="offlineManager.showOfflineQueue()">
                    View Queue
                </button>
            `;
            document.body.appendChild(indicator);
        } else if (!show && indicator) {
            indicator.remove();
        }
    }
    
    // Save data for offline use
    async saveOfflineTransaction(type, data) {
        try {
            const transaction = this.db.transaction([`pending-${type}`], 'readwrite');
            const store = transaction.objectStore(`pending-${type}`);
            
            const offlineData = {
                data: data,
                timestamp: Date.now(),
                type: type,
                synced: false
            };
            
            await this.addToStore(store, offlineData);
            
            console.log(`[OfflineManager] Saved offline ${type}:`, offlineData);
            
            // Update pending count
            this.updatePendingCount();
            
            return true;
        } catch (error) {
            console.error(`[OfflineManager] Failed to save offline ${type}:`, error);
            return false;
        }
    }
    
    // Get cached data
    async getCachedData(storeName) {
        try {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const data = await this.getAllFromStore(store);
            return data;
        } catch (error) {
            console.error(`[OfflineManager] Failed to get cached data from ${storeName}:`, error);
            return [];
        }
    }
    
    // Cache fresh data from server
    async cacheData(storeName, data) {
        try {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Clear existing data
            await this.clearStore(store);
            
            // Add new data
            for (const item of data) {
                await this.addToStore(store, item);
            }
            
            console.log(`[OfflineManager] Cached ${data.length} items in ${storeName}`);
        } catch (error) {
            console.error(`[OfflineManager] Failed to cache data in ${storeName}:`, error);
        }
    }
    
    // Sync when coming back online
    async syncWhenOnline() {
        if (!this.isOnline || this.syncInProgress) return;
        
        this.syncInProgress = true;
        this.updateOnlineStatus();
        
        try {
            console.log('[OfflineManager] Starting sync...');
            
            // Get pending transactions
            const pendingSales = await this.getCachedData('pending-sales');
            const pendingOrders = await this.getCachedData('pending-orders');
            const pendingCustomers = await this.getCachedData('pending-customers');
            
            let syncedCount = 0;
            let failedCount = 0;
            
            // Sync sales
            for (const sale of pendingSales) {
                if (await this.syncTransaction('sales', sale)) {
                    await this.removeFromPending('pending-sales', sale.id);
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }
            
            // Sync orders
            for (const order of pendingOrders) {
                if (await this.syncTransaction('orders', order)) {
                    await this.removeFromPending('pending-orders', order.id);
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }
            
            // Sync customers
            for (const customer of pendingCustomers) {
                if (await this.syncTransaction('customers', customer)) {
                    await this.removeFromPending('pending-customers', customer.id);
                    syncedCount++;
                } else {
                    failedCount++;
                }
            }
            
            // Update UI
            this.updatePendingCount();
            
            if (syncedCount > 0) {
                this.showSyncSuccess(syncedCount, failedCount);
            }
            
            console.log(`[OfflineManager] Sync complete: ${syncedCount} synced, ${failedCount} failed`);
            
        } catch (error) {
            console.error('[OfflineManager] Sync failed:', error);
            this.showSyncError(error.message);
        } finally {
            this.syncInProgress = false;
            this.updateOnlineStatus();
        }
    }
    
    async syncTransaction(type, transaction) {
        try {
            const endpoints = {
                'sales': '/api/complete-sale.php',
                'orders': '/api/create-restaurant-order.php',
                'customers': '/api/save-customer.php'
            };
            
            const response = await fetch(endpoints[type], {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Sync-Request': 'true'
                },
                body: JSON.stringify(transaction.data)
            });
            
            return response.ok;
        } catch (error) {
            console.error(`[OfflineManager] Failed to sync ${type}:`, error);
            return false;
        }
    }
    
    async removeFromPending(storeName, id) {
        try {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            await this.deleteFromStore(store, id);
        } catch (error) {
            console.error(`[OfflineManager] Failed to remove from ${storeName}:`, error);
        }
    }
    
    async updatePendingCount() {
        try {
            const pendingSales = await this.getCachedData('pending-sales');
            const pendingOrders = await this.getCachedData('pending-orders');
            const pendingCustomers = await this.getCachedData('pending-customers');
            
            // Also count localStorage backup sales
            const localStorageSales = JSON.parse(localStorage.getItem('wapos_pending_sales') || '[]');
            
            const totalPending = pendingSales.length + pendingOrders.length + pendingCustomers.length + localStorageSales.length;
            
            const countElement = document.getElementById('pending-count');
            if (countElement) {
                countElement.textContent = totalPending;
                countElement.style.display = totalPending > 0 ? 'inline' : 'none';
            }
            
            // Update sync button state
            const syncButton = document.getElementById('sync-button');
            if (syncButton) {
                if (totalPending > 0) {
                    syncButton.classList.remove('btn-outline-secondary');
                    syncButton.classList.add('btn-outline-warning');
                } else {
                    syncButton.classList.remove('btn-outline-warning');
                    syncButton.classList.add('btn-outline-secondary');
                }
            }
            
        } catch (error) {
            console.error('[OfflineManager] Failed to update pending count:', error);
        }
    }
    
    // UI Methods
    async showOfflineQueue() {
        // Get all pending transactions
        const pendingSales = await this.getCachedData('pending-sales');
        const pendingOrders = await this.getCachedData('pending-orders');
        const pendingCustomers = await this.getCachedData('pending-customers');
        
        // Also get localStorage backup
        const localStorageSales = JSON.parse(localStorage.getItem('wapos_pending_sales') || '[]');
        
        const totalPending = pendingSales.length + pendingOrders.length + pendingCustomers.length + localStorageSales.length;
        
        // Create modal
        let modal = document.getElementById('offlineQueueModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'offlineQueueModal';
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            document.body.appendChild(modal);
        }
        
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-cloud-arrow-up me-2"></i>Offline Queue
                            <span class="badge bg-dark ms-2">${totalPending} pending</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${totalPending === 0 ? `
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">All Synced!</h4>
                                <p class="text-muted">No pending transactions to sync.</p>
                            </div>
                        ` : `
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                These transactions will sync automatically when internet is restored.
                            </div>
                            
                            ${pendingSales.length > 0 || localStorageSales.length > 0 ? `
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="bi bi-cart me-2"></i>Pending Sales 
                                    <span class="badge bg-primary">${pendingSales.length + localStorageSales.length}</span>
                                </h6>
                                <div class="list-group mb-4">
                                    ${[...pendingSales, ...localStorageSales.map(s => ({data: s, timestamp: new Date(s.created_at).getTime()}))].map((sale, i) => `
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${sale.data.offline_id || 'Sale #' + (i + 1)}</strong>
                                                <br>
                                                <small class="text-muted">
                                                    ${sale.data.items?.length || 0} items • 
                                                    ${this.formatCurrency(sale.data.total || 0)} • 
                                                    ${this.formatTimeAgo(sale.timestamp || sale.data.timestamp)}
                                                </small>
                                            </div>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                            
                            ${pendingOrders.length > 0 ? `
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="bi bi-receipt me-2"></i>Pending Orders 
                                    <span class="badge bg-info">${pendingOrders.length}</span>
                                </h6>
                                <div class="list-group mb-4">
                                    ${pendingOrders.map((order, i) => `
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Order #${i + 1}</strong>
                                                <br>
                                                <small class="text-muted">${this.formatTimeAgo(order.timestamp)}</small>
                                            </div>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                            
                            ${pendingCustomers.length > 0 ? `
                                <h6 class="border-bottom pb-2 mb-3">
                                    <i class="bi bi-people me-2"></i>Pending Customers 
                                    <span class="badge bg-secondary">${pendingCustomers.length}</span>
                                </h6>
                                <div class="list-group">
                                    ${pendingCustomers.map((customer, i) => `
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${customer.data.name || 'Customer #' + (i + 1)}</strong>
                                                <br>
                                                <small class="text-muted">${this.formatTimeAgo(customer.timestamp)}</small>
                                            </div>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                        `}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        ${totalPending > 0 && navigator.onLine ? `
                            <button type="button" class="btn btn-primary" onclick="offlineManager.forceSyncAll()">
                                <i class="bi bi-arrow-repeat me-2"></i>Sync Now
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }
    
    formatTimeAgo(timestamp) {
        const now = Date.now();
        const diff = now - timestamp;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes} min ago`;
        if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }
    
    async forceSyncAll() {
        if (!navigator.onLine) {
            this.showToast('Cannot sync while offline', 'warning');
            return;
        }
        
        // Close modal
        const modal = document.getElementById('offlineQueueModal');
        if (modal) {
            bootstrap.Modal.getInstance(modal)?.hide();
        }
        
        this.showToast('Syncing...', 'info');
        await this.syncWhenOnline();
        
        // Also sync localStorage sales
        await this.syncLocalStorageSales();
    }
    
    async syncLocalStorageSales() {
        const pendingSales = JSON.parse(localStorage.getItem('wapos_pending_sales') || '[]');
        if (pendingSales.length === 0) return;
        
        const synced = [];
        const failed = [];
        
        for (const sale of pendingSales) {
            try {
                const response = await fetch('/api/complete-sale.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Sync-Request': 'true'
                    },
                    body: JSON.stringify(sale)
                });
                
                if (response.ok) {
                    synced.push(sale);
                } else {
                    failed.push(sale);
                }
            } catch (error) {
                failed.push(sale);
            }
        }
        
        localStorage.setItem('wapos_pending_sales', JSON.stringify(failed));
        this.updatePendingCount();
        
        if (synced.length > 0) {
            this.showSyncSuccess(synced.length, failed.length);
        }
    }
    
    showUpdateAvailable() {
        const updateBanner = document.createElement('div');
        updateBanner.className = 'alert alert-info position-fixed bottom-0 start-50 translate-middle-x mb-2';
        updateBanner.style.zIndex = '9999';
        updateBanner.innerHTML = `
            <i class="bi bi-download me-2"></i>
            App update available
            <button class="btn btn-sm btn-primary ms-2" onclick="offlineManager.updateApp()">
                Update Now
            </button>
        `;
        document.body.appendChild(updateBanner);
    }
    
    updateApp() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(registration => {
                if (registration && registration.waiting) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                    window.location.reload();
                }
            });
        }
    }
    
    showSyncSuccess(synced, failed) {
        this.showToast(`Sync complete: ${synced} items synced${failed > 0 ? `, ${failed} failed` : ''}`, 'success');
    }
    
    showSyncError(message) {
        this.showToast(`Sync failed: ${message}`, 'danger');
    }
    
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed top-0 end-0 mt-2 me-2`;
        toast.style.zIndex = '9999';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    handleServiceWorkerMessage(data) {
        switch (data.type) {
            case 'SYNC_COMPLETE':
                this.updatePendingCount();
                if (data.success) {
                    this.showSyncSuccess(0, 0);
                }
                break;
        }
    }
    
    // IndexedDB helper methods
    addToStore(store, data) {
        return new Promise((resolve, reject) => {
            const request = store.add(data);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }
    
    getAllFromStore(store) {
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }
    
    deleteFromStore(store, id) {
        return new Promise((resolve, reject) => {
            const request = store.delete(id);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }
    
    clearStore(store) {
        return new Promise((resolve, reject) => {
            const request = store.clear();
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }
}

// Initialize offline manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.offlineManager = new OfflineManager();
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OfflineManager;
}
