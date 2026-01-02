import api from '../services/api.js';
import { AuthUtils } from '../utils/auth.js';
import { escapeHtml, formatAmount, parseAmount, formatDate, getStatusClass, showToast, showLoading, hideLoading, validateUPI, validateIFSC, validateMobile, validateAadhar, validatePAN, formatDateForInput } from '../utils/helpers.js';
import { ThemeManager } from '../utils/theme.js';
import referralsModule from '../modules/referrals.js';

// Check authentication
if (!AuthUtils.isAuthenticated()) {
  window.location.href = '/login';
}

// Check if user is admin (redirect to admin dashboard)
if (AuthUtils.isAdmin()) {
  window.location.href = '/admin-dashboard';
}

// State
let currentView = 'overview';
let currentBalance = 0;
let currentFilter = 'all';
let currentPage = 1;
let currentHistoryPage = 1;
let currentHistoryFilter = 'all';
let historyStartDate = null;
let historyEndDate = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  setupThemeToggle();
  setupPasswordToggles();
  loadUserData();
  loadBalance();
  loadOverview();
  setupNavigation();
  setupLogout();
  setupPaymentDetailsModal();
  setupDepositForm();
  setupWithdrawForm();
  setupSettings();
  setupMobileMenu();
  setupHistoryView();
});

// Setup theme toggle
function setupThemeToggle() {
  // Mobile theme toggle
  const themeToggle = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');
  
  // Desktop theme toggle
  const desktopThemeToggle = document.getElementById('desktopThemeToggle');
  const desktopThemeIcon = document.getElementById('desktopThemeIcon');
  
  // Set initial icons
  const currentTheme = ThemeManager.getCurrentTheme();
  const iconText = currentTheme === 'dark' ? '☀️' : '🌙';
  
  if (themeIcon) themeIcon.textContent = iconText;
  if (desktopThemeIcon) desktopThemeIcon.textContent = iconText;
  
  // Mobile theme toggle handler
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const newTheme = ThemeManager.toggleTheme();
      const newIconText = newTheme === 'dark' ? '☀️' : '🌙';
      if (themeIcon) themeIcon.textContent = newIconText;
      if (desktopThemeIcon) desktopThemeIcon.textContent = newIconText;
    });
  }
  
  // Desktop theme toggle handler
  if (desktopThemeToggle) {
    desktopThemeToggle.addEventListener('click', () => {
      const newTheme = ThemeManager.toggleTheme();
      const newIconText = newTheme === 'dark' ? '☀️' : '🌙';
      if (themeIcon) themeIcon.textContent = newIconText;
      if (desktopThemeIcon) desktopThemeIcon.textContent = newIconText;
    });
  }
}

// Setup password toggle functionality
function setupPasswordToggles() {
  document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
      const input = button.parentElement.querySelector('input');
      const eyeIcon = button.querySelector('.eye-icon');
      const eyeOffIcon = button.querySelector('.eye-off-icon');
      
      if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.classList.add('hidden');
        eyeOffIcon.classList.remove('hidden');
      } else {
        input.type = 'password';
        eyeIcon.classList.remove('hidden');
        eyeOffIcon.classList.add('hidden');
      }
    });
  });
}

// Load user data
async function loadUserData() {
  try {
    const user = await api.getCurrentUser();
    
    // Update sidebar profile
    const displayName = user.name || user.email.split('@')[0];
    const firstName = displayName.split(' ')[0];
    const initials = displayName.charAt(0).toUpperCase();
    
    const sidebarUserAvatar = document.getElementById('sidebarUserAvatar');
    const sidebarUserName = document.getElementById('sidebarUserName');
    const sidebarUserEmail = document.getElementById('sidebarUserEmail');
    const mobileUserAvatar = document.getElementById('mobileUserAvatar');
    const mobilePageTitle = document.getElementById('mobilePageTitle');
    
    if (sidebarUserAvatar) sidebarUserAvatar.textContent = initials;
    if (sidebarUserName) sidebarUserName.textContent = displayName;
    if (sidebarUserEmail) sidebarUserEmail.textContent = user.email;
    if (mobileUserAvatar) mobileUserAvatar.textContent = initials;
    if (mobilePageTitle && currentView === 'overview') {
      mobilePageTitle.textContent = firstName;
    }
    
    // Check profile completion
    checkProfileCompletion(user);
    
    // Load profile data in settings
    if (document.getElementById('profileName')) {
      document.getElementById('profileName').value = user.name || '';
      document.getElementById('profileEmail').value = user.email;
      document.getElementById('profileMobile').value = user.mobileNumber || '';
      document.getElementById('profileDOB').value = formatDateForInput(user.dateOfBirth);
      document.getElementById('profileAadhar').value = user.aadharNumber || '';
      document.getElementById('profilePAN').value = user.panNumber || '';
    }
  } catch (error) {
    console.error('Error loading user data:', error);
  }
}

