import userRepository from '../repositories/userRepository.js';
import pool from '../database/db.js';

/**
 * WalletService handles wallet balance and transaction history operations
 */
class WalletService {
  /**
   * Get user's current wallet balance
   * @param {string} userId - User ID
   * @returns {Promise<Object>} Balance information
   * @throws {Error} If user not found
   */
  async getBalance(userId) {
    const user = await userRepository.findById(userId);

    if (!user) {
      throw new Error('User not found');
    }

    return {
      balance: user.walletBalance,
      currency: 'INR'
    };
  }

  /**
   * Get user's transaction history (deposits and withdrawals)
   * @param {string} userId - User ID
   * @param {number} page - Page number (1-indexed)
   * @param {number} limit - Results per page
   * @param {string|null} type - Filter by type: 'deposit', 'withdrawal', or null for all
   * @returns {Promise<Object>} Paginated transaction history
   */
  async getTransactionHistory(userId, page = 1, limit = 20, type = null) {
    const offset = (page - 1) * limit;

    let query;
    let countQuery;
    let queryParams;
    let countParams;

    if (type === 'deposit') {
      // Only deposits
      query = `
        SELECT 
          id,
          'deposit' as type,
          amount,
          status,
          created_at as "createdAt",
          processed_at as "processedAt"
        FROM deposit_requests
        WHERE user_id = $1
        ORDER BY created_at DESC
        LIMIT $2 OFFSET $3
      `;
      countQuery = `
        SELECT COUNT(*) as total
        FROM deposit_requests
        WHERE user_id = $1
      `;
      queryParams = [userId, limit, offset];
      countParams = [userId];
    } else if (type === 'withdrawal') {
      // Only withdrawals
      query = `
        SELECT 
          id,
          'withdrawal' as type,
          amount,
          status,
          created_at as "createdAt",
          processed_at as "processedAt"
        FROM withdrawal_requests
        WHERE user_id = $1
        ORDER BY created_at DESC
        LIMIT $2 OFFSET $3
      `;
      countQuery = `
        SELECT COUNT(*) as total
        FROM withdrawal_requests
        WHERE user_id = $1
      `;
      queryParams = [userId, limit, offset];
      countParams = [userId];
    } else {
      // Both deposits and withdrawals
      query = `
        SELECT * FROM (
          SELECT 
            id,
            'deposit' as type,
            amount,
            status,
            created_at as "createdAt",
            processed_at as "processedAt"
          FROM deposit_requests
          WHERE user_id = $1
          
          UNION ALL
          
          SELECT 
            id,
            'withdrawal' as type,
            amount,
            status,
            created_at as "createdAt",
            processed_at as "processedAt"
          FROM withdrawal_requests
          WHERE user_id = $1
        ) combined
        ORDER BY "createdAt" DESC
        LIMIT $2 OFFSET $3
      `;
      countQuery = `
        SELECT 
          (SELECT COUNT(*) FROM deposit_requests WHERE user_id = $1) +
          (SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = $1) as total
      `;
      queryParams = [userId, limit, offset];
      countParams = [userId];
    }

    const [transactionsResult, countResult] = await Promise.all([
      pool.query(query, queryParams),
      pool.query(countQuery, countParams)
    ]);

    const total = parseInt(countResult.rows[0].total);

    return {
      transactions: transactionsResult.rows,
      total,
      page,
      totalPages: Math.ceil(total / limit)
    };
  }
}

export default new WalletService();
