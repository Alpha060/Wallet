// Cache Buster - Forces refresh of cached resources
(function() {
  'use strict';
  
  // UPDATE THIS VERSION whenever you deploy changes!
  const VERSION = '2.0.0';
  
  // Store version
  const storedVersion = localStorage.getItem('app-version');
  
  // Check if version changed
  if (storedVersion && storedVersion !== VERSION) {
    console.log(`[Cache] Version changed: ${storedVersion} -> ${VERSION}`);
    
    // Clear all caches
    if ('caches' in window) {
      caches.keys().then((names) => {
        names.forEach(name => {
          console.log('[Cache] Deleting cache:', name);
          caches.delete(name);
        });
      });
    }
    
    // Clear localStorage cache markers
    localStorage.removeItem('sw-cache-version');
    
    // Update service worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then((registrations) => {
        registrations.forEach(registration => {
          registration.update();
          if (registration.waiting) {
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
          }
        });
      });
    }
  }
  
  // Save current version
  localStorage.setItem('app-version', VERSION);
  
  console.log(`[App] Version: ${VERSION}`);
})();
