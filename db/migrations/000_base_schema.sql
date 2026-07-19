-- =====================================================================
-- AeroPay Wallet Management System - Base Schema (MySQL Version)
-- =====================================================================
-- This is the canonical, complete database schema for a fresh deployment on MySQL.
-- =====================================================================

-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                      VARCHAR(36) PRIMARY KEY,
    email                   VARCHAR(255) NOT NULL UNIQUE,
    password_hash           VARCHAR(255) NOT NULL,
    name                    VARCHAR(255),
    is_admin                BOOLEAN DEFAULT FALSE,
    is_active               BOOLEAN DEFAULT TRUE,
    wallet_balance          INT DEFAULT 0,
    mobile_number           VARCHAR(15),
    aadhar_number           VARCHAR(12),
    date_of_birth           DATE,
    pan_number              VARCHAR(10),
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    referral_code           VARCHAR(6) UNIQUE,
    referred_by             VARCHAR(36),
    saved_upi_id            VARCHAR(100),
    saved_bank_details      JSON,
    preferred_payment_method VARCHAR(10) DEFAULT 'upi',
    deleted_at              DATETIME NULL,
    CONSTRAINT users_preferred_payment_method_check
        CHECK (preferred_payment_method IN ('upi', 'bank'))
);

CREATE INDEX idx_users_referred_by ON users (referred_by);

-- ---------------------------------------------------------------------
-- ADMIN SETTINGS (single-row configuration table)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_settings (
    id                VARCHAR(36) PRIMARY KEY,
    qr_code_url       VARCHAR(500),
    upi_id            VARCHAR(100),
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    required_referrals INT DEFAULT 5
);

-- ---------------------------------------------------------------------
-- PAYMENT METHODS (deposit destination options shown to users)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_methods (
    id          VARCHAR(36) PRIMARY KEY,
    label       VARCHAR(50) NOT NULL,
    qr_code_url VARCHAR(500),
    upi_id      VARCHAR(100),
    is_active   BOOLEAN DEFAULT TRUE,
    sort_order  INT DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- PRODUCTS (yield assets users can buy)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id                    VARCHAR(36) PRIMARY KEY,
    name                  VARCHAR(255) NOT NULL,
    image_url             VARCHAR(500) NOT NULL,
    price                 INT NOT NULL,
    duration_days         INT NOT NULL,
    daily_reward_percent  DECIMAL(10, 2) NOT NULL,
    ad_watch_seconds      INT NOT NULL DEFAULT 120,
    is_active             BOOLEAN DEFAULT TRUE,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at            DATETIME NULL,
    deleted_by            VARCHAR(36),
    CONSTRAINT products_price_check CHECK (price > 0),
    CONSTRAINT products_duration_days_check CHECK (duration_days > 0),
    CONSTRAINT products_daily_reward_percent_check
        CHECK (daily_reward_percent > 0 AND daily_reward_percent <= 100),
    CONSTRAINT products_ad_watch_seconds_check CHECK (ad_watch_seconds > 0)
);

