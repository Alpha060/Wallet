import pool from '../server/database/db.js';

async function addKYCFields() {
  try {
    console.log('Adding KYC fields to users table...\n');

    // Add mobile number
    console.log('Adding mobile_number column...');
    await pool.query(`
      ALTER TABLE users 
      ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(15)
    `);

    // Add Aadhar number
    console.log('Adding aadhar_number column...');
    await pool.query(`
      ALTER TABLE users 
      ADD COLUMN IF NOT EXISTS aadhar_number VARCHAR(12)
    `);

    // Add Date of Birth
    console.log('Adding date_of_birth column...');
    await pool.query(`
      ALTER TABLE users 
      ADD COLUMN IF NOT EXISTS date_of_birth DATE
    `);

    // Add PAN number
    console.log('Adding pan_number column...');
    await pool.query(`
      ALTER TABLE users 
      ADD COLUMN IF NOT EXISTS pan_number VARCHAR(10)
    `);

    // Create indexes
    console.log('Creating indexes...');
    await pool.query(`
      CREATE INDEX IF NOT EXISTS idx_users_mobile_number ON users(mobile_number)
    `);
    await pool.query(`
      CREATE INDEX IF NOT EXISTS idx_users_aadhar_number ON users(aadhar_number)
    `);
    await pool.query(`
      CREATE INDEX IF NOT EXISTS idx_users_pan_number ON users(pan_number)
    `);

    console.log('\n✓ KYC fields added successfully!');
    console.log('\nAdded columns:');
    console.log('  - mobile_number (VARCHAR(15)) - for both users and admins');
    console.log('  - aadhar_number (VARCHAR(12)) - for users only');
    console.log('  - date_of_birth (DATE) - for users only');
    console.log('  - pan_number (VARCHAR(10)) - for users only');

  } catch (error) {
    console.error('Error adding KYC fields:', error);
    throw error;
  } finally {
    await pool.end();
  }
}

addKYCFields();
