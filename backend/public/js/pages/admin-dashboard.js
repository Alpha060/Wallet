import api from '../services/api.js';
import { AuthUtils } from '../utils/auth.js';
import { escapeHtml, formatAmount, formatDate, getStatusClass, showToast, showLoading, hideLoading, validateUPI, validateMobile } from '../utils/helpers.js';
import { ThemeManager } from '../utils/theme.js';

// Check authentication and admin role
if (!AuthUtils.isAuthenticated()) {
  window.location.href = '/login';
}

if (!AuthUtils.isAdmin()) {
  window.location.href = '/user-dashboard';
}

// State
let currentView = 'overview';
let currentDepositPage = 1;
let currentWithdrawalPage = 1;
let currentHistoryPage = 1;
let currentHistoryFilter = 'all';
let currentBonusClaimsPage = 1;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  setupThemeToggle();
  setupPasswordToggles();
  loadUserData();
  loadOverview();
  setupNavigation();
  setupLogout();
  setupSettings();
  setupModals();
  setupRefreshButtons();
  setupMobileMenu();
});

// Setup theme toggle
function setupThemeToggle() {
  const adminThemeToggle = document.getElementById('adminThemeToggle');
  const adminThemeIcon = document.getElementById('adminThemeIcon');
  const mobileAdminThemeToggle = document.getElementById('mobileAdminThemeToggle');
  const mobileAdminThemeIcon = document.getElementById('mobileAdminThemeIcon');
  
  // Set initial icon
  const currentTheme = ThemeManager.getCurrentTheme();
  const iconText = currentTheme === 'dark' ? '☀️' : '🌙';
  
  if (adminThemeIcon) adminThemeIcon.textContent = iconText;
  if (mobileAdminThemeIcon) mobileAdminThemeIcon.textContent = iconText;
  
  // Desktop theme toggle handler
  if (adminThemeToggle) {
    adminThemeToggle.addEventListener('click', () => {
      const newTheme = ThemeManager.toggleTheme();
      const newIconText = newTheme === 'dark' ? '☀️' : '🌙';
      if (adminThemeIcon) adminThemeIcon.textContent = newIconText;
      if (mobileAdminThemeIcon) mobileAdminThemeIcon.textContent = newIconText;
    });
  }

  // Mobile theme toggle handler
  if (mobileAdminThemeToggle) {
    mobileAdminThemeToggle.addEventListener('click', () => {
      const newTheme = ThemeManager.toggleTheme();
      const newIconText = newTheme === 'dark' ? '☀️' : '🌙';
      if (adminThemeIcon) adminThemeIcon.textContent = newIconText;
      if (mobileAdminThemeIcon) mobileAdminThemeIcon.textContent = newIconText;
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
    
    // Update sidebar profile (using textContent is safe from XSS)
    const displayName = user.name || user.email.split('@')[0];
    const initials = displayName.charAt(0).toUpperCase();
    
    const adminSidebarUserAvatar = document.getElementById('adminSidebarUserAvatar');
    const adminSidebarUserName = document.getElementById('adminSidebarUserName');
    const adminSidebarUserEmail = document.getElementById('adminSidebarUserEmail');
    
    if (adminSidebarUserAvatar) adminSidebarUserAvatar.textContent = initials;
    if (adminSidebarUserName) adminSidebarUserName.textContent = displayName;
    if (adminSidebarUserEmail) adminSidebarUserEmail.textContent = user.email;
    
    // Load personal info in settings
    if (document.getElementById('adminName')) {
      document.getElementById('adminName').value = user.name || '';
      document.getElementById('adminEmail').value = user.email;
      document.getElementById('adminMobile').value = user.mobileNumber || '';
    }
  } catch (error) {
    console.error('Error loading user data:', error);
  }
}

// Load overview
async function loadOverview() {
  try {
    const stats = await api.getStatistics();
    
    document.getElementById('pendingDepositsCount').textContent = stats.pendingDepositsCount;
    document.getElementById('pendingWithdrawalsCount').textContent = stats.pendingWithdrawalsCount;
    document.getElementById('totalApprovedDeposits').textContent = formatAmount(stats.totalApprovedDeposits);
    document.getElementById('totalCompletedWithdrawals').textContent = formatAmount(stats.totalCompletedWithdrawals);

    // Update badges
    document.getElementById('depositsBadge').textContent = stats.pendingDepositsCount;
    document.getElementById('withdrawalsBadge').textContent = stats.pendingWithdrawalsCount;

    // Load bonus claims count
    try {
      const bonusClaimsData = await api.getPendingBonusClaims(1, 1);
      const bonusClaimsBadge = document.getElementById('bonusClaimsBadge');
      if (bonusClaimsBadge) {
        bonusClaimsBadge.textContent = bonusClaimsData.total || 0;
      }
    } catch (e) {
      console.log('Bonus claims not available yet');
    }
  } catch (error) {
    console.error('Error loading overview:', error);
  }
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

  // Update sidebar navigation
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.toggle('active', item.dataset.view === view);
  });

  // Update bottom navigation
  document.querySelectorAll('.bottom-nav-item').forEach(item => {
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

  // Update page title
  const titles = {
    overview: 'Overview',
    deposits: 'Pending Deposits',
    withdrawals: 'Pending Withdrawals',
    bonusClaims: 'Bonus Claims',
    history: 'Transaction History',
    users: 'Users',
    settings: 'Settings'
  };
  document.getElementById('pageTitle').textContent = titles[view] || view;
  
  // Update mobile page title
  const mobilePageTitle = document.getElementById('mobilePageTitle');
  if (mobilePageTitle) {
    mobilePageTitle.textContent = titles[view] || view;
  }

  // Load view-specific data
  if (view === 'deposits') {
    loadPendingDeposits();
  } else if (view === 'withdrawals') {
    loadPendingWithdrawals();
  } else if (view === 'bonusClaims') {
    loadPendingBonusClaims();
  } else if (view === 'history') {
    loadHistory();
  } else if (view === 'users') {
    loadUsers();
  } else if (view === 'settings') {
    loadSettings();
  }
}

// Load pending deposits
async function loadPendingDeposits() {
  try {
    const container = document.getElementById('depositsList');
    container.innerHTML = '<p class="loading-text">Loading deposits...</p>';

    const data = await api.getPendingDeposits(currentDepositPage, 20);

    if (data.deposits.length === 0) {
      container.innerHTML = '<p class="empty-state">No pending deposits</p>';
      return;
    }

    container.innerHTML = data.deposits.map(deposit => `
      <div class="request-card-compact">
        <div class="request-row">
          <div class="request-left">
            <p class="request-user-name">${escapeHtml(deposit.userName || deposit.userEmail.split('@')[0])} • ${escapeHtml(deposit.userEmail)}</p>
            <p class="request-date">${formatDate(deposit.createdAt)}</p>
            ${deposit.transactionId ? `<p class="request-details-text">TxID: ${escapeHtml(deposit.transactionId)}</p>` : ''}
            ${deposit.paymentProofUrl ? `
              <button class="btn-view-proof" onclick="viewProofModal('${escapeHtml(deposit.paymentProofUrl)}')">
                📷 View Proof
              </button>
            ` : ''}
          </div>
          <div class="request-center">
            <p class="request-amount-compact">${formatAmount(deposit.amount)}</p>
          </div>
          <div class="request-right">
            <button class="btn btn-success btn-compact" onclick="approveDeposit('${escapeHtml(deposit.id)}')">
              ✓ Approve
            </button>
            <button class="btn btn-danger btn-compact" onclick="rejectDeposit('${escapeHtml(deposit.id)}')">
              ✗ Reject
            </button>
          </div>
        </div>
      </div>
    `).join('');
  } catch (error) {
    console.error('Error loading deposits:', error);
    document.getElementById('depositsList').innerHTML = '<p class="error-text">Failed to load deposits</p>';
  }
}

// Load pending withdrawals
async function loadPendingWithdrawals() {
  try {
    const container = document.getElementById('withdrawalsList');
    container.innerHTML = '<p class="loading-text">Loading withdrawals...</p>';

    const data = await api.getPendingWithdrawals(currentWithdrawalPage, 20);

    if (data.withdrawals.length === 0) {
      container.innerHTML = '<p class="empty-state">No pending withdrawals</p>';
      return;
    }

    container.innerHTML = data.withdrawals.map(withdrawal => `
      <div class="request-card-compact">
        <div class="request-row">
          <div class="request-left">
            <p class="request-user-name">${escapeHtml(withdrawal.userName || withdrawal.userEmail.split('@')[0])} • ${escapeHtml(withdrawal.userEmail)}</p>
            <p class="request-date">${formatDate(withdrawal.createdAt)}</p>
            <p class="request-method-small">${withdrawal.bankDetails.type === 'upi' ? '💳 UPI' : '🏦 Bank Account'}</p>
            ${withdrawal.bankDetails.type === 'upi' ? `
              <p class="request-details-text">${escapeHtml(withdrawal.bankDetails.upiId)}</p>
            ` : `
              <p class="request-details-text">${escapeHtml(withdrawal.bankDetails.accountName)} • ${escapeHtml(withdrawal.bankDetails.accountNumber)}</p>
              <p class="request-details-text">IFSC: ${escapeHtml(withdrawal.bankDetails.ifscCode)}</p>
            `}
          </div>
          <div class="request-center">
            <p class="request-amount-compact">${formatAmount(withdrawal.amount)}</p>
          </div>
          <div class="request-right">
            <button class="btn btn-success btn-compact" onclick="confirmWithdrawal('${escapeHtml(withdrawal.id)}')">
              ✓ Confirm
            </button>
            <button class="btn btn-danger btn-compact" onclick="rejectWithdrawal('${escapeHtml(withdrawal.id)}')">
              ✗ Reject
            </button>
          </div>
        </div>
      </div>
    `).join('');
  } catch (error) {
    console.error('Error loading withdrawals:', error);
    document.getElementById('withdrawalsList').innerHTML = '<p class="error-text">Failed to load withdrawals</p>';
  }
}

// Approve deposit
window.approveDeposit = async function(depositId) {
  if (!confirm('Are you sure you want to approve this deposit?')) return;

  try {
    await api.approveDeposit(depositId);
    loadPendingDeposits();
    loadOverview();
  } catch (error) {
    console.error('Error approving deposit:', error);
  }
};

// Reject deposit
window.rejectDeposit = async function(depositId) {
  const reason = prompt('Enter rejection reason (optional):');
  if (reason === null) return; // User cancelled

  try {
    await api.rejectDeposit(depositId, reason);
    loadPendingDeposits();
    loadOverview();
  } catch (error) {
    console.error('Error rejecting deposit:', error);
  }
};

// Confirm withdrawal
window.confirmWithdrawal = async function(withdrawalId) {
  if (!confirm('Are you sure you want to confirm this withdrawal? Make sure you have completed the payment.')) return;

  try {
    await api.confirmWithdrawal(withdrawalId);
    loadPendingWithdrawals();
    loadOverview();
  } catch (error) {
    console.error('Error confirming withdrawal:', error);
  }
};

// Reject withdrawal
window.rejectWithdrawal = async function(withdrawalId) {
  const reason = prompt('Enter rejection reason (optional):');
  if (reason === null) return; // User cancelled

  try {
    await api.rejectWithdrawal(withdrawalId, reason);
    loadPendingWithdrawals();
    loadOverview();
  } catch (error) {
    console.error('Error rejecting withdrawal:', error);
  }
};

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

  // Admin personal info form
  const adminPersonalForm = document.getElementById('adminPersonalForm');
  if (adminPersonalForm) {
    adminPersonalForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const submitBtn = e.target.querySelector('button[type="submit"]');
      const name = document.getElementById('adminName').value.trim();
      const email = document.getElementById('adminEmail').value.trim();
      const mobileNumber = document.getElementById('adminMobile').value.trim();
      
      // Validate email
      if (!email) {
        showToast('Email is required', 'error');
        return;
      }
      
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
      }
      
      // Validate mobile if provided
      if (mobileNumber && !validateMobile(mobileNumber)) {
        showToast('Please enter a valid 10-digit mobile number', 'error');
        return;
      }

      showLoading(submitBtn);

      try {
        // Update admin profile with new email and mobile
        await api.updateAdminProfile({ name, email, mobileNumber: mobileNumber || null });
        await loadUserData();
      } catch (error) {
        console.error('Error updating admin profile:', error);
        if (error.error && error.error.message && error.error.message.includes('already exists')) {
          showToast('Email address is already in use', 'error');
        } else if (error.error && error.error.message) {
          showToast(error.error.message, 'error');
        } else {
          showToast('Error updating profile', 'error');
        }
      } finally {
        hideLoading(submitBtn);
      }
    });
  }

  // Admin password form
  document.getElementById('adminPasswordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const currentPassword = document.getElementById('adminCurrentPassword').value;
    const newPassword = document.getElementById('adminNewPassword').value;
    const confirmPassword = document.getElementById('adminConfirmPassword').value;

    if (newPassword !== confirmPassword) {
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
      e.target.reset();
    } catch (error) {
      console.error('Error changing password:', error);
    } finally {
      hideLoading(submitBtn);
    }
  });

  // UPI form
  document.getElementById('upiForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const upiId = document.getElementById('upiId').value.trim();

    if (!validateUPI(upiId)) {
      showToast('Please enter a valid UPI ID', 'error');
      return;
    }

    showLoading(submitBtn);

    try {
      await api.updateUpiId(upiId);
    } catch (error) {
      console.error('Error updating UPI ID:', error);
    } finally {
      hideLoading(submitBtn);
    }
  });

  // QR form
  document.getElementById('qrForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const qrCode = document.getElementById('qrCode').files[0];

    if (!qrCode) {
      showToast('Please select a QR code image', 'error');
      return;
    }

    showLoading(submitBtn);

    try {
      const data = await api.uploadQRCode(qrCode);
      
      // Update preview with cache-busting parameter
      const preview = document.getElementById('qrPreview');
      preview.src = data.qrCodeUrl + '?t=' + new Date().getTime();
      preview.style.display = 'block';
      
      // Reset file input
      document.getElementById('qrCode').value = '';
      
      showToast('QR code uploaded successfully!', 'success');
    } catch (error) {
      console.error('Error uploading QR code:', error);
    } finally {
      hideLoading(submitBtn);
    }
  });
}

