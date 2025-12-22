-- Run this SQL directly in your PostgreSQL client (pgAdmin, DBeaver, or psql)
-- This adds KYC fields to the users table

-- Add mobile number (for both users and admins)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(15);

-- Add Aadhar number (users only, 12 digits)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS aadhar_number VARCHAR(12);

-- Add Date of Birth (users only)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS date_of_birth DATE;

-- Add PAN number (users only, 10 characters)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS pan_number VARCHAR(10);

-- Create indexes for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_mobile_number ON users(mobile_number);
CREATE INDEX IF NOT EXISTS idx_users_aadhar_number ON users(aadhar_number);
CREATE INDEX IF NOT EXISTS idx_users_pan_number ON users(pan_number);

-- Verify the columns were added
SELECT column_name, data_type, character_maximum_length 
FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name IN ('mobile_number', 'aadhar_number', 'date_of_birth', 'pan_number')
ORDER BY column_name;