-- ---------------------------------------------------------------------
-- PRODUCT AD LINKS (per-day video ad schedule for a product)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_ad_links (
    id         VARCHAR(36) PRIMARY KEY,
    product_id VARCHAR(36) NOT NULL,
    day_number INT NOT NULL,
    video_url  TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT product_ad_links_product_id_day_number_key UNIQUE (product_id, day_number),
    CONSTRAINT product_ad_links_day_number_check CHECK (day_number > 0),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ---------------------------------------------------------------------
-- DAILY AD (global fallback ad, used when no product-specific ad exists)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_ad (
    id         VARCHAR(36) PRIMARY KEY,
    video_url  TEXT NOT NULL,
    updated_by VARCHAR(36),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- USER INVESTMENTS (a user's purchased yield asset contract)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_investments (
    id             VARCHAR(36) PRIMARY KEY,
    user_id        VARCHAR(36) NOT NULL,
    product_id     VARCHAR(36) NOT NULL,
    purchase_price INT NOT NULL,
    purchased_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at     DATETIME NOT NULL,
    is_sold        BOOLEAN DEFAULT FALSE,
    sold_at        DATETIME NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE INDEX idx_user_investments_user ON user_investments (user_id);
CREATE INDEX idx_user_investments_active ON user_investments (is_sold, expires_at);

-- ---------------------------------------------------------------------
-- AD WATCH LOG (one row per claimed daily ad reward)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ad_watch_log (
    id                  VARCHAR(36) PRIMARY KEY,
    user_investment_id  VARCHAR(36) NOT NULL,
    user_id             VARCHAR(36) NOT NULL,
    reward_amount       INT NOT NULL,
    watched_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_investment_id) REFERENCES user_investments(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX idx_ad_watch_log_investment_day ON ad_watch_log (user_investment_id, watched_at);

-- ---------------------------------------------------------------------
-- DEPOSIT REQUESTS (user money-in, pending admin approval)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deposit_requests (
    id                 VARCHAR(36) PRIMARY KEY,
    user_id            VARCHAR(36) NOT NULL,
    amount             INT NOT NULL,
    transaction_id     VARCHAR(50),
    payment_proof_url  VARCHAR(500) NOT NULL,
    status             VARCHAR(20) DEFAULT 'pending',
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at       DATETIME NULL,
    processed_by       VARCHAR(36),
    rejection_reason   TEXT,
    payment_method_id  VARCHAR(36),
    CONSTRAINT deposit_requests_status_check
        CHECK (status IN ('pending', 'approved', 'rejected')),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

CREATE INDEX idx_deposit_requests_user_status ON deposit_requests (user_id, status);

-- ---------------------------------------------------------------------
-- WITHDRAWAL REQUESTS (user money-out, balance held until processed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id               VARCHAR(36) PRIMARY KEY,
    user_id          VARCHAR(36) NOT NULL,
    amount           INT NOT NULL,
    bank_details     JSON NOT NULL,
    status           VARCHAR(20) DEFAULT 'pending',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at     DATETIME NULL,
    processed_by     VARCHAR(36),
    rejection_reason TEXT,
    payment_method   VARCHAR(20),
    verified_details BOOLEAN DEFAULT FALSE,
    CONSTRAINT withdrawal_requests_status_check
        CHECK (status IN ('pending', 'completed', 'rejected')),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

CREATE INDEX idx_withdrawal_requests_user_status ON withdrawal_requests (user_id, status);

-- ---------------------------------------------------------------------
-- REFERRAL BONUSES (5% of referred user's approved deposit, claimable)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS referral_bonuses (
    id               VARCHAR(36) PRIMARY KEY,
    referrer_id      VARCHAR(36) NOT NULL,
    referred_user_id VARCHAR(36) NOT NULL,
    deposit_id       VARCHAR(36) NOT NULL,
    deposit_amount   INT NOT NULL,
    bonus_amount     INT NOT NULL,
    is_claimed       BOOLEAN DEFAULT FALSE,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id),
    FOREIGN KEY (referred_user_id) REFERENCES users(id),
    FOREIGN KEY (deposit_id) REFERENCES deposit_requests(id)
);

CREATE INDEX idx_referral_bonuses_referrer ON referral_bonuses (referrer_id, is_claimed);

-- ---------------------------------------------------------------------
-- BONUS CLAIM REQUESTS (user requests payout of a referral bonus)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bonus_claim_requests (
    id               VARCHAR(36) PRIMARY KEY,
    user_id          VARCHAR(36) NOT NULL,
    bonus_id         VARCHAR(36) NOT NULL,
    amount           INT NOT NULL,
    status           VARCHAR(20) DEFAULT 'pending',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at     DATETIME NULL,
    processed_by     VARCHAR(36),
    rejection_reason TEXT,
    CONSTRAINT bonus_claim_requests_status_check
        CHECK (status IN ('pending', 'approved', 'rejected')),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (bonus_id) REFERENCES referral_bonuses(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- WALLET LEDGER (immutable double-entry-style balance history)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wallet_ledger (
    id              VARCHAR(36) PRIMARY KEY,
    user_id         VARCHAR(36) NOT NULL,
    amount          INT NOT NULL,
    direction       VARCHAR(10) NOT NULL,
    entry_type      VARCHAR(40) NOT NULL,
    reference_table VARCHAR(80),
    reference_id    VARCHAR(36),
    balance_after   INT,
    note            TEXT,
    created_by      VARCHAR(36),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT wallet_ledger_direction_check CHECK (direction IN ('credit', 'debit'))
);

CREATE INDEX idx_wallet_ledger_user_created ON wallet_ledger (user_id, created_at DESC);
CREATE INDEX idx_wallet_ledger_reference ON wallet_ledger (reference_table, reference_id);

-- ---------------------------------------------------------------------
-- ADMIN AUDIT LOGS (immutable record of admin actions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id           VARCHAR(36) PRIMARY KEY,
    admin_id     VARCHAR(36),
    action       VARCHAR(80) NOT NULL,
    target_table VARCHAR(80),
    target_id    VARCHAR(36),
    details      JSON,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_admin_audit_logs_admin_created ON admin_audit_logs (admin_id, created_at DESC);
CREATE INDEX idx_admin_audit_logs_target ON admin_audit_logs (target_table, target_id);

-- ---------------------------------------------------------------------
-- SEED: ensure a single admin_settings row exists (required by app)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO admin_settings (id, qr_code_url, upi_id, required_referrals)
VALUES ('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', NULL, NULL, 5);