// Check profile completion
function checkProfileCompletion(user) {
  const banner = document.getElementById('profileCompletionBanner');
  if (!banner) return;
  
  // Check if KYC fields are complete (skip for admins)
  if (!user.isAdmin) {
    const isComplete = user.mobileNumber && user.dateOfBirth && 
                      user.aadharNumber && user.panNumber;
    
    if (!isComplete) {
      banner.style.display = 'flex';
    } else {
      banner.style.display = 'none';
    }
  } else {
    banner.style.display = 'none';
  }
}

// Go to profile settings
window.goToProfileSettings = function() {
  // Switch to settings view
  switchView('settings');
  
  // Activate profile tab
  const profileTab = document.querySelector('.settings-tab[data-tab="profile"]');
  if (profileTab) {
    profileTab.click();
  }
};

// Close banner
window.closeBanner = function() {
  const banner = document.getElementById('profileCompletionBanner');
  if (banner) {
    banner.style.display = 'none';
  }
};

// Load balance
async function loadBalance() {
  try {
    const data = await api.getBalance();
    currentBalance = data.balance;
    
    const totalBalance = document.getElementById('totalBalance');
    if (totalBalance) {
      totalBalance.textContent = formatAmount(data.balance);
    }
    
    // Update deposit page balance if element exists
    const depositBalance = document.getElementById('depositAvailableBalance');
    if (depositBalance) {
      depositBalance.textContent = formatAmount(data.balance);
    }
    
    // These elements don't exist in the HTML, so check before setting
    const availableBalance = document.getElementById('availableBalance');
    if (availableBalance) {
      availableBalance.textContent = formatAmount(data.balance);
    }
    
    const maxWithdraw = document.getElementById('maxWithdraw');
    if (maxWithdraw) {
      maxWithdraw.textContent = formatAmount(data.balance);
    }
  } catch (error) {
    console.error('Error loading balance:', error);
  }
}

// Load overview
async function loadOverview() {
  try {
    // Load balance
    await loadBalance();

    // Load recent transactions
    const transactionsData = await api.getTransactions(1, 10);
    displayRecentTransactions(transactionsData.transactions);

    // Calculate totals
    const depositsData = await api.getMyDeposits(1, 100);
    const withdrawalsData = await api.getMyWithdrawals(1, 100);

    const totalDeposits = depositsData.deposits
      .filter(d => d.status === 'approved')
      .reduce((sum, d) => sum + d.amount, 0);

    const totalWithdrawals = withdrawalsData.withdrawals
      .filter(w => w.status === 'completed' || w.status === 'confirmed')
      .reduce((sum, w) => sum + w.amount, 0);

    document.getElementById('totalDeposits').textContent = formatAmount(totalDeposits);
    document.getElementById('totalWithdrawals').textContent = formatAmount(totalWithdrawals);
  } catch (error) {
    console.error('Error loading overview:', error);
  }
}

// Display recent transactions
function displayRecentTransactions(transactions) {
  const container = document.getElementById('recentTransactionsList');
  
  if (transactions.length === 0) {
    container.innerHTML = '<p class="empty-state">No transactions yet</p>';
    return;
  }

  container.innerHTML = transactions.map(tx => `
    <div class="transaction-item">
      <div class="transaction-icon ${tx.type === 'deposit' ? 'icon-deposit' : 'icon-withdrawal'}">
        ${tx.type === 'deposit' ? '📥' : '📤'}
      </div>
      <div class="transaction-details">
        <h4>${tx.type === 'deposit' ? 'Deposit' : 'Withdrawal'}</h4>
        <p class="transaction-date">${formatDate(tx.createdAt)}</p>
      </div>
      <div class="transaction-amount">
        <p class="amount ${tx.type === 'deposit' ? 'amount-positive' : 'amount-negative'}">
          ${tx.type === 'deposit' ? '+' : '-'}${formatAmount(tx.amount)}
        </p>
        <span class="status-badge ${getStatusClass(tx.status)}">${escapeHtml(tx.status)}</span>
      </div>
    </div>
  `).join('');
}

// Setup navigation
function setupNavigation() {
  const navItems = document.querySelectorAll('.nav-item');
  
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const view = item.dataset.view;
      switchView(view);
    });
  });

  // Quick action buttons
  document.querySelectorAll('.action-buttons button').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const view = e.target.dataset.view;
      if (view) switchView(view);
    });
  });
}

