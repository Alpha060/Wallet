import referralRepository from '../repositories/referralRepository.js';

/**
 * ReferralService handles referral business logic
 */
class ReferralService {
  /**
   * Validate referral code format
   * @param {string} code - Referral code to validate
   * @returns {boolean} True if valid format
   */
  validateReferralCodeFormat(code) {
    // 6 alphanumeric characters
    const codeRegex = /^[A-Z0-9]{6}$/;
    return codeRegex.test(code.toUpperCase());
  }

  /**
   * Verify referral code exists and is valid
   * @param {string} referralCode - Referral code to verify
   * @returns {Promise<Object|null>} Referrer user object or null
   */
  async verifyReferralCode(referralCode) {
    if (!referralCode || !this.validateReferralCodeFormat(referralCode)) {
      return null;
    }

    const referrer = await referralRepository.findByReferralCode(referralCode);
    return referrer;
  }

  /**
   * Get user's referral information
   * @param {string} userId - User ID
   * @returns {Promise<Object>} Referral information
   */
  async getUserReferralInfo(userId) {
    const referralCode = await referralRepository.getUserReferralCode(userId);
    const referredUsers = await referralRepository.getReferredUsers(userId);
    const stats = await referralRepository.getReferralStats(userId);

    return {
      referralCode,
      referredUsers: referredUsers.map(user => ({
        id: user.id,
        name: user.name || 'Anonymous',
        email: user.email,
        joinedAt: user.createdAt
      })),
      totalReferrals: stats.totalReferrals
    };
  }

  /**
   * Process referral during user registration
   * @param {string} userId - New user's ID
   * @param {string} referralCode - Referral code used (optional)
   * @returns {Promise<boolean>} True if referral was processed
   */
  async processReferral(userId, referralCode) {
    if (!referralCode) {
      return false;
    }

    const referrer = await this.verifyReferralCode(referralCode);
    if (!referrer) {
      throw new Error('Invalid referral code');
    }

    // Set the referrer
    await referralRepository.setReferrer(userId, referrer.id);
    return true;
  }
}

export default new ReferralService();
