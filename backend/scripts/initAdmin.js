import dotenv from 'dotenv';
import pool from '../server/database/db.js';
import readline from 'readline';
import bcrypt from 'bcrypt';

dotenv.config();

/**
 * Admin initialization script
 * This script designates a user as admin by setting the isAdmin flag
 */

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

function question(query) {
  return new Promise(resolve => rl.question(query, resolve));
}

// Export function for programmatic use
export async function initializeAdmin() {
  try {
    const adminEmail = process.env.ADMIN_EMAIL || 'admin@example.com';
    const adminPassword = process.env.ADMIN_PASSWORD || 'Admin@123456';

    // Check if admin already exists
    const checkQuery = 'SELECT id, email, is_admin FROM users WHERE email = $1';
    const checkResult = await pool.query(checkQuery, [adminEmail]);

    if (checkResult.rows.length > 0) {
      const user = checkResult.rows[0];
      if (user.is_admin) {
        console.log('Admin user already exists');
        return;
      }
      // Make existing user admin
      await pool.query('UPDATE users SET is_admin = true WHERE id = $1', [user.id]);
      console.log(`User ${adminEmail} promoted to admin`);
      return;
    }

    // Create new admin user
    const hashedPassword = await bcrypt.hash(adminPassword, 10);
    const insertQuery = `
      INSERT INTO users (email, password, name, is_admin, is_active, kyc_status)
      VALUES ($1, $2, $3, true, true, 'verified')
      RETURNING id, email
    `;
    const result = await pool.query(insertQuery, [adminEmail, hashedPassword, 'Admin']);
    
    // Create wallet for admin
    await pool.query('INSERT INTO wallets (user_id, balance) VALUES ($1, 0)', [result.rows[0].id]);
    
    console.log(`Admin user created: ${adminEmail}`);
  } catch (error) {
    console.log('Admin initialization error:', error.message);
  }
}

async function initAdmin() {
  try {
    console.log('=== Admin Initialization Script ===\n');

    // Check if ADMIN_EMAIL is set in environment
    const adminEmail = process.env.ADMIN_EMAIL;
    
    let email;
    if (adminEmail) {
      console.log(`Admin email from environment: ${adminEmail}`);
      const useEnvEmail = await question('Use this email? (y/n): ');
      
      if (useEnvEmail.toLowerCase() === 'y') {
        email = adminEmail;
      } else {
        email = await question('Enter admin email: ');
      }
    } else {
      email = await question('Enter admin email: ');
    }

    if (!email || !email.trim()) {
      console.error('Error: Email is required');
      process.exit(1);
    }

    email = email.trim();

    // Check if user exists
    const userQuery = 'SELECT id, email, is_admin FROM users WHERE email = $1';
    const userResult = await pool.query(userQuery, [email]);

    if (userResult.rows.length === 0) {
      console.error(`Error: User with email "${email}" not found`);
      console.log('Please register the user first, then run this script again.');
      process.exit(1);
    }

    const user = userResult.rows[0];

    if (user.is_admin) {
      console.log(`User "${email}" is already an admin.`);
      process.exit(0);
    }

    // Confirm action
    const confirm = await question(`\nSet "${email}" as admin? (y/n): `);
    
    if (confirm.toLowerCase() !== 'y') {
      console.log('Operation cancelled.');
      process.exit(0);
    }

    // Update user to admin
    const updateQuery = 'UPDATE users SET is_admin = true WHERE id = $1 RETURNING id, email, is_admin';
    const updateResult = await pool.query(updateQuery, [user.id]);

    if (updateResult.rows.length > 0) {
      console.log(`\n✓ Success! User "${email}" is now an admin.`);
    } else {
      console.error('Error: Failed to update user');
      process.exit(1);
    }

  } catch (error) {
    console.error('Error:', error.message);
    process.exit(1);
  } finally {
    rl.close();
    await pool.end();
  }
}

// Run the script only if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
  initAdmin();
}
