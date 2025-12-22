import pool from '../server/database/db.js';
import bcrypt from 'bcrypt';
import dotenv from 'dotenv';

dotenv.config();

async function clearAndReinitialize() {
  try {
    console.log('Starting database cleanup and reinitialization...\n');

    // Delete all data from tables (in correct order due to foreign keys)
    console.log('Deleting all withdrawal requests...');
    await pool.query('DELETE FROM withdrawal_requests');
    
    console.log('Deleting all deposit requests...');
    await pool.query('DELETE FROM deposit_requests');
    
    console.log('Deleting all users...');
    await pool.query('DELETE FROM users');
    
    console.log('Deleting all admin settings...');
    await pool.query('DELETE FROM admin_settings');
    
    console.log('\nAll data cleared successfully!\n');

    // Create single admin user
    const adminEmail = process.env.ADMIN_EMAIL || 'admin@test.com';
    const adminPassword = process.env.ADMIN_PASSWORD || 'admin123';
    
    console.log('Creating admin user...');
    console.log('Email:', adminEmail);
    console.log('Password:', adminPassword);
    
    const hashedPassword = await bcrypt.hash(adminPassword, 10);
    
    const result = await pool.query(
      `INSERT INTO users (email, password_hash, is_admin, name, is_active) 
       VALUES ($1, $2, $3, $4, $5) 
       RETURNING id, email, is_admin`,
      [adminEmail, hashedPassword, true, 'Admin', true]
    );
    
    console.log('\n✅ Admin user created successfully!');
    console.log('ID:', result.rows[0].id);
    console.log('Email:', result.rows[0].email);
    console.log('Is Admin:', result.rows[0].is_admin);
    
    console.log('\n✅ Database reinitialized successfully!');
    console.log('\nYou can now login with:');
    console.log('Email:', adminEmail);
    console.log('Password:', adminPassword);
    
  } catch (error) {
    console.error('❌ Error during cleanup and reinitialization:', error);
    throw error;
  } finally {
    await pool.end();
  }
}

clearAndReinitialize();
