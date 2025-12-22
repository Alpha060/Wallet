import authService from '../services/authService.js';

/**
 * Authentication middleware to validate JWT tokens
 * Extracts user information from token and attaches to request
 * Also checks if user account is active
 */
export const authenticate = async (req, res, next) => {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return res.status(401).json({
        error: {
          code: 'UNAUTHORIZED',
          message: 'Missing or invalid authorization header',
          timestamp: new Date().toISOString()
        }
      });
    }

    // Extract token
    const token = authHeader.substring(7); // Remove 'Bearer ' prefix

    // Verify token
    const decoded = authService.verifyToken(token);

    // Check if user account is still active (except for admins)
    if (!decoded.isAdmin) {
      const pool = (await import('../database/db.js')).default;
      const result = await pool.query(
        'SELECT is_active FROM users WHERE id = $1',
        [decoded.userId]
      );

      if (result.rows.length === 0) {
        return res.status(401).json({
          error: {
            code: 'USER_NOT_FOUND',
            message: 'User account not found',
            timestamp: new Date().toISOString()
          }
        });
      }

      if (result.rows[0].is_active === false) {
        return res.status(403).json({
          error: {
            code: 'ACCOUNT_DEACTIVATED',
            message: 'Your account has been deactivated. Please contact admin to reactivate your account.',
            timestamp: new Date().toISOString()
          }
        });
      }
    }

    // Attach user info to request
    req.user = {
      userId: decoded.userId,
      email: decoded.email,
      isAdmin: decoded.isAdmin
    };

    next();
  } catch (error) {
    if (error.message === 'Token expired') {
      return res.status(401).json({
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Token has expired',
          timestamp: new Date().toISOString()
        }
      });
    }

    return res.status(401).json({
      error: {
        code: 'INVALID_TOKEN',
        message: 'Invalid authentication token',
        timestamp: new Date().toISOString()
      }
    });
  }
};

/**
 * Admin authorization middleware
 * Must be used after authenticate middleware
 */
export const requireAdmin = (req, res, next) => {
  if (!req.user) {
    return res.status(401).json({
      error: {
        code: 'UNAUTHORIZED',
        message: 'Authentication required',
        timestamp: new Date().toISOString()
      }
    });
  }

  if (!req.user.isAdmin) {
    return res.status(403).json({
      error: {
        code: 'FORBIDDEN',
        message: 'Admin access required',
        timestamp: new Date().toISOString()
      }
    });
  }

  next();
};
