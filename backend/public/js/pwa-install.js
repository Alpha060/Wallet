// PWA Installation Handler
let deferredPrompt;
let installButton;

// Register service worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js')
      .then((registration) => {
        console.log('ServiceWorker registration successful:', registration.scope);
        
        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every minute
      })
      .catch((error) => {
        console.log('ServiceWorker registration failed:', error);
      });
  });
}

// Handle install prompt
window.addEventListener('beforeinstallprompt', (e) => {
  // Prevent the mini-infobar from appearing on mobile
  e.preventDefault();
  // Stash the event so it can be triggered later
  deferredPrompt = e;
  // Show install button
  showInstallPromotion();
});

function showInstallPromotion() {
  // Create install button if it doesn't exist
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
      bottom: 20px;
      right: 20px;
      padding: 12px 24px;
      background: #4F46E5;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
      z-index: 9999;
      transition: all 0.3s ease;
    `;
    
    installBtn.addEventListener('mouseenter', () => {
      installBtn.style.transform = 'translateY(-2px)';
      installBtn.style.boxShadow = '0 6px 16px rgba(79, 70, 229, 0.4)';
    });
    
    installBtn.addEventListener('mouseleave', () => {
      installBtn.style.transform = 'translateY(0)';
      installBtn.style.boxShadow = '0 4px 12px rgba(79, 70, 229, 0.3)';
    });
    
    installBtn.addEventListener('click', installApp);
    document.body.appendChild(installBtn);
    installButton = installBtn;
  }
}

async function installApp() {
  if (!deferredPrompt) {
    return;
  }
  
  // Show the install prompt
  deferredPrompt.prompt();
  
  // Wait for the user to respond to the prompt
  const { outcome } = await deferredPrompt.userChoice;
  console.log(`User response to the install prompt: ${outcome}`);
  
  // Clear the deferredPrompt
  deferredPrompt = null;
  
  // Hide the install button
  if (installButton) {
    installButton.style.display = 'none';
  }
}

// Detect if app is installed
window.addEventListener('appinstalled', () => {
  console.log('PWA was installed');
  deferredPrompt = null;
  if (installButton) {
    installButton.style.display = 'none';
  }
});

// Check if running as PWA
function isPWA() {
  return window.matchMedia('(display-mode: standalone)').matches || 
         window.navigator.standalone === true;
}

// Add PWA-specific styles when running as installed app
if (isPWA()) {
  document.documentElement.classList.add('pwa-mode');
}

// Handle online/offline status
window.addEventListener('online', () => {
  console.log('App is online');
  showNotification('You are back online!', 'success');
});

window.addEventListener('offline', () => {
  console.log('App is offline');
  showNotification('You are offline. Some features may be limited.', 'warning');
});

function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `pwa-notification pwa-notification-${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    background: ${type === 'success' ? '#10B981' : type === 'warning' ? '#F59E0B' : '#3B82F6'};
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    animation: slideIn 0.3s ease;
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from {
      transform: translateX(400px);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  
  @keyframes slideOut {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(400px);
      opacity: 0;
    }
  }
  
  .pwa-mode {
    /* Add any PWA-specific styles here */
  }
`;
document.head.appendChild(style);
