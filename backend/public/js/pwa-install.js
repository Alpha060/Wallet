// PWA Installation Handler with Auto-Update
let deferredPrompt;
let installButton;
let newWorkerReady = false;

// Register service worker with update handling
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js')
      .then((registration) => {
        console.log('[PWA] ServiceWorker registered:', registration.scope);
        
        // Check for updates immediately
        registration.update();
        
        // Check for updates every 30 seconds
        setInterval(() => {
          registration.update();
        }, 30000);
        
        // Handle updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          console.log('[PWA] New service worker found, installing...');
          
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed') {
              if (navigator.serviceWorker.controller) {
                // New update available
                console.log('[PWA] New version available!');
                newWorkerReady = true;
                showUpdateNotification();
              } else {
                // First install
                console.log('[PWA] App ready for offline use');
              }
            }
          });
        });
      })
      .catch((error) => {
        console.log('[PWA] ServiceWorker registration failed:', error);
      });
    
    // Handle controller change (when new SW takes over)
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (newWorkerReady) {
        console.log('[PWA] New service worker activated, reloading...');
        window.location.reload();
      }
    });
  });
}

// Show update notification banner
function showUpdateNotification() {
  // Remove existing banner if any
  const existingBanner = document.getElementById('update-banner');
  if (existingBanner) existingBanner.remove();
  
  const banner = document.createElement('div');
  banner.id = 'update-banner';
  banner.innerHTML = `
    <div class="update-banner-content">
      <span>🔄 A new version is available!</span>
      <button id="update-btn">Update Now</button>
      <button id="dismiss-btn">Later</button>
    </div>
  `;
  banner.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #0066FF 0%, #0052CC 100%);
    color: white;
    padding: 12px 16px;
    z-index: 100000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideDown 0.3s ease;
  `;
  
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideDown {
      from { transform: translateY(-100%); }
      to { transform: translateY(0); }
    }
    .update-banner-content {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
      max-width: 600px;
      margin: 0 auto;
    }
    #update-btn {
      background: white;
      color: #0066FF;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s;
    }
    #update-btn:hover {
      transform: scale(1.05);
    }
    #dismiss-btn {
      background: transparent;
      color: white;
      border: 1px solid rgba(255,255,255,0.5);
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
    }
  `;
  document.head.appendChild(style);
  document.body.appendChild(banner);
  
  // Update button - activate new service worker
  document.getElementById('update-btn').addEventListener('click', () => {
    if (navigator.serviceWorker.controller) {
      // Tell the new service worker to skip waiting
      navigator.serviceWorker.ready.then((registration) => {
        if (registration.waiting) {
          registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
      });
    }
    banner.remove();
  });
  
  // Dismiss button
  document.getElementById('dismiss-btn').addEventListener('click', () => {
    banner.remove();
  });
}

// Force update function (can be called manually)
window.forceAppUpdate = function() {
  console.log('[PWA] Force update requested');
  
  // Clear all caches
  if ('caches' in window) {
    caches.keys().then((names) => {
      Promise.all(names.map(name => caches.delete(name))).then(() => {
        console.log('[PWA] All caches cleared');
        
        // Unregister and re-register service worker
        navigator.serviceWorker.getRegistrations().then((registrations) => {
          Promise.all(registrations.map(reg => reg.unregister())).then(() => {
            console.log('[PWA] Service workers unregistered');
            window.location.reload(true);
          });
        });
      });
    });
  } else {
    window.location.reload(true);
  }
};

// Handle install prompt
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  showInstallPromotion();
});

function showInstallPromotion() {
  if (!document.getElementById('pwa-install-btn')) {
    const installBtn = document.createElement('button');
    installBtn.id = 'pwa-install-btn';
    installBtn.className = 'pwa-install-button';
    installBtn.innerHTML = `
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      Install App
    `;
    installBtn.style.cssText = `
      position: fixed;
      bottom: 80px;
      right: 20px;
      padding: 12px 24px;
      background: #0066FF;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(0, 102, 255, 0.3);
      z-index: 9999;
      transition: all 0.3s ease;
    `;
    
    installBtn.addEventListener('mouseenter', () => {
      installBtn.style.transform = 'translateY(-2px)';
    });
    
    installBtn.addEventListener('mouseleave', () => {
      installBtn.style.transform = 'translateY(0)';
    });
    
    installBtn.addEventListener('click', installApp);
    document.body.appendChild(installBtn);
    installButton = installBtn;
  }
}

async function installApp() {
  if (!deferredPrompt) return;
  
  deferredPrompt.prompt();
  const { outcome } = await deferredPrompt.userChoice;
  console.log(`[PWA] Install prompt result: ${outcome}`);
  
  deferredPrompt = null;
  if (installButton) installButton.style.display = 'none';
}

// Detect if app is installed
window.addEventListener('appinstalled', () => {
  console.log('[PWA] App was installed');
  deferredPrompt = null;
  if (installButton) installButton.style.display = 'none';
});

// Check if running as PWA
function isPWA() {
  return window.matchMedia('(display-mode: standalone)').matches || 
         window.navigator.standalone === true;
}

if (isPWA()) {
  document.documentElement.classList.add('pwa-mode');
}

// Handle online/offline status
window.addEventListener('online', () => {
  console.log('[PWA] Online');
  showPWANotification('You are back online!', 'success');
});

window.addEventListener('offline', () => {
  console.log('[PWA] Offline');
  showPWANotification('You are offline. Some features may be limited.', 'warning');
});

function showPWANotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `pwa-notification pwa-notification-${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 60px;
    right: 20px;
    padding: 12px 20px;
    background: ${type === 'success' ? '#10B981' : type === 'warning' ? '#F59E0B' : '#3B82F6'};
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    font-size: 14px;
    animation: slideIn 0.3s ease;
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(100px)';
    notification.style.transition = 'all 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add slide-in animation
const pwaStyle = document.createElement('style');
pwaStyle.textContent = `
  @keyframes slideIn {
    from { transform: translateX(100px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
`;
document.head.appendChild(pwaStyle);
