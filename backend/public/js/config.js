// API Configuration
// Using relative URLs since frontend and backend are served from same origin
const API_BASE_URL = '/api';

export const API_ENDPOINTS = {
  // Auth
  LOGIN: `${API_BASE_URL}/auth/login`,
  REGISTER: `${API_BASE_URL}/auth/register`,
  LOGOUT: `${API_BASE_URL}/auth/logout`,
  ME: `${API_BASE_URL}/auth/me`,
  UPDATE_PROFILE: `${API_BASE_URL}/auth/profile`,
  
  // Wallet
  BALANCE: `${API_BASE_URL}/wallet/balance`,
  TRANSACTIONS: `${API_BASE_URL}/wallet/transactions`,
  
  // Deposits
  PAYMENT_DETAILS: `${API_BASE_URL}/deposits/payment-details`,
  CREATE_DEPOSIT: `${API_BASE_URL}/deposits/create`,
  MY_DEPOSITS: `${API_BASE_URL}/deposits/my-deposits`,
  
  // Withdrawals
  CREATE_WITHDRAWAL: `${API_BASE_URL}/withdrawals/create`,
  MY_WITHDRAWALS: `${API_BASE_URL}/withdrawals/my-withdrawals`,
  
  // Admin
  ADMIN_STATISTICS: `${API_BASE_URL}/admin/statistics`,
  ADMIN_HISTORY: `${API_BASE_URL}/admin/history`,
  PENDING_DEPOSITS: `${API_BASE_URL}/admin/pending-deposits`,
  PENDING_WITHDRAWALS: `${API_BASE_URL}/admin/pending-withdrawals`,
  APPROVE_DEPOSIT: (id) => `${API_BASE_URL}/admin/deposits/${id}/approve`,
  REJECT_DEPOSIT: (id) => `${API_BASE_URL}/admin/deposits/${id}/reject`,
  CONFIRM_WITHDRAWAL: (id) => `${API_BASE_URL}/admin/withdrawals/${id}/confirm`,
  REJECT_WITHDRAWAL: (id) => `${API_BASE_URL}/admin/withdrawals/${id}/reject`,
  UPDATE_UPI: `${API_BASE_URL}/admin/upi-id`,
  UPLOAD_QR: `${API_BASE_URL}/admin/qr-code`,
  ADMIN_USERS: `${API_BASE_URL}/admin/users`
};

export const TOKEN_KEY = 'wallet_auth_token';
export const USER_KEY = 'wallet_user_data';
