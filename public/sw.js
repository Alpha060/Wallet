const CACHE_NAME = 'aeropay-cache-v2';
const ASSETS = [
    '/',
    '/app.css?v=2',
    '/app.js?v=2',
    '/icons/aeropay-logo.png'
];

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS).catch(err => console.log('Error caching assets on install:', err));
        })
    );
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', (e) => {
    // Only intercept GET requests
    if (e.request.method !== 'GET') return;
    
    e.respondWith(
        fetch(e.request).then((response) => {
            // If the fetch succeeds, clone and store it in cache
            if (response && response.status === 200 && response.type === 'basic') {
                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(e.request, responseClone);
                });
            }
            return response;
        }).catch(() => {
            // If offline, check if we have it in cache
            return caches.match(e.request).then((cachedResponse) => {
                return cachedResponse || caches.match('/');
            });
        })
    );
});
