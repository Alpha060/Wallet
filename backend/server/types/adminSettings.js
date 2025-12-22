/**
 * AdminSettings type definition
 * @typedef {Object} AdminSettings
 * @property {string} id - UUID
 * @property {string|null} qrCodeUrl - URL to QR code image
 * @property {string|null} upiId - UPI ID for payments
 * @property {Date} updatedAt - Last update timestamp
 */

/**
 * Update QR code input
 * @typedef {Object} UpdateQRCodeInput
 * @property {string} qrCodeUrl - URL to new QR code image
 */

/**
 * Update UPI ID input
 * @typedef {Object} UpdateUpiIdInput
 * @property {string} upiId - New UPI ID
 */

export {};
