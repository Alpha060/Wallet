import pool from '../database/db.js';

/**
 * ReferralRepository handles all database operations for referrals
 */
class ReferralRepository {
  /**
   * Find user by referral code
   * @param {string} referralCode - 6-digit referral code
   * @returns {Promise<Object|null>} User object or null if not found
   */
  async findByReferralCode(referralCode) {
    const query = `
      SELECT 
        id, 
        email,
        name,
        referral_code as "referralCode",
        is_admin as "isAdmin"
      FROM users
      WHERE referral_code = $1 AND is_admin = FALSE
    `;
    
    const result = await pool.query(query, [referralCode.toUpperCase()]);
    return result.rows[0] || null;
  }

  /**
   * Get user's referral code
   * @param {string} userId - User ID (UUID)
   * @returns {Promise<string|null>} Referral code or null
   */
  async getUserReferralCode(userId) {
    const query = `
      SELECT referral_code as "referralCode"
      FROM users
      WHERE id = $1
    `;
    
    const result = await pool.query(query, [userId]);
    return result.rows[0]?.referralCode || null;
  }

  /**
   * Get list of users referred by a specific user
   * @param {string} userId - User ID (UUID)
   * @returns {Promise<Array>} Array of referred users with deposit status
   */
  async getReferredUsers(userId) {
    const query = `
      SELECT 
        u.id,
        u.email,
        u.name,
        u.created_at as "createdAt",
        (
          SELECT COUNT(*) > 0 FROM deposit_requests d 
          WHERE d.user_id = u.id AND d.status = 'approved'
        ) as "hasDeposited"
      FROM users u
      WHERE u.referred_by = $1
      ORDER BY u.created_at DESC
    `;
    
    const result = await pool.query(query, [userId]);
    console.log('[ReferralRepository] getReferredUsers raw result:', JSON.stringify(result.rows, null, 2));
    
    // Ensure hasDeposited is a proper boolean
    return result.rows.map(row => ({
      ...row,
      hasDeposited: row.hasDeposited === true || row.hasDeposited === 't' || row.hasDeposited === 'true' || row.hasDeposited === '1'
    }));
  }

  /**
   * Get referral statistics for a user
   * @param {string} userId - User ID (UUID)
   * @returns {Promise<Object>} Referral stats
   */
  async getReferralStats(userId) {
    const query = `
      SELECT 
        COUNT(*) as "totalReferrals"
      FROM users
      WHERE referred_by = $1
    `;
    
    const result = await pool.query(query, [userId]);
    return {
      totalReferrals: parseInt(result.rows[0].totalReferrals) || 0
    };
  }

  /**
   * Set referrer for a user
   * @param {string} userId - User ID being referred
   * @param {string} referrerId - Referrer's user ID
   * @returns {Promise<void>}
   */
  async setReferrer(userId, referrerId) {
    const query = `
      UPDATE users
      SET referred_by = $1
      WHERE id = $2
    `;
    
    await pool.query(query, [referrerId, userId]);
  }
}

export default new ReferralRepository();
