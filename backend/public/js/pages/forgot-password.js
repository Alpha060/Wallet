import { showToast, showLoading, hideLoading } from '../utils/helpers.js';

// API Base URL
const API_BASE_URL = '/api';

// DOM Elements
const verifyIdentityForm = document.getElementById('verifyIdentityForm');
const resetPasswordForm = document.getElementById('resetPasswordForm');
const verificationInput = document.getElementById('verificationInput');
const verificationLabel = document.getElementById('verificationLabel');
const verificationHint = document.getElementById('verificationHint');
const verificationMethods = document.querySelectorAll('input[name="verificationMethod"]');

let verifiedEmail = '';

// Handle verification method change
verificationMethods.forEach(radio => {
  radio.addEventListener('change', (e) => {
    const method = e.target.value;
    updateVerificationInput(method);
    // Show the input group when a method is selected
    document.getElementById('verificationInputGroup').style.display = 'block';
  });
});

// Update verification input based on selected method
function updateVerificationInput(method) {
  switch (method) {
    case 'dob':
      verificationInput.type = 'date';
      verificationInput.placeholder = '';
      verificationInput.maxLength = null;
      verificationInput.pattern = null;
      verificationInput.max = '2010-12-31';
      verificationLabel.textContent = 'Date of Birth';
      verificationHint.textContent = 'Enter your date of birth (dd/mm/yyyy)';
      break;
    
    case 'pan':
      verificationInput.type = 'text';
      verificationInput.placeholder = 'Enter PAN (e.g., ABCDE1234F)';
      verificationInput.maxLength = 10;
      verificationInput.pattern = '[A-Z]{5}[0-9]{4}[A-Z]{1}';
      verificationInput.max = null;
      verificationInput.style.textTransform = 'uppercase';
      verificationLabel.textContent = 'PAN Number';
      verificationHint.textContent = 'Format: 5 letters, 4 digits, 1 letter (e.g., ABCDE1234F)';
      break;
    
    case 'aadhar':
      verificationInput.type = 'text';
      verificationInput.placeholder = 'Enter 12-digit Aadhar number';
      verificationInput.maxLength = 12;
      verificationInput.pattern = '[0-9]{12}';
      verificationInput.max = null;
      verificationInput.style.textTransform = 'none';
      verificationLabel.textContent = 'Aadhar Number';
      verificationHint.textContent = 'Enter your 12-digit Aadhar number';
      break;
  }
  
  // Clear the input value when method changes
  verificationInput.value = '';
  
  // Trigger animation
  const inputGroup = document.getElementById('verificationInputGroup');
  inputGroup.style.animation = 'none';
  setTimeout(() => {
    inputGroup.style.animation = 'fadeIn 0.3s ease-in-out';
  }, 10);
}

// Handle identity verification
verifyIdentityForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const submitBtn = verifyIdentityForm.querySelector('button[type="submit"]');
  const email = document.getElementById('verifyEmail').value.trim();
  const selectedMethod = document.querySelector('input[name="verificationMethod"]:checked');
  
  if (!selectedMethod) {
    showToast('Please select a verification method', 'error');
    return;
  }
  
  const method = selectedMethod.value;
  const value = verificationInput.value.trim();
  
  if (!email || !value) {
    showToast('Please fill in all fields', 'error');
    return;
  }
  
  // Validate input based on method
  if (method === 'pan' && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(value)) {
    showToast('Invalid PAN format', 'error');
    return;
  }
  
  if (method === 'aadhar' && !/^[0-9]{12}$/.test(value)) {
    showToast('Invalid Aadhar number', 'error');
    return;
  }
  
  showLoading(submitBtn);
  
  try {
    const response = await fetch(`${API_BASE_URL}/auth/verify-identity`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email,
        verificationType: method,
        verificationValue: value
      })
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'Verification failed');
    }
    
    // Store verified email for password reset
    verifiedEmail = email;
    
    // Show success and switch to reset password form
    showToast('Identity verified successfully!', 'success');
    verifyIdentityForm.style.display = 'none';
    resetPasswordForm.style.display = 'block';
    
  } catch (error) {
    console.error('Verification error:', error);
    showToast(error.message || 'Verification failed. Please check your details.', 'error');
  } finally {
    hideLoading(submitBtn);
  }
});

// Handle password reset
resetPasswordForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const submitBtn = resetPasswordForm.querySelector('button[type="submit"]');
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  
  // Validate passwords
  if (newPassword.length < 8) {
    showToast('Password must be at least 8 characters long', 'error');
    return;
  }
  
  if (newPassword !== confirmPassword) {
    showToast('Passwords do not match', 'error');
    return;
  }
  
  showLoading(submitBtn);
  
  try {
    const response = await fetch(`${API_BASE_URL}/auth/reset-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: verifiedEmail,
        newPassword
      })
    });
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'Password reset failed');
    }
    
    // Show success message
    showToast('Password reset successfully! Redirecting to login...', 'success');
    
    // Redirect to login after 2 seconds
    setTimeout(() => {
      window.location.href = '/login';
    }, 2000);
    
  } catch (error) {
    console.error('Password reset error:', error);
    showToast(error.message || 'Failed to reset password. Please try again.', 'error');
  } finally {
    hideLoading(submitBtn);
  }
});

// Initialize with default method
updateVerificationInput('dob');
