/**
 * WAPOS Enhanced Service Worker
 * Offline-First PWA with IndexedDB Outbox
 * Version: 2.0
 */

const CACHE_VERSION = 'wapos-v2.0';
const CACHE_STATIC = `${CACHE_VERSION}-static`;
const CACHE_DYNAMIC = `${CACHE_VERSION}-dynamic`;
const CACHE_API = `${CACHE_VERSION}-api`;

const STATIC_ASSETS = [
    '/wapos/',
    '/wapos/offline.html',
    '/wapos/manifest.json',
    '/wapos/css/style.css',
    '/wapos/assets/logo.png'
];

const DB_NAME = 'wapos_offline';
const DB_VERSION = 2;
const STORE_OUTBOX = 'outbox';
const STORE_PRODUCTS = 'products';
const STORE_CUSTOMERS = 'customers';
const STORE_SETTINGS = 'settings';
const STORE_RECEIPTS = 'receipts';

// IndexedDB initialization
let db;

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            // Outbox store
            if (!db.objectStoreNames.contains(STORE_OUTBOX)) {
                const outboxStore = db.createObjectStore(STORE_OUTBOX, { 
                    keyPath: 'external_id' 
                });
                outboxStore.createIndex('created_at', 'created_at', { unique: false });
                outboxStore.createIndex('status', 'status', { unique: false });
            }
            
            // Products store
            if (!db.objectStoreNames.contains(STORE_PRODUCTS)) {
                const productsStore = db.createObjectStore(STORE_PRODUCTS, { 
                    keyPath: 'id' 
                });
                productsStore.createIndex('barcode', 'barcode', { unique: false });
                productsStore.createIndex('sku', 'sku', { unique: false });
            }
            
            // Customers store
            if (!db.objectStoreNames.contains(STORE_CUSTOMERS)) {
                db.createObjectStore(STORE_CUSTOMERS, { keyPath: 'id' });
            }
            
            // Settings store
            if (!db.objectStoreNames.contains(STORE_SETTINGS)) {
                db.createObjectStore(STORE_SETTINGS, { keyPath: 'key' });
            }
            
            // Receipts store
            if (!db.objectStoreNames.contains(STORE_RECEIPTS)) {
                const receiptsStore = db.createObjectStore(STORE_RECEIPTS, { 
                    keyPath: 'sale_number' 
                });
                receiptsStore.createIndex('created_at', 'created_at', { unique: false });
            }
        };
    });
}

// Install event
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    
    event.waitUntil(
        Promise.all([
            caches.open(CACHE_STATIC).then(cache => {
                return cache.addAll(STATIC_ASSETS);
            }),
            openDB()
        ]).then(() => {
            console.log('[SW] Installed successfully');
            return self.skipWaiting();
        })
    );
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    
    event.waitUntil(
        Promise.all([
            // Clean old caches
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('wapos-') && name !== CACHE_VERSION)
                        .map(name => caches.delete(name))
                );
            }),
            // Claim clients
            self.clients.claim()
        ]).then(() => {
            console.log('[SW] Activated successfully');
        })
    );
});

// Fetch event with strategy routing
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // API requests: NetworkFirst
    if (url.pathname.startsWith('/wapos/api/')) {
        event.respondWith(networkFirst(request));
    }
    // Static assets: StaleWhileRevalidate
    else if (isStaticAsset(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request));
    }
    // HTML pages: NetworkFirst with offline fallback
    else if (request.headers.get('accept').includes('text/html')) {
        event.respondWith(networkFirstWithOffline(request));
    }
    // Default: Network only
    else {
        event.respondWith(fetch(request));
    }
});

// Network First strategy (for API calls)
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        
        // Cache successful API responses
        if (response.ok) {
            const cache = await caches.open(CACHE_API);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        // Try cache
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        
        // If POST/PUT/DELETE, queue in outbox
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(request.method)) {
            await queueRequest(request);
            
            return new Response(JSON.stringify({
                success: true,
                queued: true,
                message: 'Request queued for sync'
            }), {
                status: 202,
                headers: { 'Content-Type': 'application/json' }
            });
        }
        
        throw error;
    }
}

// Stale While Revalidate (for static assets)
async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_STATIC);
    const cached = await cache.match(request);
    
    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => cached);
    
    return cached || fetchPromise;
}

// Network First with offline fallback
async function networkFirstWithOffline(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        
        // Return offline page
        return caches.match('/wapos/offline.html');
    }
}

