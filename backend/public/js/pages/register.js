import api from '../services/api.js';
import { AuthUtils } from '../utils/auth.js';
import { showLoading, hideLoading, validateEmail, showToast } from '../utils/helpers.js';

// Check if already logged in
if (AuthUtils.isAuthenticated()) {
  const user = AuthUtils.getUser();
  window.location.href = user.isAdmin ? '/admin-dashboard' : '/user-dashboard';
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

// Handle register form submission
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const name = document.getElementById('name').value.trim();
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirmPassword').value;

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
    const data = await api.register(email, password, name || null);
    
    // Redirect to user dashboard (registration is for users only)
    window.location.href = '/user-dashboard';
  } catch (error) {
    hideLoading(submitBtn);
  }
});
