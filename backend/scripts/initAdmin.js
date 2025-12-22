import dotenv from 'dotenv';
import pool from '../server/database/db.js';
import readline from 'readline';

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

// Run the script
initAdmin();