// Switch view
function switchView(view) {
  currentView = view;

  // Update navigation
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.view === view);
  });

  // Update content
  document.querySelectorAll('.view-content').forEach(content => {
    content.classList.remove('active');
  });

  const viewElement = document.getElementById(`${view}View`);
  if (viewElement) {
    viewElement.classList.add('active');
  }

  // Add/remove class to content-area for withdraw view (mobile padding fix)
  const contentArea = document.querySelector('.content-area');
  if (contentArea) {
    if (view === 'withdraw') {
      contentArea.classList.add('withdraw-view-active');
    } else {
      contentArea.classList.remove('withdraw-view-active');
    }
  }

  // Update page title
  const titles = {
    overview: 'Overview',
    deposit: 'Make a Deposit',
    withdraw: 'Request Withdrawal',
    referrals: 'Referrals',
    history: 'Transaction History',
    settings: 'Settings'
  };
  const pageTitle = document.getElementById('pageTitle');
  const mobilePageTitle = document.getElementById('mobilePageTitle');
  if (pageTitle) pageTitle.textContent = titles[view] || view;
  if (mobilePageTitle) mobilePageTitle.textContent = titles[view] || view;

  // Update bottom nav
  document.querySelectorAll('.bottom-nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.view === view);
  });

  // Load view-specific data
  if (view === 'deposit') {
    loadBalance();
  } else if (view === 'withdraw') {
    loadBalance().then(() => {
      // Update withdrawable balance after loading
      const withdrawableBalance = document.getElementById('withdrawableBalance');
      const maxWithdrawAmount = document.getElementById('maxWithdrawAmount');
      if (withdrawableBalance) {
        withdrawableBalance.textContent = formatAmount(currentBalance);
      }
      if (maxWithdrawAmount) {
        maxWithdrawAmount.textContent = formatAmount(currentBalance);
      }
    });
  } else if (view === 'history') {
    loadHistory();
  } else if (view === 'referrals') {
    referralsModule.init();
  } else if (view === 'settings') {
    loadSettings();
  }
}

// Setup payment details modal
function setupPaymentDetailsModal() {
  const viewPaymentBtn = document.getElementById('viewPaymentDetailsBtn');
  const modal = document.getElementById('paymentDetailsModal');
  const closeModalBtn = document.getElementById('closePaymentModal');
  const closeModalFooterBtn = document.getElementById('closePaymentModalBtn');
  
  if (viewPaymentBtn) {
    viewPaymentBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      
      // Load payment details into modal
      try {
        const data = await api.getPaymentDetails();
        const modalContent = document.getElementById('paymentDetailsModalContent');
        
        if (data.qrCodeUrl) {
          const qrUrlWithCacheBust = data.qrCodeUrl + '?t=' + new Date().getTime();
          
          modalContent.innerHTML = `
            <img src="${escapeHtml(qrUrlWithCacheBust)}" alt="Payment QR Code" class="payment-qr-code" 
                 onerror="console.error('Failed to load QR code:', this.src); this.style.display='none';"
                 onload="console.log('QR code loaded successfully');">
            ${data.adminName ? `
              <p class="admin-name-display">Pay to: <strong>${escapeHtml(data.adminName)}</strong></p>
            ` : ''}
            ${data.upiId ? `
              <div class="upi-id-container">
                <p class="upi-id">${escapeHtml(data.upiId)}</p>
                <button type="button" class="copy-upi-btn" onclick="copyUpiId('${escapeHtml(data.upiId)}')">
                  📋 Copy
                </button>
              </div>
            ` : ''}
          `;
        } else if (data.upiId) {
          modalContent.innerHTML = `
            ${data.adminName ? `
              <p class="admin-name-display">Pay to: <strong>${escapeHtml(data.adminName)}</strong></p>
            ` : ''}
            <div class="upi-id-container">
              <p class="upi-id">${escapeHtml(data.upiId)}</p>
              <button type="button" class="copy-upi-btn" onclick="copyUpiId('${escapeHtml(data.upiId)}')">
                📋 Copy
              </button>
            </div>
          `;
        } else {
          modalContent.innerHTML = '<p class="error-text">Payment details not available</p>';
        }
        
        // Show modal
        modal.classList.add('active');
      } catch (error) {
        console.error('Error loading payment details:', error);
        showToast('Failed to load payment details', 'error');
      }
    });
  }
  
  // Close modal handlers
  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', () => {
      modal.classList.remove('active');
    });
  }
  
  if (closeModalFooterBtn) {
    closeModalFooterBtn.addEventListener('click', () => {
      modal.classList.remove('active');
    });
  }
  
  // Close modal when clicking outside
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('active');
      }
    });
  }
}

// Setup deposit form
function setupDepositForm() {
  const form = document.getElementById('depositForm');
  const fileInput = document.getElementById('paymentProof');
  const placeholder = document.querySelector('.file-upload-placeholder');
  const uploadText = placeholder.querySelector('.upload-text');
  const uploadIcon = placeholder.querySelector('.upload-icon');
  
  // Handle file selection - show filename
  fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
      const fileName = e.target.files[0].name;
      uploadText.textContent = fileName;
      uploadIcon.textContent = '✓';
      placeholder.classList.add('has-file');
    } else {
      uploadText.textContent = 'Choose payment screenshot';
      uploadIcon.textContent = '📁';
      placeholder.classList.remove('has-file');
    }
  });
  
  // Quick amount buttons
  document.querySelectorAll('.quick-amount-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const amount = btn.dataset.amount;
      document.getElementById('depositAmount').value = amount;
    });
  });
  
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const amount = document.getElementById('depositAmount').value;
    const paymentProof = document.getElementById('paymentProof').files[0];
    const transactionId = document.getElementById('transactionId').value.trim();

    if (!amount || parseFloat(amount) <= 0) {
      showToast('Please enter a valid amount', 'error');
      return;
    }

    if (!paymentProof) {
      showToast('Please upload payment proof', 'error');
      return;
    }

    showLoading(submitBtn);

    try {
      const amountInPaise = parseAmount(amount);
      await api.createDeposit(amountInPaise, paymentProof, transactionId || null);
      
      form.reset();
      // Reset file upload display
      uploadText.textContent = 'Choose payment screenshot';
      uploadIcon.textContent = '📁';
      placeholder.classList.remove('has-file');
      
      switchView('overview');
      loadOverview();
    } catch (error) {
      console.error('Error creating deposit:', error);
    } finally {
      hideLoading(submitBtn);
    }
  });
}

