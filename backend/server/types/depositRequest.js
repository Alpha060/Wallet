/**
 * DepositRequest type definition
 * @typedef {Object} DepositRequest
 * @property {string} id - UUID
 * @property {string} userId - User ID (foreign key)
 * @property {number} amount - Amount in paise
 * @property {string|null} transactionId - Optional UPI transaction ID
 * @property {string} paymentProofUrl - URL to payment proof screenshot
 * @property {string} status - 'pending' | 'approved' | 'rejected'
 * @property {Date} createdAt - Request creation timestamp
 * @property {Date|null} processedAt - Processing timestamp
 * @property {string|null} processedBy - Admin user ID who processed
 * @property {string|null} rejectionReason - Reason for rejection
 */

/**
 * Create deposit request input
 * @typedef {Object} CreateDepositInput
 * @property {string} userId - User ID
 * @property {number} amount - Amount in paise
 * @property {string|null} transactionId - Optional transaction ID
 * @property {string} paymentProofUrl - URL to payment proof
 */

export {};
