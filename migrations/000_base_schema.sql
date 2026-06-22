-- =====================================================================
-- AeroPay Wallet Management System - Base Schema (complete)
-- =====================================================================
-- This is the canonical, complete database schema for a fresh deployment.
-- It is written to be safe to run on an EMPTY database (all CREATE ... IF NOT EXISTS).
--
-- DO NOT run this against the existing production database unless you know
-- what you are doing — it is provided for fresh installs and reference.
-- On the existing Supabase project these objects already exist.
--
-- Run order: php migrate.php   (executes 000_base_schema.sql, then 001_*)
-- =====================================================================

-- Ensure the uuid-ossp and pgcrypto extensions are available
-- (gen_random_uuid is used by wallet_ledger / admin_audit_logs)
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ---------------------------------------------------------------------
-- HELPER FUNCTIONS
-- ---------------------------------------------------------------------

-- Keeps updated_at in sync automatically on every UPDATE.
CREATE OR REPLACE FUNCTION public.update_updated_at_column()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$function$;

-- Generates a single random 6-char referral code (excludes ambiguous chars 0,O,1,I).
CREATE OR REPLACE FUNCTION public.generate_referral_code()
RETURNS character varying
LANGUAGE plpgsql
AS $function$
DECLARE
    chars TEXT := 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    result VARCHAR(6) := '';
    i INTEGER;
BEGIN
    FOR i IN 1..6 LOOP
        result := result || substr(chars, floor(random() * length(chars) + 1)::int, 1);
    END LOOP;
    RETURN result;
END;
$function$;

-- Loops until it finds a referral code not already in use.
CREATE OR REPLACE FUNCTION public.ensure_unique_referral_code()
RETURNS character varying
LANGUAGE plpgsql
AS $function$
DECLARE
    new_code VARCHAR(6);
    code_exists BOOLEAN;
BEGIN
    LOOP
        new_code := generate_referral_code();
        SELECT EXISTS(SELECT 1 FROM users WHERE referral_code = new_code) INTO code_exists;
        EXIT WHEN NOT code_exists;
    END LOOP;
    RETURN new_code;
END;
$function$;

-- Trigger function: auto-assign a referral code to new non-admin users.
CREATE OR REPLACE FUNCTION public.auto_generate_referral_code()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    IF NEW.referral_code IS NULL AND NEW.is_admin = FALSE THEN
        NEW.referral_code := ensure_unique_referral_code();
    END IF;
    RETURN NEW;
END;
$function$;

-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.users (
    id                      uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    email                   character varying(255) NOT NULL,
    password_hash           character varying(255) NOT NULL,
    name                    character varying(255),
    is_admin                boolean DEFAULT false,
    is_active               boolean DEFAULT true,
    wallet_balance          integer DEFAULT 0,
    mobile_number           character varying(15),
    aadhar_number           character varying(12),
    date_of_birth           date,
    pan_number              character varying(10),
    created_at              timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at              timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    referral_code           character varying(6),
    referred_by             uuid,
    saved_upi_id            character varying(100),
    saved_bank_details      jsonb,
    preferred_payment_method character varying(10) DEFAULT 'upi',
    deleted_at              timestamp without time zone,
    CONSTRAINT users_email_key UNIQUE (email),
    CONSTRAINT users_referral_code_key UNIQUE (referral_code),
    CONSTRAINT users_preferred_payment_method_check
        CHECK (preferred_payment_method IN ('upi', 'bank'))
);

CREATE INDEX IF NOT EXISTS idx_users_referred_by ON public.users (referred_by);

DROP TRIGGER IF EXISTS update_users_updated_at ON public.users;
CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON public.users
    FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

DROP TRIGGER IF EXISTS trigger_auto_generate_referral_code ON public.users;
CREATE TRIGGER trigger_auto_generate_referral_code
    BEFORE INSERT ON public.users
    FOR EACH ROW EXECUTE FUNCTION public.auto_generate_referral_code();

