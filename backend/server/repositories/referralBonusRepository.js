import pool from '../database/db.js';

/**
 * ReferralBonusRepository handles all database operations for referral bonuses
 */
class ReferralBonusRepository {
  /**
   * Create a referral bonus when a referred user's deposit is approved
   * @param {string} referrerId - Referrer's user ID
   * @param {string} referredUserId - Referred user's ID
   * @param {string} depositId - Deposit request ID
   * @param {number} depositAmount - Original deposit amount
   * @param {number} bonusAmount - Bonus amount (5% of deposit)
   * @returns {Promise<Object>} Created bonus record
   */
  async create(referrerId, referredUserId, depositId, depositAmount, bonusAmount) {
    const query = `
      INSERT INTO referral_bonuses (referrer_id, referred_user_id, deposit_id, deposit_amount, bonus_amount)
      VALUES ($1, $2, $3, $4, $5)
      RETURNING 
        id,
        referrer_id as "referrerId",
        referred_user_id as "referredUserId",
        deposit_id as "depositId",
        deposit_amount as "depositAmount",
        bonus_amount as "bonusAmount",
        is_claimed as "isClaimed",
        created_at as "createdAt"
    `;
    const result = await pool.query(query, [referrerId, referredUserId, depositId, depositAmount, bonusAmount]);
    return result.rows[0];
  }

  /**
   * Get unclaimed bonuses for a user
   * @param {string} userId - User ID
   * @returns {Promise<Array>} Array of unclaimed bonuses with referred user info
   */
  async getUnclaimedBonuses(userId) {
    const query = `
      SELECT 
        rb.id,
        rb.bonus_amount as "bonusAmount",
        rb.deposit_amount as "depositAmount",
        rb.created_at as "createdAt",
        u.name as "referredUserName",
        u.email as "referredUserEmail"
      FROM referral_bonuses rb
      JOIN users u ON rb.referred_user_id = u.id
      WHERE rb.referrer_id = $1 AND rb.is_claimed = FALSE
      ORDER BY rb.created_at DESC
    `;
    const result = await pool.query(query, [userId]);
    return result.rows;
  }

  /**
   * Get total unclaimed bonus amount for a user
   * @param {string} userId - User ID
   * @returns {Promise<number>} Total unclaimed bonus amount
   */
  async getTotalUnclaimedAmount(userId) {
    const query = `
      SELECT COALESCE(SUM(bonus_amount), 0) as total
      FROM referral_bonuses
      WHERE referrer_id = $1 AND is_claimed = FALSE
    `;
    const result = await pool.query(query, [userId]);
    return parseInt(result.rows[0].total);
  }

  /**
   * Mark a bonus as claimed
   * @param {string} bonusId - Bonus ID
   * @returns {Promise<Object>} Updated bonus record
   */
  async markAsClaimed(bonusId) {
    const query = `
      UPDATE referral_bonuses
      SET is_claimed = TRUE
      WHERE id = $1
      RETURNING 
        id,
        referrer_id as "referrerId",
        bonus_amount as "bonusAmount",
        is_claimed as "isClaimed"
    `;
    const result = await pool.query(query, [bonusId]);
    return result.rows[0];
  }

  /**
   * Get bonus by ID
   * @param {string} bonusId - Bonus ID
   * @returns {Promise<Object|null>} Bonus record or null
   */
  async findById(bonusId) {
    const query = `
      SELECT 
        rb.id,
        rb.referrer_id as "referrerId",
        rb.referred_user_id as "referredUserId",
        rb.deposit_id as "depositId",
        rb.deposit_amount as "depositAmount",
        rb.bonus_amount as "bonusAmount",
        rb.is_claimed as "isClaimed",
        rb.created_at as "createdAt",
        u.name as "referredUserName",
        u.email as "referredUserEmail"
      FROM referral_bonuses rb
      JOIN users u ON rb.referred_user_id = u.id
      WHERE rb.id = $1
    `;
    const result = await pool.query(query, [bonusId]);
    return result.rows[0] || null;
  }

  /**
   * Create a bonus claim request
   * @param {string} userId - User ID
   * @param {string} bonusId - Bonus ID
   * @param {number} amount - Claim amount
   * @returns {Promise<Object>} Created claim request
   */
  async createClaimRequest(userId, bonusId, amount) {
    const query = `
      INSERT INTO bonus_claim_requests (user_id, bonus_id, amount)
      VALUES ($1, $2, $3)
      RETURNING 
        id,
        user_id as "userId",
        bonus_id as "bonusId",
        amount,
        status,
        created_at as "createdAt"
    `;
    const result = await pool.query(query, [userId, bonusId, amount]);
    return result.rows[0];
  }

  /**
   * Get claim request by ID
   * @param {string} claimId - Claim request ID
   * @returns {Promise<Object|null>} Claim request or null
   */
  async findClaimById(claimId) {
    const query = `
      SELECT 
        bcr.id,
        bcr.user_id as "userId",
        bcr.bonus_id as "bonusId",
        bcr.amount,
        bcr.status,
        bcr.created_at as "createdAt",
        bcr.processed_at as "processedAt",
        bcr.rejection_reason as "rejectionReason",
        u.name as "userName",
        u.email as "userEmail",
        ru.name as "referredUserName",
        ru.email as "referredUserEmail"
      FROM bonus_claim_requests bcr
      JOIN users u ON bcr.user_id = u.id
      JOIN referral_bonuses rb ON bcr.bonus_id = rb.id
      JOIN users ru ON rb.referred_user_id = ru.id
      WHERE bcr.id = $1
    `;
    const result = await pool.query(query, [claimId]);
    return result.rows[0] || null;
  }

