-- Migration: Add saved payment details to users table
-- Created: 2024-01-06

-- Add UPI ID column
ALTER TABLE users ADD COLUMN IF NOT EXISTS saved_upi_id VARCHAR(100);

-- Add bank details as JSONB (account_name, account_number, ifsc_code)
ALTER TABLE users ADD COLUMN IF NOT EXISTS saved_bank_details JSONB;

-- Add preferred payment method
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferred_payment_method VARCHAR(10) DEFAULT 'upi' CHECK (preferred_payment_method IN ('upi', 'bank'));

-- Add indexes for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_saved_upi_id ON users(saved_upi_id);

-- Column comments
COMMENT ON COLUMN users.saved_upi_id IS 'User saved UPI ID for withdrawals';
COMMENT ON COLUMN users.saved_bank_details IS 'User saved bank account details (JSON: accountName, accountNumber, ifscCode)';
COMMENT ON COLUMN users.preferred_payment_method IS 'User preferred withdrawal method: upi or bank';
