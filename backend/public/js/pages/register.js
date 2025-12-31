import api from '../services/api.js';
import { AuthUtils } from '../utils/auth.js';
import { showLoading, hideLoading, validateEmail, showToast } from '../utils/helpers.js';

// Check if already logged in
if (AuthUtils.isAuthenticated()) {
  const user = AuthUtils.getUser();
  window.location.href = user.isAdmin ? '/admin-dashboard' : '/user-dashboard';
}

// Check for referral code in URL
const urlParams = new URLSearchParams(window.location.search);
const refCode = urlParams.get('ref');
if (refCode) {
  document.getElementById('referralCode').value = refCode.toUpperCase();
  // Verify the referral code
  verifyReferralCode(refCode);
}

// Password toggle functionality
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

// Referral code verification
const referralInput = document.getElementById('referralCode');
const referralHint = document.getElementById('referralHint');
let referralVerificationTimeout;

referralInput.addEventListener('input', (e) => {
  const code = e.target.value.toUpperCase();
  e.target.value = code;
  
  // Clear previous timeout
  clearTimeout(referralVerificationTimeout);
  
  // Reset hint
  referralHint.textContent = '';
  referralHint.style.color = '';
  
  if (code.length === 0) {
    return;
  }
  
  if (code.length < 6) {
    referralHint.textContent = 'Referral code must be 6 characters';
    referralHint.style.color = 'var(--color-text-secondary)';
    return;
  }
  
  // Verify after user stops typing
  referralVerificationTimeout = setTimeout(() => {
    verifyReferralCode(code);
  }, 500);
});

async function verifyReferralCode(code) {
  if (!code || code.length !== 6) return;
  
  try {
    const response = await fetch('/api/referrals/verify', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ referralCode: code })
    });
    
    const data = await response.json();
    
    if (data.valid) {
      referralHint.textContent = `✓ Valid code from ${data.referrerName}`;
      referralHint.style.color = 'var(--color-success)';
    } else {
      referralHint.textContent = '✗ Invalid referral code';
      referralHint.style.color = 'var(--color-error)';
    }
  } catch (error) {
    console.error('Error verifying referral code:', error);
  }
}

// Handle register form submission
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const name = document.getElementById('name').value.trim();
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const referralCode = document.getElementById('referralCode').value.trim();

  // Validate email
  if (!validateEmail(email)) {
    showToast('Please enter a valid email address', 'error');
    return;
  }

  // Validate password length
  if (password.length < 8) {
    showToast('Password must be at least 8 characters long', 'error');
    return;
  }

  // Check if passwords match
  if (password !== confirmPassword) {
    showToast('Passwords do not match', 'error');
    return;
  }

  showLoading(submitBtn);

  try {
    const data = await api.register(email, password, name || null, referralCode || null);
    
    // Redirect to user dashboard (registration is for users only)
    window.location.href = '/user-dashboard';
  } catch (error) {
    hideLoading(submitBtn);
  }
});
