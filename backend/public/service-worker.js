const CACHE_NAME = 'kyc-app-v6'; // Updated version to force cache refresh
const urlsToCache = [
  '/',
  '/index.html',
  '/pages/login.html',
  '/pages/user-dashboard.html',
  '/pages/admin-dashboard.html',
  '/pages/forgot-password.html',
  '/css/styles.css',
  '/css/reset.css',
  '/css/variables.css',
  '/css/typography.css',
  '/css/layout.css',
  '/css/components.css',
  '/css/utilities.css',
  '/css/animations.css',
  '/css/mobile.css',
  '/css/forgot-password.css',
  '/js/pwa-install.js',
  '/js/config.js',
  '/favicon.ico'
];

// URLs that should NEVER be cached (always fetch from network)
const noCachePatterns = [
  '/api/',
  '/uploads/',
  '/js/pages/', // Don't cache JS pages - they change frequently
  '/js/services/', // Don't cache API services
  '/js/utils/' // Don't cache utilities
];

// URLs that should use network-first strategy (check network first, fallback to cache)
const networkFirstPatterns = [
  '/pages/',
  '/js/',
  '/css/'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch((error) => {
        console.error('Cache installation failed:', error);
      })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Check if URL should bypass cache (API calls, uploads, etc.)
function shouldBypassCache(url) {
  return noCachePatterns.some(pattern => url.includes(pattern));
}

// Check if URL should use network-first strategy
function shouldUseNetworkFirst(url) {
  return networkFirstPatterns.some(pattern => url.includes(pattern));
}

// Fetch event - smart caching strategy
self.addEventListener('fetch', (event) => {
  const requestUrl = event.request.url;
  
  // For API calls and uploads - ALWAYS go to network, never cache
  if (shouldBypassCache(requestUrl)) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // Return error response for failed API calls
          return new Response(JSON.stringify({ error: 'Network unavailable' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
          });
        })
    );
    return;
  }
  
  // For pages, JS, CSS - network-first strategy (always try network first)
  if (shouldUseNetworkFirst(requestUrl)) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // If network request succeeds, cache it and return
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });
          }
          return response;
        })
        .catch(() => {
          // Network failed, try cache
          return caches.match(event.request)
            .then((cachedResponse) => {
              if (cachedResponse) {
                console.log('Serving from cache (network failed):', requestUrl);
                return cachedResponse;
              }
              // No cache either, return offline page
              return caches.match('/index.html');
            });
        })
    );
    return;
  }
  
  // For other assets - cache-first strategy
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Cache hit - return response
        if (response) {
          return response;
        }

        return fetch(event.request).then(
          (response) => {
            // Check if valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Clone the response
            const responseToCache = response.clone();

            // Cache the fetched response for future use
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });

            return response;
          }
        );
      })
      .catch(() => {
        // Return offline page if available
        return caches.match('/index.html');
      })
  );
});

// Background sync for offline form submissions
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-kyc-data') {
    event.waitUntil(syncKYCData());
  }
});

async function syncKYCData() {
  // Implement background sync logic here
  console.log('Syncing KYC data in background');
}

// Push notification support
self.addEventListener('push', (event) => {
  const options = {
    body: event.data ? event.data.text() : 'New notification',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    }
  };

  event.waitUntil(
    self.registration.showNotification('KYC App', options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow('/')
  );
});
