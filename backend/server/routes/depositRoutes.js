import express from 'express';
import adminService from '../services/adminService.js';
import depositService from '../services/depositService.js';
import { authenticate } from '../middleware/authMiddleware.js';
import { uploadSingle, verifyFileContent } from '../middleware/uploadMiddleware.js';
import { depositLimiter } from '../middleware/rateLimitMiddleware.js';

const router = express.Router();

/**
 * GET /api/deposits/payment-details
 * Get payment details (QR code and UPI ID) - Public endpoint
 */
router.get('/payment-details', async (req, res) => {
  try {
    const paymentDetails = await adminService.getPaymentDetails();

    res.status(200).json({
      qrCodeUrl: paymentDetails.qrCodeUrl,
      upiId: paymentDetails.upiId,
      adminName: paymentDetails.adminName
    });
  } catch (error) {
    console.error('Get payment details error:', error);
    
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching payment details',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/deposits/create
 * Create a new deposit request
 */
router.post('/create', authenticate, depositLimiter, uploadSingle('paymentProof'), verifyFileContent, async (req, res) => {
  try {
    const { amount, transactionId } = req.body;
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

    // Check if payment proof was uploaded
    if (!req.file) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Payment proof is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Create deposit request
    const deposit = await depositService.createDepositRequest(
      userId,
      amountInPaise,
      req.file,
      transactionId || null
    );

    res.status(201).json({
      depositId: deposit.id,
      status: deposit.status
    });
  } catch (error) {
    console.error('Create deposit error:', error);
    
    if (error.message.includes('Amount') || 
        error.message.includes('Payment proof') ||
        error.message.includes('Invalid file')) {
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
        message: 'An error occurred while creating deposit request',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/deposits/my-deposits
 * Get user's deposit history with pagination
 */
router.get('/my-deposits', authenticate, async (req, res) => {
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

    const result = await depositService.getUserDeposits(userId, page, limit);

    res.status(200).json({
      deposits: result.deposits,
      total: result.total,
      page: result.page,
      totalPages: result.totalPages
    });
  } catch (error) {
    console.error('Get user deposits error:', error);
    
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching deposits',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
