// XSS Protection: Escape HTML to prevent Cross-Site Scripting attacks
export function escapeHtml(text) {
  if (!text) return text;
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// Format amount from paise to rupees
export function formatAmount(amountInPaise) {
  const rupees = amountInPaise / 100;
  return `₹${rupees.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Parse amount from rupees to paise
export function parseAmount(rupees) {
  return Math.round(parseFloat(rupees) * 100);
}

// Format date
export function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleString('en-IN', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

// Get status badge class
export function getStatusClass(status) {
  const statusMap = {
    'pending': 'status-pending',
    'approved': 'status-approved',
    'rejected': 'status-rejected',
    'completed': 'status-completed',
    'confirmed': 'status-completed'
  };
  return statusMap[status] || 'status-pending';
}

// Show toast notification
export function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.classList.add('show');
  }, 100);
  
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Show loading spinner
export function showLoading(element) {
  element.disabled = true;
  element.dataset.originalText = element.textContent;
  element.innerHTML = '<span class="spinner"></span> Loading...';
}

// Hide loading spinner
export function hideLoading(element) {
  element.disabled = false;
  element.textContent = element.dataset.originalText || 'Submit';
}

// Validate email
export function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

// Validate UPI ID
export function validateUPI(upiId) {
  const re = /^[\w.-]+@[\w.-]+$/;
  return re.test(upiId);
}

// Validate mobile number (10 digits)
export function validateMobile(mobile) {
  if (!mobile) return false;
  const mobileRegex = /^[0-9]{10}$/;
  return mobileRegex.test(mobile);
}

// Validate Aadhar number (12 digits)
export function validateAadhar(aadhar) {
  if (!aadhar) return false;
  const aadharRegex = /^[0-9]{12}$/;
  return aadharRegex.test(aadhar);
}

// Validate PAN number (ABCDE1234F format)
export function validatePAN(pan) {
  if (!pan) return false;
  const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
  return panRegex.test(pan.toUpperCase());
}

// Format date for input field (YYYY-MM-DD)
export function formatDateForInput(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toISOString().split('T')[0];
}

// Validate IFSC code
export function validateIFSC(ifsc) {
  const re = /^[A-Z]{4}0[A-Z0-9]{6}$/;
  return re.test(ifsc);
}

// Debounce function
export function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
