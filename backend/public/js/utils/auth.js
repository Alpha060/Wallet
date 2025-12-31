import { TOKEN_KEY, USER_KEY } from '../config.js';

export const AuthUtils = {
  // Save auth data with optional persistent storage
  saveAuth(token, user, keepLoggedIn = true) {
    const storage = keepLoggedIn ? localStorage : sessionStorage;
    storage.setItem(TOKEN_KEY, token);
    storage.setItem(USER_KEY, JSON.stringify(user));
    
    // Clear from the other storage to avoid conflicts
    const otherStorage = keepLoggedIn ? sessionStorage : localStorage;
    otherStorage.removeItem(TOKEN_KEY);
    otherStorage.removeItem(USER_KEY);
  },

  // Get token from either storage
  getToken() {
    return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
  },

  // Get user data from either storage
  getUser() {
    const userData = localStorage.getItem(USER_KEY) || sessionStorage.getItem(USER_KEY);
    return userData ? JSON.parse(userData) : null;
  },

  // Check if user is authenticated
  isAuthenticated() {
    return !!this.getToken();
  },

  // Check if user is admin
  isAdmin() {
    const user = this.getUser();
    return user && user.isAdmin === true;
  },

  // Clear auth data from both storages
  clearAuth() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
  },

  // Get auth headers
  getAuthHeaders() {
    const token = this.getToken();
    return {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    };
  },

  // Get auth headers for multipart
  getAuthHeadersMultipart() {
    const token = this.getToken();
    return {
      'Authorization': `Bearer ${token}`
    };
  }
};