  /**
   * Get pending claim requests for admin
   * @param {number} limit - Results per page
   * @param {number} offset - Offset for pagination
   * @returns {Promise<Object>} Paginated pending claims
   */
  async findPendingClaims(limit = 20, offset = 0) {
    const query = `
      SELECT 
        bcr.id,
        bcr.amount,
        bcr.status,
        bcr.created_at as "createdAt",
        u.id as "userId",
        u.name as "userName",
        u.email as "userEmail",
        ru.name as "referredUserName",
        ru.email as "referredUserEmail"
      FROM bonus_claim_requests bcr
      JOIN users u ON bcr.user_id = u.id
      JOIN referral_bonuses rb ON bcr.bonus_id = rb.id
      JOIN users ru ON rb.referred_user_id = ru.id
      WHERE bcr.status = 'pending'
      ORDER BY bcr.created_at ASC
      LIMIT $1 OFFSET $2
    `;
    const countQuery = `
      SELECT COUNT(*) as total
      FROM bonus_claim_requests
      WHERE status = 'pending'
    `;
    
    const [claimsResult, countResult] = await Promise.all([
      pool.query(query, [limit, offset]),
      pool.query(countQuery)
    ]);
    
    return {
      claims: claimsResult.rows,
      total: parseInt(countResult.rows[0].total)
    };
  }

  /**
   * Update claim request status
   * @param {string} claimId - Claim request ID
   * @param {string} status - New status
   * @param {string} adminId - Admin user ID
   * @param {string|null} rejectionReason - Rejection reason (optional)
   * @returns {Promise<Object>} Updated claim request
   */
  async updateClaimStatus(claimId, status, adminId, rejectionReason = null) {
    const query = `
      UPDATE bonus_claim_requests
      SET status = $1, processed_at = NOW(), processed_by = $2, rejection_reason = $3
      WHERE id = $4
      RETURNING 
        id,
        user_id as "userId",
        bonus_id as "bonusId",
        amount,
        status,
        processed_at as "processedAt",
        rejection_reason as "rejectionReason"
    `;
    const result = await pool.query(query, [status, adminId, rejectionReason, claimId]);
    return result.rows[0];
  }

  /**
   * Get user's claim history
   * @param {string} userId - User ID
   * @param {number} limit - Results per page
   * @param {number} offset - Offset for pagination
   * @returns {Promise<Object>} Paginated claim history
   */
  async getUserClaimHistory(userId, limit = 20, offset = 0) {
    const query = `
      SELECT 
        bcr.id,
        bcr.amount,
        bcr.status,
        bcr.created_at as "createdAt",
        bcr.processed_at as "processedAt",
        bcr.rejection_reason as "rejectionReason",
        ru.name as "referredUserName",
        ru.email as "referredUserEmail"
      FROM bonus_claim_requests bcr
      JOIN referral_bonuses rb ON bcr.bonus_id = rb.id
      JOIN users ru ON rb.referred_user_id = ru.id
      WHERE bcr.user_id = $1
      ORDER BY bcr.created_at DESC
      LIMIT $2 OFFSET $3
    `;
    const countQuery = `
      SELECT COUNT(*) as total
      FROM bonus_claim_requests
      WHERE user_id = $1
    `;
    
    const [claimsResult, countResult] = await Promise.all([
      pool.query(query, [userId, limit, offset]),
      pool.query(countQuery, [userId])
    ]);
    
    return {
      claims: claimsResult.rows,
      total: parseInt(countResult.rows[0].total)
    };
  }

  /**
   * Check if a bonus already has a pending claim
   * @param {string} bonusId - Bonus ID
   * @returns {Promise<boolean>} True if pending claim exists
   */
  async hasPendingClaim(bonusId) {
    const query = `
      SELECT id FROM bonus_claim_requests
      WHERE bonus_id = $1 AND status = 'pending'
    `;
    const result = await pool.query(query, [bonusId]);
    return result.rows.length > 0;
  }

  /**
   * Get referral bonus statistics for a user
   * @param {string} userId - User ID
   * @returns {Promise<Object>} Bonus statistics
   */
  async getUserBonusStats(userId) {
    const query = `
      SELECT 
        COALESCE(SUM(CASE WHEN is_claimed = FALSE THEN bonus_amount ELSE 0 END), 0) as "unclaimedAmount",
        COALESCE(SUM(CASE WHEN is_claimed = TRUE THEN bonus_amount ELSE 0 END), 0) as "claimedAmount",
        COUNT(CASE WHEN is_claimed = FALSE THEN 1 END) as "unclaimedCount",
        COUNT(*) as "totalBonuses"
      FROM referral_bonuses
      WHERE referrer_id = $1
    `;
    const result = await pool.query(query, [userId]);
    return {
      unclaimedAmount: parseInt(result.rows[0].unclaimedAmount),
      claimedAmount: parseInt(result.rows[0].claimedAmount),
      unclaimedCount: parseInt(result.rows[0].unclaimedCount),
      totalBonuses: parseInt(result.rows[0].totalBonuses)
    };
  }
}

export default new ReferralBonusRepository();
