import pg from 'pg';
import dotenv from 'dotenv';

dotenv.config();

const { Pool } = pg;

// Create a connection pool with Railway-optimized settings
const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  max: parseInt(process.env.DATABASE_POOL_SIZE) || 10,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 15000, // Increased for Railway
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});

// Test the connection
pool.on('connect', () => {
  console.log('Database connected successfully');
});

pool.on('error', (err) => {
  console.error('Unexpected database error:', err);
  // Don't exit in production, let Railway handle restarts
  if (process.env.NODE_ENV !== 'production') {
    process.exit(-1);
  }
});

// Wait for database function
export async function waitForDatabase(maxRetries = 15, delay = 3000) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const client = await pool.connect();
      await client.query('SELECT 1');
      client.release();
      console.log('Database connection established');
      return true;
    } catch (error) {
      console.log(`Database connection attempt ${i + 1}/${maxRetries} failed:`, error.message);
      if (i === maxRetries - 1) {
        throw new Error(`Failed to connect to database after ${maxRetries} attempts`);
      }
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
}

export default pool;
