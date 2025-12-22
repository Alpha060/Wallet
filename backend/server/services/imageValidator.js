/**
 * ImageValidator utility for validating uploaded image files
 */
class ImageValidator {
  constructor() {
    this.allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    this.maxFileSize = parseInt(process.env.MAX_FILE_SIZE) || 5 * 1024 * 1024; // 5MB
  }

  /**
   * Validate file type
   * @param {string} mimetype - File MIME type
   * @returns {Object} Validation result
   */
  validateFileType(mimetype) {
    if (!mimetype) {
      return {
        valid: false,
        error: 'File type is missing'
      };
    }

    if (!this.allowedMimeTypes.includes(mimetype)) {
      return {
        valid: false,
        error: 'Invalid file type. Only JPEG and PNG images are allowed.'
      };
    }

    return { valid: true };
  }

  /**
   * Validate file size
   * @param {number} size - File size in bytes
   * @returns {Object} Validation result
   */
  validateFileSize(size) {
    if (!size || size === 0) {
      return {
        valid: false,
        error: 'File is empty'
      };
    }

    if (size > this.maxFileSize) {
      const maxSizeMB = (this.maxFileSize / (1024 * 1024)).toFixed(2);
      return {
        valid: false,
        error: `File size exceeds the maximum limit of ${maxSizeMB}MB`
      };
    }

    return { valid: true };
  }

  /**
   * Validate uploaded file
   * @param {Object} file - Multer file object
   * @returns {Object} Validation result
   */
  validate(file) {
    if (!file) {
      return {
        valid: false,
        error: 'No file provided'
      };
    }

    // Validate file type
    const typeValidation = this.validateFileType(file.mimetype);
    if (!typeValidation.valid) {
      return typeValidation;
    }

    // Validate file size
    const sizeValidation = this.validateFileSize(file.size);
    if (!sizeValidation.valid) {
      return sizeValidation;
    }

    return { valid: true };
  }

  /**
   * Validate file and throw error if invalid
   * @param {Object} file - Multer file object
   * @throws {Error} If file is invalid
   */
  validateOrThrow(file) {
    const result = this.validate(file);
    if (!result.valid) {
      throw new Error(result.error);
    }
  }
}

export default new ImageValidator();