// Load settings
async function loadSettings() {
  try {
    const data = await api.getPaymentDetails();
    console.log('Payment details loaded:', data); // Debug log
    
    // Set UPI ID
    if (data.upiId) {
      document.getElementById('upiId').value = data.upiId;
    }

    // Set QR preview with cache-busting parameter
    if (data.qrCodeUrl) {
      console.log('QR Code URL:', data.qrCodeUrl); // Debug log
      const preview = document.getElementById('qrPreview');
      const qrUrlWithCacheBust = data.qrCodeUrl + '?t=' + new Date().getTime();
      console.log('QR Code URL with cache bust:', qrUrlWithCacheBust); // Debug log
      
      preview.src = qrUrlWithCacheBust;
      preview.style.display = 'block';
      
      // Add error handler for image loading
      preview.onerror = function() {
        console.error('Failed to load QR code image:', qrUrlWithCacheBust);
        preview.style.display = 'none';
      };
      
      preview.onload = function() {
        console.log('QR code image loaded successfully');
      };
    } else {
      console.log('No QR code URL found in response');
    }
  } catch (error) {
    console.error('Error loading settings:', error);
  }
}

// Setup modals
function setupModals() {
  const modals = document.querySelectorAll('.modal');
  
  modals.forEach(modal => {
    const closeBtn = modal.querySelector('.modal-close');
    
    closeBtn.addEventListener('click', () => {
      modal.style.display = 'none';
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.style.display = 'none';
      }
    });
  });
}

