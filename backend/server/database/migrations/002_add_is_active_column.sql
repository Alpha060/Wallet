-- Migration: Add is_active column to users table
-- This allows admins to activate/deactivate user accounts

-- Add is_active column (default to true for existing users)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- Create index for faster filtering by active status
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);

-- Update existing users to be active
UPDATE users SET is_active = TRUE WHERE is_active IS NULL;
