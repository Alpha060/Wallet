import pool from '../database/db.js';

/**
 * DepositRepository handles all database operations for deposit requests
 */
class DepositRepository {
  /**
   * Create a new deposit request
   * @param {string} userId - User ID
   * @param {number} amount - Amount in paise
   * @param {string} paymentProofUrl - URL to payment proof
   * @param {string|null} transactionId - Optional transaction ID
   * @returns {Promise<Object>} Created deposit request
   */
  async create(userId, amount, paymentProofUrl, transactionId = null) {
    const query = `
      INSERT INTO deposit_requests (user_id, amount, payment_proof_url, transaction_id, status)
      VALUES ($1, $2, $3, $4, 'pending')
      RETURNING 
        id, 
        user_id as "userId", 
        amount, 
        transaction_id as "transactionId",
        payment_proof_url as "paymentProofUrl",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
    `;
    
    const values = [userId, amount, paymentProofUrl, transactionId];
    const result = await pool.query(query, values);
    return result.rows[0];
  }

  /**
   * Find a deposit request by ID
   * @param {string} id - Deposit request ID
   * @returns {Promise<Object|null>} Deposit request or null
   */
  async findById(id) {
    const query = `
      SELECT 
        id, 
        user_id as "userId", 
        amount, 
        transaction_id as "transactionId",
        payment_proof_url as "paymentProofUrl",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
      FROM deposit_requests
      WHERE id = $1
    `;
    
    const result = await pool.query(query, [id]);
    return result.rows[0] || null;
  }

  /**
   * Find all deposit requests for a user
   * @param {string} userId - User ID
   * @param {number} limit - Number of results per page
   * @param {number} offset - Offset for pagination
   * @returns {Promise<Object>} Object with deposits array and total count
   */
  async findByUserId(userId, limit = 20, offset = 0) {
    const query = `
      SELECT 
        id, 
        user_id as "userId", 
        amount, 
        transaction_id as "transactionId",
        payment_proof_url as "paymentProofUrl",
        status,
        created_at as "createdAt",
        processed_at as "processedAt",
        processed_by as "processedBy",
        rejection_reason as "rejectionReason"
      FROM deposit_requests
      WHERE user_id = $1
      ORDER BY created_at DESC
      LIMIT $2 OFFSET $3
    `;
    
    const countQuery = `
      SELECT COUNT(*) as total
      FROM deposit_requests
      WHERE user_id = $1
    `;
    
    const [depositsResult, countResult] = await Promise.all([
      pool.query(query, [userId, limit, offset]),
      pool.query(countQuery, [userId])
    ]);
    
    return {
      deposits: depositsResult.rows,
      total: parseInt(countResult.rows[0].total)
    };
  }

  /**
   * Find all pending deposit requests
   * @param {number} limit - Number of results per page
   * @param {number} offset - Offset for pagination
   * @returns {Promise<Object>} Object with deposits array and total count
   */
  async findPending(limit = 20, offset = 0) {
    const query = `
      SELECT 
        d.id, 
        d.user_id as "userId",
        u.email as "userEmail",
        u.name as "userName",
        d.amount, 
        d.transaction_id as "transactionId",
        d.payment_proof_url as "paymentProofUrl",
        d.status,
        d.created_at as "createdAt",
        d.processed_at as "processedAt",
        d.processed_by as "processedBy",
        d.rejection_reason as "rejectionReason"
      FROM deposit_requests d
      JOIN users u ON d.user_id = u.id
      WHERE d.status = 'pending'
      ORDER BY d.created_at ASC
      LIMIT $1 OFFSET $2
    `;
    
    const countQuery = `
      SELECT COUNT(*) as total
      FROM deposit_requests
      WHERE status = 'pending'
    `;
    
    const [depositsResult, countResult] = await Promise.all([
      pool.query(query, [limit, offset]),
      pool.query(countQuery)
    ]);
    
    return {
      deposits: depositsResult.rows,
      total: parseInt(countResult.rows[0].total)
    };
  }

  /**
   * Update deposit request status
   * @param {string} id - Deposit request ID
   * @param {string} status - New status ('approved' or 'rejected')
   * @param {string} processedBy - Admin user ID
   * @param {string|null} rejectionReason - Optional rejection reason
   * @returns {Promise<Object>} Updated deposit request
   */
  async updateStatus(id, status, processedBy, rejectionReason = null) {
    const query = `
      UPDATE deposit_requests
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
        transaction_id as "transactionId",
        payment_proof_url as "paymentProofUrl",
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

export default new DepositRepository();
