// Image optimization service using Sharp
import sharp from 'sharp';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const uploadDir = process.env.UPLOAD_DIR || './uploads';
const uploadsPath = path.join(__dirname, '../../', uploadDir);

/**
 * Optimize image by compressing and resizing
 * @param {string} inputPath - Path to input image
 * @param {Object} options - Optimization options
 * @returns {Promise<Object>} - Optimization result
 */
export async function optimizeImage(inputPath, options = {}) {
  const {
    quality = 80,
    maxWidth = 1920,
    maxHeight = 1920,
    format = 'jpeg',
    createThumbnail = false,
    thumbnailWidth = 300,
    thumbnailHeight = 300
  } = options;

  try {
    const image = sharp(inputPath);
    const metadata = await image.metadata();

    // Resize if image is larger than max dimensions
    let resizeOptions = {};
    if (metadata.width > maxWidth || metadata.height > maxHeight) {
      resizeOptions = {
        width: maxWidth,
        height: maxHeight,
        fit: 'inside',
        withoutEnlargement: true
      };
    }

    // Optimize main image
    const optimizedPath = inputPath.replace(/\.(jpg|jpeg|png)$/i, `-optimized.${format}`);
    
    await image
      .resize(resizeOptions)
      .jpeg({ quality, mozjpeg: true })
      .toFile(optimizedPath);

    // Get file sizes
    const originalSize = fs.statSync(inputPath).size;
    const optimizedSize = fs.statSync(optimizedPath).size;
    const savings = ((originalSize - optimizedSize) / originalSize * 100).toFixed(2);

    const result = {
      originalPath: inputPath,
      optimizedPath: optimizedPath,
      originalSize: originalSize,
      optimizedSize: optimizedSize,
      savings: `${savings}%`,
      thumbnailPath: null
    };

    // Create thumbnail if requested
    if (createThumbnail) {
      const thumbnailPath = inputPath.replace(/\.(jpg|jpeg|png)$/i, `-thumb.${format}`);
      
      await sharp(inputPath)
        .resize(thumbnailWidth, thumbnailHeight, {
          fit: 'cover',
          position: 'center'
        })
        .jpeg({ quality: 70, mozjpeg: true })
        .toFile(thumbnailPath);

      result.thumbnailPath = thumbnailPath;
    }

    // Delete original file after optimization
    if (fs.existsSync(inputPath)) {
      fs.unlinkSync(inputPath);
    }

    return result;
  } catch (error) {
    console.error('Image optimization error:', error);
    throw new Error('Failed to optimize image');
  }
}

/**
 * Create thumbnail from image
 * @param {string} inputPath - Path to input image
 * @param {number} width - Thumbnail width
 * @param {number} height - Thumbnail height
 * @returns {Promise<string>} - Path to thumbnail
 */
export async function createThumbnail(inputPath, width = 300, height = 300) {
  try {
    const thumbnailPath = inputPath.replace(/\.(jpg|jpeg|png)$/i, '-thumb.jpg');
    
    await sharp(inputPath)
      .resize(width, height, {
        fit: 'cover',
        position: 'center'
      })
      .jpeg({ quality: 70, mozjpeg: true })
      .toFile(thumbnailPath);

    return thumbnailPath;
  } catch (error) {
    console.error('Thumbnail creation error:', error);
    throw new Error('Failed to create thumbnail');
  }
}

/**
 * Convert image to WebP format with JPEG fallback
 * @param {string} inputPath - Path to input image
 * @param {number} quality - WebP quality (0-100)
 * @returns {Promise<Object>} - Paths to WebP and JPEG versions
 */
export async function createWebPWithFallback(inputPath, quality = 80) {
  try {
    const webpPath = inputPath.replace(/\.(jpg|jpeg|png)$/i, '.webp');
    const jpegPath = inputPath.replace(/\.(jpg|jpeg|png)$/i, '.jpg');

    // Create WebP version
    await sharp(inputPath)
      .webp({ quality })
      .toFile(webpPath);

    // Create JPEG fallback
    await sharp(inputPath)
      .jpeg({ quality, mozjpeg: true })
      .toFile(jpegPath);

    return {
      webpPath,
      jpegPath
    };
  } catch (error) {
    console.error('WebP conversion error:', error);
    throw new Error('Failed to create WebP version');
  }
}

/**
 * Optimize uploaded payment proof
 * @param {string} filename - Uploaded filename
 * @returns {Promise<Object>} - Optimization result with URLs
 */
export async function optimizePaymentProof(filename) {
  const inputPath = path.join(uploadsPath, filename);
  
  const result = await optimizeImage(inputPath, {
    quality: 80,
    maxWidth: 1920,
    maxHeight: 1920,
    format: 'jpeg',
    createThumbnail: true,
    thumbnailWidth: 400,
    thumbnailHeight: 400
  });

  return {
    optimizedFilename: path.basename(result.optimizedPath),
    thumbnailFilename: result.thumbnailPath ? path.basename(result.thumbnailPath) : null,
    originalSize: result.originalSize,
    optimizedSize: result.optimizedSize,
    savings: result.savings
  };
}

/**
 * Optimize uploaded QR code
 * @param {string} filename - Uploaded filename
 * @returns {Promise<Object>} - Optimization result with URLs
 */
export async function optimizeQRCode(filename) {
  const inputPath = path.join(uploadsPath, filename);
  
  const result = await optimizeImage(inputPath, {
    quality: 90, // Higher quality for QR codes
    maxWidth: 1000,
    maxHeight: 1000,
    format: 'jpeg',
    createThumbnail: false
  });

  return {
    optimizedFilename: path.basename(result.optimizedPath),
    originalSize: result.originalSize,
    optimizedSize: result.optimizedSize,
    savings: result.savings
  };
}

/**
 * Get image metadata
 * @param {string} filename - Image filename
 * @returns {Promise<Object>} - Image metadata
 */
export async function getImageMetadata(filename) {
  try {
    const imagePath = path.join(uploadsPath, filename);
    const metadata = await sharp(imagePath).metadata();
    
    return {
      width: metadata.width,
      height: metadata.height,
      format: metadata.format,
      size: fs.statSync(imagePath).size,
      hasAlpha: metadata.hasAlpha,
      orientation: metadata.orientation
    };
  } catch (error) {
    console.error('Get metadata error:', error);
    throw new Error('Failed to get image metadata');
  }
}

export default {
  optimizeImage,
  createThumbnail,
  createWebPWithFallback,
  optimizePaymentProof,
  optimizeQRCode,
  getImageMetadata
};
