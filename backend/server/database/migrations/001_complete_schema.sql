-- Complete Database Schema for Manual Wallet Manager
-- Consolidated from all migrations
-- Created: 2024-12-23

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Users table (with all fields including KYC)
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    wallet_balance INTEGER DEFAULT 0,
    mobile_number VARCHAR(15),
    aadhar_number VARCHAR(12),
    date_of_birth DATE,
    pan_number VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_users_mobile_number ON users(mobile_number);
CREATE INDEX IF NOT EXISTS idx_users_aadhar_number ON users(aadhar_number);
CREATE INDEX IF NOT EXISTS idx_users_pan_number ON users(pan_number);

-- Deposit requests table
CREATE TABLE IF NOT EXISTS deposit_requests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount INTEGER NOT NULL,
    transaction_id VARCHAR(50),
    payment_proof_url VARCHAR(500) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    processed_by UUID REFERENCES users(id),
    rejection_reason TEXT
);

CREATE INDEX IF NOT EXISTS idx_deposit_user_id ON deposit_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_deposit_status ON deposit_requests(status);
CREATE INDEX IF NOT EXISTS idx_deposit_created_at ON deposit_requests(created_at DESC);

-- Withdrawal requests table
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    amount INTEGER NOT NULL,
    bank_details JSONB NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    processed_by UUID REFERENCES users(id),
    rejection_reason TEXT
);

CREATE INDEX IF NOT EXISTS idx_withdrawal_user_id ON withdrawal_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_withdrawal_status ON withdrawal_requests(status);
CREATE INDEX IF NOT EXISTS idx_withdrawal_created_at ON withdrawal_requests(created_at DESC);

-- Admin settings table
CREATE TABLE IF NOT EXISTS admin_settings (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    qr_code_url VARCHAR(500),
    upi_id VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin settings row if not exists
INSERT INTO admin_settings (id)
SELECT uuid_generate_v4()
WHERE NOT EXISTS (SELECT 1 FROM admin_settings);

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers
DROP TRIGGER IF EXISTS update_users_updated_at ON users;
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

DROP TRIGGER IF EXISTS update_admin_settings_updated_at ON admin_settings;
CREATE TRIGGER update_admin_settings_updated_at BEFORE UPDATE ON admin_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Column comments
COMMENT ON COLUMN users.mobile_number IS 'User mobile/phone number';
COMMENT ON COLUMN users.aadhar_number IS 'Aadhar card number (12 digits, users only)';
COMMENT ON COLUMN users.date_of_birth IS 'User date of birth (users only)';
COMMENT ON COLUMN users.pan_number IS 'PAN card number (10 characters, users only)';
