import withdrawalRepository from '../repositories/withdrawalRepository.js';
import userRepository from '../repositories/userRepository.js';
import pool from '../database/db.js';

/**
 * WithdrawalService handles withdrawal request operations with balance locking
 */
class WithdrawalService {
  /**
   * Validate bank details
   * @param {Object} bankDetails - Bank details object
   * @throws {Error} If bank details are invalid
   */
  validateBankDetails(bankDetails) {
    if (!bankDetails || typeof bankDetails !== 'object') {
      throw new Error('Bank details are required');
    }

    const { upiId, accountNumber, ifscCode, accountHolderName } = bankDetails;

    // Must have either UPI ID or account details
    if (!upiId && !accountNumber) {
      throw new Error('Either UPI ID or account number is required');
    }

    // If UPI ID is provided, validate format
    if (upiId) {
      const upiRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9]+$/;
      if (!upiRegex.test(upiId)) {
        throw new Error('Invalid UPI ID format. Expected format: user@bank');
      }
    }

    // If account number is provided, IFSC code is required
    if (accountNumber && !ifscCode) {
      throw new Error('IFSC code is required when providing account number');
    }

    // Validate IFSC code format if provided
    if (ifscCode) {
      const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
      if (!ifscRegex.test(ifscCode)) {
        throw new Error('Invalid IFSC code format');
      }
    }
  }

  /**
   * Create a new withdrawal request (locks funds immediately)
   * @param {string} userId - User ID
   * @param {number} amount - Amount in paise
   * @param {Object} bankDetails - Bank details
   * @returns {Promise<Object>} Created withdrawal request
   * @throws {Error} If validation fails or insufficient balance
   */
  async createWithdrawalRequest(userId, amount, bankDetails) {
    const client = await pool.connect();

    try {
      await client.query('BEGIN');

      // Validate amount
      if (!amount || amount <= 0) {
        throw new Error('Amount must be greater than zero');
      }

      if (amount < 100) {
        throw new Error('Minimum withdrawal amount is ₹1 (100 paise)');
      }

      const referralCheckQuery = `
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN deposit_requests d ON u.id = d.user_id
        WHERE u.referred_by = $1 AND d.status = 'approved'
      `;
      const refResult = await client.query(referralCheckQuery, [userId]);
      const confirmedReferrals = parseInt(refResult.rows[0].count);
      const REQUIRED_REFERRALS = 5;

      if (confirmedReferrals < REQUIRED_REFERRALS) {
        const needed = REQUIRED_REFERRALS - confirmedReferrals;
        throw new Error(`Requirement not met: You need ${needed} more confirmed referral${needed > 1 ? 's' : ''} to withdraw.`);
      }

      // Validate bank details
      this.validateBankDetails(bankDetails);

      // Get user with row lock to prevent concurrent withdrawals
      const userQuery = `
        SELECT id, wallet_balance as "walletBalance"
        FROM users
        WHERE id = $1
        FOR UPDATE
      `;
      const userResult = await client.query(userQuery, [userId]);
      const user = userResult.rows[0];

      if (!user) {
        throw new Error('User not found');
      }

      // Check if user has sufficient balance
      if (user.walletBalance < amount) {
        throw new Error('Insufficient balance');
      }

      // Deduct balance immediately (lock funds)
      const newBalance = user.walletBalance - amount;
      const updateBalanceQuery = `
        UPDATE users
        SET wallet_balance = $1
        WHERE id = $2
      `;
      await client.query(updateBalanceQuery, [newBalance, userId]);

      // Create withdrawal request using the transaction client
      const insertQuery = `
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
      const insertValues = [userId, amount, JSON.stringify(bankDetails)];
      const withdrawalResult = await client.query(insertQuery, insertValues);
      const withdrawal = withdrawalResult.rows[0];

      await client.query('COMMIT');

      return withdrawal;
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }

  /**
   * Confirm a withdrawal request (finalize status)
   * @param {string} withdrawalId - Withdrawal request ID
   * @param {string} adminId - Admin user ID
   * @returns {Promise<Object>} Updated withdrawal request
   * @throws {Error} If withdrawal not found or already processed
   */
  async confirmWithdrawal(withdrawalId, adminId) {
    // Get withdrawal request
    const withdrawal = await withdrawalRepository.findById(withdrawalId);

    if (!withdrawal) {
      throw new Error('Withdrawal request not found');
    }

    if (withdrawal.status !== 'pending') {
      throw new Error('Withdrawal request has already been processed');
    }

    // Update withdrawal status to completed
    const updatedWithdrawal = await withdrawalRepository.updateStatus(
      withdrawalId,
      'completed',
      adminId,
      null
    );

    return updatedWithdrawal;
  }

  /**
   * Reject a withdrawal request (refund balance)
   * @param {string} withdrawalId - Withdrawal request ID
   * @param {string} adminId - Admin user ID
   * @param {string|null} rejectionReason - Optional rejection reason
   * @returns {Promise<Object>} Object with updated withdrawal and refunded balance
   * @throws {Error} If withdrawal not found or already processed
   */
  async rejectWithdrawal(withdrawalId, adminId, rejectionReason = null) {
    const client = await pool.connect();

    try {
      await client.query('BEGIN');

      // Get withdrawal request
      const withdrawal = await withdrawalRepository.findById(withdrawalId);

      if (!withdrawal) {
        throw new Error('Withdrawal request not found');
      }

      if (withdrawal.status !== 'pending') {
        throw new Error('Withdrawal request has already been processed');
      }

      // Get user
      const user = await userRepository.findById(withdrawal.userId);

      if (!user) {
        throw new Error('User not found');
      }

      // Refund the amount to user's balance
      const newBalance = user.walletBalance + withdrawal.amount;
      await userRepository.updateBalance(withdrawal.userId, newBalance);

      // Update withdrawal status to rejected
      const updatedWithdrawal = await withdrawalRepository.updateStatus(
        withdrawalId,
        'rejected',
        adminId,
        rejectionReason
      );

      await client.query('COMMIT');

      return {
        withdrawal: updatedWithdrawal,
        refundedBalance: newBalance
      };
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }

  /**
   * Get user's withdrawal history
   * @param {string} userId - User ID
   * @param {number} page - Page number (1-indexed)
   * @param {number} limit - Results per page
   * @returns {Promise<Object>} Paginated withdrawal history
   */
  async getUserWithdrawals(userId, page = 1, limit = 20) {
    const offset = (page - 1) * limit;
    const result = await withdrawalRepository.findByUserId(userId, limit, offset);

    return {
      withdrawals: result.withdrawals,
      total: result.total,
      page,
      totalPages: Math.ceil(result.total / limit)
    };
  }

  /**
   * Get pending withdrawals for admin review
   * @param {number} page - Page number (1-indexed)
   * @param {number} limit - Results per page
   * @returns {Promise<Object>} Paginated pending withdrawals
   */
  async getPendingWithdrawals(page = 1, limit = 20) {
    const offset = (page - 1) * limit;
    const result = await withdrawalRepository.findPending(limit, offset);

    return {
      withdrawals: result.withdrawals,
      total: result.total,
      page,
      totalPages: Math.ceil(result.total / limit)
    };
  }

  /**
   * Get count of pending withdrawals
   * @returns {Promise<number>} Count of pending withdrawals
   */
  async getPendingCount() {
    const result = await withdrawalRepository.findPending(1, 1);
    return result.total;
  }

  /**
   * Get total amount of completed withdrawals
   * @returns {Promise<number>} Total amount in paise
   */
  async getTotalCompletedAmount() {
    const query = `
      SELECT COALESCE(SUM(amount), 0) as total
      FROM withdrawal_requests
      WHERE status = 'completed'
    `;
    const result = await pool.query(query);
    return parseInt(result.rows[0].total);
  }
}

export default new WithdrawalService();
