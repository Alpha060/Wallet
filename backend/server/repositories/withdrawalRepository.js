import pool from '../database/db.js';

/**
 * WithdrawalRepository handles all database operations for withdrawal requests
 */
class WithdrawalRepository {
  /**
   * Create a new withdrawal request
   * @param {string} userId - User ID
   * @param {number} amount - Amount in paise
   * @param {Object} bankDetails - Bank details (JSONB)
   * @returns {Promise<Object>} Created withdrawal request
   */
  async create(userId, amount, bankDetails) {
    const query = `
      INSERT INTO withdrawal_requests (user_id, amount, bank_details, status)
      VALUES ($1, $2, $3, 'pending')
      RETURNING 
        id, 
        user_id as "userId", 
        amount, 
        bank_details as "bankDetails",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
    `;
    
    const values = [userId, amount, JSON.stringify(bankDetails)];
    const result = await pool.query(query, values);
    return result.rows[0];
  }

  /**
   * Find a withdrawal request by ID
   * @param {string} id - Withdrawal request ID
   * @returns {Promise<Object|null>} Withdrawal request or null
   */
  async findById(id) {
    const query = `
      SELECT 
        id, 
        user_id as "userId", 
        amount, 
        bank_details as "bankDetails",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
      FROM withdrawal_requests
      WHERE id = $1
    `;
    
    const result = await pool.query(query, [id]);
    return result.rows[0] || null;
  }

  /**
   * Find all withdrawal requests for a user
   * @param {string} userId - User ID
   * @param {number} limit - Number of results per page
   * @param {number} offset - Offset for pagination
   * @returns {Promise<Object>} Object with withdrawals array and total count
   */
  async findByUserId(userId, limit = 20, offset = 0) {
    const query = `
      SELECT 
        id, 
        user_id as "userId", 
        amount, 
        bank_details as "bankDetails",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
      FROM withdrawal_requests
      WHERE user_id = $1
      ORDER BY created_at DESC
      LIMIT $2 OFFSET $3
    `;
    
    const countQuery = `
      SELECT COUNT(*) as total
      FROM withdrawal_requests
      WHERE user_id = $1
    `;
    
    const [withdrawalsResult, countResult] = await Promise.all([
      pool.query(query, [userId, limit, offset]),
      pool.query(countQuery, [userId])
    ]);
    
    return {
      withdrawals: withdrawalsResult.rows,
      total: parseInt(countResult.rows[0].total)
    };
  }

  /**
   * Find all pending withdrawal requests
   * @param {number} limit - Number of results per page
   * @param {number} offset - Offset for pagination
   * @returns {Promise<Object>} Object with withdrawals array and total count
   */
  async findPending(limit = 20, offset = 0) {
    const query = `
      SELECT 
        w.id, 
        w.user_id as "userId",
        u.email as "userEmail",
        u.name as "userName",
        w.amount, 
        w.bank_details as "bankDetails",
        w.status,
        w.created_at as "createdAt",
        w.processed_at as "processedAt",
        w.processed_by as "processedBy",
        w.rejection_reason as "rejectionReason"
      FROM withdrawal_requests w
      JOIN users u ON w.user_id = u.id
      WHERE w.status = 'pending'
      ORDER BY w.created_at ASC
      LIMIT $1 OFFSET $2
    `;
    
    const countQuery = `
      SELECT COUNT(*) as total
      FROM withdrawal_requests
      WHERE status = 'pending'
    `;
    
    const [withdrawalsResult, countResult] = await Promise.all([
      pool.query(query, [limit, offset]),
      pool.query(countQuery)
    ]);
    
    return {
      withdrawals: withdrawalsResult.rows,
      total: parseInt(countResult.rows[0].total)
    };
  }

  /**
   * Update withdrawal request status
   * @param {string} id - Withdrawal request ID
   * @param {string} status - New status ('completed' or 'rejected')
   * @param {string} processedBy - Admin user ID
   * @param {string|null} rejectionReason - Optional rejection reason
   * @returns {Promise<Object>} Updated withdrawal request
   */
  async updateStatus(id, status, processedBy, rejectionReason = null) {
    const query = `
      UPDATE withdrawal_requests
      SET 
        status = $1,
        processed_at = CURRENT_TIMESTAMP,
        processed_by = $2,
        rejection_reason = $3
      WHERE id = $4
      RETURNING 
        id, 
        user_id as "userId", 
        amount, 
        bank_details as "bankDetails",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
    `;
    
    const values = [status, processedBy, rejectionReason, id];
    const result = await pool.query(query, values);
    return result.rows[0];
  }
}

export default new WithdrawalRepository();
