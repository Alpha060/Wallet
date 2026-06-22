<?php
// dashboard.php - User Dashboard UI
require_once __DIR__ . '/helpers.php';
$user = requireAuth();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AeroPay - Dashboard</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        /* Extra dashboard specific styling */
        .roi-badge {
            align-self: flex-start;
            margin-bottom: 12px;
        }
        .step-wizard {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
            position: relative;
        }
        .step-wizard::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 0;
            transform: translateY(-50%);
        }
        .wizard-step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--background);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            z-index: 1;
            transition: all 0.3s;
        }
        .wizard-step.active {
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }
        .wizard-step.complete {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: rgba(0,0,0,0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        .dark .progress-bar-container {
            background: rgba(255,255,255,0.05);
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, #6366f1 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .tab-view {
            display: none;
        }
        .tab-view.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Confetti Canvas overlay */
        #confetti-canvas {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9999;
            display: none;
        }
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <canvas id="confetti-canvas"></canvas>

    <div class="dashboard-container">
        <!-- Desktop Sidebar -->
        <aside class="sidebar">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.2rem;">A</div>
                <h2 style="font-weight: 800; font-size: 1.4rem;">Aero<span class="text-gradient">Pay</span></h2>
            </div>

            <div class="glass-card" style="padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; border-radius: var(--radius-sm);">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo strtoupper(substr($user['name'] ?: 'U', 0, 1)); ?>
                </div>
                <div style="overflow: hidden;">
                    <div style="font-size: 0.85rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($user['name'] ?: 'User'); ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <button class="nav-link active" data-tab="marketplace">🛒 Marketplace</button>
                <button class="nav-link" data-tab="overview">📊 Overview</button>
                <button class="nav-link" data-tab="deposit">📥 Deposit Funds</button>
                <button class="nav-link" data-tab="withdraw">📤 Withdraw</button>
                <button class="nav-link" data-tab="investments">💼 My Investments</button>
                <button class="nav-link" data-tab="referrals">👥 Referrals</button>
                <button class="nav-link" data-tab="history">⌛ History</button>
                <button class="nav-link" data-tab="settings">⚙️ Settings</button>
            </nav>

            <div style="margin-top: auto; display: flex; flex-direction: column; gap: 16px;">
                <div style="display: flex; justify-content: center;">
                    <button class="theme-toggle-btn" id="theme-toggle">☀️</button>
                </div>
                <button class="btn-secondary" id="logout-btn" style="width: 100%;">🚪 Logout</button>
            </div>
        </aside>

        <!-- Mobile Header -->
        <header class="mobile-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button id="mobile-menu-trigger" style="background: none; border: none; font-size: 1.5rem; color: var(--foreground);">☰</button>
                <h3 id="mobile-title" style="font-weight: 700;">Marketplace</h3>
            </div>
            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                <?php echo strtoupper(substr($user['name'] ?: 'U', 0, 1)); ?>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- 1. MARKETPLACE TAB -->
            <section id="tab-marketplace" class="tab-view active">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Marketplace</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Invest in high-yield assets to secure daily rewards</p>
                <div class="marketplace-grid" id="products-list">
                    <!-- Products dynamically loaded -->
                </div>
            </section>

            <!-- 2. OVERVIEW TAB -->
            <section id="tab-overview" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Overview</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Your AeroPay balance and transaction highlights</p>
                
                <!-- Apple Card Style Wallet -->
                <div style="background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 50%, #f1f5f9 100%); border-radius: 24px; padding: 32px; margin-bottom: 32px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1), inset 0 1px 1px rgba(255,255,255,0.8); position: relative; overflow: hidden; max-width: 500px; transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);">
                    <!-- Shine Effect -->
                    <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: linear-gradient(to bottom right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%); transform: rotate(30deg); pointer-events: none;"></div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
                        <div>
                            <div style="color: #475569; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;">AeroPay Card</div>
                            <div style="font-size: 2.8rem; font-weight: 800; letter-spacing: -0.05em; color: #0f172a;" id="wallet-balance">₹0.00</div>
                            <div style="color: #64748b; font-size: 0.9rem; font-weight: 500; margin-top: 4px;"><?php echo htmlspecialchars($user['name'] ?: 'Cardholder'); ?></div>
                        </div>
                        <!-- Logo / Chip placeholder -->
                        <div style="width: 48px; height: 32px; background: rgba(0,0,0,0.1); border-radius: 6px; border: 1px solid rgba(255,255,255,0.5);"></div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 32px; position: relative; z-index: 1;">
                        <button class="btn-primary" onclick="switchTab('deposit')" style="background: #0f172a; color: #fff; box-shadow: none;">📥 Deposit</button>
                        <button class="btn-secondary" onclick="switchTab('withdraw')" style="background: rgba(255,255,255,0.5); border: none;">📤 Withdraw</button>
                    </div>
                </div>

                <div class="bento-grid">
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Total Deposits</div>
                        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--success);" id="total-deposits">₹0.00</h3>
                    </div>
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Total Withdrawals</div>
                        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--destructive);" id="total-withdrawals">₹0.00</h3>
                    </div>
                </div>

                <div class="glass-panel" style="padding: 24px; margin-top: 32px;">
                    <h3 style="font-weight: 700; margin-bottom: 20px;">Recent Activity</h3>
                    <div id="recent-transactions" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Recent transactions dynamically loaded -->
                    </div>
                </div>
            </section>

            <!-- 3. DEPOSIT TAB -->
            <section id="tab-deposit" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Deposit Funds</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Add funds using UPI or direct Bank Transfer</p>

                <div class="glass-panel" style="padding: 36px; max-width: 600px;">
                    <div class="step-wizard">
                        <div class="wizard-step active" id="step1-indicator">1</div>
                        <div class="wizard-step" id="step2-indicator">2</div>
                        <div class="wizard-step" id="step3-indicator">3</div>
                    </div>

                    <form id="deposit-form" enctype="multipart/form-data">
                        <!-- Step 1: Amount -->
                        <div id="deposit-step-1">
                            <h3 style="font-weight: 700; margin-bottom: 16px;">Step 1: Enter Amount</h3>
                            <div class="form-group">
                                <label class="form-label">Amount (in Rupees)</label>
                                <input class="form-input" type="text" id="dep-amount-input" name="amount" placeholder="e.g. 2000" required>
                            </div>
                            <button type="button" class="btn-primary" onclick="nextDepositStep(2)" style="width: 100%;">Continue</button>
                        </div>

                        <!-- Step 2: Pay -->
                        <div id="deposit-step-2" style="display: none;">
                            <h3 style="font-weight: 700; margin-bottom: 16px;">Step 2: Scan and Send</h3>
                            <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 20px;">Scan the QR code below or transfer to the UPI ID, then send the payment.</p>
                            
                            <!-- Payment Method Tabs -->
                            <div id="deposit-pm-tabs" style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;"></div>

                            <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 24px;">
                                <img id="deposit-qr" src="" alt="QR Code" style="width: 220px; height: 220px; border-radius: 12px; border: 1px solid var(--border); display: none; margin-bottom: 16px;">
                                <div style="font-size: 1rem; font-weight: 700;" id="deposit-upi-text">UPI ID: Loading...</div>
                            </div>
                            
                            <div style="display: flex; gap: 12px;">
                                <button type="button" class="btn-secondary" onclick="nextDepositStep(1)" style="flex: 1;">Back</button>
                                <button type="button" class="btn-primary" onclick="nextDepositStep(3)" style="flex: 1;">I Have Paid</button>
                            </div>
                        </div>

                        <!-- Step 3: Proof -->
                        <div id="deposit-step-3" style="display: none;">
                            <h3 style="font-weight: 700; margin-bottom: 16px;">Step 3: Upload Proof</h3>
                            <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 20px;">Upload a screenshot of the payment receipt and enter the Transaction ID / Ref ID.</p>
                            
                            <div class="form-group">
                                <label class="form-label">Screenshot Receipt</label>
                                <input class="form-input" type="file" name="paymentProof" required accept="image/*">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Transaction ID (Optional)</label>
                                <input class="form-input" type="text" name="transactionId" placeholder="12-digit UPI Transaction Ref ID">
                            </div>

                            <div style="display: flex; gap: 12px;">
                                <button type="button" class="btn-secondary" onclick="nextDepositStep(2)" style="flex: 1;">Back</button>
                                <button type="submit" class="btn-primary" style="flex: 1;" id="deposit-submit-btn">Submit Request</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- 4. WITHDRAW TAB -->
            <section id="tab-withdraw" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Withdraw Funds</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Cash out your balance to bank or UPI</p>

                <div class="glass-panel" style="padding: 36px; max-width: 600px;">
                    <div style="margin-bottom: 24px; font-weight: 700; font-size: 1.1rem;">
                        Available: <span style="color: var(--primary);" id="withdraw-available-balance">₹0.00</span>
                    </div>

                    <div id="withdrawal-referral-warning" style="display: none; background: var(--destructive-glow); border: 1px solid rgba(239, 68, 68, 0.15); color: var(--destructive); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 24px; font-size: 0.85rem; font-weight: 600;">
                        <!-- Display referral limits warnings -->
                    </div>

                    <form id="withdrawal-form">
                        <div class="form-group">
                            <label class="form-label">Withdrawal Amount (Rupees)</label>
                            <input class="form-input" type="text" id="withdraw-amount-input" name="amount" placeholder="e.g. 500" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Type</label>
                            <div style="display: flex; gap: 12px;">
                                <label style="flex: 1; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="withdrawMethod" value="upi" checked onclick="toggleWithdrawMethod('upi')"> UPI
                                </label>
                                <label style="flex: 1; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="withdrawMethod" value="bank" onclick="toggleWithdrawMethod('bank')"> Bank Account
                                </label>
                            </div>
                        </div>

                        <!-- UPI Fields -->
                        <div id="withdraw-upi-fields" class="form-group">
                            <label class="form-label">UPI ID</label>
                            <input class="form-input" type="text" id="withdraw-upi-id" placeholder="user@bank">
                        </div>

                        <!-- Bank Fields -->
                        <div id="withdraw-bank-fields" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Account Holder Name</label>
                                <input class="form-input" type="text" id="withdraw-acc-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account Number</label>
                                <input class="form-input" type="text" id="withdraw-acc-num">
                            </div>
                            <div class="form-group">
                                <label class="form-label">IFSC Code</label>
                                <input class="form-input" type="text" id="withdraw-ifsc" placeholder="ABCD0123456" style="text-transform: uppercase;">
                            </div>
                        </div>

                        <!-- Swipe to Withdraw -->
                        <div class="swipe-container" id="withdraw-swipe">
                            <div class="swipe-bg"></div>
                            <div class="swipe-handle">➔</div>
                            <span class="swipe-text">Swipe to Withdraw</span>
                        </div>
                    </form>
                </div>
            </section>

            <!-- 5. MY INVESTMENTS TAB -->
            <section id="tab-investments" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">My Investments</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Manage active yield contracts and watch daily ads</p>

                <div class="marketplace-grid" id="investments-list" style="margin-bottom: 40px;">
                    <!-- Investments dynamically loaded -->
                </div>

                <div class="glass-panel" style="padding: 24px;">
                    <h3 style="font-weight: 700; margin-bottom: 20px;">Yield Earnings History</h3>
                    <div id="investment-history-list" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Earnings list dynamically loaded -->
                    </div>
                </div>
            </section>

            <!-- 6. REFERRALS TAB -->
            <section id="tab-referrals" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Referral Program</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Invite your friends to earn 5% cash rewards on their deposits</p>

                <div class="bento-grid">
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">My Referral Code</div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <h2 style="font-size: 1.8rem; font-weight: 800; font-family: monospace;" id="my-referral-code">------</h2>
                            <button class="btn-secondary" onclick="copyReferralCode()" style="padding: 8px 12px; font-size: 0.8rem;">Copy</button>
                        </div>
                    </div>
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Claimable Commissions</div>
                        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--success); margin-bottom: 8px;" id="claimable-amount">₹0.00</h2>
                        <button class="btn-primary" onclick="claimAllBonuses()" style="padding: 8px 16px; font-size: 0.8rem;" id="claim-all-btn">Claim All</button>
                    </div>
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Roadmap Progress</div>
                        <h3 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 6px;" id="referral-roadmap-text">0 / 5 Referrals</h3>
                        <div class="progress-bar-container"><div class="progress-bar" id="referral-roadmap-bar" style="width: 0%;"></div></div>
                        <p style="font-size: 0.75rem; color: var(--muted); margin-top: 6px;">Invited members with active deposits</p>
                    </div>
                </div>

                <div class="glass-panel" style="padding: 24px; margin-top: 32px;">
                    <h3 style="font-weight: 700; margin-bottom: 20px;">Referred Members</h3>
                    <div id="referred-members" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Referred members dynamic loading -->
                    </div>
                </div>

                <div class="glass-panel" style="padding: 24px; margin-top: 32px;">
                    <h3 style="font-weight: 700; margin-bottom: 20px;">Commissions Ledger</h3>
                    <div id="unclaimed-bonuses-list" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                        <!-- Unclaimed bonuses lists -->
                    </div>
                    <h3 style="font-weight: 700; margin-bottom: 20px; border-top: 1px solid var(--border); padding-top: 20px;">Claim Claims History</h3>
                    <div id="claim-history-list" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Claims logs -->
                    </div>
                </div>
            </section>

            <!-- 7. HISTORY TAB -->
            <section id="tab-history" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Transaction History</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Track deposits, withdrawals, yield earnings, and sales</p>

                <div class="glass-panel" style="padding: 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
                    <div>
                        <label class="form-label" style="margin-bottom: 4px;">Type Filter</label>
                        <select class="form-input" id="history-filter" style="width: 160px; padding: 8px 12px;">
                            <option value="all">All Transactions</option>
                            <option value="deposit">Deposits</option>
                            <option value="withdrawal">Withdrawals</option>
                            <option value="buy">Asset Purchases</option>
                            <option value="sell">Asset Sells</option>
                            <option value="reward">Daily Rewards</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="margin-bottom: 4px;">Start Date</label>
                        <input class="form-input" type="date" id="history-start" style="width: 160px; padding: 8px 12px;">
                    </div>

                    <div>
                        <label class="form-label" style="margin-bottom: 4px;">End Date</label>
                        <input class="form-input" type="date" id="history-end" style="width: 160px; padding: 8px 12px;">
                    </div>

                    <button class="btn-primary" onclick="fetchHistory()" style="margin-top: 20px; padding: 10px 20px;">Apply Filters</button>
                </div>

                <div class="glass-panel" style="padding: 24px;">
                    <div id="history-ledger" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Transaction history dynamically loaded -->
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
                        <button class="btn-secondary" id="history-prev-btn" onclick="prevHistoryPage()" style="padding: 8px 16px;">Previous</button>
                        <span id="history-page-indicator" style="font-weight: 700;">Page 1 of 1</span>
                        <button class="btn-secondary" id="history-next-btn" onclick="nextHistoryPage()" style="padding: 8px 16px;">Next</button>
                    </div>
                </div>
            </section>

            <!-- 8. SETTINGS TAB -->
            <section id="tab-settings" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Account Settings</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Configure Profile details, KYC data, and password</p>

                <div style="display: flex; gap: 16px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                    <button class="nav-link active" onclick="switchSettingsSubTab('profile')" id="subtab-profile-btn" style="width: auto; padding: 8px 16px;">👤 Profile</button>
                    <button class="nav-link" onclick="switchSettingsSubTab('payment')" id="subtab-payment-btn" style="width: auto; padding: 8px 16px;">💳 Payment Details</button>
                    <button class="nav-link" onclick="switchSettingsSubTab('password')" id="subtab-password-btn" style="width: auto; padding: 8px 16px;">🔑 Change Password</button>
                </div>

                <!-- Profile Subtab -->
                <div id="settings-profile-panel">
                    <form id="profile-form" class="glass-panel" style="padding: 36px; max-width: 600px;">
                        <h3 style="font-weight: 700; margin-bottom: 20px;">Profile Information</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input class="form-input" type="text" id="prof-name" name="name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mobile Number</label>
                            <input class="form-input" type="text" id="prof-mobile" name="mobileNumber">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input class="form-input" type="date" id="prof-dob" name="dateOfBirth">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Aadhar Card Number (12 digits)</label>
                            <input class="form-input" type="text" id="prof-aadhar" name="aadharNumber" maxlength="12">
                        </div>

                        <div class="form-group">
                            <label class="form-label">PAN Card Number (10 characters)</label>
                            <input class="form-input" type="text" id="prof-pan" name="panNumber" maxlength="10" style="text-transform: uppercase;">
                        </div>

                        <button type="submit" class="btn-primary">Save Profile</button>
                    </form>
                </div>

                <!-- Payment Details Subtab -->
                <div id="settings-payment-panel" style="display: none;">
                    <form id="payment-details-form" class="glass-panel" style="padding: 36px; max-width: 600px;">
                        <h3 style="font-weight: 700; margin-bottom: 20px;">Withdrawal Details</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Cashout Method</label>
                            <select class="form-input" id="set-pref-method" name="preferredPaymentMethod">
                                <option value="upi">UPI ID</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Saved UPI ID</label>
                            <input class="form-input" type="text" id="set-upi" name="upiId">
                        </div>

                        <h4 style="font-weight: 700; margin: 24px 0 12px 0;">Saved Bank Details</h4>

                        <div class="form-group">
                            <label class="form-label">Account Holder Name</label>
                            <input class="form-input" type="text" id="set-acc-name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Account Number</label>
                            <input class="form-input" type="text" id="set-acc-num">
                        </div>

                        <div class="form-group">
                            <label class="form-label">IFSC Code</label>
                            <input class="form-input" type="text" id="set-ifsc" style="text-transform: uppercase;">
                        </div>

                        <button type="submit" class="btn-primary">Save Methods</button>
                    </form>
                </div>

                <!-- Password Subtab -->
                <div id="settings-password-panel" style="display: none;">
                    <form id="password-change-form" class="glass-panel" style="padding: 36px; max-width: 600px;">
                        <h3 style="font-weight: 700; margin-bottom: 20px;">Change Password</h3>

                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input class="form-input" type="password" id="currentPassword" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input class="form-input" type="password" id="newPassword" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input class="form-input" type="password" id="confirmNewPassword" required>
                        </div>

                        <button type="submit" class="btn-primary">Update Password</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <!-- Ad Watch Theater Video Overlay -->
    <div class="theater-mode" id="ad-theater" style="display: none;">
        <div class="video-container">
            <video id="ad-video-element" controls autoplay style="display: none;"></video>
            <!-- In case it is an embedded YouTube URL or other link, we support iframe as fallback -->
            <iframe id="ad-iframe-element" style="display: none;" allow="autoplay"></iframe>

            <div class="countdown-overlay">
                ⏱️ <span id="ad-time-left">120</span>s Remaining
            </div>
        </div>
        <div class="claim-button-container" id="ad-claim-box" style="display: none;">
            <button class="btn-primary" onclick="claimDailyReward()">🎁 Claim Daily Reward</button>
        </div>
        <button class="btn-secondary" onclick="closeAdPlayer()" style="margin-top: 16px; padding: 10px 20px;">Cancel</button>
    </div>

    <!-- Purchase Confirmation Modal -->
    <div class="modal-overlay" id="purchase-modal">
        <div class="modal-content">
            <h3 style="font-weight: 800; margin-bottom: 16px;">Confirm Purchase</h3>
            <p style="font-size: 0.95rem; color: var(--muted); margin-bottom: 24px; line-height: 1.5;" id="purchase-modal-text"></p>
            <div style="display: flex; gap: 12px;">
                <button class="btn-secondary" onclick="closePurchaseModal()" style="flex: 1;">Cancel</button>
                <button class="btn-primary" id="purchase-confirm-btn" style="flex: 1;">Confirm Purchase</button>
            </div>
        </div>
    </div>

    <script src="/app.js"></script>
    <script>
        // Tab switching logic
        const tabs = document.querySelectorAll('.nav-link[data-tab]');
        const tabViews = document.querySelectorAll('.tab-view');
        const mobileTitle = document.getElementById('mobile-title');

        function switchTab(tabId) {
            tabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-tab') === tabId));
            tabViews.forEach(v => v.classList.toggle('active', v.id === `tab-${tabId}`));
            
            // Set mobile title
            if (mobileTitle) {
                const activeTabBtn = document.querySelector(`.nav-link[data-tab="${tabId}"]`);
                if (activeTabBtn) mobileTitle.innerText = activeTabBtn.innerText.substring(3);
            }

            // Trigger fetches based on tab
            if (tabId === 'overview') {
                fetchOverview();
            } else if (tabId === 'marketplace') {
                fetchMarketplace();
            } else if (tabId === 'deposit') {
                fetchDepositDetails();
            } else if (tabId === 'withdraw') {
                fetchWithdrawalDetails();
            } else if (tabId === 'investments') {
                fetchInvestments();
            } else if (tabId === 'referrals') {
                fetchReferrals();
            } else if (tabId === 'history') {
                fetchHistory();
            } else if (tabId === 'settings') {
                fetchSettings();
            }
        }

        tabs.forEach(btn => {
            btn.addEventListener('click', () => {
                switchTab(btn.getAttribute('data-tab'));
                if (window.innerWidth <= 768) {
                    sidebar.style.display = 'none';
                }
            });
        });

        // Mobile menu toggle
        const menuTrigger = document.getElementById('mobile-menu-trigger');
        const sidebar = document.querySelector('.sidebar');
        if (menuTrigger) {
            menuTrigger.addEventListener('click', () => {
                const isOpen = sidebar.style.display === 'flex';
                sidebar.style.display = isOpen ? 'none' : 'flex';
            });
        }

        // --- Tab Fetches & Logic ---
        
        // Formatters
        function formatRupees(paise) {
            return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // 1. Marketplace
        let purchaseTargetProduct = null;
        async function fetchMarketplace() {
            try {
                const data = await apiRequest('/api/products');
                const list = document.getElementById('products-list');
                list.innerHTML = '';

                data.products.forEach(p => {
                    const card = document.createElement('div');
                    card.className = 'glass-card marketplace-card';
                    card.innerHTML = `
                        <div class="roi-badge"><span class="badge-roi">${p.dailyRewardPercent}% Daily ROI</span></div>
                        <img src="${p.imageUrl}" alt="${p.name}" style="width: 100%; height: 160px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px;">
                        <h3 style="font-weight: 700; margin-bottom: 4px;">${p.name}</h3>
                        <p style="color: var(--muted); font-size: 0.8rem; margin-bottom: 16px;">Duration: ${p.durationDays} Days | Watch Time: ${p.adWatchSeconds}s</p>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: auto; border-top: 1px solid var(--border); padding-top: 16px;">
                            <div>
                                <span style="font-size: 0.75rem; color: var(--muted); display: block;">Purchase Price</span>
                                <span style="font-size: 1.2rem; font-weight: 800; color: var(--foreground);">${formatRupees(p.price)}</span>
                            </div>
                            <button class="btn-primary" onclick="openPurchaseModal('${p.id}', '${p.name}', ${p.price})" style="padding: 10px 20px;">Buy</button>
                        </div>
                    `;
                    list.appendChild(card);
                });
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        function openPurchaseModal(id, name, price) {
            purchaseTargetProduct = id;
            document.getElementById('purchase-modal-text').innerHTML = `Are you sure you want to purchase the asset <strong>"${name}"</strong> for <strong>${formatRupees(price)}</strong>?<br>Confirming will automatically debit this amount from your available balance.`;
            document.getElementById('purchase-modal').classList.add('active');
        }

        function closePurchaseModal() {
            document.getElementById('purchase-modal').classList.remove('active');
            purchaseTargetProduct = null;
        }

        document.getElementById('purchase-confirm-btn').addEventListener('click', async () => {
            if (!purchaseTargetProduct) return;
            try {
                const res = await apiRequest(`/api/products/buy/${purchaseTargetProduct}`, { method: 'POST' });
                Toast.show('Asset purchased successfully!');
                closePurchaseModal();
                switchTab('investments');
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        });

        // 2. Overview
        async function fetchOverview() {
            try {
                const balData = await apiRequest('/api/wallet/balance');
                document.getElementById('wallet-balance').innerText = formatRupees(balData.balance);
                
                const txData = await apiRequest('/api/wallet/transactions?page=1&limit=5');
                const recentList = document.getElementById('recent-transactions');
                recentList.innerHTML = '';

                if (txData.transactions.length === 0) {
                    recentList.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No transactions recorded.</div>';
                }

                txData.transactions.forEach(t => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border);';
                    const isCredit = t.type === 'deposit';
                    const symbol = isCredit ? '↘️' : '↗️';
                    const amtColor = isCredit ? 'var(--success)' : 'var(--destructive)';
                    const prefix = isCredit ? '+' : '-';
                    const date = new Date(t.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });

                    row.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.1rem;">${symbol}</span>
                            <div>
                                <span style="font-weight: 700; font-size: 0.9rem; text-transform: capitalize;">${t.type}</span>
                                <span style="font-size: 0.75rem; color: var(--muted); display: block;">${date} | Status: <span style="font-weight: 600;">${t.status}</span></span>
                            </div>
                        </div>
                        <span style="font-weight: 800; color: ${amtColor};">${prefix}${formatRupees(t.amount)}</span>
                    `;
                    recentList.appendChild(row);
                });

                const summary = await apiRequest('/api/wallet/summary');
                document.getElementById('total-deposits').innerText = formatRupees(summary.totalApprovedDeposits);
                document.getElementById('total-withdrawals').innerText = formatRupees(summary.totalCompletedWithdrawals);
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 3. Deposit Wizard
        let depositPaymentMethods = [];
        async function fetchDepositDetails() {
            try {
                const data = await apiRequest('/api/deposits/payment-details');
                const methodsData = await apiRequest('/api/admin/payment-methods/public');
                const tabsContainer = document.getElementById('deposit-pm-tabs');
                tabsContainer.innerHTML = '';

                if (methodsData.methods && methodsData.methods.length > 0) {
                    depositPaymentMethods = methodsData.methods;
                    // Build tabs for each payment method
                    methodsData.methods.forEach((pm, idx) => {
                        const tab = document.createElement('button');
                        tab.type = 'button';
                        tab.className = idx === 0 ? 'btn-primary' : 'btn-secondary';
                        tab.style.cssText = 'padding: 8px 16px; font-size: 0.8rem;';
                        tab.innerText = pm.label || `Option ${idx + 1}`;
                        tab.addEventListener('click', () => {
                            tabsContainer.querySelectorAll('button').forEach(b => b.className = 'btn-secondary');
                            tab.className = 'btn-primary';
                            showDepositPaymentMethod(pm);
                        });
                        tabsContainer.appendChild(tab);
                    });
                    // Show first method by default
                    showDepositPaymentMethod(methodsData.methods[0]);
                } else {
                    // Fallback to primary admin settings
                    showDepositPaymentMethod({ qrCodeUrl: data.qrCodeUrl, upiId: data.upiId });
                }
            } catch (err) {
                document.getElementById('deposit-upi-text').innerText = 'UPI ID: Error loading details';
            }
        }

        function showDepositPaymentMethod(pm) {
            if (pm.qrCodeUrl) {
                document.getElementById('deposit-qr').src = pm.qrCodeUrl;
                document.getElementById('deposit-qr').style.display = 'block';
            } else {
                document.getElementById('deposit-qr').style.display = 'none';
            }
            document.getElementById('deposit-upi-text').innerText = `UPI ID: ${pm.upiId || 'Not Setup'}`;
        }

        function nextDepositStep(stepNum) {
            // Validate amount before advancing from step 1
            if (stepNum === 2) {
                const amountInput = document.getElementById('dep-amount-input');
                const amount = parseFloat(amountInput.value);
                if (isNaN(amount) || amount <= 0) {
                    Toast.show('Please enter a valid deposit amount', 'error');
                    return;
                }
                if (amount < 1) {
                    Toast.show('Minimum deposit amount is ₹1', 'error');
                    return;
                }
            }

            document.getElementById('deposit-step-1').style.display = stepNum === 1 ? 'block' : 'none';
            document.getElementById('deposit-step-2').style.display = stepNum === 2 ? 'block' : 'none';
            document.getElementById('deposit-step-3').style.display = stepNum === 3 ? 'block' : 'none';

            // Update step Indicators
            document.getElementById('step1-indicator').className = `wizard-step ${stepNum === 1 ? 'active' : 'complete'}`;
            document.getElementById('step2-indicator').className = `wizard-step ${stepNum === 2 ? 'active' : (stepNum > 2 ? 'complete' : '')}`;
            document.getElementById('step3-indicator').className = `wizard-step ${stepNum === 3 ? 'active' : ''}`;
        }

        // Format currency amount input automatically
        formatCurrencyInput(document.getElementById('dep-amount-input'));

        document.getElementById('deposit-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('deposit-submit-btn');
            btn.disabled = true;
            btn.innerText = 'Submitting...';

            const formElement = document.getElementById('deposit-form');
            const rupees = parseFloat(document.getElementById('dep-amount-input').value);
            const paise = Math.round(rupees * 100);

            const formData = new FormData(formElement);
            formData.set('amount', paise); // Overwrite in paise

            try {
                await apiRequest('/api/deposits/create', {
                    method: 'POST',
                    body: formData,
                    isMultipart: true
                });

                Toast.show('Deposit request submitted! Awaiting admin approval.');
                formElement.reset();
                nextDepositStep(1);
                switchTab('overview');
            } catch (err) {
                Toast.show(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Submit Request';
            }
        });

        // 4. Withdrawal Swipe
        formatCurrencyInput(document.getElementById('withdraw-amount-input'));

        async function fetchWithdrawalDetails() {
            try {
                const balData = await apiRequest('/api/wallet/balance');
                document.getElementById('withdraw-available-balance').innerText = formatRupees(balData.balance);
                document.getElementById('withdraw-available-balance').setAttribute('data-raw', balData.balance);

                // Fetch limits/referral limits
                const refStats = await apiRequest('/api/referrals/stats');
                const warningDiv = document.getElementById('withdrawal-referral-warning');
                
                if (refStats.confirmedReferrals < refStats.requiredReferrals) {
                    const needed = refStats.requiredReferrals - refStats.confirmedReferrals;
                    warningDiv.innerText = `⚠️ Requirement: You must invite ${refStats.requiredReferrals} friends with active deposits to unlock withdrawals. You currently have ${refStats.confirmedReferrals} (${needed} remaining).`;
                    warningDiv.style.display = 'block';
                } else {
                    warningDiv.style.display = 'none';
                }

                // Autoload saved settings
                const payment = await apiRequest('/api/auth/payment-details');
                if (payment.savedUpiId) {
                    document.getElementById('withdraw-upi-id').value = payment.savedUpiId;
                }
                if (payment.savedBankDetails) {
                    document.getElementById('withdraw-acc-name').value = payment.savedBankDetails.accountName || '';
                    document.getElementById('withdraw-acc-num').value = payment.savedBankDetails.accountNumber || '';
                    document.getElementById('withdraw-ifsc').value = payment.savedBankDetails.ifscCode || '';
                }
                if (payment.preferredPaymentMethod) {
                    const radio = document.querySelector(`input[name="withdrawMethod"][value="${payment.preferredPaymentMethod}"]`);
                    if (radio) {
                        radio.checked = true;
                        toggleWithdrawMethod(payment.preferredPaymentMethod);
                    }
                }
            } catch (err) {}
        }

        function toggleWithdrawMethod(method) {
            document.getElementById('withdraw-upi-fields').style.display = method === 'upi' ? 'block' : 'none';
            document.getElementById('withdraw-bank-fields').style.display = method === 'bank' ? 'block' : 'none';
        }

        // Initialize swipe to withdraw
        SwipeSlider.init('withdraw-swipe', async () => {
            const amount = parseFloat(document.getElementById('withdraw-amount-input').value);
            if (isNaN(amount) || amount <= 0) {
                Toast.show('Please enter a valid amount', 'error');
                SwipeSlider.reset();
                return;
            }

            const method = document.querySelector('input[name="withdrawMethod"]:checked').value;
            let bankDetails = {};

            if (method === 'upi') {
                const upiVal = document.getElementById('withdraw-upi-id').value.trim();
                if (!upiVal) {
                    Toast.show('UPI ID is required', 'error');
                    SwipeSlider.reset();
                    return;
                }
                bankDetails = { upiId: upiVal };
            } else {
                const name = document.getElementById('withdraw-acc-name').value.trim();
                const num = document.getElementById('withdraw-acc-num').value.trim();
                const ifsc = document.getElementById('withdraw-ifsc').value.trim().toUpperCase();

                if (!name || !num || !ifsc) {
                    Toast.show('All bank account details are required', 'error');
                    SwipeSlider.reset();
                    return;
                }
                bankDetails = { accountName: name, accountNumber: num, ifscCode: ifsc };
            }

            try {
                await apiRequest('/api/withdrawals/create', {
                    method: 'POST',
                    body: {
                        amount: Math.round(amount * 100),
                        bankDetails
                    }
                });

                Toast.show('Withdrawal request submitted successfully!');
                document.getElementById('withdraw-amount-input').value = '';
                SwipeSlider.reset();
                switchTab('overview');
            } catch (err) {
                Toast.show(err.message, 'error');
                SwipeSlider.reset();
            }
        });

        // 5. My Investments & Daily Ad
        let activeInvestmentId = null;
        let activeAdClaimToken = null;
        let adCountdownTimer = null;
        
        async function fetchInvestments() {
            try {
                const data = await apiRequest('/api/products/my-investments');
                const list = document.getElementById('investments-list');
                list.innerHTML = '';

                if (data.investments.length === 0) {
                    list.innerHTML = '<div class="glass-card" style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">You do not have any active investment contracts. Buy yield assets from the Marketplace to get started.</div>';
                }

                data.investments.forEach(inv => {
                    // Compute days remaining
                    const exp = new Date(inv.expiresAt);
                    const now = new Date();
                    const daysRemaining = Math.max(0, Math.ceil((exp.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)));
                    
                    // Daily percentage reward
                    const dailyReward = Math.floor((inv.purchasePrice * parseFloat(inv.dailyRewardPercent)) / 100);

                    const card = document.createElement('div');
                    card.className = 'glass-card';
                    card.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h3 style="font-weight: 700;">${inv.name}</h3>
                            <span class="badge-roi" style="background: var(--primary-glow); color: var(--primary);">${inv.dailyRewardPercent}% ROI</span>
                        </div>
                        <img src="${inv.imageUrl}" alt="${inv.name}" style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px;">
                        
                        <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 6px;">Daily Yield: <strong style="color: var(--foreground);">${formatRupees(dailyReward)}</strong></p>
                        <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 12px;">Contract Expiry: <strong>${daysRemaining} Days remaining</strong></p>
                        
                        <div style="display: flex; gap: 12px;">
                            ${inv.watchedToday 
                                ? `<button class="btn-secondary" style="flex: 1; border-color: var(--success); color: var(--success); cursor: default;" disabled>✓ Completed</button>`
                                : `<button class="btn-primary" onclick="watchAd('${inv.id}', ${dailyReward}, ${inv.adWatchSeconds})" style="flex: 1;">Watch Ad</button>`
                            }
                            <button class="btn-destructive" onclick="exitInvestmentEarly('${inv.id}', '${inv.name}', ${inv.purchasePrice})" style="padding: 10px 14px;">Exit</button>
                        </div>
                    `;
                    list.appendChild(card);
                });

                // Load investment earnings logs
                const histData = await apiRequest('/api/products/investment-history');
                const logContainer = document.getElementById('investment-history-list');
                logContainer.innerHTML = '';

                // Combine buys and rewards
                const combinedLogs = [];
                histData.buys.forEach(b => combinedLogs.push({ ...b, type: 'buy' }));
                histData.rewards.forEach(r => combinedLogs.push({ ...r, type: 'reward' }));
                histData.sells.forEach(s => combinedLogs.push({ ...s, type: 'sell' }));

                combinedLogs.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt));

                if (combinedLogs.length === 0) {
                    logContainer.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No logs found.</div>';
                }

                combinedLogs.slice(0, 10).forEach(log => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                    const isCredit = log.type !== 'buy';
                    const color = isCredit ? 'var(--success)' : 'var(--destructive)';
                    const prefix = isCredit ? '+' : '-';
                    const label = log.type === 'buy' ? `Purchased ${log.name}` : (log.type === 'sell' ? `Early Refund ${log.name}` : `Daily Ad Reward: ${log.name}`);
                    const date = new Date(log.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });

                    row.innerHTML = `
                        <div>
                            <span style="font-weight: 700; font-size: 0.85rem;">${label}</span>
                            <span style="font-size: 0.7rem; color: var(--muted); display: block;">${date}</span>
                        </div>
                        <span style="font-weight: 800; color: ${color};">${prefix}${formatRupees(log.amount)}</span>
                    `;
                    logContainer.appendChild(row);
                });
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        async function exitInvestmentEarly(id, name, price) {
            const confirmExit = confirm(`Are you sure you want to terminate "${name}" contract early? Your initial purchase principal of ${formatRupees(price)} will be immediately refunded to your wallet balance.`);
            if (!confirmExit) return;

            try {
                await apiRequest(`/api/products/sell/${id}`, { method: 'POST' });
                Toast.show('Contract exited. Balance refunded.');
                fetchInvestments();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        async function watchAd(investmentId, rewardAmount, seconds) {
            try {
                const data = await apiRequest(`/api/products/ad-url/${investmentId}`);
                activeInvestmentId = investmentId;
                activeAdClaimToken = data.claimToken;
                
                const theater = document.getElementById('ad-theater');
                const videoEl = document.getElementById('ad-video-element');
                const iframeEl = document.getElementById('ad-iframe-element');
                const claimBox = document.getElementById('ad-claim-box');

                videoEl.style.display = 'none';
                iframeEl.style.display = 'none';
                claimBox.style.display = 'none';
                theater.style.display = 'flex';

                // Check link type
                const url = data.videoUrl;
                if (url.includes('youtube.com') || url.includes('youtu.be') || url.includes('vimeo.com')) {
                    // Handle iframe embeds
                    let embedUrl = url;
                    if (url.includes('watch?v=')) {
                        embedUrl = url.replace('watch?v=', 'embed/');
                    }
                    iframeEl.src = embedUrl;
                    iframeEl.style.display = 'block';
                } else {
                    // Handle raw video files
                    videoEl.src = url;
                    videoEl.style.display = 'block';
                    videoEl.play().catch(() => {});
                }

                // Start countdown timer
                let timeLeft = seconds;
                document.getElementById('ad-time-left').innerText = timeLeft;

                clearInterval(adCountdownTimer);
                adCountdownTimer = setInterval(() => {
                    timeLeft--;
                    document.getElementById('ad-time-left').innerText = timeLeft;
                    
                    if (timeLeft <= 0) {
                        clearInterval(adCountdownTimer);
                        claimBox.style.display = 'block';
                        Toast.show('Daily ad watched completely! Click Claim Reward.', 'success');
                    }
                }, 1000);

            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        function closeAdPlayer() {
            clearInterval(adCountdownTimer);
            document.getElementById('ad-theater').style.display = 'none';
            document.getElementById('ad-video-element').src = '';
            document.getElementById('ad-iframe-element').src = '';
            activeInvestmentId = null;
            activeAdClaimToken = null;
        }

        // Confetti script helper
        function startConfettiAnimation() {
            const canvas = document.getElementById('confetti-canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            canvas.style.display = 'block';

            const particles = [];
            for (let i = 0; i < 150; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height - canvas.height,
                    size: Math.random() * 8 + 4,
                    color: `hsl(${Math.random() * 360}, 80%, 60%)`,
                    speedX: Math.random() * 4 - 2,
                    speedY: Math.random() * 5 + 3,
                    rotation: Math.random() * 360
                });
            }

            function update() {
                ctx.clearRect(0,0, canvas.width, canvas.height);
                let active = false;
                particles.forEach(p => {
                    p.y += p.speedY;
                    p.x += p.speedX;
                    p.rotation += 2;
                    if (p.y < canvas.height) {
                        active = true;
                        ctx.fillStyle = p.color;
                        ctx.save();
                        ctx.translate(p.x, p.y);
                        ctx.rotate(p.rotation * Math.PI / 180);
                        ctx.fillRect(-p.size/2, -p.size/2, p.size, p.size);
                        ctx.restore();
                    }
                });

                if (active) {
                    requestAnimationFrame(update);
                } else {
                    canvas.style.display = 'none';
                }
            }
            update();
        }

        async function claimDailyReward() {
            if (!activeInvestmentId || !activeAdClaimToken) return;

            try {
                await apiRequest(`/api/products/watch/${activeInvestmentId}`, {
                    method: 'POST',
                    body: { claimToken: activeAdClaimToken }
                });
                closeAdPlayer();
                startConfettiAnimation();
                Toast.show('Reward credited to wallet!', 'success');
                fetchInvestments();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 6. Referrals
        async function fetchReferrals() {
            try {
                const codeData = await apiRequest('/api/referrals/my-code');
                document.getElementById('my-referral-code').innerText = codeData.referralCode;

                const stats = await apiRequest('/api/referrals/stats');
                document.getElementById('referral-roadmap-text').innerText = `${stats.confirmedReferrals} / ${stats.requiredReferrals} Referrals`;
                
                const percent = stats.requiredReferrals > 0 ? Math.min(100, (stats.confirmedReferrals / stats.requiredReferrals) * 100) : 100;
                document.getElementById('referral-roadmap-bar').style.width = `${percent}%`;

                // Unclaimed commissions
                const bonusStats = await apiRequest('/api/referral-bonus/stats');
                document.getElementById('claimable-amount').innerText = formatRupees(bonusStats.unclaimedAmount);
                document.getElementById('claim-all-btn').disabled = (bonusStats.unclaimedAmount === 0);

                // Load list of referred members
                const listData = await apiRequest('/api/referrals/my-referrals');
                const membersDiv = document.getElementById('referred-members');
                membersDiv.innerHTML = '';

                if (listData.referrals.length === 0) {
                    membersDiv.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No members referred.</div>';
                }

                listData.referrals.forEach(m => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                    const statusColor = m.isConfirmed ? 'var(--success)' : 'var(--muted)';
                    const statusText = m.isConfirmed ? '✓ Active Investor' : '⌛ Pending Deposit';

                    row.innerHTML = `
                        <div>
                            <span style="font-weight: 700; font-size: 0.85rem;">${m.name || 'Anonymous User'}</span>
                            <span style="font-size: 0.7rem; color: var(--muted); display: block;">${m.email}</span>
                        </div>
                        <span style="font-size: 0.8rem; font-weight: 700; color: ${statusColor};">${statusText}</span>
                    `;
                    membersDiv.appendChild(row);
                });

                // Load commissions list
                const unclaimedData = await apiRequest('/api/referral-bonus/unclaimed');
                const unclaimedList = document.getElementById('unclaimed-bonuses-list');
                unclaimedList.innerHTML = '';

                if (unclaimedData.length === 0) {
                    unclaimedList.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No commissions pending.</div>';
                }

                unclaimedData.forEach(bonus => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border);';
                    row.innerHTML = `
                        <div>
                            <span style="font-weight: 700; font-size: 0.85rem;">Bonus from ${bonus.referredUserName}</span>
                            <span style="font-size: 0.7rem; color: var(--muted); display: block;">Deposit amount: ${formatRupees(bonus.depositAmount)}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-weight: 850; color: var(--success);">${formatRupees(bonus.bonusAmount)}</span>
                            <button class="btn-primary" onclick="claimBonus('${bonus.id}')" style="padding: 6px 12px; font-size: 0.75rem;">Claim</button>
                        </div>
                    `;
                    unclaimedList.appendChild(row);
                });

                // Load claims requests history
                const claimHistory = await apiRequest('/api/referral-bonus/claim-history');
                const claimsContainer = document.getElementById('claim-history-list');
                claimsContainer.innerHTML = '';

                if (claimHistory.claims.length === 0) {
                    claimsContainer.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No claims logged.</div>';
                }

                claimHistory.claims.forEach(c => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                    const statusColor = c.status === 'approved' ? 'var(--success)' : (c.status === 'rejected' ? 'var(--destructive)' : 'var(--muted)');

                    row.innerHTML = `
                        <div>
                            <span style="font-weight: 700; font-size: 0.85rem;">Claim: ${formatRupees(c.amount)}</span>
                            <span style="font-size: 0.7rem; color: var(--muted); display: block;">For referee: ${c.referredUserName} | Status: <strong style="color: ${statusColor};">${c.status}</strong></span>
                        </div>
                        <span style="font-size: 0.8rem; color: var(--muted);">${new Date(c.createdAt).toLocaleDateString('en-IN')}</span>
                    `;
                    claimsContainer.appendChild(row);
                });

            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        function copyReferralCode() {
            const code = document.getElementById('my-referral-code').innerText;
            navigator.clipboard.writeText(code);
            Toast.show('Referral code copied to clipboard!');
        }

        async function claimBonus(bonusId) {
            try {
                await apiRequest(`/api/referral-bonus/claim/${bonusId}`, { method: 'POST' });
                Toast.show('Claim submitted! Awaiting admin approval.');
                fetchReferrals();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        async function claimAllBonuses() {
            try {
                const data = await apiRequest('/api/referral-bonus/unclaimed');
                let count = 0;
                for (const b of data) {
                    await apiRequest(`/api/referral-bonus/claim/${b.id}`, { method: 'POST' });
                    count++;
                }
                if (count > 0) {
                    Toast.show(`Submitted ${count} claim requests!`);
                    fetchReferrals();
                }
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 7. Transaction History
        let historyPage = 1;
        async function fetchHistory() {
            const filter = document.getElementById('history-filter').value;
            const start = document.getElementById('history-start').value;
            const end = document.getElementById('history-end').value;

            let path = `/api/wallet/transactions?page=${historyPage}&limit=10`;
            if (filter === 'deposit' || filter === 'withdrawal') {
                path += `&type=${filter}`;
            }

            try {
                const data = await apiRequest(path);
                let list = data.transactions || [];

                // Load product buys/sells/rewards on client side filters (compatible with Next.js architecture)
                if (['all', 'buy', 'sell', 'reward'].includes(filter)) {
                    const inv = await apiRequest('/api/products/investment-history');
                    const buys = inv.buys.map(x => ({ ...x, type: 'buy', status: 'approved' }));
                    const sells = inv.sells.map(x => ({ ...x, type: 'sell', status: 'completed' }));
                    const rewards = inv.rewards.map(x => ({ ...x, type: 'reward', status: 'completed' }));

                    let merged = [...list];
                    if (filter === 'all') {
                        merged = [...merged, ...buys, ...sells, ...rewards];
                    } else if (filter === 'buy') {
                        merged = buys;
                    } else if (filter === 'sell') {
                        merged = sells;
                    } else if (filter === 'reward') {
                        merged = rewards;
                    }

                    // Filter by dates
                    if (start) {
                        const sDate = new Date(start);
                        sDate.setHours(0,0,0,0);
                        merged = merged.filter(x => new Date(x.createdAt) >= sDate);
                    }
                    if (end) {
                        const eDate = new Date(end);
                        eDate.setHours(23,59,59,999);
                        merged = merged.filter(x => new Date(x.createdAt) <= eDate);
                    }

                    // Sort
                    merged.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt));

                    const limit = 10;
                    const totalPages = Math.ceil(merged.length / limit) || 1;
                    document.getElementById('history-page-indicator').innerText = `Page ${historyPage} of ${totalPages}`;
                    document.getElementById('history-prev-btn').disabled = (historyPage === 1);
                    document.getElementById('history-next-btn').disabled = (historyPage === totalPages);

                    renderHistoryList(merged.slice((historyPage - 1) * limit, historyPage * limit));
                } else {
                    // Date filters on deposits/withdrawals
                    if (start) {
                        const sDate = new Date(start);
                        sDate.setHours(0,0,0,0);
                        list = list.filter(x => new Date(x.createdAt) >= sDate);
                    }
                    if (end) {
                        const eDate = new Date(end);
                        eDate.setHours(23,59,59,999);
                        list = list.filter(x => new Date(x.createdAt) <= eDate);
                    }

                    document.getElementById('history-page-indicator').innerText = `Page ${historyPage} of ${data.totalPages || 1}`;
                    document.getElementById('history-prev-btn').disabled = (historyPage === 1);
                    document.getElementById('history-next-btn').disabled = (historyPage === (data.totalPages || 1));
                    
                    renderHistoryList(list);
                }
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        function renderHistoryList(arr) {
            const container = document.getElementById('history-ledger');
            container.innerHTML = '';

            if (arr.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 32px 0;">No matching transactions found.</div>';
                return;
            }

            arr.forEach(t => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 14px; border-bottom: 1px solid var(--border);';
                
                const isCredit = ['deposit', 'reward', 'sell'].includes(t.type);
                const color = isCredit ? 'var(--success)' : 'var(--destructive)';
                const prefix = isCredit ? '+' : '-';
                const date = new Date(t.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                
                let label = t.type;
                if (t.name) {
                    label = t.type === 'buy' ? `Purchase ${t.name}` : (t.type === 'sell' ? `Early Refund ${t.name}` : `Reward: ${t.name}`);
                }

                row.innerHTML = `
                    <div>
                        <span style="font-weight: 700; font-size: 0.95rem; text-transform: capitalize;">${label}</span>
                        <span style="font-size: 0.75rem; color: var(--muted); display: block;">${date} | Status: <strong style="text-transform: capitalize;">${t.status}</strong></span>
                        ${t.rejectionReason ? `<span style="font-size: 0.7rem; color: var(--destructive); display: block;">Reason: ${t.rejectionReason}</span>` : ''}
                    </div>
                    <span style="font-weight: 850; font-size: 1.1rem; color: ${color};">${prefix}${formatRupees(t.amount)}</span>
                `;
                container.appendChild(row);
            });
        }

        function prevHistoryPage() {
            if (historyPage > 1) {
                historyPage--;
                fetchHistory();
            }
        }

        function nextHistoryPage() {
            historyPage++;
            fetchHistory();
        }

        // 8. Settings Subtabs & Forms
        function switchSettingsSubTab(subId) {
            document.getElementById('settings-profile-panel').style.display = subId === 'profile' ? 'block' : 'none';
            document.getElementById('settings-payment-panel').style.display = subId === 'payment' ? 'block' : 'none';
            document.getElementById('settings-password-panel').style.display = subId === 'password' ? 'block' : 'none';

            document.getElementById('subtab-profile-btn').className = `nav-link ${subId === 'profile' ? 'active' : ''}`;
            document.getElementById('subtab-payment-btn').className = `nav-link ${subId === 'payment' ? 'active' : ''}`;
            document.getElementById('subtab-password-btn').className = `nav-link ${subId === 'password' ? 'active' : ''}`;
        }

        async function fetchSettings() {
            try {
                // Profile
                const profile = await apiRequest('/api/auth/me');
                document.getElementById('prof-name').value = profile.name || '';
                document.getElementById('prof-mobile').value = profile.mobileNumber || '';
                document.getElementById('prof-dob').value = profile.dateOfBirth ? profile.dateOfBirth.split('T')[0] : '';
                document.getElementById('prof-aadhar').value = profile.aadharNumber || '';
                document.getElementById('prof-pan').value = profile.panNumber || '';

                // Payments Details
                const payment = await apiRequest('/api/auth/payment-details');
                document.getElementById('set-pref-method').value = payment.preferredPaymentMethod || 'upi';
                document.getElementById('set-upi').value = payment.savedUpiId || '';
                
                if (payment.savedBankDetails) {
                    document.getElementById('set-acc-name').value = payment.savedBankDetails.accountName || '';
                    document.getElementById('set-acc-num').value = payment.savedBankDetails.accountNumber || '';
                    document.getElementById('set-ifsc').value = payment.savedBankDetails.ifscCode || '';
                }
            } catch (err) {}
        }

        document.getElementById('profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await apiRequest('/api/auth/profile', {
                    method: 'PUT',
                    body: {
                        name: document.getElementById('prof-name').value,
                        mobileNumber: document.getElementById('prof-mobile').value,
                        dateOfBirth: document.getElementById('prof-dob').value || null,
                        aadharNumber: document.getElementById('prof-aadhar').value,
                        panNumber: document.getElementById('prof-pan').value
                    }
                });
                Toast.show('Profile updated successfully!');
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        });

        document.getElementById('payment-details-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await apiRequest('/api/auth/payment-details', {
                    method: 'PUT',
                    body: {
                        preferredPaymentMethod: document.getElementById('set-pref-method').value,
                        upiId: document.getElementById('set-upi').value,
                        bankDetails: {
                            accountName: document.getElementById('set-acc-name').value,
                            accountNumber: document.getElementById('set-acc-num').value,
                            ifscCode: document.getElementById('set-ifsc').value
                        }
                    }
                });
                Toast.show('Payment methods updated successfully!');
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        });

        document.getElementById('password-change-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const curr = document.getElementById('currentPassword').value;
            const newVal = document.getElementById('newPassword').value;
            const conf = document.getElementById('confirmNewPassword').value;

            if (newVal !== conf) {
                Toast.show('New passwords do not match', 'error');
                return;
            }

            try {
                await apiRequest('/api/auth/profile', {
                    method: 'PUT',
                    body: {
                        currentPassword: curr,
                        newPassword: newVal
                    }
                });
                Toast.show('Password changed successfully!');
                document.getElementById('password-change-form').reset();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        });

        // 9. Logout
        document.getElementById('logout-btn').addEventListener('click', async () => {
            const btn = document.getElementById('logout-btn');
            btn.disabled = true;
            btn.innerText = 'Logging out...';
            // Clear local state immediately so a stuck session can't trap the user
            try { localStorage.removeItem('theme'); } catch(e) {}
            // Fire-and-forget logout with a hard redirect fallback so the button
            // never appears "dead" even if the network/API is slow or errors.
            try {
                await Promise.race([
                    apiRequest('/api/auth/logout', { method: 'POST' }),
                    new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 4000))
                ]);
            } catch(e) {}
            window.location.href = '/login';
        });

        // Initial setup
        fetchMarketplace();
    </script>
</body>
</html>
