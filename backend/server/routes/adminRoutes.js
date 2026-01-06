import express from 'express';
import adminService from '../services/adminService.js';
import depositService from '../services/depositService.js';
import withdrawalService from '../services/withdrawalService.js';
import { authenticate, requireAdmin } from '../middleware/authMiddleware.js';
import { uploadSingle, verifyFileContent } from '../middleware/uploadMiddleware.js';
import { adminLimiter } from '../middleware/rateLimitMiddleware.js';

const router = express.Router();

// Apply admin rate limiter to all admin routes
router.use(adminLimiter);

/**
 * POST /api/admin/qr-code
 * Upload QR code image (admin only)
 */
router.post('/qr-code', authenticate, requireAdmin, uploadSingle('qrCode'), verifyFileContent, async (req, res) => {
  try {
    // Check if file was uploaded
    if (!req.file) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'QR code image is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Upload QR code
    const settings = await adminService.uploadQRCode(req.file);

    res.status(200).json({
      qrCodeUrl: settings.qrCodeUrl
    });
  } catch (error) {
    console.error('QR code upload error:', error);
    
    if (error.message.includes('Invalid file type') || error.message.includes('File size')) {
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
        message: 'An error occurred while uploading QR code',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * PUT /api/admin/upi-id
 * Update UPI ID (admin only)
 */
router.put('/upi-id', authenticate, requireAdmin, async (req, res) => {
  try {
    const { upiId } = req.body;

    // Validate input
    if (!upiId) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'UPI ID is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Update UPI ID
    const settings = await adminService.updateUpiId(upiId);

    res.status(200).json({
      upiId: settings.upiId
    });
  } catch (error) {
    console.error('UPI ID update error:', error);
    
    if (error.message.includes('Invalid UPI ID format') || error.message.includes('required')) {
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
        message: 'An error occurred while updating UPI ID',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/pending-deposits
 * Get all pending deposit requests (admin only)
 */
router.get('/pending-deposits', authenticate, requireAdmin, async (req, res) => {
  try {
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

    const result = await depositService.getPendingDeposits(page, limit);

    res.status(200).json({
      deposits: result.deposits,
      total: result.total,
      page: result.page,
      totalPages: result.totalPages
    });
  } catch (error) {
    console.error('Get pending deposits error:', error);
    
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching pending deposits',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/admin/deposits/:id/approve
 * Approve a deposit request (admin only)
 */
router.post('/deposits/:id/approve', authenticate, requireAdmin, async (req, res) => {
  try {
    const depositId = req.params.id;
    const adminId = req.user.userId;

    const result = await depositService.approveDeposit(depositId, adminId);

    res.status(200).json({
      success: true,
      newBalance: result.newBalance
    });
  } catch (error) {
    console.error('Approve deposit error:', error);
    
    if (error.message === 'Deposit request not found') {
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
        message: 'An error occurred while approving deposit',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/admin/deposits/:id/reject
 * Reject a deposit request (admin only)
 */
router.post('/deposits/:id/reject', authenticate, requireAdmin, async (req, res) => {
  try {
    const depositId = req.params.id;
    const adminId = req.user.userId;
    const { reason } = req.body;

    const deposit = await depositService.rejectDeposit(depositId, adminId, reason || null);

    res.status(200).json({
      success: true
    });
  } catch (error) {
    console.error('Reject deposit error:', error);
    
    if (error.message === 'Deposit request not found') {
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
        message: 'An error occurred while rejecting deposit',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/pending-withdrawals
 * Get all pending withdrawal requests (admin only)
 */
router.get('/pending-withdrawals', authenticate, requireAdmin, async (req, res) => {
  try {
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

    const result = await withdrawalService.getPendingWithdrawals(page, limit);

    res.status(200).json({
      withdrawals: result.withdrawals,
      total: result.total,
      page: result.page,
      totalPages: result.totalPages
    });
  } catch (error) {
    console.error('Get pending withdrawals error:', error);

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching pending withdrawals',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/admin/withdrawals/:id/confirm
 * Confirm a withdrawal request (admin only)
 */
router.post('/withdrawals/:id/confirm', authenticate, requireAdmin, async (req, res) => {
  try {
    const withdrawalId = req.params.id;
    const adminId = req.user.userId;

    const withdrawal = await withdrawalService.confirmWithdrawal(withdrawalId, adminId);

    res.status(200).json({
      success: true
    });
  } catch (error) {
    console.error('Confirm withdrawal error:', error);

    if (error.message === 'Withdrawal request not found') {
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
        message: 'An error occurred while confirming withdrawal',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/admin/withdrawals/:id/reject
 * Reject a withdrawal request (admin only)
 */
router.post('/withdrawals/:id/reject', authenticate, requireAdmin, async (req, res) => {
  try {
    const withdrawalId = req.params.id;
    const adminId = req.user.userId;
    const { reason } = req.body;

    const result = await withdrawalService.rejectWithdrawal(withdrawalId, adminId, reason || null);

    res.status(200).json({
      success: true
    });
  } catch (error) {
    console.error('Reject withdrawal error:', error);

    if (error.message === 'Withdrawal request not found') {
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
        message: 'An error occurred while rejecting withdrawal',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/history
 * Get all transaction history (admin only)
 */
router.get('/history', authenticate, requireAdmin, async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 20;
    const type = req.query.type; // 'deposit', 'withdrawal', or undefined for all

    if (page < 1 || limit < 1 || limit > 100) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Invalid pagination parameters',
          timestamp: new Date().toISOString()
        }
      });
    }

    const offset = (page - 1) * limit;
    const pool = (await import('../database/db.js')).default;

    console.log('Fetching history with type:', type, 'page:', page, 'limit:', limit);

    let transactions = [];
    let totalCount = 0;

    if (!type || type === 'all') {
      // Get deposits
      const depositsResult = await pool.query(`
        SELECT 
          d.id::text,
          d.amount,
          d.transaction_id as "transactionId",
          d.payment_proof_url as "paymentProofUrl",
          d.status,
          d.created_at as "createdAt",
          d.processed_at as "processedAt",
          u.email as "userEmail",
          COALESCE(u.name, 'N/A') as "userName"
        FROM deposit_requests d
        JOIN users u ON d.user_id = u.id
        WHERE d.status IN ('approved', 'rejected')
        ORDER BY d.created_at DESC
      `);

      // Get withdrawals
      const withdrawalsResult = await pool.query(`
        SELECT 
          w.id::text,
          w.amount,
          w.bank_details as "bankDetails",
          w.status,
          w.created_at as "createdAt",
          w.processed_at as "processedAt",
          u.email as "userEmail",
          COALESCE(u.name, 'N/A') as "userName"
        FROM withdrawal_requests w
        JOIN users u ON w.user_id = u.id
        WHERE w.status IN ('completed', 'rejected')
        ORDER BY w.created_at DESC
      `);

      // Combine and sort
      const deposits = depositsResult.rows.map(d => ({ ...d, type: 'deposit' }));
      const withdrawals = withdrawalsResult.rows.map(w => ({ ...w, type: 'withdrawal' }));
      
      transactions = [...deposits, ...withdrawals]
        .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
        .slice(offset, offset + limit);

      totalCount = deposits.length + withdrawals.length;

    } else if (type === 'deposit') {
      const result = await pool.query(`
        SELECT 
          d.id::text,
          d.amount,
          d.transaction_id as "transactionId",
          d.payment_proof_url as "paymentProofUrl",
          d.status,
          d.created_at as "createdAt",
          d.processed_at as "processedAt",
          u.email as "userEmail",
          COALESCE(u.name, 'N/A') as "userName"
        FROM deposit_requests d
        JOIN users u ON d.user_id = u.id
        WHERE d.status IN ('approved', 'rejected')
        ORDER BY d.created_at DESC
        LIMIT $1 OFFSET $2
      `, [limit, offset]);

      const countResult = await pool.query(`
        SELECT COUNT(*) as total
        FROM deposit_requests
        WHERE status IN ('approved', 'rejected')
      `);

      transactions = result.rows.map(d => ({ ...d, type: 'deposit' }));
      totalCount = parseInt(countResult.rows[0].total);

    } else if (type === 'withdrawal') {
      const result = await pool.query(`
        SELECT 
          w.id::text,
          w.amount,
          w.bank_details as "bankDetails",
          w.status,
          w.created_at as "createdAt",
          w.processed_at as "processedAt",
          u.email as "userEmail",
          COALESCE(u.name, 'N/A') as "userName"
        FROM withdrawal_requests w
        JOIN users u ON w.user_id = u.id
        WHERE w.status IN ('completed', 'rejected')
        ORDER BY w.created_at DESC
        LIMIT $1 OFFSET $2
      `, [limit, offset]);

      const countResult = await pool.query(`
        SELECT COUNT(*) as total
        FROM withdrawal_requests
        WHERE status IN ('completed', 'rejected')
      `);

      transactions = result.rows.map(w => ({ ...w, type: 'withdrawal' }));
      totalCount = parseInt(countResult.rows[0].total);
    }

    const totalPages = Math.ceil(totalCount / limit);

    console.log('History fetched:', transactions.length, 'transactions');

    res.status(200).json({
      transactions,
      total: totalCount,
      page,
      totalPages
    });
  } catch (error) {
    console.error('Get history error:', error);
    console.error('Error stack:', error.stack);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: error.message || 'An error occurred while fetching history',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/statistics
 * Get system-wide transaction statistics (admin only)
 */
router.get('/statistics', authenticate, requireAdmin, async (req, res) => {
  try {
    // Get all statistics in parallel
    const [
      pendingDepositsCount,
      pendingWithdrawalsCount,
      totalApprovedDeposits,
      totalCompletedWithdrawals
    ] = await Promise.all([
      depositService.getPendingCount(),
      withdrawalService.getPendingCount(),
      depositService.getTotalApprovedAmount(),
      withdrawalService.getTotalCompletedAmount()
    ]);

    res.status(200).json({
      pendingDepositsCount,
      pendingWithdrawalsCount,
      totalApprovedDeposits,
      totalCompletedWithdrawals
    });
  } catch (error) {
    console.error('Get statistics error:', error);

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching statistics',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/users
 * Get all users with pagination and search (admin only)
 */
router.get('/users', authenticate, requireAdmin, async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 20;
    const search = req.query.search || '';

    if (page < 1 || limit < 1 || limit > 100) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Invalid pagination parameters',
          timestamp: new Date().toISOString()
        }
      });
    }

    const offset = (page - 1) * limit;
    const pool = (await import('../database/db.js')).default;

    // Build search condition (exclude admins)
    let searchCondition = 'WHERE u.is_admin = FALSE';
    let queryParams = [limit, offset];
    
    if (search) {
      searchCondition = 'WHERE u.is_admin = FALSE AND (u.email ILIKE $3 OR u.name ILIKE $3)';
      queryParams.push(`%${search}%`);
    }

    // Get users with their wallet balance (excluding admins)
    const usersResult = await pool.query(`
      SELECT 
        u.id::text,
        u.email,
        u.name,
        u.mobile_number as "mobileNumber",
        u.aadhar_number as "aadharNumber",
        u.date_of_birth as "dateOfBirth",
        u.pan_number as "panNumber",
        u.is_active as "isActive",
        u.created_at as "createdAt",
        COALESCE(u.wallet_balance, 0) as "walletBalance"
      FROM users u
      ${searchCondition}
      ORDER BY u.created_at DESC
      LIMIT $1 OFFSET $2
    `, queryParams);

    // Get total count (excluding admins)
    const countParams = search ? [`%${search}%`] : [];
    const countResult = await pool.query(`
      SELECT COUNT(*) as total
      FROM users u
      ${searchCondition}
    `, countParams);

    // Get active/inactive counts (excluding admins)
    const statsResult = await pool.query(`
      SELECT 
        COUNT(*) FILTER (WHERE is_active = true) as active_count,
        COUNT(*) FILTER (WHERE is_active = false) as inactive_count
      FROM users
      WHERE is_admin = FALSE
    `);

    const totalCount = parseInt(countResult.rows[0].total);
    const totalPages = Math.ceil(totalCount / limit);

    res.status(200).json({
      users: usersResult.rows,
      total: totalCount,
      page,
      totalPages,
      activeCount: parseInt(statsResult.rows[0].active_count),
      inactiveCount: parseInt(statsResult.rows[0].inactive_count)
    });
  } catch (error) {
    console.error('Get users error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching users',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/users/:id
 * Get single user details (admin only)
 */
router.get('/users/:id', authenticate, requireAdmin, async (req, res) => {
  try {
    const userId = req.params.id;
    const pool = (await import('../database/db.js')).default;

    const result = await pool.query(`
      SELECT 
        u.id::text,
        u.email,
        u.name,
        u.mobile_number as "mobileNumber",
        u.aadhar_number as "aadharNumber",
        u.date_of_birth as "dateOfBirth",
        u.pan_number as "panNumber",
        u.is_active as "isActive",
        u.created_at as "createdAt",
        COALESCE(u.wallet_balance, 0) as "walletBalance"
      FROM users u
      WHERE u.id = $1
    `, [userId]);

    if (result.rows.length === 0) {
      return res.status(404).json({
        error: {
          code: 'NOT_FOUND',
          message: 'User not found',
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(200).json(result.rows[0]);
  } catch (error) {
    console.error('Get user error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching user',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * GET /api/admin/users/:id/transactions
 * Get user's transaction history (admin only)
 */
router.get('/users/:id/transactions', authenticate, requireAdmin, async (req, res) => {
  try {
    const userId = req.params.id;
    const page = parseInt(req.query.page) || 1;
    const limit = parseInt(req.query.limit) || 50;

    if (page < 1 || limit < 1 || limit > 100) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Invalid pagination parameters',
          timestamp: new Date().toISOString()
        }
      });
    }

    const offset = (page - 1) * limit;
    const pool = (await import('../database/db.js')).default;

    // Get deposits
    const depositsResult = await pool.query(`
      SELECT 
        d.id::text,
        d.amount,
        d.transaction_id as "transactionId",
        d.payment_proof_url as "paymentProofUrl",
        d.status,
        d.created_at as "createdAt",
        d.processed_at as "processedAt"
      FROM deposit_requests d
      WHERE d.user_id = $1
      ORDER BY d.created_at DESC
    `, [userId]);

    // Get withdrawals
    const withdrawalsResult = await pool.query(`
      SELECT 
        w.id::text,
        w.amount,
        w.bank_details as "bankDetails",
        w.status,
        w.created_at as "createdAt",
        w.processed_at as "processedAt"
      FROM withdrawal_requests w
      WHERE w.user_id = $1
      ORDER BY w.created_at DESC
    `, [userId]);

    // Get bonus claims
    const bonusClaimsResult = await pool.query(`
      SELECT 
        bc.id::text,
        bc.amount,
        bc.status,
        bc.created_at as "createdAt",
        bc.processed_at as "processedAt",
        u.name as "referredUserName",
        u.email as "referredUserEmail"
      FROM bonus_claim_requests bc
      LEFT JOIN referral_bonuses rb ON bc.bonus_id = rb.id
      LEFT JOIN users u ON rb.referred_user_id = u.id
      WHERE bc.user_id = $1
      ORDER BY bc.created_at DESC
    `, [userId]);

    // Combine and sort
    const deposits = depositsResult.rows.map(d => ({ ...d, type: 'deposit' }));
    const withdrawals = withdrawalsResult.rows.map(w => ({ ...w, type: 'withdrawal' }));
    const bonusClaims = bonusClaimsResult.rows.map(b => ({ 
      ...b, 
      type: 'bonus',
      referredUserName: b.referredUserName || b.referredUserEmail?.split('@')[0] || 'Referral'
    }));
    
    const allTransactions = [...deposits, ...withdrawals, ...bonusClaims]
      .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));

    const totalCount = allTransactions.length;
    const paginatedTransactions = allTransactions.slice(offset, offset + limit);
    const totalPages = Math.ceil(totalCount / limit);

    res.status(200).json({
      transactions: paginatedTransactions,
      total: totalCount,
      page,
      totalPages
    });
  } catch (error) {
    console.error('Get user transactions error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching user transactions',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * PATCH /api/admin/users/:id/status
 * Toggle user active status (admin only)
 */
router.patch('/users/:id/status', authenticate, requireAdmin, async (req, res) => {
  try {
    const userId = req.params.id;
    const { isActive } = req.body;

    if (typeof isActive !== 'boolean') {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'isActive must be a boolean',
          timestamp: new Date().toISOString()
        }
      });
    }

    const pool = (await import('../database/db.js')).default;

    // Check if user exists
    const checkResult = await pool.query(
      'SELECT id FROM users WHERE id = $1',
      [userId]
    );

    if (checkResult.rows.length === 0) {
      return res.status(404).json({
        error: {
          code: 'NOT_FOUND',
          message: 'User not found',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Update user status
    await pool.query(
      'UPDATE users SET is_active = $1 WHERE id = $2',
      [isActive, userId]
    );

    res.status(200).json({
      success: true,
      isActive
    });
  } catch (error) {
    console.error('Toggle user status error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while updating user status',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * PUT /api/admin/profile
 * Update admin profile (email, name)
 */
router.put('/profile', authenticate, requireAdmin, async (req, res) => {
  try {
    const { email, name, mobileNumber } = req.body;
    const adminId = req.user.userId;

    // Validation
    if (!email || !email.trim()) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Email is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Invalid email format',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Mobile number validation if provided
    if (mobileNumber && mobileNumber.trim()) {
      const mobileRegex = /^[0-9]{10}$/;
      if (!mobileRegex.test(mobileNumber.trim())) {
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Invalid mobile number format. Must be 10 digits',
            timestamp: new Date().toISOString()
          }
        });
      }
    }

    // Update admin profile
    const updatedAdmin = await adminService.updateAdminProfile(adminId, { 
      email: email.trim(), 
      name: name?.trim(),
      mobileNumber: mobileNumber?.trim() || null
    });

    res.status(200).json({
      message: 'Profile updated successfully',
      admin: {
        id: updatedAdmin.id,
        email: updatedAdmin.email,
        name: updatedAdmin.name,
        mobileNumber: updatedAdmin.mobile_number,
        is_admin: updatedAdmin.is_admin
      }
    });
  } catch (error) {
    console.error('Update admin profile error:', error);
    
    if (error.message.includes('already exists')) {
      return res.status(409).json({
        error: {
          code: 'EMAIL_EXISTS',
          message: 'Email address is already in use',
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while updating profile',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * DELETE /api/admin/users/:id
 * Delete a user (admin only)
 */
router.delete('/users/:id', authenticate, requireAdmin, async (req, res) => {
  try {
    const userId = req.params.id;
    const adminId = req.user.id;

    // Validation
    if (!userId) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'User ID is required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Prevent admin from deleting themselves
    if (userId === adminId) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Cannot delete your own account',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Delete user
    const deletedUser = await adminService.deleteUser(userId);

    res.status(200).json({
      message: 'User deleted successfully',
      deletedUser: {
        id: deletedUser.id,
        email: deletedUser.email,
        name: deletedUser.name
      }
    });
  } catch (error) {
    console.error('Delete user error:', error);
    
    if (error.message.includes('not found')) {
      return res.status(404).json({
        error: {
          code: 'USER_NOT_FOUND',
          message: 'User not found',
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message.includes('Cannot delete admin')) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Cannot delete admin users',
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while deleting user',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
