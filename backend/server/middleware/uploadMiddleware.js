import multer from 'multer';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';
import crypto from 'crypto';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Ensure upload directory exists
const uploadDir = process.env.UPLOAD_DIR || './uploads';
const uploadsPath = path.join(__dirname, '../../', uploadDir);

if (!fs.existsSync(uploadsPath)) {
  fs.mkdirSync(uploadsPath, { recursive: true });
}

// Configure storage
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, uploadsPath);
  },
  filename: (req, file, cb) => {
    // Generate unique filename using crypto
    const uniqueSuffix = crypto.randomBytes(16).toString('hex');
    const ext = path.extname(file.originalname);
    const filename = `${Date.now()}-${uniqueSuffix}${ext}`;
    cb(null, filename);
  }
});

// File filter for image validation
const fileFilter = (req, file, cb) => {
  // Accept only JPEG and PNG images
  const allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png'];
  
  // Check MIME type
  if (!allowedMimeTypes.includes(file.mimetype)) {
    return cb(new Error('Invalid file type. Only JPEG and PNG images are allowed.'), false);
  }
  
  // Check file extension to prevent MIME type spoofing
  const ext = path.extname(file.originalname).toLowerCase();
  const allowedExtensions = ['.jpg', '.jpeg', '.png'];
  
  if (!allowedExtensions.includes(ext)) {
    return cb(new Error('Invalid file extension. Only .jpg, .jpeg, and .png files are allowed.'), false);
  }
  
  // Prevent path traversal attacks in filename
  const filename = path.basename(file.originalname);
  if (filename !== file.originalname || filename.includes('..')) {
    return cb(new Error('Invalid filename.'), false);
  }
  
  cb(null, true);
};

// Configure multer
const upload = multer({
  storage: storage,
  fileFilter: fileFilter,
  limits: {
    fileSize: parseInt(process.env.MAX_FILE_SIZE) || 5 * 1024 * 1024 // 5MB default
  }
});

/**
 * Middleware for single image upload
 * @param {string} fieldName - Name of the form field
 */
export const uploadSingle = (fieldName) => {
  return (req, res, next) => {
    const uploadHandler = upload.single(fieldName);
    
    uploadHandler(req, res, (err) => {
      if (err instanceof multer.MulterError) {
        if (err.code === 'LIMIT_FILE_SIZE') {
          return res.status(400).json({
            error: {
              code: 'FILE_TOO_LARGE',
              message: 'File size exceeds the maximum limit of 5MB',
              timestamp: new Date().toISOString()
            }
          });
        }
        
        return res.status(400).json({
          error: {
            code: 'UPLOAD_ERROR',
            message: err.message,
            timestamp: new Date().toISOString()
          }
        });
      } else if (err) {
        return res.status(400).json({
          error: {
            code: 'INVALID_FILE',
            message: err.message,
            timestamp: new Date().toISOString()
          }
        });
      }
      
      next();
    });
  };
};

/**
 * Verify file type by checking magic numbers (file signature)
 * @param {Buffer} buffer - File buffer
 * @returns {boolean} True if valid image
 */
export const verifyImageType = (buffer) => {
  if (!buffer || buffer.length < 4) {
    return false;
  }
  
  // Check for JPEG magic numbers (FF D8 FF)
  if (buffer[0] === 0xFF && buffer[1] === 0xD8 && buffer[2] === 0xFF) {
    return true;
  }
  
  // Check for PNG magic numbers (89 50 4E 47)
  if (buffer[0] === 0x89 && buffer[1] === 0x50 && buffer[2] === 0x4E && buffer[3] === 0x47) {
    return true;
  }
  
  return false;
};

/**
 * Middleware to verify uploaded file content
 */
export const verifyFileContent = (req, res, next) => {
  if (!req.file) {
    return next();
  }
  
  try {
    const filePath = req.file.path;
    const buffer = fs.readFileSync(filePath);
    
    if (!verifyImageType(buffer)) {
      // Delete the invalid file
      deleteFile(req.file.filename);
      
      return res.status(400).json({
        error: {
          code: 'INVALID_FILE_CONTENT',
          message: 'File content does not match expected image format',
          timestamp: new Date().toISOString()
        }
      });
    }
    
    next();
  } catch (error) {
    console.error('File verification error:', error);
    
    // Clean up file on error
    if (req.file) {
      deleteFile(req.file.filename);
    }
    
    return res.status(500).json({
      error: {
        code: 'FILE_VERIFICATION_ERROR',
        message: 'Failed to verify file',
        timestamp: new Date().toISOString()
      }
    });
  }
};

/**
 * Get the public URL for an uploaded file
 * @param {string} filename - Name of the uploaded file
 * @returns {string} Public URL
 */
export const getFileUrl = (filename) => {
  // Use relative URL since frontend and backend are served from same origin
  return `/uploads/${filename}`;
};

/**
 * Delete a file from the uploads directory
 * @param {string} filename - Name of the file to delete
 */
export const deleteFile = (filename) => {
  const filePath = path.join(uploadsPath, filename);
  
  if (fs.existsSync(filePath)) {
    fs.unlinkSync(filePath);
  }
};

export default upload;