// Load payment details
async function loadPaymentDetails() {
  try {
    const data = await api.getPaymentDetails();
    console.log('Payment details loaded (user):', data); // Debug log
    const container = document.getElementById('paymentDetailsContent');
    
    if (data.qrCodeUrl) {
      const qrUrlWithCacheBust = data.qrCodeUrl + '?t=' + new Date().getTime();
      console.log('User QR Code URL:', qrUrlWithCacheBust); // Debug log
      
      container.innerHTML = `
        <img src="${escapeHtml(qrUrlWithCacheBust)}" alt="Payment QR Code" class="payment-qr-code" 
             onerror="console.error('Failed to load QR code:', this.src); this.style.display='none';"
             onload="console.log('QR code loaded successfully');">
        ${data.adminName ? `
          <p class="admin-name-display">Pay to: <strong>${escapeHtml(data.adminName)}</strong></p>
        ` : ''}
        ${data.upiId ? `
          <div class="upi-id-container">
            <p class="upi-id">${escapeHtml(data.upiId)}</p>
            <button type="button" class="copy-upi-btn" onclick="copyUpiId('${escapeHtml(data.upiId)}')">
              📋 Copy
            </button>
          </div>
        ` : ''}
      `;
    } else if (data.upiId) {
      console.log('No QR code, showing UPI ID only'); // Debug log
      container.innerHTML = `
        ${data.adminName ? `
          <p class="admin-name-display">Pay to: <strong>${escapeHtml(data.adminName)}</strong></p>
        ` : ''}
        <div class="upi-id-container">
          <p class="upi-id">${escapeHtml(data.upiId)}</p>
          <button type="button" class="copy-upi-btn" onclick="copyUpiId('${escapeHtml(data.upiId)}')">
            📋 Copy
          </button>
        </div>
      `;
    } else {
      console.log('No payment details available'); // Debug log
      container.innerHTML = '<p class="error-text">Payment details not available</p>';
    }
  } catch (error) {
    console.error('Error loading payment details:', error);
    document.getElementById('paymentDetailsContent').innerHTML = '<p class="error-text">Failed to load payment details</p>';
  }
}

// Copy UPI ID function
window.copyUpiId = function(upiId) {
  // Try modern clipboard API first
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(upiId).then(() => {
      showToast('UPI ID copied to clipboard!', 'success');
    }).catch(() => {
      fallbackCopy(upiId);
    });
  } else {
    fallbackCopy(upiId);
  }
}

// Fallback copy method
function fallbackCopy(text) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.left = '-999999px';
  textArea.style.top = '-999999px';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  
  try {
    const successful = document.execCommand('copy');
    if (successful) {
      showToast('UPI ID copied to clipboard!', 'success');
    } else {
      showToast('Failed to copy. Please copy manually: ' + text, 'error');
    }
  } catch (err) {
    showToast('Failed to copy. Please copy manually: ' + text, 'error');
  }
  
  document.body.removeChild(textArea);
}

