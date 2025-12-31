import bcrypt from 'bcrypt';
import dotenv from 'dotenv';

dotenv.config();

/**
 * PasswordHasher handles password hashing and comparison using bcrypt
 */
class PasswordHasher {
  constructor() {
    this.saltRounds = parseInt(process.env.BCRYPT_ROUNDS) || 12;
  }

  /**
   * Hash a plain text password
   * @param {string} password - Plain text password
   * @returns {Promise<string>} Hashed password
   */
  async hash(password) {
    return await bcrypt.hash(password, this.saltRounds);
  }

  /**
   * Compare a plain text password with a hashed password
   * @param {string} password - Plain text password
   * @param {string} hashedPassword - Hashed password
   * @returns {Promise<boolean>} True if passwords match, false otherwise
   */
  async compare(password, hashedPassword) {
    return await bcrypt.compare(password, hashedPassword);
  }
}

export default new PasswordHasher();
