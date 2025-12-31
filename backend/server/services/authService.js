import jwt from 'jsonwebtoken';
import dotenv from 'dotenv';
import userRepository from '../repositories/userRepository.js';
import passwordHasher from './passwordHasher.js';

dotenv.config();

/**
 * AuthService handles user authentication operations
 */
class AuthService {
  constructor() {
    this.jwtSecret = process.env.JWT_SECRET || 'default-secret-key';
    this.jwtExpiry = process.env.JWT_EXPIRY || '24h';
  }

  /**
   * Validate email format
   * @param {string} email - Email to validate
   * @returns {boolean} True if valid, false otherwise
   */
  validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  /**
   * Register a new user
   * @param {string} email - User email
   * @param {string} password - Plain text password
   * @param {string} name - User name (optional)
   * @param {string} referralCode - Referral code (optional)
   * @returns {Promise<Object>} Object containing user and token
   * @throws {Error} If email is invalid or already exists
   */
  async register(email, password, name = null, referralCode = null) {
    // Validate email format
    if (!this.validateEmail(email)) {
      throw new Error('Invalid email format');
    }

    // Check if user already exists
    const existingUser = await userRepository.findByEmail(email);
    if (existingUser) {
      throw new Error('Email already registered');
    }

    // Verify referral code if provided
    if (referralCode) {
      const referralService = (await import('./referralService.js')).default;
      const referrer = await referralService.verifyReferralCode(referralCode);
      if (!referrer) {
        throw new Error('Invalid referral code');
      }
    }

    // Hash password
    const passwordHash = await passwordHasher.hash(password);

    // Create user
    const user = await userRepository.create(email, passwordHash, false, name);

    // Process referral if code was provided
    if (referralCode) {
      const referralService = (await import('./referralService.js')).default;
      await referralService.processReferral(user.id, referralCode);
    }

    // Generate JWT token
    const token = this.generateToken(user.id, user.email, user.isAdmin);

    // Return user without password hash
    const { passwordHash: _, ...userWithoutPassword } = user;
    
    return {
      user: userWithoutPassword,
      token
    };
  }

  /**
   * Login a user
   * @param {string} email - User email
   * @param {string} password - Plain text password
   * @returns {Promise<Object>} Object containing user and token
   * @throws {Error} If credentials are invalid or account is inactive
   */
  async login(email, password) {
    // Find user by email
    const user = await userRepository.findByEmail(email);
    if (!user) {
      throw new Error('Invalid credentials');
    }

    // Check if account is active
    if (user.isActive === false) {
      throw new Error('Account has been deactivated. Please contact admin to reactivate your account.');
    }

    // Verify password
    const isPasswordValid = await passwordHasher.compare(password, user.passwordHash);
    if (!isPasswordValid) {
      throw new Error('Invalid credentials');
    }

    // Generate JWT token
    const token = this.generateToken(user.id, user.email, user.isAdmin);

    // Return user without password hash
    const { passwordHash: _, ...userWithoutPassword } = user;
    
    return {
      user: userWithoutPassword,
      token
    };
  }

  /**
   * Generate JWT token
   * @param {string} userId - User ID
   * @param {string} email - User email
   * @param {boolean} isAdmin - Admin flag
   * @returns {string} JWT token
   */
  generateToken(userId, email, isAdmin) {
    const payload = {
      userId,
      email,
      isAdmin
    };

    return jwt.sign(payload, this.jwtSecret, { expiresIn: this.jwtExpiry });
  }

  /**
   * Verify JWT token
   * @param {string} token - JWT token
   * @returns {Object} Decoded token payload
   * @throws {Error} If token is invalid or expired
   */
  verifyToken(token) {
    try {
      return jwt.verify(token, this.jwtSecret);
    } catch (error) {
      if (error.name === 'TokenExpiredError') {
        throw new Error('Token expired');
      }
      throw new Error('Invalid token');
    }
  }
}

export default new AuthService();
