/**
 * WAPOS Service Worker - Enhanced Offline-First POS System
 * Handles comprehensive offline functionality and intelligent caching
 */

const CACHE_VERSION = '2.1';
const CACHE_NAME = `wapos-v${CACHE_VERSION}`;
const DATA_CACHE_NAME = `wapos-data-v${CACHE_VERSION}`;
const OFFLINE_URL = '/wapos/offline.html';

// Core application files to cache (App Shell) - ONLY STATIC FILES
const APP_SHELL_FILES = [
    '/wapos/offline.html',
    '/wapos/assets/images/logo.png'
];

// External resources to cache
const EXTERNAL_RESOURCES = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
];

// API endpoints that should be cached for offline use
const CACHEABLE_API_ENDPOINTS = [
    '/wapos/api/get-products.php',
    '/wapos/api/get-customers.php',
    '/wapos/api/get-categories.php',
    '/wapos/api/get-settings.php'
];

// Network-first endpoints (always try network first)
const NETWORK_FIRST_ENDPOINTS = [
    '/wapos/api/complete-sale.php',
    '/wapos/api/create-restaurant-order.php',
    '/wapos/api/update-order-item-status.php',
    '/wapos/api/complete-order.php'
];

// Install event - cache essential files
self.addEventListener('install', (event) => {
    console.log('[ServiceWorker] Install v' + CACHE_VERSION);
    event.waitUntil(
        Promise.all([
            // Cache app shell
            caches.open(CACHE_NAME).then((cache) => {
                console.log('[ServiceWorker] Caching app shell');
                return cache.addAll([...APP_SHELL_FILES, ...EXTERNAL_RESOURCES]);
            }),
            // Initialize IndexedDB for offline data
            initializeOfflineDatabase()
        ])
    );
    self.skipWaiting();
});

// Activate event - clean up old caches and claim clients
self.addEventListener('activate', (event) => {
    console.log('[ServiceWorker] Activate v' + CACHE_VERSION);
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then((keyList) => {
                return Promise.all(keyList.map((key) => {
                    if (key !== CACHE_NAME && key !== DATA_CACHE_NAME) {
                        console.log('[ServiceWorker] Removing old cache', key);
                        return caches.delete(key);
                    }
                }));
            }),
            // Claim all clients
            self.clients.claim()
        ])
    );
});

// Enhanced fetch event with intelligent caching strategies
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests for caching (except for offline queue)
    if (request.method !== 'GET') {
        // Handle POST requests for offline functionality
        if (isNetworkFirstEndpoint(url.pathname)) {
            event.respondWith(handleOfflinePost(request));
        }
        return;
    }
    
    // Only handle same-origin requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // NEVER cache PHP files - always go to network first
    if (url.pathname.endsWith('.php')) {
        event.respondWith(networkFirst(request));
        return;
    }
    
    // Determine caching strategy based on request type
    if (isAppShellRequest(url.pathname)) {
        // App Shell: Cache First (only static files)
        event.respondWith(cacheFirst(request));
    } else if (isCacheableApiEndpoint(url.pathname)) {
        // API Data: Network First with cache fallback
        event.respondWith(networkFirstWithCache(request));
    } else if (isNetworkFirstEndpoint(url.pathname)) {
        // Critical APIs: Network First
        event.respondWith(networkFirst(request));
    } else if (isStaticAsset(url.pathname)) {
        // Static assets: Cache First
        event.respondWith(cacheFirst(request));
    } else {
        // Default: Network First (safer for dynamic content)
        event.respondWith(networkFirst(request));
    }
});

// Caching Strategies

// Cache First - for app shell and static assets
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('[ServiceWorker] Cache first failed:', error);
        return caches.match(OFFLINE_URL);
    }
}

// Network First - for dynamic data with cache fallback
async function networkFirstWithCache(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.status === 200) {
            const cache = await caches.open(DATA_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[ServiceWorker] Network failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Add offline indicator header
            const response = cachedResponse.clone();
            response.headers.set('X-Served-From', 'cache');
            return response;
        }
        throw error;
    }
}

// Network First - for critical operations
async function networkFirst(request) {
    try {
        return await fetch(request);
    } catch (error) {
        console.error('[ServiceWorker] Network first failed:', error);
        // For critical operations, we might want to queue them
        if (request.method === 'POST') {
            await queueOfflineRequest(request);
            return new Response(JSON.stringify({
                success: false,
                offline: true,
                message: 'Request queued for when online'
            }), {
                status: 202,
                headers: { 'Content-Type': 'application/json' }
            });
        }
        throw error;
    }
}

