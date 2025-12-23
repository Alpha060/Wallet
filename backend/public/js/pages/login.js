import api from '../services/api.js';
import { AuthUtils } from '../utils/auth.js';
import { showLoading, hideLoading, validateEmail } from '../utils/helpers.js';

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

// Handle login form submission
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const keepMeLoggedIn = document.getElementById('keepMeLoggedIn').checked;

  // Validate email
  if (!validateEmail(email)) {
    alert('Please enter a valid email address');
    return;
  }

  showLoading(submitBtn);

  try {
    const data = await api.login(email, password, keepMeLoggedIn);
    
    // Redirect based on user role
    if (data.user.isAdmin) {
      window.location.href = '/admin-dashboard';
    } else {
      window.location.href = '/user-dashboard';
    }
  } catch (error) {
    hideLoading(submitBtn);
  }
});