// Setup withdraw form
function setupWithdrawForm() {
  const form = document.getElementById('withdrawForm');
  const amountInput = document.getElementById('withdrawAmount');
  const withdrawableBalance = document.getElementById('withdrawableBalance');
  const maxWithdrawAmount = document.getElementById('maxWithdrawAmount');
  const paymentMethods = document.querySelectorAll('.payment-method-card');
  const upiDetailsExpanded = document.getElementById('upiDetailsExpanded');
  const bankDetailsExpanded = document.getElementById('bankDetailsExpanded');
  const swipeButton = document.getElementById('swipeButton');
  const swipeSlider = document.getElementById('swipeSlider');

  // Update withdrawable balance when view loads
  function updateWithdrawableBalance() {
    if (withdrawableBalance) {
      withdrawableBalance.textContent = formatAmount(currentBalance);
    }
    if (maxWithdrawAmount) {
      maxWithdrawAmount.textContent = formatAmount(currentBalance);
    }
  }

  // Call on initial load
  updateWithdrawableBalance();

  // Quick amount buttons
  const quickAmountBtns = document.querySelectorAll('.quick-amount-btn');
  quickAmountBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const amount = btn.dataset.amount;
      if (amount === 'max') {
        amountInput.value = Math.floor(currentBalance / 100);
      } else {
        amountInput.value = amount;
      }
    });
  });

  // Payment method selection - handle both card click and radio change
  paymentMethods.forEach(method => {
    const radio = method.querySelector('input[type="radio"]');
    
    // Handle card click
    method.addEventListener('click', () => {
      if (radio) radio.checked = true;
      updatePaymentMethod();
    });
    
    // Handle radio change
    if (radio) {
      radio.addEventListener('change', () => {
        updatePaymentMethod();
      });
    }
  });

  function updatePaymentMethod() {
    const selectedRadio = document.querySelector('input[name="withdrawMethod"]:checked');
    if (!selectedRadio) return;
    
    const methodType = selectedRadio.value;
    
    // Show/hide payment details
    if (methodType === 'upi') {
      if (upiDetailsExpanded) upiDetailsExpanded.style.display = 'block';
      if (bankDetailsExpanded) bankDetailsExpanded.style.display = 'none';
    } else if (methodType === 'bank') {
      if (upiDetailsExpanded) upiDetailsExpanded.style.display = 'none';
      if (bankDetailsExpanded) bankDetailsExpanded.style.display = 'block';
    }
  }

  // Set initial state
  updatePaymentMethod();

  // Swipe to withdraw functionality
  if (swipeButton && swipeSlider) {
    const swipeProgress = document.getElementById('swipeProgress');
    const swipeTextFilled = document.getElementById('swipeTextFilled');
    let isDragging = false;
    let startX = 0;
    let hasMovedSignificantly = false;
    
    function getMaxSwipe() {
      return swipeButton.offsetWidth - swipeSlider.offsetWidth - 8;
    }

    function updateProgress(dragDistance) {
      if (swipeProgress) {
        const maxSwipe = getMaxSwipe();
        const progressWidth = Math.min(dragDistance + swipeSlider.offsetWidth, swipeButton.offsetWidth);
        swipeProgress.style.width = `${progressWidth}px`;
        
        // Update text color based on progress
        if (swipeTextFilled) {
          const percentage = (progressWidth / swipeButton.offsetWidth) * 100;
          swipeTextFilled.style.clipPath = `inset(0 ${100 - percentage}% 0 0)`;
        }
      }
    }

    function handleStart(e) {
      isDragging = true;
      hasMovedSignificantly = false;
      startX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
      swipeSlider.style.transition = 'none';
      if (swipeProgress) swipeProgress.style.transition = 'none';
    }

    function handleMove(e) {
      if (!isDragging) return;
      
      const currentX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
      const diff = currentX - startX;
      
      // Mark as moved if dragged more than 5px
      if (Math.abs(diff) > 5) {
        hasMovedSignificantly = true;
      }
      
      const maxSwipe = getMaxSwipe();
      const newLeft = Math.max(4, Math.min(4 + diff, 4 + maxSwipe));
      swipeSlider.style.left = `${newLeft}px`;
      
      // Update progress bar
      updateProgress(newLeft - 4);
    }

    async function handleEnd() {
      if (!isDragging) return;
      isDragging = false;
      
      const maxSwipe = getMaxSwipe();
      const currentLeft = parseInt(swipeSlider.style.left || '4px');
      const dragDistance = currentLeft - 4;
      
      swipeSlider.style.transition = 'left 0.3s ease, background 0.3s ease';
      if (swipeProgress) swipeProgress.style.transition = 'width 0.3s ease';
      
      // Check if swiped more than 85% and actually moved
      if (hasMovedSignificantly && dragDistance >= maxSwipe * 0.85) {
        swipeSlider.style.left = `${4 + maxSwipe}px`;
        swipeSlider.classList.add('success');
        updateProgress(maxSwipe);
        
        setTimeout(async () => {
          await submitWithdrawal();
          setTimeout(() => {
            swipeSlider.style.left = '4px';
            swipeSlider.classList.remove('success');
            if (swipeProgress) swipeProgress.style.width = '0';
            if (swipeTextFilled) swipeTextFilled.style.clipPath = 'inset(0 100% 0 0)';
          }, 500);
        }, 300);
      } else {
        swipeSlider.style.left = '4px';
        if (swipeProgress) swipeProgress.style.width = '0';
        if (swipeTextFilled) swipeTextFilled.style.clipPath = 'inset(0 100% 0 0)';
      }
    }

    // Mouse events
    swipeSlider.addEventListener('mousedown', handleStart);
    window.addEventListener('mousemove', handleMove);
    window.addEventListener('mouseup', handleEnd);

    // Touch events
    swipeSlider.addEventListener('touchstart', handleStart);
    window.addEventListener('touchmove', handleMove);
    window.addEventListener('touchend', handleEnd);
  }

  async function submitWithdrawal() {
    const amount = amountInput.value;
    const method = form.querySelector('input[name="withdrawMethod"]:checked')?.value || 'upi';

    if (!amount || parseFloat(amount) <= 0) {
      showToast('Please enter a valid amount', 'error');
      return;
    }

    const amountInPaise = parseAmount(amount);
    if (amountInPaise > currentBalance) {
      showToast('Insufficient balance', 'error');
      return;
    }

    let bankDetailsObj;

    if (method === 'upi') {
      const upiId = document.getElementById('upiId')?.value.trim();
      if (!upiId) {
        showToast('Please enter UPI ID', 'error');
        return;
      }
      if (!validateUPI(upiId)) {
        showToast('Please enter a valid UPI ID', 'error');
        return;
      }
      bankDetailsObj = { type: 'upi', upiId };
    } else {
      const accountName = document.getElementById('accountName')?.value.trim();
      const accountNumber = document.getElementById('accountNumber')?.value.trim();
      const ifscCode = document.getElementById('ifscCode')?.value.trim().toUpperCase();

      if (!accountName || !accountNumber || !ifscCode) {
        showToast('Please fill all bank details', 'error');
        return;
      }

      if (!validateIFSC(ifscCode)) {
        showToast('Please enter a valid IFSC code', 'error');
        return;
      }

      bankDetailsObj = { type: 'bank', accountName, accountNumber, ifscCode };
    }

    try {
      await api.createWithdrawal(amountInPaise, bankDetailsObj);
      
      showToast('Withdrawal request submitted successfully', 'success');
      
      // Reset form
      form.reset();
      amountInput.value = '500';
      
      // Reset to UPI method
      const upiRadio = document.querySelector('input[value="upi"]');
      if (upiRadio) upiRadio.checked = true;
      
      // Update payment method display
      updatePaymentMethod();
      
      // Switch to overview and reload
      switchView('overview');
      loadOverview();
    } catch (error) {
      console.error('Error creating withdrawal:', error);
    }
  }
}