// Setup refresh buttons
function setupRefreshButtons() {
  document.getElementById('refreshDeposits').addEventListener('click', () => {
    loadPendingDeposits();
    loadOverview();
  });

  document.getElementById('refreshWithdrawals').addEventListener('click', () => {
    loadPendingWithdrawals();
    loadOverview();
  });

  const refreshBonusClaims = document.getElementById('refreshBonusClaims');
  if (refreshBonusClaims) {
    refreshBonusClaims.addEventListener('click', () => {
      loadPendingBonusClaims();
      loadOverview();
    });
  }
}

// Load pending bonus claims
async function loadPendingBonusClaims() {
  try {
    const container = document.getElementById('bonusClaimsList');
    container.innerHTML = '<p class="loading-text">Loading bonus claims...</p>';

    const data = await api.getPendingBonusClaims(currentBonusClaimsPage, 20);

    if (data.claims.length === 0) {
      container.innerHTML = '<p class="empty-state">No pending bonus claims</p>';
      return;
    }

    container.innerHTML = data.claims.map(claim => `
      <div class="request-card-compact">
        <div class="request-row">
          <div class="request-left">
            <p class="request-user-name">${escapeHtml(claim.userName || claim.userEmail.split('@')[0])} • ${escapeHtml(claim.userEmail)}</p>
            <p class="request-date">${formatDate(claim.createdAt)}</p>
            <p class="request-details-text">🎁 Referral bonus from: ${escapeHtml(claim.referredUserName || 'Anonymous')}</p>
          </div>
          <div class="request-center">
            <p class="request-amount-compact">${formatAmount(claim.amount)}</p>
          </div>
          <div class="request-right">
            <button class="btn btn-success btn-compact" onclick="approveBonusClaim('${escapeHtml(claim.id)}')">
              ✓ Approve
            </button>
            <button class="btn btn-danger btn-compact" onclick="rejectBonusClaim('${escapeHtml(claim.id)}')">
              ✗ Reject
            </button>
          </div>
        </div>
      </div>
    `).join('');
  } catch (error) {
    console.error('Error loading bonus claims:', error);
    document.getElementById('bonusClaimsList').innerHTML = '<p class="error-text">Failed to load bonus claims</p>';
  }
}

