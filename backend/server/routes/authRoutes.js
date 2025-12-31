import express from 'express';
import authService from '../services/authService.js';
import userRepository from '../repositories/userRepository.js';
import { authenticate } from '../middleware/authMiddleware.js';
import { loginLimiter, registerLimiter } from '../middleware/rateLimitMiddleware.js';
import { getCsrfToken } from '../middleware/csrfMiddleware.js';

const router = express.Router();

/**
 * GET /api/auth/csrf-token
 * Get CSRF token
 */
router.get('/csrf-token', getCsrfToken);

/**
 * POST /api/auth/register
 * Register a new user
 */
router.post('/register', registerLimiter, async (req, res) => {
  try {
    const { email, password, name, referralCode } = req.body;

    // Validate input
    if (!email || !password) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Email and password are required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Validate password strength (minimum 8 characters)
    if (password.length < 8) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Password must be at least 8 characters long',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Register user with optional referral code
    const result = await authService.register(email, password, name || null, referralCode || null);

    res.status(201).json({
      user: {
        id: result.user.id,
        email: result.user.email,
        name: result.user.name,
        isAdmin: result.user.isAdmin
      },
      token: result.token
    });
  } catch (error) {
    if (error.message === 'Invalid email format') {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message === 'Email already registered') {
      return res.status(422).json({
        error: {
          code: 'DUPLICATE_EMAIL',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message === 'Invalid referral code') {
      return res.status(400).json({
        error: {
          code: 'INVALID_REFERRAL_CODE',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    console.error('Registration error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred during registration',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/auth/login
 * Login a user
 */
router.post('/login', loginLimiter, async (req, res) => {
  try {
    const { email, password } = req.body;

    // Validate input
    if (!email || !password) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Email and password are required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Login user
    const result = await authService.login(email, password);

    res.status(200).json({
      user: {
        id: result.user.id,
        email: result.user.email,
        isAdmin: result.user.isAdmin
      },
      token: result.token
    });
  } catch (error) {
    if (error.message === 'Invalid credentials') {
      return res.status(401).json({
        error: {
          code: 'INVALID_CREDENTIALS',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    if (error.message.includes('deactivated')) {
      return res.status(403).json({
        error: {
          code: 'ACCOUNT_DEACTIVATED',
          message: error.message,
          timestamp: new Date().toISOString()
        }
      });
    }

    console.error('Login error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred during login',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/auth/logout
 * Logout a user (client-side token removal)
 */
router.post('/logout', (req, res) => {
  // Since we're using JWT, logout is handled client-side by removing the token
  // This endpoint exists for consistency and future enhancements (e.g., token blacklisting)
  res.status(200).json({
    success: true,
    message: 'Logged out successfully'
  });
});

/**
 * GET /api/auth/me
 * Get current user information
 */
router.get('/me', authenticate, async (req, res) => {
  try {
    // Get full user details from database
    const user = await userRepository.findById(req.user.userId);

    if (!user) {
      return res.status(404).json({
        error: {
          code: 'USER_NOT_FOUND',
          message: 'User not found',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Return user without password hash
    const { passwordHash, ...userWithoutPassword } = user;

    res.status(200).json({
      userId: userWithoutPassword.id,
      email: userWithoutPassword.email,
      name: userWithoutPassword.name,
      mobileNumber: userWithoutPassword.mobileNumber,
      aadharNumber: userWithoutPassword.aadharNumber,
      dateOfBirth: userWithoutPassword.dateOfBirth,
      panNumber: userWithoutPassword.panNumber,
      isAdmin: userWithoutPassword.isAdmin
    });
  } catch (error) {
    console.error('Get user error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while fetching user information',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * PUT /api/auth/profile
 * Update user profile
 */
router.put('/profile', authenticate, async (req, res) => {
  try {
    const { name, currentPassword, newPassword, mobileNumber, aadharNumber, dateOfBirth, panNumber } = req.body;
    const userId = req.user.userId;

    const updates = {};

    // Update name if provided
    if (name !== undefined) {
      updates.name = name.trim() || null;
    }

    // Update mobile number if provided
    if (mobileNumber !== undefined) {
      const mobile = mobileNumber ? mobileNumber.trim() : null;
      if (mobile && !/^[0-9]{10}$/.test(mobile)) {
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Mobile number must be 10 digits',
            timestamp: new Date().toISOString()
          }
        });
      }
      updates.mobileNumber = mobile;
    }

    // Update Aadhar number if provided (users only)
    if (aadharNumber !== undefined && !req.user.isAdmin) {
      const aadhar = aadharNumber ? aadharNumber.trim() : null;
      if (aadhar && !/^[0-9]{12}$/.test(aadhar)) {
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Aadhar number must be 12 digits',
            timestamp: new Date().toISOString()
          }
        });
      }
      updates.aadharNumber = aadhar;
    }

    // Update date of birth if provided (users only)
    if (dateOfBirth !== undefined && !req.user.isAdmin) {
      updates.dateOfBirth = dateOfBirth ? dateOfBirth : null;
    }

    // Update PAN number if provided (users only)
    if (panNumber !== undefined && !req.user.isAdmin) {
      const pan = panNumber ? panNumber.trim().toUpperCase() : null;
      if (pan && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan)) {
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Invalid PAN format (e.g., ABCDE1234F)',
            timestamp: new Date().toISOString()
          }
        });
      }
      updates.panNumber = pan;
    }

    // Update password if provided
    if (newPassword) {
      if (!currentPassword) {
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Current password is required to set new password',
            timestamp: new Date().toISOString()
          }
        });
      }

      // Validate new password strength
      if (newPassword.length < 8) {
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'New password must be at least 8 characters long',
            timestamp: new Date().toISOString()
          }
        });
      }

      // Verify current password
      const user = await userRepository.findById(userId);
      const passwordHasher = (await import('../services/passwordHasher.js')).default;
      const isPasswordValid = await passwordHasher.compare(currentPassword, user.passwordHash);
      
      if (!isPasswordValid) {
        return res.status(401).json({
          error: {
            code: 'INVALID_PASSWORD',
            message: 'Current password is incorrect',
            timestamp: new Date().toISOString()
          }
        });
      }

      // Hash new password
      updates.passwordHash = await passwordHasher.hash(newPassword);
    }

    if (Object.keys(updates).length === 0) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'No fields to update',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Update profile
    const updatedUser = await userRepository.updateProfile(userId, updates);

    // Determine appropriate message based on what was updated
    let message = 'Profile updated successfully';
    if (updates.passwordHash && Object.keys(updates).length === 1) {
      message = 'Password updated successfully';
    } else if (updates.passwordHash) {
      message = 'Profile and password updated successfully';
    }

    res.status(200).json({
      message: message,
      user: {
        id: updatedUser.id,
        email: updatedUser.email,
        name: updatedUser.name,
        mobileNumber: updatedUser.mobileNumber,
        aadharNumber: updatedUser.aadharNumber,
        dateOfBirth: updatedUser.dateOfBirth,
        panNumber: updatedUser.panNumber,
        isAdmin: updatedUser.isAdmin
      }
    });
  } catch (error) {
    console.error('Update profile error:', error);
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
 * POST /api/auth/verify-identity
 * Verify user identity for password reset
 */
router.post('/verify-identity', async (req, res) => {
  try {
    const { email, verificationType, verificationValue } = req.body;

    // Validate input
    if (!email || !verificationType || !verificationValue) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'All fields are required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Find user by email
    const user = await userRepository.findByEmail(email);
    
    if (!user) {
      return res.status(404).json({
        error: {
          code: 'USER_NOT_FOUND',
          message: 'No account found with this email',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Verify based on type
    let isVerified = false;
    
    switch (verificationType) {
      case 'dob':
        isVerified = user.dateOfBirth === verificationValue;
        break;
      
      case 'pan':
        isVerified = user.panNumber === verificationValue.toUpperCase();
        break;
      
      case 'aadhar':
        isVerified = user.aadharNumber === verificationValue;
        break;
      
      default:
        return res.status(400).json({
          error: {
            code: 'VALIDATION_ERROR',
            message: 'Invalid verification type',
            timestamp: new Date().toISOString()
          }
        });
    }

    if (!isVerified) {
      return res.status(401).json({
        error: {
          code: 'VERIFICATION_FAILED',
          message: 'Verification details do not match our records',
          timestamp: new Date().toISOString()
        }
      });
    }

    res.status(200).json({
      success: true,
      message: 'Identity verified successfully'
    });
  } catch (error) {
    console.error('Identity verification error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred during verification',
        timestamp: new Date().toISOString()
      }
    });
  }
});

/**
 * POST /api/auth/reset-password
 * Reset user password after identity verification
 */
router.post('/reset-password', async (req, res) => {
  try {
    const { email, newPassword } = req.body;

    // Validate input
    if (!email || !newPassword) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Email and new password are required',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Validate password strength
    if (newPassword.length < 8) {
      return res.status(400).json({
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Password must be at least 8 characters long',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Find user by email
    const user = await userRepository.findByEmail(email);
    
    if (!user) {
      return res.status(404).json({
        error: {
          code: 'USER_NOT_FOUND',
          message: 'No account found with this email',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Hash new password
    const passwordHasher = (await import('../services/passwordHasher.js')).default;
    const passwordHash = await passwordHasher.hash(newPassword);

    // Update password
    await userRepository.updateProfile(user.id, { passwordHash });

    res.status(200).json({
      success: true,
      message: 'Password reset successfully'
    });
  } catch (error) {
    console.error('Password reset error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'An error occurred while resetting password',
        timestamp: new Date().toISOString()
      }
    });
  }
});

export default router;
