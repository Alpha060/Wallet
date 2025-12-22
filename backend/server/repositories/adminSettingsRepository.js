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
}

export default new AdminSettingsRepository();
