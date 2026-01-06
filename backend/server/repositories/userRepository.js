import pool from '../database/db.js';

/**
 * UserRepository handles all database operations for users
 */
class UserRepository {
  /**
   * Create a new user
   * @param {string} email - User email
   * @param {string} passwordHash - Hashed password
   * @param {boolean} isAdmin - Admin flag (default: false)
   * @param {string} name - User name (optional)
   * @returns {Promise<Object>} Created user object
   */
  async create(email, passwordHash, isAdmin = false, name = null) {
    const query = `
      INSERT INTO users (email, password_hash, is_admin, wallet_balance, name)
      VALUES ($1, $2, $3, 0, $4)
      RETURNING id, email, name, is_admin as "isAdmin", wallet_balance as "walletBalance", created_at as "createdAt", updated_at as "updatedAt"
    `;
    
    const values = [email, passwordHash, isAdmin, name];
    const result = await pool.query(query, values);
    return result.rows[0];
  }

  /**
   * Find a user by email
   * @param {string} email - User email
   * @returns {Promise<Object|null>} User object or null if not found
   */
  async findByEmail(email) {
    const query = `
      SELECT 
        id, 
        email,
        name,
        mobile_number as "mobileNumber",
        aadhar_number as "aadharNumber",
        date_of_birth as "dateOfBirth",
        pan_number as "panNumber",
        referral_code as "referralCode",
        referred_by as "referredBy",
        password_hash as "passwordHash",
        is_admin as "isAdmin",
        is_active as "isActive",
        wallet_balance as "walletBalance", 
        created_at as "createdAt", 
        updated_at as "updatedAt"
      FROM users
      WHERE email = $1
    `;
    
    const result = await pool.query(query, [email]);
    return result.rows[0] || null;
  }

  /**
   * Find a user by ID
   * @param {string} id - User ID (UUID)
   * @returns {Promise<Object|null>} User object or null if not found
   */
  async findById(id) {
    const query = `
      SELECT 
        id, 
        email,
        name,
        mobile_number as "mobileNumber",
        aadhar_number as "aadharNumber",
        date_of_birth as "dateOfBirth",
        pan_number as "panNumber",
        referral_code as "referralCode",
        referred_by as "referredBy",
        password_hash as "passwordHash",
        is_admin as "isAdmin",
        is_active as "isActive",
        wallet_balance as "walletBalance",
        saved_upi_id as "savedUpiId",
        saved_bank_details as "savedBankDetails",
        preferred_payment_method as "preferredPaymentMethod",
        created_at as "createdAt", 
        updated_at as "updatedAt"
      FROM users
      WHERE id = $1
    `;
    
    const result = await pool.query(query, [id]);
    return result.rows[0] || null;
  }

  /**
   * Update user profile
   * @param {string} id - User ID (UUID)
   * @param {Object} updates - Fields to update
   * @returns {Promise<Object>} Updated user object
   */
  async updateProfile(id, updates) {
    const { name, passwordHash, mobileNumber, aadharNumber, dateOfBirth, panNumber } = updates;
    const fields = [];
    const values = [];
    let paramCount = 1;

    if (name !== undefined) {
      fields.push(`name = $${paramCount++}`);
      values.push(name);
    }

    if (passwordHash !== undefined) {
      fields.push(`password_hash = $${paramCount++}`);
      values.push(passwordHash);
    }

    if (mobileNumber !== undefined) {
      fields.push(`mobile_number = $${paramCount++}`);
      values.push(mobileNumber);
    }

    if (aadharNumber !== undefined) {
      fields.push(`aadhar_number = $${paramCount++}`);
      values.push(aadharNumber);
    }

    if (dateOfBirth !== undefined) {
      fields.push(`date_of_birth = $${paramCount++}`);
      values.push(dateOfBirth);
    }

    if (panNumber !== undefined) {
      fields.push(`pan_number = $${paramCount++}`);
      values.push(panNumber);
    }

    if (fields.length === 0) {
      throw new Error('No fields to update');
    }

    values.push(id);
    const query = `
      UPDATE users
      SET ${fields.join(', ')}
      WHERE id = $${paramCount}
      RETURNING id, email, name, mobile_number as "mobileNumber", aadhar_number as "aadharNumber", 
                date_of_birth as "dateOfBirth", pan_number as "panNumber", is_admin as "isAdmin", 
                wallet_balance as "walletBalance", created_at as "createdAt", updated_at as "updatedAt"
    `;

    const result = await pool.query(query, values);
    return result.rows[0];
  }

  /**
   * Update user's wallet balance
   * @param {string} id - User ID (UUID)
   * @param {number} newBalance - New balance in paise
   * @returns {Promise<Object>} Updated user object
   */
  async updateBalance(id, newBalance) {
    const query = `
      UPDATE users
      SET wallet_balance = $1
      WHERE id = $2
      RETURNING id, email, is_admin as "isAdmin", wallet_balance as "walletBalance", created_at as "createdAt", updated_at as "updatedAt"
    `;
    
    const values = [newBalance, id];
    const result = await pool.query(query, values);
    return result.rows[0];
  }

  /**
   * Update user's saved payment details
   * @param {string} id - User ID (UUID)
   * @param {Object} updates - Payment details to update
   * @returns {Promise<Object>} Updated user object
   */
  async updatePaymentDetails(id, updates) {
    const { savedUpiId, savedBankDetails, preferredPaymentMethod } = updates;
    const fields = [];
    const values = [];
    let paramCount = 1;

    if (savedUpiId !== undefined) {
      fields.push(`saved_upi_id = $${paramCount++}`);
      values.push(savedUpiId);
    }

    if (savedBankDetails !== undefined) {
      fields.push(`saved_bank_details = $${paramCount++}`);
      values.push(savedBankDetails ? JSON.stringify(savedBankDetails) : null);
    }

    if (preferredPaymentMethod !== undefined) {
      fields.push(`preferred_payment_method = $${paramCount++}`);
      values.push(preferredPaymentMethod);
    }

    if (fields.length === 0) {
      throw new Error('No fields to update');
    }

    values.push(id);
    const query = `
      UPDATE users
      SET ${fields.join(', ')}
      WHERE id = $${paramCount}
      RETURNING id, saved_upi_id as "savedUpiId", saved_bank_details as "savedBankDetails", 
                preferred_payment_method as "preferredPaymentMethod"
    `;

    const result = await pool.query(query, values);
    return result.rows[0];
  }
}

export default new UserRepository();