// Queue request in IndexedDB outbox
async function queueRequest(request) {
    if (!db) await openDB();
    
    const body = await request.clone().text();
    let payload;
    
    try {
        payload = JSON.parse(body);
    } catch {
        payload = { body };
    }
    
    const external_id = payload.external_id || generateUUID();
    
    const outboxItem = {
        external_id,
        url: request.url,
        method: request.method,
        headers: Object.fromEntries(request.headers.entries()),
        payload,
        status: 'queued',
        attempts: 0,
        created_at: new Date().toISOString(),
        device_id: await getDeviceId(),
        last_error: null
    };
    
    const tx = db.transaction([STORE_OUTBOX], 'readwrite');
    const store = tx.objectStore(STORE_OUTBOX);
    await store.put(outboxItem);
    
    console.log('[SW] Request queued:', external_id);
    
    // Try to sync immediately
    if (self.registration.sync) {
        await self.registration.sync.register('sync-outbox');
    } else {
        // Fallback: notify clients to flush
        notifyClients({ type: 'OUTBOX_UPDATED' });
    }
}

// Background Sync event
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-outbox') {
        event.waitUntil(flushOutbox());
    }
});

// Flush outbox (send queued requests)
async function flushOutbox() {
    if (!db) await openDB();
    
    const tx = db.transaction([STORE_OUTBOX], 'readonly');
    const store = tx.objectStore(STORE_OUTBOX);
    const index = store.index('status');
    const queued = await index.getAll('queued');
    
    console.log(`[SW] Flushing ${queued.length} queued requests`);
    
    for (const item of queued) {
        try {
            await sendQueuedRequest(item);
        } catch (error) {
            console.error('[SW] Failed to send queued request:', error);
        }
    }
    
    notifyClients({ type: 'OUTBOX_FLUSHED' });
}

// Send queued request
async function sendQueuedRequest(item) {
    const { external_id, url, method, headers, payload } = item;
    
    try {
        const response = await fetch(url, {
            method,
            headers: {
                ...headers,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        if (response.ok) {
            // Mark as sent
            await updateOutboxItem(external_id, {
                status: 'sent',
                sent_at: new Date().toISOString()
            });
            
            console.log('[SW] Request sent successfully:', external_id);
        } else {
            throw new Error(`HTTP ${response.status}`);
        }
    } catch (error) {
        // Update attempts and error
        await updateOutboxItem(external_id, {
            attempts: item.attempts + 1,
            last_error: error.message,
            status: item.attempts >= 3 ? 'failed' : 'queued'
        });
        
        throw error;
    }
}

// Update outbox item
async function updateOutboxItem(external_id, updates) {
    if (!db) await openDB();
    
    const tx = db.transaction([STORE_OUTBOX], 'readwrite');
    const store = tx.objectStore(STORE_OUTBOX);
    const item = await store.get(external_id);
    
    if (item) {
        Object.assign(item, updates);
        await store.put(item);
    }
}

// Message handler (for manual flush, etc.)
self.addEventListener('message', (event) => {
    if (event.data.type === 'FLUSH_OUTBOX') {
        event.waitUntil(flushOutbox());
    } else if (event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(names => {
                return Promise.all(names.map(name => caches.delete(name)));
            })
        );
    } else if (event.data.type === 'GET_OUTBOX_COUNT') {
        event.waitUntil(
            getOutboxCount().then(count => {
                event.ports[0].postMessage({ count });
            })
        );
    }
});

// Get outbox count
async function getOutboxCount() {
    if (!db) await openDB();
    
    const tx = db.transaction([STORE_OUTBOX], 'readonly');
    const store = tx.objectStore(STORE_OUTBOX);
    const index = store.index('status');
    const queued = await index.count('queued');
    
    return queued;
}

// Notify all clients
async function notifyClients(message) {
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach(client => client.postMessage(message));
}

// Utility: Check if static asset
function isStaticAsset(pathname) {
    return /\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$/.test(pathname);
}

// Utility: Generate UUID v4
function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

// Utility: Get or create device ID
async function getDeviceId() {
    if (!db) await openDB();
    
    const tx = db.transaction([STORE_SETTINGS], 'readwrite');
    const store = tx.objectStore(STORE_SETTINGS);
    let setting = await store.get('device_id');
    
    if (!setting) {
        const deviceId = 'device-' + generateUUID();
        await store.put({ key: 'device_id', value: deviceId });
        return deviceId;
    }
    
    return setting.value;
}

console.log('[SW] Service Worker loaded');
