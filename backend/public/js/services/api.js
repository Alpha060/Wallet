import { API_ENDPOINTS, TOKEN_KEY } from '../config.js';
import { AuthUtils } from '../utils/auth.js';
import { showToast } from '../utils/helpers.js';

class ApiService {
  // Handle API errors
  handleError(error, customMessage) {
    console.error('API Error:', error);
    
    // Check if account is deactivated
    if (error.error && error.error.code === 'ACCOUNT_DEACTIVATED') {
      showToast(error.error.message, 'error');
      // Force logout after a short delay
      setTimeout(() => {
        AuthUtils.clearAuth();
        window.location.href = '/login';
      }, 2000);
      return error.error.message;
    }
    
    if (error.error && error.error.message) {
      showToast(error.error.message, 'error');
      return error.error.message;
    }
    
    showToast(customMessage || 'An error occurred', 'error');
    return customMessage || 'An error occurred';
  }

  // Auth APIs
  async login(email, password, keepLoggedIn = true) {
    try {
      const response = await fetch(API_ENDPOINTS.LOGIN, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      AuthUtils.saveAuth(data.token, data.user, keepLoggedIn);
      return data;
    } catch (error) {
      throw this.handleError(error, 'Login failed');
    }
  }

  async register(email, password, name = null, referralCode = null) {
    try {
      const body = { email, password };
      if (name) body.name = name;
      if (referralCode) body.referralCode = referralCode;

      const response = await fetch(API_ENDPOINTS.REGISTER, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      AuthUtils.saveAuth(data.token, data.user);
      return data;
    } catch (error) {
      throw this.handleError(error, 'Registration failed');
    }
  }

  async logout() {
    try {
      await fetch(API_ENDPOINTS.LOGOUT, {
        method: 'POST',
        headers: AuthUtils.getAuthHeaders()
      });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      AuthUtils.clearAuth();
    }
  }

  async getCurrentUser() {
    try {
      const response = await fetch(API_ENDPOINTS.ME, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch user data');
    }
  }

  async updateProfile(name, currentPassword, newPassword, mobileNumber, aadharNumber, dateOfBirth, panNumber) {
    try {
      const body = {};
      if (name !== undefined) body.name = name;
      if (currentPassword) body.currentPassword = currentPassword;
      if (newPassword) body.newPassword = newPassword;
      if (mobileNumber !== undefined) body.mobileNumber = mobileNumber;
      if (aadharNumber !== undefined) body.aadharNumber = aadharNumber;
      if (dateOfBirth !== undefined) body.dateOfBirth = dateOfBirth;
      if (panNumber !== undefined) body.panNumber = panNumber;

      const response = await fetch(API_ENDPOINTS.UPDATE_PROFILE, {
        method: 'PUT',
        headers: {
          ...AuthUtils.getAuthHeaders(),
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      // Update stored user data
      if (data.user) {
        const currentUser = AuthUtils.getUser();
        AuthUtils.saveAuth(AuthUtils.getToken(), { ...currentUser, ...data.user });
      }

      showToast(data.message || 'Profile updated successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to update profile');
    }
  }

  // Wallet APIs
  async getBalance() {
    try {
      const response = await fetch(API_ENDPOINTS.BALANCE, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch balance');
    }
  }

  async getTransactions(page = 1, limit = 20, type = null) {
    try {
      let url = `${API_ENDPOINTS.TRANSACTIONS}?page=${page}&limit=${limit}`;
      if (type) url += `&type=${type}`;

      const response = await fetch(url, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch transactions');
    }
  }

  // Deposit APIs
  async getPaymentDetails() {
    try {
      const response = await fetch(API_ENDPOINTS.PAYMENT_DETAILS);
      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch payment details');
    }
  }

  async createDeposit(amount, paymentProof, transactionId) {
    try {
      const formData = new FormData();
      formData.append('amount', amount);
      formData.append('paymentProof', paymentProof);
      if (transactionId) {
        formData.append('transactionId', transactionId);
      }

      const response = await fetch(API_ENDPOINTS.CREATE_DEPOSIT, {
        method: 'POST',
        headers: AuthUtils.getAuthHeadersMultipart(),
        body: formData
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('Deposit request submitted successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to create deposit');
    }
  }

  async getMyDeposits(page = 1, limit = 20) {
    try {
      const response = await fetch(`${API_ENDPOINTS.MY_DEPOSITS}?page=${page}&limit=${limit}`, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch deposits');
    }
  }

  // Withdrawal APIs
  async createWithdrawal(amount, bankDetails) {
    try {
      const response = await fetch(API_ENDPOINTS.CREATE_WITHDRAWAL, {
        method: 'POST',
        headers: AuthUtils.getAuthHeaders(),
        body: JSON.stringify({ amount, bankDetails })
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('Withdrawal request submitted successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to create withdrawal');
    }
  }

  async getMyWithdrawals(page = 1, limit = 20) {
    try {
      const response = await fetch(`${API_ENDPOINTS.MY_WITHDRAWALS}?page=${page}&limit=${limit}`, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch withdrawals');
    }
  }

  // Admin APIs
  async getStatistics() {
    try {
      const response = await fetch(API_ENDPOINTS.ADMIN_STATISTICS, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch statistics');
    }
  }

  async getAdminHistory(page = 1, limit = 20, type = 'all') {
    try {
      let url = `${API_ENDPOINTS.ADMIN_HISTORY}?page=${page}&limit=${limit}`;
      if (type && type !== 'all') url += `&type=${type}`;

      const response = await fetch(url, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch history');
    }
  }

  async getPendingDeposits(page = 1, limit = 20) {
    try {
      const response = await fetch(`${API_ENDPOINTS.PENDING_DEPOSITS}?page=${page}&limit=${limit}`, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch pending deposits');
    }
  }

  async getPendingWithdrawals(page = 1, limit = 20) {
    try {
      const response = await fetch(`${API_ENDPOINTS.PENDING_WITHDRAWALS}?page=${page}&limit=${limit}`, {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch pending withdrawals');
    }
  }

  async approveDeposit(depositId) {
    try {
      const response = await fetch(API_ENDPOINTS.APPROVE_DEPOSIT(depositId), {
        method: 'POST',
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('Deposit approved successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to approve deposit');
    }
  }

  async rejectDeposit(depositId, reason) {
    try {
      const response = await fetch(API_ENDPOINTS.REJECT_DEPOSIT(depositId), {
        method: 'POST',
        headers: AuthUtils.getAuthHeaders(),
        body: JSON.stringify({ reason })
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('Deposit rejected', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to reject deposit');
    }
  }

  async confirmWithdrawal(withdrawalId) {
    try {
      const response = await fetch(API_ENDPOINTS.CONFIRM_WITHDRAWAL(withdrawalId), {
        method: 'POST',
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('Withdrawal confirmed successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to confirm withdrawal');
    }
  }

  async rejectWithdrawal(withdrawalId, reason) {
    try {
      const response = await fetch(API_ENDPOINTS.REJECT_WITHDRAWAL(withdrawalId), {
        method: 'POST',
        headers: AuthUtils.getAuthHeaders(),
        body: JSON.stringify({ reason })
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('Withdrawal rejected', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to reject withdrawal');
    }
  }

  async updateUpiId(upiId) {
    try {
      const response = await fetch(API_ENDPOINTS.UPDATE_UPI, {
        method: 'PUT',
        headers: AuthUtils.getAuthHeaders(),
        body: JSON.stringify({ upiId })
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('UPI ID updated successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to update UPI ID');
    }
  }

  async uploadQRCode(qrCodeFile) {
    try {
      const formData = new FormData();
      formData.append('qrCode', qrCodeFile);

      const response = await fetch(API_ENDPOINTS.UPLOAD_QR, {
        method: 'POST',
        headers: AuthUtils.getAuthHeadersMultipart(),
        body: formData
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      showToast('QR code updated successfully', 'success');
      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to upload QR code');
    }
  }

  // User Management APIs (Admin only)
  async getUsers(page = 1, limit = 20, search = '') {
    try {
      const response = await fetch(`${API_ENDPOINTS.ADMIN_USERS}?page=${page}&limit=${limit}&search=${encodeURIComponent(search)}`, {
        method: 'GET',
        headers: AuthUtils.getAuthHeaders(),
        credentials: 'include'
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error?.message || 'Failed to fetch users');
      }

      const data = await response.json();
      console.log(`Found ${data.total} users (page ${page})`);
      
      return data;
    } catch (error) {
      console.error('Get users error:', error);
      throw error;
    }
  }

  async getUserById(userId) {
    try {
      const response = await fetch(`${API_ENDPOINTS.ADMIN_USERS}/${userId}`, {
        method: 'GET',
        headers: AuthUtils.getAuthHeaders(),
        credentials: 'include'
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error?.message || 'Failed to fetch user');
      }

      const data = await response.json();
      console.log('User data loaded:', data.email);
      
      return data;
    } catch (error) {
      console.error('Get user error:', error);
      throw error;
    }
  }

  async toggleUserStatus(userId, activate) {
    try {
      console.log('API: toggleUserStatus called', { userId, activate });
      
      const response = await fetch(`${API_ENDPOINTS.ADMIN_USERS}/${userId}/status`, {
        method: 'PATCH',
        headers: {
          ...AuthUtils.getAuthHeaders(),
          'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({ isActive: activate })
      });

      console.log('API: Response status:', response.status);

      if (!response.ok) {
        const error = await response.json();
        console.error('API: Error response:', error);
        throw new Error(error.error?.message || 'Failed to update user status');
      }

      const data = await response.json();
      console.log(`API: User ${activate ? 'activated' : 'deactivated'} successfully`, data);
      
      return data;
    } catch (error) {
      console.error('API: Toggle user status error:', error);
      throw error;
    }
  }

  async getUserTransactions(userId, page = 1, limit = 50) {
    try {
      const response = await fetch(`${API_ENDPOINTS.ADMIN_USERS}/${userId}/transactions?page=${page}&limit=${limit}`, {
        method: 'GET',
        headers: AuthUtils.getAuthHeaders(),
        credentials: 'include'
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error?.message || 'Failed to fetch user transactions');
      }

      const data = await response.json();
      console.log(`Found ${data.total} transactions for user`);
      
      return data;
    } catch (error) {
      console.error('Get user transactions error:', error);
      throw error;
    }
  }

  // Update admin profile
  async updateAdminProfile(profileData) {
    try {
      const response = await fetch('/api/admin/profile', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${AuthUtils.getToken()}`
        },
        body: JSON.stringify(profileData)
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      // Update stored user data with all fields including mobileNumber
      const currentUser = AuthUtils.getUser();
      if (currentUser && data.admin) {
        const updatedUser = { 
          ...currentUser, 
          email: data.admin.email,
          name: data.admin.name,
          mobileNumber: data.admin.mobileNumber,
          isAdmin: data.admin.is_admin
        };
        const token = AuthUtils.getToken();
        const keepLoggedIn = !!localStorage.getItem(TOKEN_KEY);
        AuthUtils.saveAuth(token, updatedUser, keepLoggedIn);
      }

      showToast('Profile updated successfully', 'success');
      return data;
    } catch (error) {
      console.error('Update admin profile error:', error);
      throw error;
    }
  }

  // Delete user
  async deleteUser(userId) {
    try {
      const response = await fetch(`/api/admin/users/${userId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${AuthUtils.getToken()}`
        }
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      console.error('Delete user error:', error);
      throw error;
    }
  }

  // Referral APIs
  async getMyReferralCode() {
    try {
      const response = await fetch('/api/referrals/my-code', {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch referral code');
    }
  }

  async getMyReferrals() {
    try {
      const response = await fetch('/api/referrals/my-referrals', {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch referrals');
    }
  }

  async getReferralStats() {
    try {
      const response = await fetch('/api/referrals/stats', {
        headers: AuthUtils.getAuthHeaders()
      });

      const data = await response.json();
      
      if (!response.ok) {
        throw data;
      }

      return data;
    } catch (error) {
      throw this.handleError(error, 'Failed to fetch referral stats');
    }
  }
}

export default new ApiService();
