import referralBonusRepository from '../repositories/referralBonusRepository.js';
import userRepository from '../repositories/userRepository.js';
import pool from '../database/db.js';

/**
 * ReferralBonusService handles referral bonus business logic
 */
class ReferralBonusService {
  /**
   * Bonus percentage (5%)
   */
  static BONUS_PERCENTAGE = 0.05;

  /**
   * Create a referral bonus when a referred user's deposit is approved
   * Called from depositService.approveDeposit
   * @param {string} depositUserId - User who made the deposit
   * @param {string} depositId - Deposit request ID
   * @param {number} depositAmount - Deposit amount in paise
   * @returns {Promise<Object|null>} Created bonus or null if no referrer
   */
  async createBonusForDeposit(depositUserId, depositId, depositAmount) {
    // Get the user who made the deposit
    const depositUser = await userRepository.findById(depositUserId);
    
    if (!depositUser || !depositUser.referredBy) {
      // User has no referrer, no bonus to create
      return null;
    }

    // Calculate bonus amount (5% of deposit)
    const bonusAmount = Math.floor(depositAmount * ReferralBonusService.BONUS_PERCENTAGE);
    
    if (bonusAmount <= 0) {
      return null;
    }

    // Create the bonus record
    const bonus = await referralBonusRepository.create(
      depositUser.referredBy,
      depositUserId,
      depositId,
      depositAmount,
      bonusAmount
    );

    console.log(`Referral bonus created: ₹${bonusAmount / 100} for referrer ${depositUser.referredBy}`);
    return bonus;
  }

  /**
   * Get unclaimed bonuses for a user
   * @param {string} userId - User ID
   * @returns {Promise<Object>} Unclaimed bonuses and total
   */
  async getUnclaimedBonuses(userId) {
    const bonuses = await referralBonusRepository.getUnclaimedBonuses(userId);
    const totalAmount = await referralBonusRepository.getTotalUnclaimedAmount(userId);
    
    return {
      bonuses: bonuses.map(b => ({
        id: b.id,
        bonusAmount: b.bonusAmount,
        depositAmount: b.depositAmount,
        referredUserName: b.referredUserName || 'Anonymous',
        referredUserEmail: b.referredUserEmail,
        createdAt: b.createdAt
      })),
      totalAmount
    };
  }

  /**
   * Get bonus statistics for a user
   * @param {string} userId - User ID
   * @returns {Promise<Object>} Bonus statistics
   */
  async getBonusStats(userId) {
    return await referralBonusRepository.getUserBonusStats(userId);
  }

  /**
   * Claim a bonus (creates a claim request for admin approval)
   * @param {string} userId - User ID
   * @param {string} bonusId - Bonus ID to claim
   * @returns {Promise<Object>} Created claim request
   * @throws {Error} If bonus not found, already claimed, or has pending claim
   */
  async claimBonus(userId, bonusId) {
    // Get the bonus
    const bonus = await referralBonusRepository.findById(bonusId);
    
    if (!bonus) {
      throw new Error('Bonus not found');
    }

    if (bonus.referrerId !== userId) {
      throw new Error('You are not authorized to claim this bonus');
    }

    if (bonus.isClaimed) {
      throw new Error('This bonus has already been claimed');
    }

    // Check for pending claim
    const hasPending = await referralBonusRepository.hasPendingClaim(bonusId);
    if (hasPending) {
      throw new Error('A claim request for this bonus is already pending');
    }

    // Create claim request
    const claimRequest = await referralBonusRepository.createClaimRequest(
      userId,
      bonusId,
      bonus.bonusAmount
    );

    return {
      ...claimRequest,
      referredUserName: bonus.referredUserName || 'Anonymous'
    };
  }

  /**
   * Get user's claim history
   * @param {string} userId - User ID
   * @param {number} page - Page number
   * @param {number} limit - Results per page
   * @returns {Promise<Object>} Paginated claim history
   */
  async getClaimHistory(userId, page = 1, limit = 20) {
    const offset = (page - 1) * limit;
    const result = await referralBonusRepository.getUserClaimHistory(userId, limit, offset);
    
    return {
      claims: result.claims.map(c => ({
        id: c.id,
        amount: c.amount,
        status: c.status,
        referredUserName: c.referredUserName || 'Anonymous',
        referredUserEmail: c.referredUserEmail,
        createdAt: c.createdAt,
        processedAt: c.processedAt,
        rejectionReason: c.rejectionReason
      })),
      total: result.total,
      page,
      totalPages: Math.ceil(result.total / limit)
    };
  }

  /**
   * Get pending claims for admin
   * @param {number} page - Page number
   * @param {number} limit - Results per page
   * @returns {Promise<Object>} Paginated pending claims
   */
  async getPendingClaims(page = 1, limit = 20) {
    const offset = (page - 1) * limit;
    const result = await referralBonusRepository.findPendingClaims(limit, offset);
    
    return {
      claims: result.claims,
      total: result.total,
      page,
      totalPages: Math.ceil(result.total / limit)
    };
  }

  /**
   * Get count of pending claims
   * @returns {Promise<number>} Count of pending claims
   */
  async getPendingCount() {
    const result = await referralBonusRepository.findPendingClaims(1, 0);
    return result.total;
  }

  /**
   * Approve a bonus claim request
   * @param {string} claimId - Claim request ID
   * @param {string} adminId - Admin user ID
   * @returns {Promise<Object>} Result with updated claim and new balance
   * @throws {Error} If claim not found or already processed
   */
  async approveClaim(claimId, adminId) {
    const client = await pool.connect();

    try {
      await client.query('BEGIN');

      // Get claim request
      const claim = await referralBonusRepository.findClaimById(claimId);
      
      if (!claim) {
        throw new Error('Claim request not found');
      }

      if (claim.status !== 'pending') {
        throw new Error('Claim request has already been processed');
      }

      // Get user
      const user = await userRepository.findById(claim.userId);
      if (!user) {
        throw new Error('User not found');
      }

      // Calculate new balance
      const newBalance = user.walletBalance + claim.amount;

      // Update user balance
      await userRepository.updateBalance(claim.userId, newBalance);

      // Mark bonus as claimed
      await referralBonusRepository.markAsClaimed(claim.bonusId);

      // Update claim status
      const updatedClaim = await referralBonusRepository.updateClaimStatus(
        claimId,
        'approved',
        adminId,
        null
      );

      await client.query('COMMIT');

      return {
        claim: updatedClaim,
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
   * Reject a bonus claim request
   * @param {string} claimId - Claim request ID
   * @param {string} adminId - Admin user ID
   * @param {string|null} rejectionReason - Rejection reason
   * @returns {Promise<Object>} Updated claim request
   * @throws {Error} If claim not found or already processed
   */
  async rejectClaim(claimId, adminId, rejectionReason = null) {
    // Get claim request
    const claim = await referralBonusRepository.findClaimById(claimId);
    
    if (!claim) {
      throw new Error('Claim request not found');
    }

    if (claim.status !== 'pending') {
      throw new Error('Claim request has already been processed');
    }

    // Update claim status (bonus remains unclaimed, user can try again)
    const updatedClaim = await referralBonusRepository.updateClaimStatus(
      claimId,
      'rejected',
      adminId,
      rejectionReason
    );

    return updatedClaim;
  }
}

export default new ReferralBonusService();
