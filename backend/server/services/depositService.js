import depositRepository from '../repositories/depositRepository.js';
import userRepository from '../repositories/userRepository.js';
import imageValidator from './imageValidator.js';
import { getFileUrl } from '../middleware/uploadMiddleware.js';
import { optimizePaymentProof } from './imageOptimizer.js';
import pool from '../database/db.js';
import referralBonusService from './referralBonusService.js';

/**
 * DepositService handles deposit request operations
 */
class DepositService {
  /**
   * Create a new deposit request
   * @param {string} userId - User ID
   * @param {number} amount - Amount in paise
   * @param {Object} paymentProofFile - Multer file object
   * @param {string|null} transactionId - Optional transaction ID
   * @returns {Promise<Object>} Created deposit request
   * @throws {Error} If validation fails
   */
  async createDepositRequest(userId, amount, paymentProofFile, transactionId = null) {
    // Validate amount
    if (!amount || amount <= 0) {
      throw new Error('Amount must be greater than zero');
    }

    if (amount < 100) {
      throw new Error('Minimum deposit amount is ₹1 (100 paise)');
    }

    // Validate payment proof file
    if (!paymentProofFile) {
      throw new Error('Payment proof is required');
    }

    imageValidator.validateOrThrow(paymentProofFile);

    // Optimize image (compress and create thumbnail)
    let optimizedFilename = paymentProofFile.filename;
    try {
      const optimizationResult = await optimizePaymentProof(paymentProofFile.filename);
      optimizedFilename = optimizationResult.optimizedFilename;
      console.log(`Image optimized: ${optimizationResult.savings} savings`);
    } catch (error) {
      console.error('Image optimization failed, using original:', error);
      // Continue with original file if optimization fails
    }

    // Generate public URL for payment proof
    const paymentProofUrl = getFileUrl(optimizedFilename);

    // Create deposit request
    const deposit = await depositRepository.create(
      userId,
      amount,
      paymentProofUrl,
      transactionId
    );

    return deposit;
  }

  /**
   * Approve a deposit request and update user balance
   * @param {string} depositId - Deposit request ID
   * @param {string} adminId - Admin user ID
   * @returns {Promise<Object>} Object with updated deposit and new balance
   * @throws {Error} If deposit not found or already processed
   */
  async approveDeposit(depositId, adminId) {
    const client = await pool.connect();

    try {
      await client.query('BEGIN');

      // Get deposit request
      const deposit = await depositRepository.findById(depositId);

      if (!deposit) {
        throw new Error('Deposit request not found');
      }

      if (deposit.status !== 'pending') {
        throw new Error('Deposit request has already been processed');
      }

      // Get user
      const user = await userRepository.findById(deposit.userId);

      if (!user) {
        throw new Error('User not found');
      }

      // Calculate new balance
      const newBalance = user.walletBalance + deposit.amount;

      // Update user balance
      await userRepository.updateBalance(deposit.userId, newBalance);

      // Update deposit status
      const updatedDeposit = await depositRepository.updateStatus(
        depositId,
        'approved',
        adminId,
        null
      );

      // Create referral bonus if user was referred
      try {
        await referralBonusService.createBonusForDeposit(deposit.userId, depositId, deposit.amount);
      } catch (bonusError) {
        console.error('Error creating referral bonus:', bonusError);
        // Don't fail the deposit approval if bonus creation fails
      }

      await client.query('COMMIT');

      return {
        deposit: updatedDeposit,
        newBalance
      };
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }

  /**
   * Reject a deposit request
   * @param {string} depositId - Deposit request ID
   * @param {string} adminId - Admin user ID
   * @param {string|null} rejectionReason - Optional rejection reason
   * @returns {Promise<Object>} Updated deposit request
   * @throws {Error} If deposit not found or already processed
   */
  async rejectDeposit(depositId, adminId, rejectionReason = null) {
    // Get deposit request
    const deposit = await depositRepository.findById(depositId);

    if (!deposit) {
      throw new Error('Deposit request not found');
    }

    if (deposit.status !== 'pending') {
      throw new Error('Deposit request has already been processed');
    }

    // Update deposit status
    const updatedDeposit = await depositRepository.updateStatus(
      depositId,
      'rejected',
      adminId,
      rejectionReason
    );

    return updatedDeposit;
  }

  /**
   * Get user's deposit history
   * @param {string} userId - User ID
   * @param {number} page - Page number (1-indexed)
   * @param {number} limit - Results per page
   * @returns {Promise<Object>} Paginated deposit history
   */
  async getUserDeposits(userId, page = 1, limit = 20) {
    const offset = (page - 1) * limit;
    const result = await depositRepository.findByUserId(userId, limit, offset);

    return {
      deposits: result.deposits,
      total: result.total,
      page,
      totalPages: Math.ceil(result.total / limit)
    };
  }

  /**
   * Get pending deposits for admin review
   * @param {number} page - Page number (1-indexed)
   * @param {number} limit - Results per page
   * @returns {Promise<Object>} Paginated pending deposits
   */
  async getPendingDeposits(page = 1, limit = 20) {
    const offset = (page - 1) * limit;
    const result = await depositRepository.findPending(limit, offset);

    return {
      deposits: result.deposits,
      total: result.total,
      page,
      totalPages: Math.ceil(result.total / limit)
    };
  }

  /**
   * Get count of pending deposits
   * @returns {Promise<number>} Count of pending deposits
   */
  async getPendingCount() {
    const result = await depositRepository.findPending(1, 1);
    return result.total;
  }

  /**
   * Get total amount of approved deposits
   * @returns {Promise<number>} Total amount in paise
   */
  async getTotalApprovedAmount() {
    const query = `
      SELECT COALESCE(SUM(amount), 0) as total
      FROM deposit_requests
      WHERE status = 'approved'
    `;
    const result = await pool.query(query);
    return parseInt(result.rows[0].total);
  }
}

export default new DepositService();
