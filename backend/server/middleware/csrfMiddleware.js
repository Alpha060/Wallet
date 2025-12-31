// CSRF protection middleware for Manual Wallet Manager
import crypto from 'crypto';

// Store for CSRF tokens (in production, use Redis or database)
const csrfTokens = new Map();

// Token expiration time (15 minutes)
const TOKEN_EXPIRY = 15 * 60 * 1000;

/**
 * Generate a CSRF token
 * @returns {string} - CSRF token
 */
export function generateCsrfToken() {
  return crypto.randomBytes(32).toString('hex');
}

/**
 * Store CSRF token with expiration
 * @param {string} token - CSRF token
 * @param {string} userId - User ID
 */
function storeToken(token, userId) {
  const expiry = Date.now() + TOKEN_EXPIRY;
  csrfTokens.set(token, { userId, expiry });
  
  // Clean up expired tokens periodically
  cleanupExpiredTokens();
}

/**
 * Validate CSRF token
 * @param {string} token - CSRF token to validate
 * @param {string} userId - User ID
 * @returns {boolean} - True if valid
 */
function validateToken(token, userId) {
  const tokenData = csrfTokens.get(token);
  
  if (!tokenData) {
    return false;
  }
  
  // Check if token is expired
  if (Date.now() > tokenData.expiry) {
    csrfTokens.delete(token);
    return false;
  }
  
  // Check if token belongs to the user
  if (tokenData.userId !== userId) {
    return false;
  }
  
  return true;
}

/**
 * Clean up expired tokens
 */
function cleanupExpiredTokens() {
  const now = Date.now();
  for (const [token, data] of csrfTokens.entries()) {
    if (now > data.expiry) {
      csrfTokens.delete(token);
    }
  }
}

/**
 * Middleware to generate and send CSRF token
 */
export function getCsrfToken(req, res) {
  try {
    // Generate new token
    const token = generateCsrfToken();
    
    // Store token with user ID (if authenticated)
    const userId = req.user?.userId || req.ip;
    storeToken(token, userId);
    
    res.status(200).json({
      csrfToken: token
    });
  } catch (error) {
    console.error('CSRF token generation error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'Failed to generate CSRF token',
        timestamp: new Date().toISOString()
      }
    });
  }
}

/**
 * Middleware to validate CSRF token
 */
export function validateCsrfToken(req, res, next) {
  try {
    // Skip CSRF validation for GET, HEAD, OPTIONS requests
    if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
      return next();
    }
    
    // Get token from header
    const token = req.headers['x-csrf-token'];
    
    if (!token) {
      return res.status(403).json({
        error: {
          code: 'CSRF_TOKEN_MISSING',
          message: 'CSRF token is required',
          timestamp: new Date().toISOString()
        }
      });
    }
    
    // Get user ID
    const userId = req.user?.userId || req.ip;
    
    // Validate token
    if (!validateToken(token, userId)) {
      return res.status(403).json({
        error: {
          code: 'CSRF_TOKEN_INVALID',
          message: 'Invalid or expired CSRF token',
          timestamp: new Date().toISOString()
        }
      });
    }
    
    // Token is valid, proceed
    next();
  } catch (error) {
    console.error('CSRF validation error:', error);
    res.status(500).json({
      error: {
        code: 'INTERNAL_ERROR',
        message: 'CSRF validation failed',
        timestamp: new Date().toISOString()
      }
    });
  }
}

/**
 * Optional: Middleware to validate CSRF token (can be disabled in development)
 */
export function csrfProtection(req, res, next) {
  // Skip CSRF in development if configured
  if (process.env.DISABLE_CSRF === 'true') {
    return next();
  }
  
  return validateCsrfToken(req, res, next);
}
