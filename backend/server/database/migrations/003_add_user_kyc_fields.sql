-- Migration: Add KYC fields to users table
-- Adds mobile number, aadhar, DOB, and PAN for user verification

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

-- Add comments for documentation
COMMENT ON COLUMN users.mobile_number IS 'User mobile/phone number';
COMMENT ON COLUMN users.aadhar_number IS 'Aadhar card number (12 digits, users only)';
COMMENT ON COLUMN users.date_of_birth IS 'User date of birth (users only)';
COMMENT ON COLUMN users.pan_number IS 'PAN card number (10 characters, users only)';
