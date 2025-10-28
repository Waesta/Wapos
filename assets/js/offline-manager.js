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
        
        this.init();
    }
    
    async init() {
        // Initialize IndexedDB
        await this.openDatabase();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Initial sync if online
        if (this.isOnline) {
            this.syncWhenOnline();
        }
        
        console.log('[OfflineManager] Initialized');
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
        // Online/offline events
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateOnlineStatus();
            this.syncWhenOnline();
        });
        
        window.addEventListener('offline', () => {
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
                const registration = await navigator.serviceWorker.register('/wapos/service-worker.js');
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
            statusElement.innerHTML = this.isOnline 
                ? '<i class="bi bi-wifi text-success"></i> Online'
                : '<i class="bi bi-wifi-off text-danger"></i> Offline';
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
                'sales': '/wapos/api/complete-sale.php',
                'orders': '/wapos/api/create-restaurant-order.php',
                'customers': '/wapos/api/save-customer.php'
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
            
            const totalPending = pendingSales.length + pendingOrders.length + pendingCustomers.length;
            
            const countElement = document.getElementById('pending-count');
            if (countElement) {
                countElement.textContent = totalPending;
                countElement.style.display = totalPending > 0 ? 'inline' : 'none';
            }
            
        } catch (error) {
            console.error('[OfflineManager] Failed to update pending count:', error);
        }
    }
    
    // UI Methods
    showOfflineQueue() {
        // This would show a modal with pending transactions
        alert('Offline queue viewer - to be implemented');
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
