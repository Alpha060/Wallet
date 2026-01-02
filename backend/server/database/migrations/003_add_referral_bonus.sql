-- Migration: Add referral bonus system
-- When a referred user's deposit is approved, 5% goes to referrer as claimable bonus

-- Referral bonuses table - tracks earned bonuses from referred users' deposits
CREATE TABLE IF NOT EXISTS referral_bonuses (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    referrer_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referred_user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    deposit_id UUID NOT NULL REFERENCES deposit_requests(id) ON DELETE CASCADE,
    deposit_amount INTEGER NOT NULL,
    bonus_amount INTEGER NOT NULL,
    is_claimed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bonus claim requests table - tracks claim requests (similar to deposits)
CREATE TABLE IF NOT EXISTS bonus_claim_requests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    bonus_id UUID NOT NULL REFERENCES referral_bonuses(id) ON DELETE CASCADE,
    amount INTEGER NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    processed_by UUID REFERENCES users(id),
    rejection_reason TEXT
);

-- Indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_referral_bonuses_referrer ON referral_bonuses(referrer_id);
CREATE INDEX IF NOT EXISTS idx_referral_bonuses_unclaimed ON referral_bonuses(referrer_id, is_claimed) WHERE is_claimed = FALSE;
CREATE INDEX IF NOT EXISTS idx_bonus_claims_user ON bonus_claim_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_bonus_claims_status ON bonus_claim_requests(status);