// Setup logout
function setupLogout() {
  document.getElementById('logoutBtn').addEventListener('click', async () => {
    await api.logout();
    window.location.href = '/login';
  });
}

// Setup mobile menu
function setupMobileMenu() {
  const mobileUserAvatar = document.getElementById('mobileUserAvatar');
  const hamburgerMenu = document.getElementById('hamburgerMenu');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const mobileOverlay = document.getElementById('mobileOverlay');

  // Note: User info is loaded by loadUserData() function, no need to duplicate here

  // Profile avatar click handler (opens sidebar)
  if (mobileUserAvatar) {
    mobileUserAvatar.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
      sidebarOverlay.classList.toggle('active');
    });
  }

  // Legacy hamburger menu (desktop)
  if (hamburgerMenu) {
    hamburgerMenu.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
      if (mobileOverlay) mobileOverlay.classList.toggle('active');
    });
  }

  // Overlay click handlers
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      sidebarOverlay.classList.remove('active');
      if (hamburgerBtn) hamburgerBtn.classList.remove('active');
    });
  }

  if (mobileOverlay) {
    mobileOverlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      mobileOverlay.classList.remove('active');
    });
  }

  // Close menu when clicking nav items on mobile
  document.querySelectorAll('.nav-item, .bottom-nav-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        sidebar.classList.remove('mobile-open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
        if (mobileOverlay) mobileOverlay.classList.remove('active');
        if (hamburgerBtn) hamburgerBtn.classList.remove('active');
      }
    });
  });

  // Bottom nav navigation
  document.querySelectorAll('.bottom-nav-item').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const view = item.dataset.view;
      
      // Update active states
      document.querySelectorAll('.bottom-nav-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');
      
      // Update sidebar nav
      document.querySelectorAll('.nav-item').forEach(i => {
        if (i.dataset.view === view) {
          i.classList.add('active');
        } else {
          i.classList.remove('active');
        }
      });
      
      // Switch view
      switchView(view);
    });
  });
}

// Setup settings
function setupSettings() {
  // Setup tabs
  const settingsTabs = document.querySelectorAll('#settingsView .settings-tab');
  settingsTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const tabName = tab.dataset.tab;
      
      // Update active tab
      settingsTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      
      // Show corresponding section
      document.querySelectorAll('#settingsView .settings-section').forEach(section => {
        section.classList.remove('active');
      });
      document.getElementById(`${tabName}Settings`).classList.add('active');
    });
  });

  // Profile form
  const profileForm = document.getElementById('profileForm');
  profileForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = profileForm.querySelector('button[type="submit"]');
    const name = document.getElementById('profileName').value.trim();
    const mobileNumber = document.getElementById('profileMobile').value.trim();
    const dateOfBirth = document.getElementById('profileDOB').value;
    const aadharNumber = document.getElementById('profileAadhar').value.trim();
    const panNumber = document.getElementById('profilePAN').value.trim().toUpperCase();
    
    // Validate mobile
    if (mobileNumber && !validateMobile(mobileNumber)) {
      showToast('Please enter a valid 10-digit mobile number', 'error');
      return;
    }
    
    // Validate Aadhar
    if (aadharNumber && !validateAadhar(aadharNumber)) {
      showToast('Please enter a valid 12-digit Aadhar number', 'error');
      return;
    }
    
    // Validate PAN
    if (panNumber && !validatePAN(panNumber)) {
      showToast('Please enter a valid PAN number (e.g., ABCDE1234F)', 'error');
      return;
    }

    showLoading(submitBtn);

    try {
      await api.updateProfile(name, null, null, mobileNumber, aadharNumber, dateOfBirth, panNumber);
      
      // Reload user data to update UI and banner
      const user = await api.getCurrentUser();
      
      // Update sidebar profile
      const displayName = user.name || user.email.split('@')[0];
      const initials = displayName.charAt(0).toUpperCase();
      
      const sidebarUserAvatar = document.getElementById('sidebarUserAvatar');
      const sidebarUserName = document.getElementById('sidebarUserName');
      const sidebarUserEmail = document.getElementById('sidebarUserEmail');
      const mobileUserAvatar = document.getElementById('mobileUserAvatar');
      
      if (sidebarUserAvatar) sidebarUserAvatar.textContent = initials;
      if (sidebarUserName) sidebarUserName.textContent = displayName;
      if (sidebarUserEmail) sidebarUserEmail.textContent = user.email;
      if (mobileUserAvatar) mobileUserAvatar.textContent = initials;
      
      // Update profile completion banner
      checkProfileCompletion(user);
    } catch (error) {
      console.error('Error updating profile:', error);
    } finally {
      hideLoading(submitBtn);
    }
  });

  // Password form
  const passwordForm = document.getElementById('passwordForm');
  passwordForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = passwordForm.querySelector('button[type="submit"]');
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmNewPassword = document.getElementById('confirmNewPassword').value;

    if (newPassword !== confirmNewPassword) {
      showToast('New passwords do not match', 'error');
      return;
    }

    if (newPassword.length < 8) {
      showToast('New password must be at least 8 characters', 'error');
      return;
    }

    showLoading(submitBtn);

    try {
      await api.updateProfile(undefined, currentPassword, newPassword);
      passwordForm.reset();
    } catch (error) {
      console.error('Error changing password:', error);
    } finally {
      hideLoading(submitBtn);
    }
  });
}

