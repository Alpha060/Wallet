import express from 'express';
import withdrawalService from '../services/withdrawalService.js';
import { authenticate } from '../middleware/authMiddleware.js';
import { withdrawalLimiter } from '../middleware/rateLimitMiddleware.js';

const router = express.Router();

/**
 * POST /api/withdrawals/create
 * Create a new withdrawal request
 */
router.post('/create', authenticate, withdrawalLimiter, async (req, res) => {
  try {
    const { amount, bankDetails } = req.body;
    const userId = req.user.userId;

    // Validate amount
    if (!amount) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Amount is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    const amountInPaise = parseInt(amount);
    if (isNaN(amountInPaise)) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Amount must be a valid number',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Validate bank details
    if (!bankDetails) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Bank details are required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Create withdrawal request
    const withdrawal = await withdrawalService.createWithdrawalRequest(
      userId,
      amountInPaise,
      bankDetails
    );

    res.status(201).json({
      withdrawalId: withdrawal.id,
      status: withdrawal.status
    });
  } catch (error) {
    console.error('Create withdrawal error:', error);

    if (error.message === 'Insufficient balance') {
      return res.status(422).json({
        error: {
          code: 'INSUFFICIENT_BALANCE',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message.includes('Amount') ||
        error.message.includes('Bank details') ||
        error.message.includes('UPI ID') ||
        error.message.includes('IFSC') ||
        error.message.includes('account')) {
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
        message: 'An error occurred while creating withdrawal request',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/withdrawals/my-withdrawals
 * Get user's withdrawal history with pagination
 */
router.get('/my-withdrawals', authenticate, async (req, res) => {
  try {
    const userId = req.user.userId;
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 20;

    // Validate pagination parameters
    if (page < 1) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Page must be greater than 0',
          timestamp: new Date().toISOString()
        }
      });
    }

    if (limit < 1 || limit > 100) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Limit must be between 1 and 100',
          timestamp: new Date().toISOString()
        }
      });
    }

    const result = await withdrawalService.getUserWithdrawals(userId, page, limit);

    res.status(200).json({
      withdrawals: result.withdrawals,
      total: result.total,
      page: result.page,
      totalPages: result.totalPages
    });
  } catch (error) {
    console.error('Get user withdrawals error:', error);

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching withdrawals',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
