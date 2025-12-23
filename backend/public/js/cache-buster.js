// Cache Buster - Forces refresh of cached resources
(function() {
  'use strict';
  
  const VERSION = '1.0.6'; // Update this when you want to force cache refresh
  
  // Add version parameter to dynamic imports and script loads
  function addVersionToUrl(url) {
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}v=${VERSION}`;
  }
  
  // Override fetch to add version to certain requests
  const originalFetch = window.fetch;
  window.fetch = function(resource, options = {}) {
    if (typeof resource === 'string') {
      // Add version to JS, CSS, and HTML files
      if (resource.match(/\.(js|css|html)$/)) {
        resource = addVersionToUrl(resource);
      }
    }
    return originalFetch.call(this, resource, options);
  };
  
  // Force service worker update on version change
  if ('serviceWorker' in navigator) {
    const storedVersion = localStorage.getItem('app-version');
    if (storedVersion && storedVersion !== VERSION) {
      console.log('New version detected, updating service worker...');
      navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
          registration.update();
        }
      });
      // Clear caches
      if ('caches' in window) {
        caches.keys().then(function(names) {
          for (let name of names) {
            caches.delete(name);
          }
        });
      }
    }
    localStorage.setItem('app-version', VERSION);
  }
  
  console.log(`App version: ${VERSION}`);
})();