-- ---------------------------------------------------------------------
-- ADMIN SETTINGS (single-row configuration table)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.admin_settings (
    id                uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    qr_code_url       character varying(500),
    upi_id            character varying(100),
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    required_referrals integer DEFAULT 5
);

DROP TRIGGER IF EXISTS update_admin_settings_updated_at ON public.admin_settings;
CREATE TRIGGER update_admin_settings_updated_at
    BEFORE UPDATE ON public.admin_settings
    FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- ---------------------------------------------------------------------
-- PAYMENT METHODS (deposit destination options shown to users)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.payment_methods (
    id          uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    label       character varying(50) NOT NULL,
    qr_code_url character varying(500),
    upi_id      character varying(100),
    is_active   boolean DEFAULT true,
    sort_order  integer DEFAULT 0,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- PRODUCTS (yield assets users can buy)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.products (
    id                    uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    name                  character varying(255) NOT NULL,
    image_url             character varying(500) NOT NULL,
    price                 integer NOT NULL,
    duration_days         integer NOT NULL,
    daily_reward_percent  numeric NOT NULL,
    ad_watch_seconds      integer NOT NULL DEFAULT 120,
    is_active             boolean DEFAULT true,
    created_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at            timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    deleted_at            timestamp without time zone,
    deleted_by            uuid,
    CONSTRAINT products_price_check CHECK (price > 0),
    CONSTRAINT products_duration_days_check CHECK (duration_days > 0),
    CONSTRAINT products_daily_reward_percent_check
        CHECK (daily_reward_percent > 0 AND daily_reward_percent <= 100),
    CONSTRAINT products_ad_watch_seconds_check CHECK (ad_watch_seconds > 0)
);

-- ---------------------------------------------------------------------
-- PRODUCT AD LINKS (per-day video ad schedule for a product)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.product_ad_links (
    id         uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    product_id uuid NOT NULL REFERENCES public.products(id),
    day_number integer NOT NULL,
    video_url  text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT product_ad_links_product_id_day_number_key UNIQUE (product_id, day_number),
    CONSTRAINT product_ad_links_day_number_check CHECK (day_number > 0)
);

-- ---------------------------------------------------------------------
-- DAILY AD (global fallback ad, used when no product-specific ad exists)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.daily_ad (
    id         uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    video_url  text NOT NULL,
    updated_by uuid REFERENCES public.users(id),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- USER INVESTMENTS (a user's purchased yield asset contract)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.user_investments (
    id             uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id        uuid NOT NULL REFERENCES public.users(id),
    product_id     uuid NOT NULL REFERENCES public.products(id),
    purchase_price integer NOT NULL,
    purchased_at   timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    expires_at     timestamp without time zone NOT NULL,
    is_sold        boolean DEFAULT false,
    sold_at        timestamp without time zone,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_user_investments_user ON public.user_investments (user_id);
CREATE INDEX IF NOT EXISTS idx_user_investments_active ON public.user_investments (is_sold, expires_at);

-- ---------------------------------------------------------------------
-- AD WATCH LOG (one row per claimed daily ad reward)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.ad_watch_log (
    id                  uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_investment_id  uuid NOT NULL REFERENCES public.user_investments(id),
    user_id             uuid NOT NULL REFERENCES public.users(id),
    reward_amount       integer NOT NULL,
    watched_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ad_watch_log_investment_day
    ON public.ad_watch_log (user_investment_id, watched_at);

-- ---------------------------------------------------------------------
-- DEPOSIT REQUESTS (user money-in, pending admin approval)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.deposit_requests (
    id                 uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id            uuid NOT NULL REFERENCES public.users(id),
    amount             integer NOT NULL,
    transaction_id     character varying(50),
    payment_proof_url  character varying(500) NOT NULL,
    status             character varying(20) DEFAULT 'pending',
    created_at         timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    processed_at       timestamp without time zone,
    processed_by       uuid REFERENCES public.users(id),
    rejection_reason   text,
    payment_method_id  uuid,
    CONSTRAINT deposit_requests_status_check
        CHECK (status IN ('pending', 'approved', 'rejected'))
);

CREATE INDEX IF NOT EXISTS idx_deposit_requests_user_status
    ON public.deposit_requests (user_id, status);

-- ---------------------------------------------------------------------
-- WITHDRAWAL REQUESTS (user money-out, balance held until processed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.withdrawal_requests (
    id               uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id          uuid NOT NULL REFERENCES public.users(id),
    amount           integer NOT NULL,
    bank_details     jsonb NOT NULL,
    status           character varying(20) DEFAULT 'pending',
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    processed_at     timestamp without time zone,
    processed_by     uuid REFERENCES public.users(id),
    rejection_reason text,
    payment_method   character varying(20),
    verified_details boolean DEFAULT false,
    CONSTRAINT withdrawal_requests_status_check
        CHECK (status IN ('pending', 'completed', 'rejected'))
);

CREATE INDEX IF NOT EXISTS idx_withdrawal_requests_user_status
    ON public.withdrawal_requests (user_id, status);

-- ---------------------------------------------------------------------
-- REFERRAL BONUSES (5% of referred user's approved deposit, claimable)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.referral_bonuses (
    id               uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    referrer_id      uuid NOT NULL REFERENCES public.users(id),
    referred_user_id uuid NOT NULL REFERENCES public.users(id),
    deposit_id       uuid NOT NULL REFERENCES public.deposit_requests(id),
    deposit_amount   integer NOT NULL,
    bonus_amount     integer NOT NULL,
    is_claimed       boolean DEFAULT false,
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_referral_bonuses_referrer
    ON public.referral_bonuses (referrer_id, is_claimed);

-- ---------------------------------------------------------------------
-- BONUS CLAIM REQUESTS (user requests payout of a referral bonus)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.bonus_claim_requests (
    id               uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id          uuid NOT NULL REFERENCES public.users(id),
    bonus_id         uuid NOT NULL REFERENCES public.referral_bonuses(id),
    amount           integer NOT NULL,
    status           character varying(20) DEFAULT 'pending',
    created_at       timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    processed_at     timestamp without time zone,
    processed_by     uuid REFERENCES public.users(id),
    rejection_reason text,
    CONSTRAINT bonus_claim_requests_status_check
        CHECK (status IN ('pending', 'approved', 'rejected'))
);

-- ---------------------------------------------------------------------
-- WALLET LEDGER (immutable double-entry-style balance history)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.wallet_ledger (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         uuid NOT NULL,
    amount          integer NOT NULL,
    direction       character varying(10) NOT NULL,
    entry_type      character varying(40) NOT NULL,
    reference_table character varying(80),
    reference_id    uuid,
    balance_after   integer,
    note            text,
    created_by      uuid,
    created_at      timestamp without time zone DEFAULT NOW(),
    CONSTRAINT wallet_ledger_direction_check CHECK (direction IN ('credit', 'debit'))
);

CREATE INDEX IF NOT EXISTS idx_wallet_ledger_user_created
    ON public.wallet_ledger (user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_wallet_ledger_reference
    ON public.wallet_ledger (reference_table, reference_id);

-- ---------------------------------------------------------------------
-- ADMIN AUDIT LOGS (immutable record of admin actions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.admin_audit_logs (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_id     uuid,
    action       character varying(80) NOT NULL,
    target_table character varying(80),
    target_id    uuid,
    details      jsonb,
    created_at   timestamp without time zone DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_admin_audit_logs_admin_created
    ON public.admin_audit_logs (admin_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_admin_audit_logs_target
    ON public.admin_audit_logs (target_table, target_id);

-- ---------------------------------------------------------------------
-- SEED: ensure a single admin_settings row exists (required by app)
-- ---------------------------------------------------------------------
INSERT INTO public.admin_settings (qr_code_url, upi_id, required_referrals)
SELECT NULL, NULL, 5
WHERE NOT EXISTS (SELECT 1 FROM public.admin_settings LIMIT 1);