// Handle offline POST requests
async function handleOfflinePost(request) {
    try {
        return await fetch(request);
    } catch (error) {
        console.log('[ServiceWorker] POST request failed, queuing for sync');
        await queueOfflineRequest(request);
        
        return new Response(JSON.stringify({
            success: true,
            offline: true,
            message: 'Transaction saved offline. Will sync when online.',
            queued: true
        }), {
            status: 202,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Helper functions
function isAppShellRequest(pathname) {
    return APP_SHELL_FILES.some(file => pathname.endsWith(file.replace('/wapos', '')));
}

function isCacheableApiEndpoint(pathname) {
    return CACHEABLE_API_ENDPOINTS.some(endpoint => pathname.includes(endpoint.replace('/wapos', '')));
}

function isNetworkFirstEndpoint(pathname) {
    return NETWORK_FIRST_ENDPOINTS.some(endpoint => pathname.includes(endpoint.replace('/wapos', '')));
}

function isStaticAsset(pathname) {
    const staticExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.eot', '.ico'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

// Background sync events
self.addEventListener('sync', (event) => {
    console.log('[ServiceWorker] Background sync triggered:', event.tag);
    
    switch (event.tag) {
        case 'sync-sales':
            event.waitUntil(syncPendingTransactions('pending-sales'));
            break;
        case 'sync-orders':
            event.waitUntil(syncPendingTransactions('pending-orders'));
            break;
        case 'sync-customers':
            event.waitUntil(syncPendingTransactions('pending-customers'));
            break;
        case 'sync-all':
            event.waitUntil(syncAllPendingData());
            break;
    }
});

// Comprehensive sync function
async function syncAllPendingData() {
    console.log('[ServiceWorker] Starting comprehensive sync');
    
    try {
        await Promise.all([
            syncPendingTransactions('pending-sales'),
            syncPendingTransactions('pending-orders'),
            syncPendingTransactions('pending-customers'),
            syncPendingTransactions('pending-inventory')
        ]);
        
        // Notify all clients about successful sync
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'SYNC_COMPLETE',
                success: true,
                timestamp: Date.now()
            });
        });
        
    } catch (error) {
        console.error('[ServiceWorker] Comprehensive sync failed:', error);
    }
}

// Generic sync function for different data types
async function syncPendingTransactions(storeName) {
    try {
        const db = await openOfflineDatabase();
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        const pendingItems = await getAllFromStore(store);
        
        console.log(`[ServiceWorker] Syncing ${pendingItems.length} items from ${storeName}`);
        
        let syncedCount = 0;
        let failedCount = 0;
        
        for (const item of pendingItems) {
            try {
                const endpoint = getEndpointForStore(storeName);
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Sync-Request': 'true'
                    },
                    body: JSON.stringify(item.data)
                });
                
                if (response.ok) {
                    await deleteFromStore(store, item.id);
                    syncedCount++;
                    console.log(`[ServiceWorker] Synced ${storeName} item:`, item.id);
                } else {
                    console.error(`[ServiceWorker] Failed to sync ${storeName} item:`, item.id, response.status);
                    failedCount++;
                }
            } catch (error) {
                console.error(`[ServiceWorker] Error syncing ${storeName} item:`, item.id, error);
                failedCount++;
            }
        }
        
        console.log(`[ServiceWorker] ${storeName} sync complete: ${syncedCount} synced, ${failedCount} failed`);
        
    } catch (error) {
        console.error(`[ServiceWorker] ${storeName} sync failed:`, error);
    }
}

// Queue offline requests
async function queueOfflineRequest(request) {
    try {
        const requestData = {
            url: request.url,
            method: request.method,
            headers: Object.fromEntries(request.headers.entries()),
            body: await request.text(),
            timestamp: Date.now()
        };
        
        const db = await openOfflineDatabase();
        const storeName = getStoreNameFromUrl(request.url);
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        
        await addToStore(store, {
            data: JSON.parse(requestData.body),
            metadata: {
                url: requestData.url,
                timestamp: requestData.timestamp,
                retryCount: 0
            }
        });
        
        console.log('[ServiceWorker] Queued offline request:', request.url);
        
        // Register for background sync
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            const registration = await self.registration;
            await registration.sync.register('sync-' + storeName.replace('pending-', ''));
        }
        
    } catch (error) {
        console.error('[ServiceWorker] Failed to queue offline request:', error);
    }
}

// Enhanced IndexedDB operations
async function initializeOfflineDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('wapos-offline', 3);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            console.log('[ServiceWorker] Offline database initialized');
            resolve(request.result);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            console.log('[ServiceWorker] Upgrading offline database');
            
            // Create object stores for different data types
            const stores = [
                'pending-sales',
                'pending-orders', 
                'pending-customers',
                'pending-inventory',
                'products',
                'customers',
                'categories',
                'settings',
                'sync-log'
            ];
            
            stores.forEach(storeName => {
                if (!db.objectStoreNames.contains(storeName)) {
                    const store = db.createObjectStore(storeName, { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    
                    // Add indexes for better querying
                    if (storeName.startsWith('pending-')) {
                        store.createIndex('timestamp', 'metadata.timestamp');
                        store.createIndex('retryCount', 'metadata.retryCount');
                    }
                    
                    console.log(`[ServiceWorker] Created store: ${storeName}`);
                }
            });
        };
    });
}

// Helper functions for IndexedDB operations
async function openOfflineDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('wapos-offline', 3);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

function getAllFromStore(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

function addToStore(store, data) {
    return new Promise((resolve, reject) => {
        const request = store.add(data);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

function deleteFromStore(store, id) {
    return new Promise((resolve, reject) => {
        const request = store.delete(id);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

// Utility functions
function getStoreNameFromUrl(url) {
    if (url.includes('complete-sale.php')) return 'pending-sales';
    if (url.includes('create-restaurant-order.php')) return 'pending-orders';
    if (url.includes('customers')) return 'pending-customers';
    return 'pending-sales'; // default
}

function getEndpointForStore(storeName) {
    const endpoints = {
        'pending-sales': '/wapos/api/complete-sale.php',
        'pending-orders': '/wapos/api/create-restaurant-order.php',
        'pending-customers': '/wapos/api/save-customer.php',
        'pending-inventory': '/wapos/api/update-inventory.php'
    };
    return endpoints[storeName] || '/wapos/api/sync-data.php';
}

// Message handling for communication with main thread
self.addEventListener('message', (event) => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
        case 'FORCE_SYNC':
            syncAllPendingData();
            break;
        case 'CLEAR_CACHE':
            clearAllCaches();
            break;
    }
});

// Clear all caches (for debugging/maintenance)
async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map(name => caches.delete(name)));
    console.log('[ServiceWorker] All caches cleared');
}