// Approve bonus claim
window.approveBonusClaim = async function(claimId) {
  if (!confirm('Are you sure you want to approve this bonus claim?')) return;

  try {
    await api.approveBonusClaim(claimId);
    loadPendingBonusClaims();
    loadOverview();
  } catch (error) {
    console.error('Error approving bonus claim:', error);
  }
};

// Reject bonus claim
window.rejectBonusClaim = async function(claimId) {
  const reason = prompt('Enter rejection reason (optional):');
  if (reason === null) return; // User cancelled

  try {
    await api.rejectBonusClaim(claimId, reason);
    loadPendingBonusClaims();
    loadOverview();
  } catch (error) {
    console.error('Error rejecting bonus claim:', error);
  }
};

// Setup logout
function setupLogout() {
  document.getElementById('logoutBtn').addEventListener('click', async () => {
    await api.logout();
    window.location.href = '/login';
  });
}

// Load history
async function loadHistory() {
  try {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="loading-text">Loading history...</td></tr>';

    const data = await api.getAdminHistory(currentHistoryPage, 20, currentHistoryFilter);

    if (data.transactions.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No transactions found</td></tr>';
      return;
    }

    tbody.innerHTML = data.transactions.map(tx => `
      <tr>
        <td data-label="Date">${formatDate(tx.createdAt)}</td>
        <td data-label="User">
          <div class="table-user-info">
            <span class="table-user-name">${escapeHtml(tx.userName || 'N/A')}</span>
            <span class="table-user-email">${escapeHtml(tx.userEmail)}</span>
          </div>
        </td>
        <td data-label="Type">
          <span class="table-type">
            ${tx.type === 'deposit' ? '📥 Deposit' : '📤 Withdrawal'}
          </span>
        </td>
        <td data-label="Amount" class="table-amount">${formatAmount(tx.amount)}</td>
        <td data-label="Status">
          <span class="status-badge ${getStatusClass(tx.status)}">${escapeHtml(tx.status)}</span>
        </td>
        <td data-label="Action">
          <div class="table-actions">
            ${tx.type === 'deposit' && tx.paymentProofUrl ? 
              `<button class="btn-view" onclick="viewScreenshot('${escapeHtml(tx.paymentProofUrl)}')">View Proof</button>` : 
              '<span style="color: var(--text-secondary); font-size: 0.875rem;">N/A</span>'
            }
          </div>
        </td>
      </tr>
    `).join('');

    // Setup filter tabs
    setupHistoryFilters();
  } catch (error) {
    console.error('Error loading history:', error);
    document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="6" class="error-text">Failed to load history</td></tr>';
  }
}

// Setup history filters
function setupHistoryFilters() {
  const filterTabs = document.querySelectorAll('#historyView .filter-tab');
  
  filterTabs.forEach(tab => {
    tab.onclick = () => {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      
      currentHistoryFilter = tab.dataset.filter;
      currentHistoryPage = 1;
      loadHistory();
    };
  });
}

// View screenshot in modal
window.viewScreenshot = function(url) {
  // Create modal if doesn't exist
  let modal = document.getElementById('screenshotModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'screenshotModal';
    modal.className = 'screenshot-modal';
    modal.innerHTML = `
      <div class="screenshot-content">
        <button class="screenshot-close" onclick="closeScreenshot()">&times;</button>
        <img id="screenshotImg" src="" alt="Payment Proof">
      </div>
    `;
    document.body.appendChild(modal);

    // Close on background click
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeScreenshot();
      }
    });
  }

  document.getElementById('screenshotImg').src = url;
  modal.classList.add('active');
};