// Load settings
async function loadSettings() {
  try {
    // Fetch fresh user data from API
    const user = await api.getCurrentUser();
    
    // Update all profile fields
    if (document.getElementById('profileName')) {
      document.getElementById('profileName').value = user.name || '';
      document.getElementById('profileEmail').value = user.email;
      document.getElementById('profileMobile').value = user.mobileNumber || '';
      document.getElementById('profileDOB').value = formatDateForInput(user.dateOfBirth);
      document.getElementById('profileAadhar').value = user.aadharNumber || '';
      document.getElementById('profilePAN').value = user.panNumber || '';
    }
  } catch (error) {
    console.error('Error loading settings:', error);
  }
}

// Setup history view
function setupHistoryView() {
  // Filter tabs
  const filterTabs = document.querySelectorAll('#historyView .filter-tab');
  filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      
      currentHistoryFilter = tab.dataset.filter;
      currentHistoryPage = 1;
      loadHistory();
    });
  });

  // Date search
  const searchBtn = document.getElementById('searchByDate');
  const clearBtn = document.getElementById('clearDateFilter');
  
  if (searchBtn) {
    searchBtn.addEventListener('click', () => {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      
      if (startDate && endDate && startDate > endDate) {
        showToast('Start date must be before end date', 'error');
        return;
      }
      
      historyStartDate = startDate || null;
      historyEndDate = endDate || null;
      currentHistoryPage = 1;
      loadHistory();
    });
  }
  
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      document.getElementById('startDate').value = '';
      document.getElementById('endDate').value = '';
      historyStartDate = null;
      historyEndDate = null;
      currentHistoryPage = 1;
      loadHistory();
    });
  }

  // View all link in overview
  const viewAllLink = document.querySelector('.view-all-link');
  if (viewAllLink) {
    viewAllLink.addEventListener('click', (e) => {
      e.preventDefault();
      switchView('history');
    });
  }
}

