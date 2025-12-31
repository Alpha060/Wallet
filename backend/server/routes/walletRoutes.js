import express from 'express';
import walletService from '../services/walletService.js';
import { authenticate } from '../middleware/authMiddleware.js';

const router = express.Router();

/**
 * GET /api/wallet/balance
 * Get user's current wallet balance
 */
router.get('/balance', authenticate, async (req, res) => {
  try {
    const userId = req.user.userId;

    const balanceInfo = await walletService.getBalance(userId);

    res.status(200).json({
      balance: balanceInfo.balance,
      currency: balanceInfo.currency
    });
  } catch (error) {
    console.error('Get balance error:', error);

    if (error.message === 'User not found') {
      return res.status(404).json({
        error: {
          code: 'USER_NOT_FOUND',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching balance',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/wallet/transactions
 * Get user's transaction history with pagination and filtering
 */
router.get('/transactions', authenticate, async (req, res) => {
  try {
    const userId = req.user.userId;
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 20;
    const type = req.query.type || null;

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

    // Validate type filter
    if (type && !['deposit', 'withdrawal'].includes(type)) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Type must be either "deposit" or "withdrawal"',
          timestamp: new Date().toISOString()
        }
      });
    }

    const result = await walletService.getTransactionHistory(userId, page, limit, type);

    res.status(200).json({
      transactions: result.transactions,
      total: result.total,
      page: result.page,
      totalPages: result.totalPages
    });
  } catch (error) {
    console.error('Get transactions error:', error);

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching transactions',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
