/**
 * WAPOS Service Worker
 * Handles offline functionality and caching
 */

const CACHE_NAME = 'wapos-v1';
const OFFLINE_URL = '/wapos/offline.html';

// Files to cache for offline use
const FILES_TO_CACHE = [
    '/wapos/',
    '/wapos/index.php',
    '/wapos/pos.php',
    '/wapos/restaurant.php',
    '/wapos/products.php',
    '/wapos/offline.html',
    '/wapos/assets/images/logo.png',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
];

// Install event - cache files
self.addEventListener('install', (event) => {
    console.log('[ServiceWorker] Install');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[ServiceWorker] Pre-caching offline page');
            return cache.addAll(FILES_TO_CACHE);
        })
    );
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[ServiceWorker] Activate');
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    console.log('[ServiceWorker] Removing old cache', key);
                    return caches.delete(key);
                }
            }));
        })
    );
    self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip cross-origin requests
    if (event.request.url.startsWith(self.location.origin)) {
        event.respondWith(
            caches.match(event.request).then((response) => {
                if (response) {
                    return response;
                }
                
                return fetch(event.request).then((response) => {
                    // Don't cache POST requests or non-OK responses
                    if (event.request.method !== 'GET' || !response || response.status !== 200) {
                        return response;
                    }
                    
                    // Clone the response
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                    
                    return response;
                }).catch(() => {
                    // If both cache and network fail, show offline page
                    return caches.match(OFFLINE_URL);
                });
            })
        );
    }
});

// Background sync for pending sales
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-sales') {
        event.waitUntil(syncPendingSales());
    }
});

// Sync pending sales when back online
async function syncPendingSales() {
    try {
        // Open IndexedDB
        const db = await openDatabase();
        const tx = db.transaction('pending-sales', 'readwrite');
        const store = tx.objectStore('pending-sales');
        const sales = await store.getAll();
        
        // Sync each sale
        for (const sale of sales) {
            try {
                const response = await fetch('/wapos/api/complete-sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(sale.data)
                });
                
                if (response.ok) {
                    // Remove from pending queue
                    await store.delete(sale.id);
                    console.log('[ServiceWorker] Synced sale:', sale.id);
                }
            } catch (error) {
                console.error('[ServiceWorker] Failed to sync sale:', sale.id, error);
            }
        }
        
        await tx.complete;
    } catch (error) {
        console.error('[ServiceWorker] Sync failed:', error);
    }
}

// Helper to open IndexedDB
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('wapos-offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('pending-sales')) {
                db.createObjectStore('pending-sales', { keyPath: 'id', autoIncrement: true });
            }
            
            if (!db.objectStoreNames.contains('products')) {
                db.createObjectStore('products', { keyPath: 'id' });
            }
        };
    });
}