window.closeScreenshot = function() {
  const modal = document.getElementById('screenshotModal');
  if (modal) {
    modal.classList.remove('active');
  }
};

// View proof modal (same as viewScreenshot but with different name for clarity)
window.viewProofModal = function(url) {
  window.viewScreenshot(url);
};

// Setup mobile menu
function setupMobileMenu() {
  const hamburger = document.getElementById('hamburgerMenu');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('mobileOverlay');
  const mobileAvatar = document.getElementById('mobileAdminAvatar');

  if (!hamburger) return;

  hamburger.addEventListener('click', () => {
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
  });

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
  });

  // Mobile avatar click to open sidebar
  if (mobileAvatar) {
    mobileAvatar.addEventListener('click', () => {
      sidebar.classList.add('mobile-open');
      overlay.classList.add('active');
    });
  }

  // Close menu when clicking nav items on mobile
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
      }
    });
  });

  // Setup bottom nav items
  document.querySelectorAll('.bottom-nav-item').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const view = item.dataset.view;
      if (view) {
        switchView(view);
      }
    });
  });
}
// Users Management
let currentUsersPage = 1;
let usersSearchQuery = '';

// Load users
async function loadUsers() {
  try {
    const container = document.getElementById('usersTableBody');
    container.innerHTML = '<tr><td colspan="8" class="loading-text">Loading users...</td></tr>';

    const data = await api.getUsers(currentUsersPage, 20, usersSearchQuery);

    if (data.users.length === 0) {
      container.innerHTML = '<tr><td colspan="8" class="empty-state">No users found</td></tr>';
      return;
    }

    // Update stats
    document.getElementById('totalUsersCount').textContent = data.total;
    document.getElementById('activeUsersCount').textContent = data.activeCount || 0;
    document.getElementById('inactiveUsersCount').textContent = data.inactiveCount || 0;

    // Render users table
    container.innerHTML = data.users.map(user => `
      <tr>
        <td data-label="Name">
          <a href="#" class="user-name-link" data-user-id="${escapeHtml(user.id)}" data-user-name="${escapeHtml(user.name || 'N/A')}" data-user-email="${escapeHtml(user.email)}">
            ${escapeHtml(user.name || 'N/A')}
          </a>
        </td>
        <td data-label="Email">${escapeHtml(user.email)}</td>
        <td data-label="Aadhar">${user.aadharNumber ? escapeHtml(user.aadharNumber) : '<span style="color: var(--text-muted);">NULL</span>'}</td>
        <td data-label="PAN">${user.panNumber ? escapeHtml(user.panNumber) : '<span style="color: var(--text-muted);">NULL</span>'}</td>
        <td data-label="Status">
          <span class="user-status ${user.isActive ? 'active' : 'inactive'}">
            ${user.isActive ? 'Active' : 'Inactive'}
          </span>
        </td>
        <td data-label="Joined">${formatDate(user.createdAt)}</td>
        <td data-label="Balance">${formatAmount(user.walletBalance || 0)}</td>
        <td data-label="Actions">
          <div class="user-actions">
            ${user.isActive ? 
              `<button class="btn-deactivate" data-user-id="${escapeHtml(user.id)}" data-action="deactivate">Deactivate</button>` :
              `<button class="btn-activate" data-user-id="${escapeHtml(user.id)}" data-action="activate">Activate</button>`
            }
            ${!user.isAdmin ? 
              `<button class="btn-delete" data-user-id="${escapeHtml(user.id)}" data-user-name="${escapeHtml(user.name || user.email)}" data-action="delete">Delete</button>` :
              ''
            }
          </div>
        </td>
      </tr>
    `).join('');

    // Setup event delegation for user name links
    setupUserNameClickHandlers();

    // Setup pagination
    setupUsersPagination(data.total, data.page, data.totalPages);

  } catch (error) {
    console.error('Error loading users:', error);
    document.getElementById('usersTableBody').innerHTML = 
      '<tr><td colspan="8" class="error-text">Error loading users</td></tr>';
  }
}

