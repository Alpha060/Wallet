/**
 * WithdrawalRequest type definition
 * @typedef {Object} WithdrawalRequest
 * @property {string} id - UUID
 * @property {string} userId - User ID (foreign key)
 * @property {number} amount - Amount in paise
 * @property {Object} bankDetails - Bank details (JSONB)
 * @property {string} status - 'pending' | 'completed' | 'rejected'
 * @property {Date} createdAt - Request creation timestamp
 * @property {Date|null} processedAt - Processing timestamp
 * @property {string|null} processedBy - Admin user ID who processed
 * @property {string|null} rejectionReason - Reason for rejection
 */

/**
 * Bank details type
 * @typedef {Object} BankDetails
 * @property {string} [upiId] - UPI ID (optional)
 * @property {string} [accountNumber] - Bank account number (optional)
 * @property {string} [ifscCode] - IFSC code (optional)
 * @property {string} [accountHolderName] - Account holder name (optional)
 */

/**
 * Create withdrawal request input
 * @typedef {Object} CreateWithdrawalInput
 * @property {string} userId - User ID
 * @property {number} amount - Amount in paise
 * @property {BankDetails} bankDetails - Bank details
 */

export {};
