import dotenv from 'dotenv';
import pool from '../server/database/db.js';
import bcrypt from 'bcrypt';

dotenv.config();

// Export function for programmatic use (doesn't close pool)
export async function initializeAdmin() {
  try {
    const email = process.env.ADMIN_EMAIL || 'admin@example.com';
    const password = process.env.ADMIN_PASSWORD || 'Admin@123456';

    // Check if admin already exists
    const checkResult = await pool.query('SELECT id, is_admin FROM users WHERE email = $1', [email]);

    if (checkResult.rows.length > 0) {
      if (checkResult.rows[0].is_admin) {
        console.log(`Admin already exists: ${email}`);
      } else {
        await pool.query('UPDATE users SET is_admin = true WHERE id = $1', [checkResult.rows[0].id]);
        console.log(`User promoted to admin: ${email}`);
      }
    } else {
      // Create new admin
      const hashedPassword = await bcrypt.hash(password, 10);
      const insertResult = await pool.query(
        'INSERT INTO users (email, password_hash, name, is_admin, is_active) VALUES ($1, $2, $3, true, true) RETURNING id',
        [email, hashedPassword, 'Admin']
      );
      
      // Create wallet for admin
      await pool.query('INSERT INTO wallets (user_id, balance) VALUES ($1, 0)', [insertResult.rows[0].id]);
      
      console.log(`Admin created: ${email}`);
    }
  } catch (error) {
    console.log('Admin initialization error:', error.message);
  }
  // DON'T close pool - it's used by the main server
}

async function initAdmin() {
  try {
    const email = process.env.ADMIN_EMAIL || 'admin@example.com';
    const password = process.env.ADMIN_PASSWORD || 'Admin@123456';

    // Check if admin already exists
    const checkResult = await pool.query('SELECT id, is_admin FROM users WHERE email = $1', [email]);

    if (checkResult.rows.length > 0) {
      if (checkResult.rows[0].is_admin) {
        console.log(`Admin already exists: ${email}`);
      } else {
        await pool.query('UPDATE users SET is_admin = true WHERE id = $1', [checkResult.rows[0].id]);
        console.log(`User promoted to admin: ${email}`);
      }
    } else {
      // Create new admin
      const hashedPassword = await bcrypt.hash(password, 10);
      const insertResult = await pool.query(
        'INSERT INTO users (email, password_hash, name, is_admin, is_active) VALUES ($1, $2, $3, true, true) RETURNING id',
        [email, hashedPassword, 'Admin']
      );
      
      // Create wallet for admin
      await pool.query('INSERT INTO wallets (user_id, balance) VALUES ($1, 0)', [insertResult.rows[0].id]);
      
      console.log(`Admin created: ${email}`);
    }
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await pool.end(); // Only close pool when running as standalone script
  }
}

// Run the script only if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
  initAdmin();
}