// Setup users pagination
function setupUsersPagination(total, currentPage, totalPages) {
  const container = document.getElementById('usersPagination');
  
  if (totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  let paginationHTML = '';
  
  // Previous button
  if (currentPage > 1) {
    paginationHTML += `<button class="pagination-btn" onclick="loadUsersPage(${currentPage - 1})">Previous</button>`;
  }
  
  // Page numbers
  for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
    paginationHTML += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="loadUsersPage(${i})">${i}</button>`;
  }
  
  // Next button
  if (currentPage < totalPages) {
    paginationHTML += `<button class="pagination-btn" onclick="loadUsersPage(${currentPage + 1})">Next</button>`;
  }
  
  container.innerHTML = paginationHTML;
}

// Load users page
function loadUsersPage(page) {
  currentUsersPage = page;
  loadUsers();
}

// Setup event handlers for user name links using event delegation
function setupUserNameClickHandlers() {
  console.log('Setting up user name click handlers');
  
  // Use event delegation on the table container (which doesn't get replaced)
  const tableContainer = document.querySelector('.users-table-container');
  if (!tableContainer) {
    console.error('Users table container not found');
    return;
  }
  
  console.log('Table container found:', tableContainer);
  
  // Remove existing listener if any
  if (tableContainer._userClickHandler) {
    tableContainer.removeEventListener('click', tableContainer._userClickHandler);
    console.log('Removed existing click handler');
  }
  
  // Create and store the handler
  tableContainer._userClickHandler = handleUserTableClick;
  
  // Add event listener to the container
  tableContainer.addEventListener('click', tableContainer._userClickHandler);
  
  console.log('User click handlers set up successfully');
}

// Handle clicks on user table
function handleUserTableClick(e) {
  console.log('Table clicked:', e.target, 'Classes:', e.target.className);
  
  // Handle user name link clicks
  if (e.target.classList.contains('user-name-link')) {
    e.preventDefault();
    
    const userId = e.target.dataset.userId;
    const userName = e.target.dataset.userName;
    const userEmail = e.target.dataset.userEmail;
    
    console.log('User name clicked:', { userId, userName, userEmail });
    
    // Call showUserHistory with the user data
    showUserHistory(userId, userName, userEmail);
    return;
  }
  
  // Handle activate/deactivate button clicks
  if (e.target.classList.contains('btn-deactivate') || e.target.classList.contains('btn-activate')) {
    e.preventDefault();
    e.stopPropagation();
    
    const userId = e.target.dataset.userId;
    const action = e.target.dataset.action;
    const activate = action === 'activate';
    
    console.log('User status button clicked:', { userId, action, activate });
    
    toggleUserStatus(userId, activate);
    return;
  }

  // Handle delete button clicks
  if (e.target.classList.contains('btn-delete')) {
    e.preventDefault();
    e.stopPropagation();
    
    const userId = e.target.dataset.userId;
    const userName = e.target.dataset.userName;
    
    console.log('Delete user button clicked:', { userId, userName });
    
    deleteUser(userId, userName);
    return;
  }
}

// Toggle user status
async function toggleUserStatus(userId, activate) {
  console.log('toggleUserStatus called with:', { userId, activate });
  
  try {
    const action = activate ? 'activate' : 'deactivate';
    const confirmMessage = `Are you sure you want to ${action} this user?`;
    
    if (!confirm(confirmMessage)) {
      console.log('User cancelled the action');
      return;
    }

    console.log('Calling API to toggle user status...');
    const result = await api.toggleUserStatus(userId, activate);
    console.log('API response:', result);
    
    showToast(`User ${action}d successfully`, 'success');
    loadUsers(); // Reload users list
    
  } catch (error) {
    console.error(`Error ${activate ? 'activating' : 'deactivating'} user:`, error);
    showToast(`Error ${activate ? 'activating' : 'deactivating'} user`, 'error');
  }
}

// Delete user
async function deleteUser(userId, userName) {
  console.log('deleteUser called with:', { userId, userName });
  
  try {
    const confirmMessage = `⚠️ WARNING: This will permanently delete "${userName}" and all their data including:\n\n• User account\n• Wallet balance\n• All deposit requests\n• All withdrawal requests\n\nThis action CANNOT be undone!\n\nType "DELETE" to confirm:`;
    
    const confirmation = prompt(confirmMessage);
    
    if (confirmation !== 'DELETE') {
      console.log('User cancelled deletion or incorrect confirmation');
      showToast('Deletion cancelled', 'info');
      return;
    }

    console.log('Calling API to delete user...');
    
    // Show loading state
    const deleteButtons = document.querySelectorAll(`[data-user-id="${userId}"][data-action="delete"]`);
    deleteButtons.forEach(btn => {
      btn.disabled = true;
      btn.textContent = 'Deleting...';
    });
    
    const result = await api.deleteUser(userId);
    console.log('API response:', result);
    
    showToast(`User "${userName}" deleted successfully`, 'success');
    loadUsers(); // Reload users list
    
  } catch (error) {
    console.error('Error deleting user:', error);
    
    // Re-enable buttons
    const deleteButtons = document.querySelectorAll(`[data-user-id="${userId}"][data-action="delete"]`);
    deleteButtons.forEach(btn => {
      btn.disabled = false;
      btn.textContent = 'Delete';
    });
    
    if (error.error && error.error.message) {
      showToast(error.error.message, 'error');
    } else if (error.message) {
      if (error.message.includes('Cannot delete admin')) {
        showToast('Cannot delete admin users', 'error');
      } else if (error.message.includes('not found')) {
        showToast('User not found', 'error');
      } else {
        showToast(error.message, 'error');
      }
    } else {
      showToast('Error deleting user', 'error');
    }
  }
}

// Show user transaction history
async function showUserHistory(userId, userName, userEmail) {
  try {
    console.log('=== Loading User Transaction History ===');
    console.log('User ID:', userId);
    console.log('User Name:', userName);
    console.log('User Email:', userEmail);
    
    const modal = document.getElementById('userHistoryModal');
    const modalUserName = document.getElementById('modalUserName');
    const modalUserEmail = document.getElementById('modalUserEmail');
    const modalUserAvatar = document.getElementById('modalUserAvatar');
    const modalUserBalance = document.getElementById('modalUserBalance');
    const modalUserTxCount = document.getElementById('modalUserTxCount');
    const modalTransactionsList = document.getElementById('modalTransactionsList');

    // Reset modal state first
    console.log('Resetting modal state...');
    modalTransactionsList.innerHTML = '<p class="loading-text">Loading transactions...</p>';
    modalUserBalance.textContent = '₹0.00';
    modalUserTxCount.textContent = '0';

    // Set user info (using textContent is safe, but keeping for consistency)
    modalUserName.textContent = userName || 'N/A';
    modalUserEmail.textContent = userEmail;
    modalUserAvatar.textContent = (userName || userEmail).charAt(0).toUpperCase();

    // Show modal
    console.log('Opening modal...');
    modal.classList.add('active');
    modal.style.display = 'flex'; // Force display

    // Load user data and transactions
    console.log('Fetching user data and transactions...');
    
    let userData, transactionsData;
    
    try {
      userData = await api.getUserById(userId);
      console.log('User data loaded');
    } catch (userError) {
      console.error('Error loading user data:', userError);
      userData = { walletBalance: 0 };
    }
    
    try {
      transactionsData = await api.getUserTransactions(userId, 1, 50);
      console.log(`Loaded ${transactionsData.transactions.length} transactions (Total: ${transactionsData.total})`);
    } catch (txError) {
      console.error('Error loading transactions:', txError);
      transactionsData = { transactions: [], total: 0 };
    }

    // Calculate balance from transactions
    let calculatedBalance = 0;
    let depositTotal = 0;
    let withdrawalTotal = 0;
    
    console.log('Calculating balance from transactions...');
    
    // Sort transactions by date (oldest first) for correct balance calculation
    const sortedTransactions = [...transactionsData.transactions].sort((a, b) => {
      return new Date(a.createdAt) - new Date(b.createdAt);
    });
    
    console.log(`Processing ${sortedTransactions.length} transactions chronologically (oldest to newest)`);
    
    sortedTransactions.forEach((tx, index) => {
      
      if (tx.status === 'completed' || tx.status === 'approved') {
        const isDeposit = tx.paymentProofUrl || tx.transactionId;
        const isWithdrawal = tx.bankDetails;
        
        if (isDeposit) {
          depositTotal += tx.amount;
          calculatedBalance += tx.amount; // Add deposits
          console.log(`  ${index + 1}. Deposit +₹${(tx.amount / 100).toFixed(2)} -> Balance: ₹${(calculatedBalance / 100).toFixed(2)}`);
        } else if (isWithdrawal) {
          withdrawalTotal += tx.amount;
          calculatedBalance -= tx.amount; // Subtract withdrawals
          console.log(`  ${index + 1}. Withdrawal -₹${(tx.amount / 100).toFixed(2)} -> Balance: ₹${(calculatedBalance / 100).toFixed(2)}`);
        }
      } else {
        console.log(`  ${index + 1}. Skipped (${tx.status})`);
      }
    });
    
    console.log('Final Balance Summary:');
    console.log(`  Total Deposits: ₹${(depositTotal / 100).toFixed(2)}`);
    console.log(`  Total Withdrawals: ₹${(withdrawalTotal / 100).toFixed(2)}`);
    console.log(`  Calculated Balance from last 50 transactions: ₹${(calculatedBalance / 100).toFixed(2)}`);
    console.log(`  Actual Database Balance: ₹${(userData.walletBalance / 100).toFixed(2)}`);

    // Update user stats - Use the actual balance from the database
    modalUserBalance.textContent = formatAmount(userData.walletBalance);
    modalUserTxCount.textContent = transactionsData.total || 0;
    
    console.log(`Updated modal: Balance = ₹${(userData.walletBalance/ 100).toFixed(2)}, Total Transactions = ${transactionsData.total}`);

    // Check if modal is still active (user might have closed it)
    if (!modal.classList.contains('active')) {
      console.log('Modal was closed during loading, aborting...');
      return;
    }

    // Render transactions (newest first for display)
    if (transactionsData.transactions.length === 0) {
      modalTransactionsList.innerHTML = '<p class="empty-state">No transactions found</p>';
      console.log('No transactions to display');
      return;
    }

    console.log(`Rendering ${transactionsData.transactions.length} transactions (newest to oldest for display)`);
    
    // Sort transactions for display (newest first)
    const displayTransactions = [...transactionsData.transactions].sort((a, b) => {
      return new Date(b.createdAt) - new Date(a.createdAt);
    });
    
    modalTransactionsList.innerHTML = displayTransactions.map((tx, index) => {
      // Determine transaction type based on available fields
      const isDeposit = tx.paymentProofUrl || tx.transactionId;
      const isWithdrawal = tx.bankDetails;
      const txType = isDeposit ? 'deposit' : (isWithdrawal ? 'withdrawal' : 'unknown');
      
      return `
        <div class="transaction-item" data-type="${escapeHtml(txType)}">
          <div class="transaction-icon">
            ${txType === 'deposit' ? '+' : txType === 'withdrawal' ? '↗' : '↔'}
          </div>
          <div class="transaction-details">
            <h4>${escapeHtml(txType.charAt(0).toUpperCase() + txType.slice(1))}</h4>
            <p class="transaction-date">${formatDate(tx.createdAt)}</p>
            <p class="transaction-status ${getStatusClass(tx.status)}">${escapeHtml(tx.status)}</p>
          </div>
          <div class="transaction-amount ${txType === 'deposit' ? 'positive' : 'negative'}">
            ${txType === 'deposit' ? '+' : '-'}${formatAmount(tx.amount)}
          </div>
        </div>
      `;
    }).join('');
    
    console.log('Modal rendering complete');
    
    // Re-setup filter handlers after content is loaded
    setupModalFilters();

  } catch (error) {
    console.error('Error loading user history:', error);
    const modalTransactionsList = document.getElementById('modalTransactionsList');
    if (modalTransactionsList) {
      modalTransactionsList.innerHTML = '<p class="error-text">Error loading transactions</p>';
    }
  } finally {
    console.log('=== End User Transaction History ===\n');
  }
}

// Setup users search and refresh
document.addEventListener('DOMContentLoaded', () => {
  // Search functionality
  const searchInput = document.getElementById('userSearchInput');
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        usersSearchQuery = e.target.value.trim();
        currentUsersPage = 1;
        loadUsers();
      }, 500);
    });
  }

  // Refresh button
  const refreshUsersBtn = document.getElementById('refreshUsers');
  if (refreshUsersBtn) {
    refreshUsersBtn.addEventListener('click', () => {
      usersSearchQuery = '';
      currentUsersPage = 1;
      if (searchInput) searchInput.value = '';
      loadUsers();
    });
  }

  // Modal close functionality
  const userHistoryModal = document.getElementById('userHistoryModal');
  if (userHistoryModal) {
    const closeBtn = userHistoryModal.querySelector('.modal-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        console.log('Closing modal via close button');
        closeUserHistoryModal();
      });
    }

    // Close on backdrop click
    userHistoryModal.addEventListener('click', (e) => {
      if (e.target === userHistoryModal) {
        console.log('Closing modal via backdrop click');
        closeUserHistoryModal();
      }
    });
    
    // Setup modal filter tabs
    setupModalFilters();
  }
});

// Setup modal transaction filters
function setupModalFilters() {
  const modal = document.getElementById('userHistoryModal');
  if (!modal) {
    console.log('Modal not found for filter setup');
    return;
  }
  
  console.log('Setting up modal filters');
  
  // Use the same simple approach as history filters
  const filterTabs = modal.querySelectorAll('.filter-tab');
  console.log('Found', filterTabs.length, 'filter tabs in modal');
  
  if (filterTabs.length === 0) {
    console.error('No filter tabs found in modal!');
    return;
  }
  
  filterTabs.forEach((tab, index) => {
    console.log(`Filter tab ${index}:`, tab.textContent, 'filter:', tab.dataset.filter, 'element:', tab);
    
    // Clear any existing onclick
    tab.onclick = null;
    
    // Simple onclick handler like history filters
    tab.onclick = function() {
      const filter = this.dataset.filter;
      console.log('=== MODAL Filter button CLICKED ===');
      console.log('Filter:', filter);
      console.log('Button:', this.textContent);
      console.log('This element:', this);
      
      // Update active state
      filterTabs.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      
      // Filter transactions
      filterModalTransactions(filter);
    };
    
    // Test if onclick is set
    console.log(`Tab ${index} onclick set:`, tab.onclick !== null);
  });
  
  console.log('Modal filter handlers attached to', filterTabs.length, 'buttons');
}

// Filter modal transactions
function filterModalTransactions(filter) {
  console.log('=== Filtering transactions by:', filter, '===');
  const transactionItems = document.querySelectorAll('#userHistoryModal .modal-transactions-list .transaction-item');
  
  console.log('Found', transactionItems.length, 'transaction items');
  
  if (transactionItems.length === 0) {
    console.warn('No transaction items found!');
    return;
  }
  
  let visibleCount = 0;
  transactionItems.forEach((item, index) => {
    const type = item.dataset.type;
    console.log(`Item ${index}: type="${type}", filter="${filter}"`);
    
    if (filter === 'all') {
      item.style.display = 'flex';
      visibleCount++;
    } else if (filter === type) {
      item.style.display = 'flex';
      visibleCount++;
    } else {
      item.style.display = 'none';
    }
  });
  
  console.log(`Filtering complete. Visible: ${visibleCount}, Hidden: ${transactionItems.length - visibleCount}`);
}

// Function to properly close and reset the modal
function closeUserHistoryModal() {
  const modal = document.getElementById('userHistoryModal');
  const modalTransactionsList = document.getElementById('modalTransactionsList');
  
  console.log('Closing and resetting user history modal');
  
  // Hide modal
  if (modal) {
    modal.classList.remove('active');
    modal.style.display = 'none'; // Force hide
  }
  
  // Reset content to prevent stale data
  if (modalTransactionsList) {
    modalTransactionsList.innerHTML = '<p class="loading-text">Loading transactions...</p>';
  }
  
  // Reset user stats
  const modalUserBalance = document.getElementById('modalUserBalance');
  const modalUserTxCount = document.getElementById('modalUserTxCount');
  
  if (modalUserBalance) modalUserBalance.textContent = '₹0.00';
  if (modalUserTxCount) modalUserTxCount.textContent = '0';
  
  console.log('Modal closed and reset');
}

// Add keyboard support for closing modal
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modal = document.getElementById('userHistoryModal');
    if (modal && modal.classList.contains('active')) {
      console.log('Closing modal via ESC key');
      closeUserHistoryModal();
    }
  }
});


// Make functions globally available
window.showUserHistory = showUserHistory;
window.toggleUserStatus = toggleUserStatus;
window.loadUsersPage = loadUsersPage;
window.closeUserHistoryModal = closeUserHistoryModal;
