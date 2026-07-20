/* app.js - Premium client logic engine for AeroPay and AdminPay */



// Global Toast Notifications
const Toast = {
    container: null,

    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    show(message, type = 'success') {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icon = document.createElement('span');
        icon.innerHTML = type === 'success' ? '✓' : '✕';
        icon.style.fontWeight = 'bold';

        const text = document.createElement('span');
        text.innerText = message;

        toast.appendChild(icon);
        toast.appendChild(text);
        this.container.appendChild(toast);

        // Remove toast after 4 seconds
        setTimeout(() => {
            toast.style.animation = 'toast-slide-in 0.3s ease reverse forwards';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }
};

// CSRF Token Provider
let cachedCsrfToken = null;
async function fetchCsrfToken(force = false) {
    if (cachedCsrfToken && !force) {
        return cachedCsrfToken;
    }
    try {
        const res = await fetch('/api/auth/csrf-token', {
            method: 'GET',
            headers: { 'Cache-Control': 'no-cache' }
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        cachedCsrfToken = data.csrfToken;
        return cachedCsrfToken;
    } catch (e) {
        console.error('Failed to fetch CSRF token');
        return '';
    }
}

// Unified API Request Wrapper
async function apiRequest(path, options = {}) {
    const method = options.method || 'GET';
    const isMutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
    const headers = options.headers || {};

    if (isMutating) {
        const csrfToken = await fetchCsrfToken();
        if (csrfToken) {
            headers['x-csrf-token'] = csrfToken;
        }
    }

    let body = options.body;
    if (body && !options.isMultipart) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    }

    let response = await fetch(path, {
        method,
        headers,
        body
    });

    // Handle CSRF expiration retry
    if (response.status === 403 && isMutating) {
        const cloned = response.clone();
        const errorData = await cloned.json().catch(() => ({}));
        if (errorData?.error?.code === 'CSRF_TOKEN_INVALID' || errorData?.error?.code === 'CSRF_TOKEN_MISSING') {
            const newToken = await fetchCsrfToken(true);
            headers['x-csrf-token'] = newToken;
            response = await fetch(path, {
                method,
                headers,
                body
            });
        }
    }

    if (!response.ok) {
        let errorData;
        try {
            errorData = await response.json();
        } catch (e) {
            errorData = { error: { message: `Request failed with status ${response.status}` } };
        }

        // Auto-logout on authorization failures
        if (response.status === 401 && window.location.pathname !== '/login') {
            window.location.href = '/login';
            return;
        }

        throw new Error(errorData?.error?.message || 'Request failed');
    }

    return await response.json();
}

// Swipe Slider Widget
const SwipeSlider = {
    init(sliderId, onSwipeComplete) {
        const container = document.getElementById(sliderId);
        if (!container) return;

        const handle = container.querySelector('.swipe-handle');
        const bg = container.querySelector('.swipe-bg');
        let isDragging = false;
        let startX = 0;
        let maxSlide = container.clientWidth - handle.clientWidth - 8;

        const setPosition = (x) => {
            const currentX = Math.max(0, Math.min(x, maxSlide));
            handle.style.left = `${currentX + 4}px`;
            bg.style.width = `${currentX + 24}px`;
            return currentX;
        };

        const onStart = (e) => {
            isDragging = true;
            startX = (e.type === 'touchstart' ? e.touches[0].clientX : e.clientX) - handle.offsetLeft;
            handle.style.transition = 'none';
            bg.style.transition = 'none';
        };

        const onMove = (e) => {
            if (!isDragging) return;
            const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            setPosition(clientX - startX);
        };

        const onEnd = () => {
            if (!isDragging) return;
            isDragging = false;
            handle.style.transition = 'left 0.2s ease';
            bg.style.transition = 'width 0.2s ease';

            const currentX = handle.offsetLeft - 4;
            maxSlide = container.clientWidth - handle.clientWidth - 8;
            
            if (currentX >= maxSlide * 0.9) {
                // Completed swipe
                setPosition(maxSlide);
                if (onSwipeComplete) onSwipeComplete();
            } else {
                // Reset slider
                setPosition(0);
            }
        };

        handle.addEventListener('mousedown', onStart);
        handle.addEventListener('touchstart', onStart);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('touchmove', onMove);
        window.addEventListener('mouseup', onEnd);
        window.addEventListener('touchend', onEnd);

        // Reset helper
        this.reset = () => {
            handle.style.transition = 'left 0.2s ease';
            bg.style.transition = 'width 0.2s ease';
            setPosition(0);
        };
    }
};

// Image Magnifier Widget
const ImageMagnifier = {
    init(imageId, magnifierGlassId, scale = 2) {
        const img = document.getElementById(imageId);
        const glass = document.getElementById(magnifierGlassId);
        if (!img || !glass) return;

        const moveMagnifier = (e) => {
            e.preventDefault();
            const pos = getCursorPos(e);
            let x = pos.x;
            let y = pos.y;

            // Boundaries check
            const w = glass.offsetWidth / 2;
            const h = glass.offsetHeight / 2;
            if (x > img.width - w) x = img.width - w;
            if (x < w) x = w;
            if (y > img.height - h) y = img.height - h;
            if (y < h) y = h;

            glass.style.left = `${x - w}px`;
            glass.style.top = `${y - h}px`;

            // Background positioning
            glass.style.backgroundImage = `url('${img.src}')`;
            glass.style.backgroundRepeat = 'no-repeat';
            glass.style.backgroundSize = `${img.width * scale}px ${img.height * scale}px`;
            glass.style.backgroundPosition = `-${(x * scale) - w}px -${(y * scale) - h}px`;
        };

        const getCursorPos = (e) => {
            const rect = img.getBoundingClientRect();
            let x = (e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX) - rect.left;
            let y = (e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY) - rect.top;
            x = x - window.pageXOffset;
            y = y - window.pageYOffset;
            return { x, y };
        };

        const showGlass = () => { glass.style.display = 'block'; };
        const hideGlass = () => { glass.style.display = 'none'; };

        img.addEventListener('mousemove', moveMagnifier);
        img.addEventListener('touchmove', moveMagnifier);
        img.addEventListener('mouseenter', showGlass);
        img.addEventListener('mouseleave', hideGlass);
        img.addEventListener('touchstart', showGlass);
        img.addEventListener('touchend', hideGlass);
    }
};

// Currency Formatter Helper
function formatCurrencyInput(inputElement) {
    if (!inputElement) return;

    inputElement.addEventListener('input', (e) => {
        let value = e.target.value.replace(/[^0-9.]/g, ''); // strip letters
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        if (parts[1] && parts[1].length > 2) {
            value = parseFloat(value).toFixed(2);
        }
        e.target.value = value;
    });
}

// Language Toggle Handler
function initLanguageToggle() {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; lang=`);
    const lang = parts.length === 2 ? parts.pop().split(';').shift() : 'en';

    document.querySelectorAll('.lang-toggle-btn').forEach(btn => {
        btn.innerHTML = lang === 'en' ? '<span style="font-size: 0.8rem; font-weight: 800;">🌐 HI</span>' : '<span style="font-size: 0.8rem; font-weight: 800;">🌐 EN</span>';
        btn.addEventListener('click', () => {
            const newLang = lang === 'en' ? 'hi' : 'en';
            document.cookie = `lang=${newLang}; path=/; max-age=31536000; SameSite=Lax`;
            window.location.reload();
        });
    });
}

// Password Visibility Toggle Handler
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        if (btn.dataset.initialized) return;
        btn.dataset.initialized = 'true';
        btn.addEventListener('click', () => {
            const container = btn.closest('.password-container');
            if (!container) return;
            const input = container.querySelector('input');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            // Modern Lucide SVG Icons
            const eyeOpen = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const eyeClosed = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>`;
            
            btn.innerHTML = isPassword ? eyeClosed : eyeOpen;
        });
    });
}

// Initializer
document.addEventListener('DOMContentLoaded', () => {
    Toast.init();
    initPasswordToggles();
    initLanguageToggle();
    
    // Register Service Worker for PWA
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('Service Worker registered successfully:', reg.scope))
                .catch(err => console.log('Service Worker registration failed:', err));
        });
    }
});
