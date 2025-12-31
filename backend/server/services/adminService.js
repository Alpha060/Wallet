import adminSettingsRepository from '../repositories/adminSettingsRepository.js';
import imageValidator from './imageValidator.js';
import { getFileUrl, deleteFile } from '../middleware/uploadMiddleware.js';
import { optimizeQRCode } from './imageOptimizer.js';
import path from 'path';

/**
 * AdminService handles admin-specific operations
 */
class AdminService {
  /**
   * Upload and store QR code image
   * @param {Object} file - Multer file object
   * @returns {Promise<Object>} Updated admin settings with new QR code URL
   * @throws {Error} If file validation fails
   */
  async uploadQRCode(file) {
    // Validate the uploaded file
    imageValidator.validateOrThrow(file);

    // Get current settings to delete old QR code if exists
    const currentSettings = await adminSettingsRepository.get();
    
    // Optimize QR code image
    let optimizedFilename = file.filename;
    try {
      const optimizationResult = await optimizeQRCode(file.filename);
      optimizedFilename = optimizationResult.optimizedFilename;
      console.log(`QR code optimized: ${optimizationResult.savings} savings`);
    } catch (error) {
      console.error('QR code optimization failed, using original:', error);
      // Continue with original file if optimization fails
    }
    
    // Generate public URL for the new file
    const qrCodeUrl = getFileUrl(optimizedFilename);

    // Update database with new QR code URL
    const updatedSettings = await adminSettingsRepository.updateQRCode(qrCodeUrl);

    // Delete old QR code file if it exists
    if (currentSettings && currentSettings.qrCodeUrl) {
      try {
        const oldFilename = path.basename(currentSettings.qrCodeUrl);
        deleteFile(oldFilename);
      } catch (error) {
        // Log error but don't fail the operation
        console.error('Failed to delete old QR code:', error);
      }
    }

    return updatedSettings;
  }

  /**
   * Update UPI ID
   * @param {string} upiId - New UPI ID
   * @returns {Promise<Object>} Updated admin settings
   * @throws {Error} If UPI ID is invalid
   */
  async updateUpiId(upiId) {
    // Validate UPI ID format (basic validation)
    if (!upiId || typeof upiId !== 'string' || upiId.trim().length === 0) {
      throw new Error('UPI ID is required');
    }

    // Basic UPI ID format validation (user@bank)
    const upiRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9]+$/;
    if (!upiRegex.test(upiId.trim())) {
      throw new Error('Invalid UPI ID format. Expected format: user@bank');
    }

    // Update database
    const updatedSettings = await adminSettingsRepository.updateUpiId(upiId.trim());

    return updatedSettings;
  }

  /**
   * Get current payment details (QR code and UPI ID)
   * @returns {Promise<Object>} Payment details
   */
  async getPaymentDetails() {
    const settings = await adminSettingsRepository.get();
    
    // Get admin user info
    const pool = (await import('../database/db.js')).default;
    const adminResult = await pool.query(`
      SELECT name, email FROM users WHERE is_admin = TRUE LIMIT 1
    `);
    
    const adminName = adminResult.rows.length > 0 ? adminResult.rows[0].name : null;

    if (!settings) {
      return {
        qrCodeUrl: null,
        upiId: null,
        adminName: adminName
      };
    }

    return {
      qrCodeUrl: settings.qrCodeUrl,
      upiId: settings.upiId,
      adminName: adminName
    };
  }

  /**
   * Update admin profile (email, name, mobileNumber)
   * @param {string} adminId - Admin user ID
   * @param {Object} profileData - Profile data to update
   * @returns {Promise<Object>} Updated admin user
   * @throws {Error} If update fails or email already exists
   */
  async updateAdminProfile(adminId, profileData) {
    const { email, name, mobileNumber } = profileData;
    
    // Check if email already exists (for different user)
    if (email) {
      const existingUser = await adminSettingsRepository.findUserByEmail(email);
      if (existingUser && existingUser.id.toString() !== adminId.toString()) {
        throw new Error('Email already exists');
      }
    }

    // Update admin profile
    const updatedAdmin = await adminSettingsRepository.updateAdminProfile(adminId, {
      email,
      name,
      mobileNumber
    });

    if (!updatedAdmin) {
      throw new Error('Admin not found');
    }

    return updatedAdmin;
  }

  /**
   * Delete a user
   * @param {string} userId - User ID to delete
   * @returns {Promise<Object>} Deleted user data
   * @throws {Error} If user not found or is admin
   */
  async deleteUser(userId) {
    // Get user details first
    const user = await adminSettingsRepository.findUserById(userId);
    
    if (!user) {
      throw new Error('User not found');
    }

    // Prevent deletion of admin users
    if (user.is_admin) {
      throw new Error('Cannot delete admin users');
    }

    // Delete user and related data
    const deletedUser = await adminSettingsRepository.deleteUser(userId);
    
    return deletedUser;
  }
}

export default new AdminService();