// Load history
async function loadHistory() {
  try {
    const container = document.getElementById('historyTransactionsList');
    container.innerHTML = '<p class="loading-text">Loading history...</p>';

    // Fetch all transactions (API limit is 100, so we need to fetch multiple pages)
    let allTransactions = [];
    
    if (currentHistoryFilter === 'all' || currentHistoryFilter === 'deposit') {
      // Fetch deposits with pagination
      let page = 1;
      let hasMore = true;
      while (hasMore) {
        const depositsData = await api.getMyDeposits(page, 100);
        allTransactions = allTransactions.concat(
          depositsData.deposits.map(d => ({ ...d, type: 'deposit' }))
        );
        hasMore = depositsData.deposits.length === 100;
        page++;
        if (page > 10) break; // Safety limit: max 1000 transactions
      }
    }
    
    if (currentHistoryFilter === 'all' || currentHistoryFilter === 'withdrawal') {
      // Fetch withdrawals with pagination
      let page = 1;
      let hasMore = true;
      while (hasMore) {
        const withdrawalsData = await api.getMyWithdrawals(page, 100);
        allTransactions = allTransactions.concat(
          withdrawalsData.withdrawals.map(w => ({ ...w, type: 'withdrawal' }))
        );
        hasMore = withdrawalsData.withdrawals.length === 100;
        page++;
        if (page > 10) break; // Safety limit: max 1000 transactions
      }
    }

    if (currentHistoryFilter === 'all' || currentHistoryFilter === 'bonus') {
      // Fetch bonus claims
      try {
        let page = 1;
        let hasMore = true;
        while (hasMore) {
          const claimsData = await api.getClaimHistory(page, 100);
          allTransactions = allTransactions.concat(
            claimsData.claims.map(c => ({ ...c, type: 'bonus' }))
          );
          hasMore = claimsData.claims.length === 100;
          page++;
          if (page > 10) break; // Safety limit
        }
      } catch (e) {
        console.log('Bonus claims not available');
      }
    }

    // Filter by date if specified
    if (historyStartDate || historyEndDate) {
      allTransactions = allTransactions.filter(tx => {
        const txDate = new Date(tx.createdAt).toISOString().split('T')[0];
        
        if (historyStartDate && txDate < historyStartDate) return false;
        if (historyEndDate && txDate > historyEndDate) return false;
        
        return true;
      });
    }

    // Sort by date (newest first)
    allTransactions.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));

    if (allTransactions.length === 0) {
      container.innerHTML = '<p class="empty-state">No transactions found</p>';
      document.getElementById('historyPagination').innerHTML = '';
      return;
    }

    // Pagination
    const itemsPerPage = 20;
    const totalPages = Math.ceil(allTransactions.length / itemsPerPage);
    const startIndex = (currentHistoryPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageTransactions = allTransactions.slice(startIndex, endIndex);

    // Display transactions
    container.innerHTML = pageTransactions.map(tx => {
      // Determine icon and styling based on type
      let icon, iconClass, title, amountClass, amountPrefix;
      
      if (tx.type === 'deposit') {
        icon = '📥';
        iconClass = 'icon-deposit';
        title = 'Deposit';
        amountClass = 'amount-positive';
        amountPrefix = '+';
      } else if (tx.type === 'withdrawal') {
        icon = '📤';
        iconClass = 'icon-withdrawal';
        title = 'Withdrawal';
        amountClass = 'amount-negative';
        amountPrefix = '-';
      } else if (tx.type === 'bonus') {
        icon = '🎁';
        iconClass = 'icon-bonus';
        title = `Bonus from ${escapeHtml(tx.referredUserName || 'Referral')}`;
        amountClass = 'amount-positive';
        amountPrefix = '+';
      }

      return `
        <div class="transaction-item">
          <div class="transaction-icon ${iconClass}">
            ${icon}
          </div>
          <div class="transaction-details">
            <h4>${title}</h4>
            <p class="transaction-date">${formatDate(tx.createdAt)}</p>
            ${tx.transactionId ? `<p class="transaction-id">TXN: ${escapeHtml(tx.transactionId)}</p>` : ''}
          </div>
          <div class="transaction-amount">
            <p class="amount ${amountClass}">
              ${amountPrefix}${formatAmount(tx.amount)}
            </p>
            <span class="status-badge ${getStatusClass(tx.status)}">${escapeHtml(tx.status)}</span>
          </div>
        </div>
      `;
    }).join('');

    // Render pagination
    renderHistoryPagination(totalPages);
  } catch (error) {
    console.error('Error loading history:', error);
    document.getElementById('historyTransactionsList').innerHTML = '<p class="error-text">Failed to load history</p>';
  }
}

// Render history pagination
function renderHistoryPagination(totalPages) {
  const container = document.getElementById('historyPagination');
  
  if (totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  let paginationHTML = '';
  
  // Previous button
  if (currentHistoryPage > 1) {
    paginationHTML += `<button class="pagination-btn" onclick="changeHistoryPage(${currentHistoryPage - 1})">← Previous</button>`;
  }
  
  // Page numbers
  const maxVisiblePages = 5;
  let startPage = Math.max(1, currentHistoryPage - Math.floor(maxVisiblePages / 2));
  let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
  
  if (endPage - startPage < maxVisiblePages - 1) {
    startPage = Math.max(1, endPage - maxVisiblePages + 1);
  }
  
  if (startPage > 1) {
    paginationHTML += `<button class="pagination-btn" onclick="changeHistoryPage(1)">1</button>`;
    if (startPage > 2) {
      paginationHTML += `<span class="pagination-ellipsis">...</span>`;
    }
  }
  
  for (let i = startPage; i <= endPage; i++) {
    paginationHTML += `<button class="pagination-btn ${i === currentHistoryPage ? 'active' : ''}" onclick="changeHistoryPage(${i})">${i}</button>`;
  }
  
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      paginationHTML += `<span class="pagination-ellipsis">...</span>`;
    }
    paginationHTML += `<button class="pagination-btn" onclick="changeHistoryPage(${totalPages})">${totalPages}</button>`;
  }
  
  // Next button
  if (currentHistoryPage < totalPages) {
    paginationHTML += `<button class="pagination-btn" onclick="changeHistoryPage(${currentHistoryPage + 1})">Next →</button>`;
  }
  
  container.innerHTML = paginationHTML;
}

// Change history page
window.changeHistoryPage = function(page) {
  currentHistoryPage = page;
  loadHistory();
  
  // Scroll to top of history view
  const historyView = document.getElementById('historyView');
  if (historyView) {
    historyView.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};
