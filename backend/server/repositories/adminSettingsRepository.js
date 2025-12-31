import pool from '../database/db.js';

/**
 * AdminSettingsRepository handles all database operations for admin settings
 */
class AdminSettingsRepository {
  /**
   * Get admin settings (there should only be one row)
   * @returns {Promise<Object|null>} Admin settings object or null
   */
  async get() {
    const query = `
      SELECT 
        id, 
        qr_code_url as "qrCodeUrl",
        upi_id as "upiId",
        updated_at as "updatedAt"
      FROM admin_settings
      LIMIT 1
    `;
    
    const result = await pool.query(query);
    return result.rows[0] || null;
  }

  /**
   * Update QR code URL
   * @param {string} qrCodeUrl - New QR code URL
   * @returns {Promise<Object>} Updated admin settings
   */
  async updateQRCode(qrCodeUrl) {
    // Get the existing settings row
    const settings = await this.get();
    
    if (!settings) {
      // If no settings exist, create one
      const insertQuery = `
        INSERT INTO admin_settings (qr_code_url)
        VALUES ($1)
        RETURNING id, qr_code_url as "qrCodeUrl", upi_id as "upiId", updated_at as "updatedAt"
      `;
      const result = await pool.query(insertQuery, [qrCodeUrl]);
      return result.rows[0];
    }

    // Update existing settings
    const updateQuery = `
      UPDATE admin_settings
      SET qr_code_url = $1
      WHERE id = $2
      RETURNING id, qr_code_url as "qrCodeUrl", upi_id as "upiId", updated_at as "updatedAt"
    `;
    
    const result = await pool.query(updateQuery, [qrCodeUrl, settings.id]);
    return result.rows[0];
  }

  /**
   * Update UPI ID
   * @param {string} upiId - New UPI ID
   * @returns {Promise<Object>} Updated admin settings
   */
  async updateUpiId(upiId) {
    // Get the existing settings row
    const settings = await this.get();
    
    if (!settings) {
      // If no settings exist, create one
      const insertQuery = `
        INSERT INTO admin_settings (upi_id)
        VALUES ($1)
        RETURNING id, qr_code_url as "qrCodeUrl", upi_id as "upiId", updated_at as "updatedAt"
      `;
      const result = await pool.query(insertQuery, [upiId]);
      return result.rows[0];
    }

    // Update existing settings
    const updateQuery = `
      UPDATE admin_settings
      SET upi_id = $1
      WHERE id = $2
      RETURNING id, qr_code_url as "qrCodeUrl", upi_id as "upiId", updated_at as "updatedAt"
    `;
    
    const result = await pool.query(updateQuery, [upiId, settings.id]);
    return result.rows[0];
  }

  /**
   * Find user by email
   * @param {string} email - Email to search for
   * @returns {Promise<Object|null>} User object or null
   */
  async findUserByEmail(email) {
    const query = `
      SELECT id, email, name, is_admin, is_active
      FROM users
      WHERE email = $1
    `;
    
    const result = await pool.query(query, [email]);
    return result.rows[0] || null;
  }

  /**
   * Find user by ID
   * @param {string} userId - User ID to search for
   * @returns {Promise<Object|null>} User object or null
   */
  async findUserById(userId) {
    const query = `
      SELECT id, email, name, is_admin, is_active
      FROM users
      WHERE id = $1
    `;
    
    const result = await pool.query(query, [userId]);
    return result.rows[0] || null;
  }

  /**
   * Update admin profile
   * @param {string} adminId - Admin user ID
   * @param {Object} profileData - Profile data to update
   * @returns {Promise<Object>} Updated admin user
   */
  async updateAdminProfile(adminId, profileData) {
    const { email, name, mobileNumber } = profileData;
    
    const updateQuery = `
      UPDATE users
      SET 
        email = COALESCE($1, email),
        name = COALESCE($2, name),
        mobile_number = $3,
        updated_at = CURRENT_TIMESTAMP
      WHERE id = $4 AND is_admin = true
      RETURNING id, email, name, mobile_number, is_admin, is_active, updated_at
    `;
    
    const result = await pool.query(updateQuery, [email, name, mobileNumber, adminId]);
    return result.rows[0];
  }

  /**
   * Delete user and related data
   * @param {string} userId - User ID to delete
   * @returns {Promise<Object>} Deleted user data
   */
  async deleteUser(userId) {
    const client = await pool.connect();
    
    try {
      await client.query('BEGIN');
      
      // Get user data before deletion
      const getUserQuery = `
        SELECT id, email, name, is_admin
        FROM users
        WHERE id = $1
      `;
      const userResult = await client.query(getUserQuery, [userId]);
      const user = userResult.rows[0];
      
      if (!user) {
        throw new Error('User not found');
      }
      
      // Handle foreign key constraints properly
      
      // First, set processed_by to NULL for any requests processed by this user
      await client.query('UPDATE deposit_requests SET processed_by = NULL WHERE processed_by = $1', [userId]);
      await client.query('UPDATE withdrawal_requests SET processed_by = NULL WHERE processed_by = $1', [userId]);
      
      // Delete deposit requests created by this user
      await client.query('DELETE FROM deposit_requests WHERE user_id = $1', [userId]);
      
      // Delete withdrawal requests created by this user
      await client.query('DELETE FROM withdrawal_requests WHERE user_id = $1', [userId]);
      
      // Finally delete user (wallet_balance is stored in users table)
      await client.query('DELETE FROM users WHERE id = $1', [userId]);
      
      await client.query('COMMIT');
      
      return user;
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }
}

export default new AdminSettingsRepository();
