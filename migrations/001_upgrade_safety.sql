-- AeroPay safety upgrade migration.
-- This file is intentionally idempotent so it can be run more than once.

CREATE TABLE IF NOT EXISTS public.wallet_ledger (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL,
    amount integer NOT NULL,
    direction character varying(10) NOT NULL CHECK (direction IN ('credit', 'debit')),
    entry_type character varying(40) NOT NULL,
    reference_table character varying(80),
    reference_id uuid,
    balance_after integer,
    note text,
    created_by uuid,
    created_at timestamp without time zone DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_wallet_ledger_user_created
    ON public.wallet_ledger (user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_wallet_ledger_reference
    ON public.wallet_ledger (reference_table, reference_id);

CREATE TABLE IF NOT EXISTS public.admin_audit_logs (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_id uuid,
    action character varying(80) NOT NULL,
    target_table character varying(80),
    target_id uuid,
    details jsonb,
    created_at timestamp without time zone DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_admin_audit_logs_admin_created
    ON public.admin_audit_logs (admin_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_admin_audit_logs_target
    ON public.admin_audit_logs (target_table, target_id);

ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS deleted_at timestamp without time zone;

ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS deleted_at timestamp without time zone;

ALTER TABLE public.products
    ADD COLUMN IF NOT EXISTS deleted_by uuid;

ALTER TABLE public.deposit_requests
    ADD COLUMN IF NOT EXISTS payment_method_id uuid;

ALTER TABLE public.withdrawal_requests
    ADD COLUMN IF NOT EXISTS payment_method character varying(20);

ALTER TABLE public.withdrawal_requests
    ADD COLUMN IF NOT EXISTS verified_details boolean DEFAULT FALSE;
