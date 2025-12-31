/**
 * User type definition
 * @typedef {Object} User
 * @property {string} id - UUID
 * @property {string} email - User email (unique)
 * @property {string} passwordHash - Hashed password
 * @property {boolean} isAdmin - Admin flag
 * @property {number} walletBalance - Balance in paise
 * @property {Date} createdAt - Account creation timestamp
 * @property {Date} updatedAt - Last update timestamp
 */

/**
 * User creation input
 * @typedef {Object} CreateUserInput
 * @property {string} email - User email
 * @property {string} passwordHash - Hashed password
 * @property {boolean} [isAdmin] - Optional admin flag
 */

export {};
