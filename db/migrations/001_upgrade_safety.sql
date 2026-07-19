-- AeroPay safety upgrade migration (MySQL Version)
-- This file is intentionally idempotent.

CREATE TABLE IF NOT EXISTS wallet_ledger (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    amount INT NOT NULL,
    direction VARCHAR(10) NOT NULL,
    entry_type VARCHAR(40) NOT NULL,
    reference_table VARCHAR(80),
    reference_id VARCHAR(36),
    balance_after INT,
    note TEXT,
    created_by VARCHAR(36),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT wallet_ledger_direction_check CHECK (direction IN ('credit', 'debit'))
);

CREATE INDEX idx_wallet_ledger_user_created ON wallet_ledger (user_id, created_at DESC);
CREATE INDEX idx_wallet_ledger_reference ON wallet_ledger (reference_table, reference_id);

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id VARCHAR(36) PRIMARY KEY,
    admin_id VARCHAR(36),
    action VARCHAR(80) NOT NULL,
    target_table VARCHAR(80),
    target_id VARCHAR(36),
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admin_audit_logs_admin_created ON admin_audit_logs (admin_id, created_at DESC);
CREATE INDEX idx_admin_audit_logs_target ON admin_audit_logs (target_table, target_id);

ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE products ADD COLUMN deleted_by VARCHAR(36) NULL;
ALTER TABLE deposit_requests ADD COLUMN payment_method_id VARCHAR(36) NULL;
ALTER TABLE withdrawal_requests ADD COLUMN payment_method VARCHAR(20) NULL;
ALTER TABLE withdrawal_requests ADD COLUMN verified_details BOOLEAN DEFAULT FALSE;
