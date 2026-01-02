import express from 'express';
import referralBonusService from '../services/referralBonusService.js';
import { authenticate, requireAdmin } from '../middleware/authMiddleware.js';

const router = express.Router();

/**
 * GET /api/referral-bonus/unclaimed
 * Get user's unclaimed bonuses
 */
router.get('/unclaimed', authenticate, async (req, res) => {
  try {
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_BONUS',
          message: 'Admin users do not have referral bonuses',
          timestamp: new Date().toISOString()
        }
      });
    }

    const result = await referralBonusService.getUnclaimedBonuses(req.user.userId);

    res.status(200).json(result);
  } catch (error) {
    console.error('Get unclaimed bonuses error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching unclaimed bonuses',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/referral-bonus/stats
 * Get user's bonus statistics
 */
router.get('/stats', authenticate, async (req, res) => {
  try {
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_BONUS',
          message: 'Admin users do not have referral bonuses',
          timestamp: new Date().toISOString()
        }
      });
    }

    const stats = await referralBonusService.getBonusStats(req.user.userId);

    res.status(200).json(stats);
  } catch (error) {
    console.error('Get bonus stats error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching bonus statistics',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/referral-bonus/claim/:bonusId
 * Claim a specific bonus
 */
router.post('/claim/:bonusId', authenticate, async (req, res) => {
  try {
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_BONUS',
          message: 'Admin users cannot claim bonuses',
          timestamp: new Date().toISOString()
        }
      });
    }

    const bonusId = req.params.bonusId;
    const claimRequest = await referralBonusService.claimBonus(req.user.userId, bonusId);

    res.status(201).json({
      message: 'Claim request submitted successfully',
      claim: claimRequest
    });
  } catch (error) {
    console.error('Claim bonus error:', error);
    
    if (error.message === 'Bonus not found') {
      return res.status(404).json({
        error: {
          code: 'NOT_FOUND',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message.includes('not authorized') || 
        error.message.includes('already been claimed') ||
        error.message.includes('already pending')) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while claiming bonus',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/referral-bonus/claim-history
 * Get user's claim history
 */
router.get('/claim-history', authenticate, async (req, res) => {
  try {
    if (req.user.isAdmin) {
      return res.status(403).json({
        error: {
          code: 'ADMIN_NO_BONUS',
          message: 'Admin users do not have claim history',
          timestamp: new Date().toISOString()
        }
      });
    }

    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 20;

    const result = await referralBonusService.getClaimHistory(req.user.userId, page, limit);

    res.status(200).json(result);
  } catch (error) {
    console.error('Get claim history error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching claim history',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/referral-bonus/admin/pending
 * Get pending claim requests (admin only)
 */
router.get('/admin/pending', authenticate, requireAdmin, async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 20;

    const result = await referralBonusService.getPendingClaims(page, limit);

    res.status(200).json(result);
  } catch (error) {
    console.error('Get pending claims error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching pending claims',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/referral-bonus/admin/claims/:id/approve
 * Approve a claim request (admin only)
 */
router.post('/admin/claims/:id/approve', authenticate, requireAdmin, async (req, res) => {
  try {
    const claimId = req.params.id;
    const adminId = req.user.userId;

    const result = await referralBonusService.approveClaim(claimId, adminId);

    res.status(200).json({
      success: true,
      newBalance: result.newBalance
    });
  } catch (error) {
    console.error('Approve claim error:', error);
    
    if (error.message === 'Claim request not found') {
      return res.status(404).json({
        error: {
          code: 'NOT_FOUND',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message.includes('already been processed')) {
      return res.status(422).json({
        error: {
          code: 'ALREADY_PROCESSED',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while approving claim',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/referral-bonus/admin/claims/:id/reject
 * Reject a claim request (admin only)
 */
router.post('/admin/claims/:id/reject', authenticate, requireAdmin, async (req, res) => {
  try {
    const claimId = req.params.id;
    const adminId = req.user.userId;
    const { reason } = req.body;

    await referralBonusService.rejectClaim(claimId, adminId, reason || null);

    res.status(200).json({
      success: true
    });
  } catch (error) {
    console.error('Reject claim error:', error);
    
    if (error.message === 'Claim request not found') {
      return res.status(404).json({
        error: {
          code: 'NOT_FOUND',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message.includes('already been processed')) {
      return res.status(422).json({
        error: {
          code: 'ALREADY_PROCESSED',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while rejecting claim',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
