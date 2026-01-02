import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import authRoutes from './routes/authRoutes.js';
import adminRoutes from './routes/adminRoutes.js';
import depositRoutes from './routes/depositRoutes.js';
import walletRoutes from './routes/walletRoutes.js';
import withdrawalRoutes from './routes/withdrawalRoutes.js';
import referralRoutes from './routes/referralRoutes.js';
import referralBonusRoutes from './routes/referralBonusRoutes.js';
import { generalLimiter } from './middleware/rateLimitMiddleware.js';
import runMigrations from './database/migrate.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// Trust proxy for Railway deployment
app.set('trust proxy', 1);

// CORS configuration - Allow all origins in development
app.use(cors({
  origin: true,
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Apply general rate limiting to all API routes
app.use('/api/', generalLimiter);

// Disable caching for all API routes
app.use('/api/', (req, res, next) => {
  res.set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
  res.set('Pragma', 'no-cache');
  res.set('Expires', '0');
  res.set('Surrogate-Control', 'no-store');
  next();
});

// URL rewriting middleware - Remove .html extension
app.use((req, res, next) => {
  // Skip if it's an API route, static file, or already has an extension
  if (req.path.startsWith('/api/') || 
      req.path.startsWith('/uploads/') ||
      req.path.includes('.') ||
      req.path === '/') {
    return next();
  }
  
  // List of valid page routes
  const validPages = [
    '/login',
    '/register',
    '/forgot-password',
    '/user-dashboard',
    '/admin-dashboard'
  ];
  
  // Check if the requested path is a valid page
  if (validPages.includes(req.path)) {
    req.url = `/pages${req.path}.html`;
  }
  
  next();
});

// Serve static frontend files with proper cache headers
app.use(express.static(path.join(__dirname, '../public'), {
  setHeaders: (res, path) => {
    // Cache static assets for a short time in development, longer in production
    if (path.endsWith('.html')) {
      // HTML files - no cache (always check for updates)
      res.set('Cache-Control', 'no-cache, must-revalidate');
    } else if (path.endsWith('.js') || path.endsWith('.css')) {
      // JS/CSS files - short cache with revalidation
      res.set('Cache-Control', 'public, max-age=300, must-revalidate'); // 5 minutes
    } else {
      // Other static files (images, fonts) - longer cache
      res.set('Cache-Control', 'public, max-age=3600'); // 1 hour
    }
  }
}));

// Serve uploaded files
const uploadDir = process.env.UPLOAD_DIR || './uploads';
app.use('/uploads', express.static(path.join(__dirname, '../', uploadDir)));

// Health check endpoints
app.get('/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// API routes
app.use('/api/auth', authRoutes);
app.use('/api/admin', adminRoutes);
app.use('/api/deposits', depositRoutes);
app.use('/api/wallet', walletRoutes);
app.use('/api/withdrawals', withdrawalRoutes);
app.use('/api/referrals', referralRoutes);
app.use('/api/referral-bonus', referralBonusRoutes);

// Serve index.html for all non-API routes (SPA support) - MUST be last
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, '../public/index.html'));
});

// Run migrations and start server
async function startServer() {
  try {
    // Wait for database to be ready (Railway startup)
    if (process.env.NODE_ENV === 'production') {
      console.log('Waiting for database connection...');
      const { waitForDatabase } = await import('./database/db.js');
      await waitForDatabase();
    }
    
    // Run database migrations
    await runMigrations();
    
    // Initialize admin user on first run (production)
    if (process.env.NODE_ENV === 'production') {
      try {
        const { initializeAdmin } = await import('../scripts/initAdmin.js');
        await initializeAdmin();
        console.log('Admin initialization check completed');
      } catch (error) {
        console.log('Admin initialization skipped or already exists');
      }
    }
    
    // Start server - Listen on all network interfaces
    app.listen(PORT, '0.0.0.0', () => {
      console.log(`Server running on port ${PORT}`);
      console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
      console.log(`Local: http://localhost:${PORT}`);
      console.log(`Network: http://192.168.1.17:${PORT}`);
      console.log(`\nTo access from phone/tablet, use the Network URL`);
    });
  } catch (error) {
    console.error('Failed to start server:', error);
    process.exit(1);
  }
}

startServer();

export default app;
