import express from 'express';
import referralService from '../services/referralService.js';
import { authenticate } from '../middleware/authMiddleware.js';

const router = express.Router();

/**
 * GET /api/referrals/my-code
 * Get current user's referral code
 */
router.get('/my-code', authenticate, async (req, res) => {
  try {
    // Admin users don't have referral codes
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_REFERRAL',
          message: 'Admin users do not have referral codes',
          timestamp: new Date().toISOString()
        }
      });
    }

    const referralInfo = await referralService.getUserReferralInfo(req.user.userId);

    res.status(200).json({
      referralCode: referralInfo.referralCode
    });
  } catch (error) {
    console.error('Get referral code error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching referral code',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/referrals/my-referrals
 * Get list of users referred by current user
 */
router.get('/my-referrals', authenticate, async (req, res) => {
  try {
    // Admin users don't have referrals
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_REFERRAL',
          message: 'Admin users do not have referrals',
          timestamp: new Date().toISOString()
        }
      });
    }

    const referralInfo = await referralService.getUserReferralInfo(req.user.userId);
    
    console.log('[ReferralRoutes] Referred users:', JSON.stringify(referralInfo.referredUsers, null, 2));

    res.status(200).json({
      referrals: referralInfo.referredUsers.map(user => ({
        ...user,
        hasDeposited: user.hasDeposited === true
      })),
      totalReferrals: referralInfo.totalReferrals
    });
  } catch (error) {
    console.error('Get referrals error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching referrals',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/referrals/stats
 * Get referral statistics for current user
 */
router.get('/stats', authenticate, async (req, res) => {
  try {
    // Admin users don't have referral stats
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_REFERRAL',
          message: 'Admin users do not have referral statistics',
          timestamp: new Date().toISOString()
        }
      });
    }

    const referralInfo = await referralService.getUserReferralInfo(req.user.userId);

    res.status(200).json({
      referralCode: referralInfo.referralCode,
      totalReferrals: referralInfo.totalReferrals,
      confirmedReferrals: referralInfo.confirmedReferrals,
      requiredReferrals: 5 // Minimum confirmed referrals needed for withdrawal
    });
  } catch (error) {
    console.error('Get referral stats error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching referral statistics',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/referrals/verify
 * Verify if a referral code is valid (public endpoint for registration)
 */
router.post('/verify', async (req, res) => {
  try {
    const { referralCode } = req.body;

    if (!referralCode) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Referral code is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    const referrer = await referralService.verifyReferralCode(referralCode);

    if (!referrer) {
      return res.status(404).json({
        valid: false,
        error: {
          code: 'INVALID_REFERRAL_CODE',
          message: 'Invalid referral code',
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(200).json({
      valid: true,
      referrerName: referrer.name || 'A user'
    });
  } catch (error) {
    console.error('Verify referral code error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while verifying referral code',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
