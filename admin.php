<?php
// admin.php - Admin Command Center View
require_once __DIR__ . '/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdminPay - Admin Control Center</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .split-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-top: 24px;
        }
        @media (max-width: 1024px) {
            .split-layout {
                grid-template-columns: 1fr;
            }
        }
        .pane-list-container {
            max-height: 60vh;
            overflow-y: auto;
        }
        .pane-item {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
        }
        .pane-item:hover {
            background: rgba(0,0,0,0.02);
        }
        .dark .pane-item:hover {
            background: rgba(255,255,255,0.02);
        }
        .pane-item.active {
            background: var(--primary-glow);
            border-color: var(--primary);
        }
        .drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.5);
            backdrop-filter: blur(4px);
            z-index: 150;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .drawer-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .side-drawer {
            position: fixed;
            top: 0;
            right: -450px;
            width: 90%;
            max-width: 450px;
            height: 100vh;
            background: var(--background);
            border-left: 1px solid var(--border);
            box-shadow: -12px 0 32px rgba(0,0,0,0.15);
            z-index: 160;
            padding: 36px;
            overflow-y: auto;
            transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .drawer-overlay.active .side-drawer {
            right: 0;
        }
        .ad-links-grid {
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 12px;
            align-items: center;
            max-height: 40vh;
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 8px;
        }
        .ad-links-grid input {
            width: 100%;
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
        .glass-card.pulse {
            animation: borderPulse 2.5s infinite;
        }
        @keyframes borderPulse {
            0% { border-color: var(--destructive); box-shadow: 0 0 0 0 var(--destructive-glow); }
            50% { border-color: rgba(239, 68, 68, 0.5); box-shadow: 0 0 16px 4px var(--destructive-glow); }
            100% { border-color: var(--destructive); box-shadow: 0 0 0 0 var(--destructive-glow); }
        }
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.2rem;">A</div>
                <h2 style="font-weight: 800; font-size: 1.4rem;">Admin<span class="text-gradient">Pay</span></h2>
            </div>

            <div class="glass-card" style="padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; border-radius: var(--radius-sm);">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">A</div>
                <div>
                    <div style="font-size: 0.85rem; font-weight: 700;"><?php echo htmlspecialchars($user['name'] ?: 'Admin'); ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted);">System Administrator</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <button class="nav-link active" data-tab="statistics">📊 Statistics</button>
                <button class="nav-link" data-tab="deposits">📥 Manage Deposits</button>
                <button class="nav-link" data-tab="withdrawals">📤 Manage Withdrawals</button>
                <button class="nav-link" data-tab="claims">👥 Bonus Claims</button>
                <button class="nav-link" data-tab="users">🧑 User Accounts</button>
                <button class="nav-link" data-tab="products">🖼️ Yield Assets</button>
                <button class="nav-link" data-tab="settings">⚙️ System Settings</button>
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
                <h3 id="mobile-title" style="font-weight: 700;">Statistics</h3>
            </div>
            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">A</div>
        </header>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- 1. STATISTICS TAB -->
            <section id="tab-statistics" class="tab-view active">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">System workload</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Lifetime statistics and pending transaction queues</p>

                <div class="bento-grid">
                    <div class="glass-card" id="card-pending-deposits">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Pending Deposits</div>
                        <h2 style="font-size: 2.2rem; font-weight: 800;" id="stats-pending-deposits">0</h2>
                        <button class="btn-secondary" onclick="switchTab('deposits')" style="margin-top: 12px; padding: 6px 12px; font-size: 0.75rem;">Manage Queue</button>
                    </div>
                    <div class="glass-card" id="card-pending-withdrawals">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Pending Withdrawals</div>
                        <h2 style="font-size: 2.2rem; font-weight: 800;" id="stats-pending-withdrawals">0</h2>
                        <button class="btn-secondary" onclick="switchTab('withdrawals')" style="margin-top: 12px; padding: 6px 12px; font-size: 0.75rem;">Manage Queue</button>
                    </div>
                    <div class="glass-card" id="card-pending-claims">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Pending Bonus Claims</div>
                        <h2 style="font-size: 2.2rem; font-weight: 800;" id="stats-pending-claims">0</h2>
                        <button class="btn-secondary" onclick="switchTab('claims')" style="margin-top: 12px; padding: 6px 12px; font-size: 0.75rem;">Manage Queue</button>
                    </div>
                </div>

                <div class="bento-grid" style="margin-top: 32px;">
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Total Approved Deposits</div>
                        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--success);" id="stats-approved-deposits">₹0.00</h2>
                    </div>
                    <div class="glass-card">
                        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Total Completed Withdrawals</div>
                        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--destructive);" id="stats-completed-withdrawals">₹0.00</h2>
                    </div>
                </div>
            </section>

            <!-- 2. DEPOSITS TAB -->
            <section id="tab-deposits" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Manage Deposits</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Approve or reject pending user deposit screenshots</p>

                <div class="split-layout">
                    <!-- Left: Pending list -->
                    <div class="glass-panel" style="padding: 24px;">
                        <h3 style="font-weight: 700; margin-bottom: 16px;">Request Queue</h3>
                        <div class="pane-list-container" id="dep-queue-list">
                            <!-- Pending deposits loaded dynamically -->
                        </div>
                    </div>

                    <!-- Right: Pane details -->
                    <div class="glass-panel pane-details" style="padding: 24px;" id="dep-details-pane">
                        <div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a deposit request to inspect proof and process</div>
                    </div>
                </div>
            </section>

            <!-- 3. WITHDRAWALS TAB -->
            <section id="tab-withdrawals" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Manage Withdrawals</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Confirm payment transfers or reject cashout requests</p>

                <div class="split-layout">
                    <!-- Left: Pending List -->
                    <div class="glass-panel" style="padding: 24px;">
                        <h3 style="font-weight: 700; margin-bottom: 16px;">Request Queue</h3>
                        <div class="pane-list-container" id="with-queue-list">
                            <!-- Pending withdrawals list -->
                        </div>
                    </div>

                    <!-- Right: Pane details -->
                    <div class="glass-panel pane-details" style="padding: 24px;" id="with-details-pane">
                        <div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a withdrawal request to view bank details and process</div>
                    </div>
                </div>
            </section>

            <!-- 4. BONUS CLAIMS TAB -->
            <section id="tab-claims" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Referral Claims</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Review and payout referral commissions milestone requests</p>

                <div class="split-layout">
                    <!-- Left: List -->
                    <div class="glass-panel" style="padding: 24px;">
                        <h3 style="font-weight: 700; margin-bottom: 16px;">Pending Claims</h3>
                        <div class="pane-list-container" id="claims-queue-list">
                            <!-- Pending claims list -->
                        </div>
                    </div>

                    <!-- Right: Details -->
                    <div class="glass-panel pane-details" style="padding: 24px;" id="claims-details-pane">
                        <div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a claim to review user roadmap milestone metrics and process</div>
                    </div>
                </div>
            </section>

            <!-- 5. USERS TAB -->
            <section id="tab-users" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">User Accounts</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Search and audit active user ledgers or suspend/reactivate profiles</p>

                <div class="glass-panel" style="padding: 24px; margin-bottom: 24px; display: flex; gap: 16px; align-items: center;">
                    <input class="form-input" type="text" id="user-search" placeholder="Search by name, email, or mobile..." style="max-width: 360px;">
                    <button class="btn-primary" onclick="fetchUsers()">Search</button>
                </div>

                <div class="glass-panel" style="padding: 24px; overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
                                <th style="padding: 12px;">User</th>
                                <th style="padding: 12px;">Mobile</th>
                                <th style="padding: 12px;">Wallet Balance</th>
                                <th style="padding: 12px;">Status</th>
                                <th style="padding: 12px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <!-- User accounts rows -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 6. YIELD ASSETS TAB -->
            <section id="tab-products" class="tab-view">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <div>
                        <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">Yield Assets</h1>
                        <p style="color: var(--muted); font-size: 0.9rem;">Create and schedule video ad links for investment products</p>
                    </div>
                    <button class="btn-primary" onclick="openProductModal()">➕ Create Product</button>
                </div>

                <div class="marketplace-grid" id="products-list">
                    <!-- Admin products list -->
                </div>
            </section>

            <!-- 7. SYSTEM SETTINGS TAB -->
            <section id="tab-settings" class="tab-view">
                <h1 style="font-weight: 800; font-size: 1.8rem; margin-bottom: 6px;">System Configuration</h1>
                <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 24px;">Configure receiving gateway codes, primary numbers and payout limits</p>

                <div class="bento-grid">
                    <!-- Primary QR Code -->
                    <form id="settings-qr-form" class="glass-card" style="display: flex; flex-direction: column; gap: 16px;">
                        <h3 style="font-weight: 700;">Receiving QR Code</h3>
                        <img id="settings-qr-preview" src="" alt="System QR" style="width: 150px; height: 150px; object-fit: contain; align-self: center; border-radius: var(--radius-sm); border: 1px solid var(--border); display: none;">
                        <div class="form-group">
                            <label class="form-label">Upload New QR Code Image</label>
                            <input class="form-input" type="file" name="qrCode" required accept="image/*">
                        </div>
                        <button type="submit" class="btn-primary" style="margin-top: auto;">Upload QR Code</button>
                    </form>

                    <!-- Core Config Form -->
                    <form id="settings-config-form" class="glass-card" style="display: flex; flex-direction: column; gap: 16px;">
                        <h3 style="font-weight: 700;">Global Settings</h3>
                        <div class="form-group">
                            <label class="form-label">Primary UPI ID</label>
                            <input class="form-input" type="text" id="settings-upi-id" name="upiId" placeholder="merchant@bank">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Backup Global Video Ad URL</label>
                            <input class="form-input" type="url" id="settings-global-ad" name="videoUrl" placeholder="https://example.com/ad.mp4">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Required Referrals Count (Withdrawals)</label>
                            <input class="form-input" type="number" id="settings-req-referrals" name="requiredReferrals" min="0" placeholder="5">
                        </div>
                        <button type="submit" class="btn-primary" style="margin-top: auto;">Save Configuration</button>
                    </form>

                    <!-- Payment Tiers Settings -->
                    <div class="glass-card" style="grid-column: 1/-1;">
                        <h3 style="font-weight: 700; margin-bottom: 16px;">Active Deposit Tiers (Gold, Diamond, Platinum)</h3>
                        <div style="display: flex; flex-direction: column; gap: 16px;" id="payment-methods-list">
                            <!-- Payment Tiers editor dynamically loaded -->
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- User Transactions Audit Side Drawer -->
    <div class="drawer-overlay" id="audit-drawer-overlay">
        <div class="side-drawer" id="audit-drawer">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-weight: 800;">Member Transactions</h3>
                <button onclick="closeAuditDrawer()" style="background: none; border: none; font-size: 1.4rem; color: var(--foreground); cursor: pointer;">✕</button>
            </div>
            <div id="audit-user-details" style="margin-bottom: 24px;">
                <!-- User account audit information -->
            </div>
            <div id="audit-transactions-list" style="display: flex; flex-direction: column; gap: 12px;">
                <!-- Audit transactions dynamic rows -->
            </div>
        </div>
    </div>

    <!-- Create/Edit Product Modal -->
    <div class="modal-overlay" id="product-modal">
        <div class="modal-content" style="max-width: 520px;">
            <h3 style="font-weight: 800; margin-bottom: 20px;" id="product-modal-title">Create Yield Product</h3>
            <form id="product-form" enctype="multipart/form-data">
                <input type="hidden" id="edit-product-id">
                
                <div class="form-group">
                    <label class="form-label">Asset Name</label>
                    <input class="form-input" type="text" id="prod-name" name="name" required placeholder="Aero Yield Gold">
                </div>

                <div class="form-group">
                    <label class="form-label">Purchase Price (in Rupees)</label>
                    <input class="form-input" type="text" id="prod-price" name="price" required placeholder="e.g. 5000">
                </div>

                <div class="form-group">
                    <label class="form-label">Contract Duration (Days)</label>
                    <input class="form-input" type="number" id="prod-duration" name="durationDays" required min="1" placeholder="30">
                </div>

                <div class="form-group">
                    <label class="form-label">Daily Reward Percentage ROI (%)</label>
                    <input class="form-input" type="text" id="prod-reward" name="dailyRewardPercent" required placeholder="e.g. 2.50">
                </div>

                <div class="form-group">
                    <label class="form-label">Daily Video Ad Watch Time (Seconds)</label>
                    <input class="form-input" type="number" id="prod-seconds" name="adWatchSeconds" required min="10" max="3600" value="120">
                </div>

                <div class="form-group" id="prod-file-group">
                    <label class="form-label">Asset Photo/Banner Image</label>
                    <input class="form-input" type="file" id="prod-image" name="productImage" accept="image/*">
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn-secondary" onclick="closeProductModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn-primary" style="flex: 1;" id="prod-submit-btn">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ad Scheduler Modal -->
    <div class="modal-overlay" id="ad-scheduler-modal">
        <div class="modal-content" style="max-width: 600px;">
            <h3 style="font-weight: 800; margin-bottom: 16px;">Configure Day-by-Day Ad Schedules</h3>
            <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 20px;">Provide custom video links (.mp4 or Youtube) for each day of the contract. If empty, the backup global Daily Ad will be shown.</p>
            
            <div class="ad-links-grid" id="ad-scheduler-grid">
                <!-- Inputs generated dynamically based on duration -->
            </div>

            <div style="display: flex; gap: 12px; margin-top: 16px;">
                <button type="button" class="btn-secondary" onclick="closeAdScheduler()" style="flex: 1;">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveAdSchedule()" style="flex: 1;">Save Schedules</button>
            </div>
        </div>
    </div>

    <script src="/app.js"></script>
    <script>
        // Tab routing logic
        const tabs = document.querySelectorAll('.nav-link[data-tab]');
        const tabViews = document.querySelectorAll('.tab-view');
        const mobileTitle = document.getElementById('mobile-title');

        function switchTab(tabId) {
            tabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-tab') === tabId));
            tabViews.forEach(v => v.classList.toggle('active', v.id === `tab-${tabId}`));
            
            if (mobileTitle) {
                const activeTabBtn = document.querySelector(`.nav-link[data-tab="${tabId}"]`);
                if (activeTabBtn) mobileTitle.innerText = activeTabBtn.innerText.substring(3);
            }

            // Route fetchers
            if (tabId === 'statistics') {
                fetchStatistics();
            } else if (tabId === 'deposits') {
                fetchDeposits();
            } else if (tabId === 'withdrawals') {
                fetchWithdrawals();
            } else if (tabId === 'claims') {
                fetchClaims();
            } else if (tabId === 'users') {
                fetchUsers();
            } else if (tabId === 'products') {
                fetchProducts();
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

        // Mobile sidebar trigger
        const menuTrigger = document.getElementById('mobile-menu-trigger');
        const sidebar = document.querySelector('.sidebar');
        if (menuTrigger) {
            menuTrigger.addEventListener('click', () => {
                const isOpen = sidebar.style.display === 'flex';
                sidebar.style.display = isOpen ? 'none' : 'flex';
            });
        }

        // Formatters
        function formatRupees(paise) {
            return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // 1. Statistics
        async function fetchStatistics() {
            try {
                const data = await apiRequest('/api/admin/statistics');
                
                document.getElementById('stats-pending-deposits').innerText = data.pendingDepositsCount;
                document.getElementById('stats-pending-withdrawals').innerText = data.pendingWithdrawalsCount;
                document.getElementById('stats-pending-claims').innerText = data.pendingClaimsCount;
                
                document.getElementById('stats-approved-deposits').innerText = formatRupees(data.totalApprovedDeposits);
                document.getElementById('stats-completed-withdrawals').innerText = formatRupees(data.totalCompletedWithdrawals);

                // Pulse effects
                document.getElementById('card-pending-deposits').classList.toggle('pulse', data.pendingDepositsCount > 0);
                document.getElementById('card-pending-withdrawals').classList.toggle('pulse', data.pendingWithdrawalsCount > 0);
                document.getElementById('card-pending-claims').classList.toggle('pulse', data.pendingClaimsCount > 0);

            } catch (err) {
                Toast.show('Failed to fetch statistics', 'error');
            }
        }

        // 2. Deposits Split Pane
        let selectedDepositId = null;
        async function fetchDeposits() {
            try {
                const data = await apiRequest('/api/admin/pending-deposits?page=1&limit=20');
                const container = document.getElementById('dep-queue-list');
                container.innerHTML = '';
                document.getElementById('dep-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a deposit request to inspect proof and process</div>';

                if (data.deposits.length === 0) {
                    container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;">No pending deposits found.</div>';
                    return;
                }

                let foundSelected = false;
                data.deposits.forEach(d => {
                    const row = document.createElement('div');
                    row.className = 'pane-item';
                    if (selectedDepositId === d.id) {
                        row.classList.add('active');
                        foundSelected = true;
                    }
                    row.innerHTML = `
                        <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 8px;">${formatRupees(d.amount)}</div>
                        <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 4px;">User: ${d.userName}</div>
                        <div style="font-size: 0.75rem; color: var(--muted);">${new Date(d.createdAt).toLocaleString('en-IN')}</div>
                    `;
                    row.addEventListener('click', () => {
                        document.querySelectorAll('#dep-queue-list .pane-item').forEach(x => x.classList.remove('active'));
                        row.classList.add('active');
                        selectedDepositId = d.id;
                        renderDepositDetails(d);
                    });
                    container.appendChild(row);
                });
                if (!foundSelected) {
                    selectedDepositId = null;
                    document.getElementById('dep-details-pane').innerHTML = '<div style="color:var(--muted); text-align:center; padding: 40px;">Select a request to review</div>';
                }
            } catch (err) {
                console.error('fetchDeposits failed:', err);
                Toast.show(err.message || 'Failed to load deposits', 'error');
            }
        }

        function renderDepositDetails(d) {
            const pane = document.getElementById('dep-details-pane');
            pane.innerHTML = `
                <h3 style="font-weight: 700; margin-bottom: 20px;">Request Audit</h3>
                <div style="font-size: 0.9rem; margin-bottom: 16px; line-height: 1.6;">
                    <div>User: <strong>${d.userName}</strong> (${d.userEmail})</div>
                    <div>Amount: <strong>${formatRupees(d.amount)}</strong></div>
                    <div>Tx ID: <strong>${d.transactionId || 'Not provided'}</strong></div>
                    <div>Submitted: <strong>${new Date(d.createdAt).toLocaleString('en-IN')}</strong></div>
                </div>

                <div class="magnifier-container" style="margin-bottom: 24px; position: relative;">
                    <img id="dep-proof-img" src="${d.paymentProofUrl}" alt="Proof" style="width: 100%; max-height: 280px; object-fit: contain; border-radius: var(--radius-sm);">
                    <div id="magnifier-lens" class="magnifier-glass"></div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Rejection Reason (if rejecting)</label>
                    <input class="form-input" type="text" id="dep-reject-reason" placeholder="Incorrect Transaction ID, amount mismatched, etc.">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button class="btn-destructive" onclick="rejectDeposit()" style="flex: 1;">Reject Request</button>
                    <button class="btn-primary" onclick="approveDeposit()" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow);">Approve Deposit</button>
                </div>
            `;
            // Initialize Magnifier
            setTimeout(() => {
                ImageMagnifier.init('dep-proof-img', 'magnifier-lens', 2);
            }, 100);
        }

        async function approveDeposit() {
            if (!selectedDepositId) return;
            const conf = confirm('Confirm approval of this deposit? User wallet balance will be updated.');
            if (!conf) return;

            try {
                await apiRequest(`/api/admin/deposits/${selectedDepositId}/approve`, { 
                    method: 'POST',
                    body: {}
                });
                Toast.show('Deposit approved and credited.');
                fetchDeposits();
                fetchStatistics();
            } catch (err) {
                console.error('Deposit approval error:', err);
                Toast.show(err.message || 'Failed to approve deposit', 'error');
            }
        }

        async function rejectDeposit() {
            if (!selectedDepositId) return;
            const reason = document.getElementById('dep-reject-reason').value.trim();
            if (!reason) {
                Toast.show('Please enter a rejection reason', 'error');
                return;
            }

            try {
                await apiRequest(`/api/admin/deposits/${selectedDepositId}/reject`, {
                    method: 'POST',
                    body: { reason }
                });
                Toast.show('Deposit request rejected.');
                fetchDeposits();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 3. Withdrawals Split Pane
        let selectedWithdrawalId = null;
        async function fetchWithdrawals() {
            try {
                const data = await apiRequest('/api/admin/pending-withdrawals?page=1&limit=20');
                const container = document.getElementById('with-queue-list');
                container.innerHTML = '';
                document.getElementById('with-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a withdrawal request to view bank details and process</div>';

                if (data.withdrawals.length === 0) {
                    container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;">No pending withdrawals found.</div>';
                    return;
                }

                let foundSelected = false;
                data.withdrawals.forEach(w => {
                    const row = document.createElement('div');
                    row.className = 'pane-item';
                    if (selectedWithdrawalId === w.id) {
                        row.classList.add('active');
                        foundSelected = true;
                    }
                    row.innerHTML = `
                        <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 8px;">${formatRupees(w.amount)}</div>
                        <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 4px;">User: ${w.userName}</div>
                        <div style="font-size: 0.75rem; color: var(--muted);">${new Date(w.createdAt).toLocaleString('en-IN')}</div>
                    `;
                    row.addEventListener('click', () => {
                        document.querySelectorAll('#with-queue-list .pane-item').forEach(x => x.classList.remove('active'));
                        row.classList.add('active');
                        selectedWithdrawalId = w.id;
                        renderWithdrawalDetails(w);
                    });
                    container.appendChild(row);
                });
                if (!foundSelected) {
                    selectedWithdrawalId = null;
                    document.getElementById('with-details-pane').innerHTML = '<div style="color:var(--muted); text-align:center; padding: 40px;">Select a request to review</div>';
                }
            } catch (err) {
                console.error('fetchWithdrawals failed:', err);
                Toast.show(err.message || 'Failed to load withdrawals', 'error');
            }
        }

        function renderWithdrawalDetails(w) {
            const pane = document.getElementById('with-details-pane');
            let payDetailsHtml = '';
            
            if (w.bankDetails.upiId) {
                payDetailsHtml = `<div>Preferred Type: <strong>UPI ID</strong></div><div style="font-size: 1.1rem; color: var(--primary); font-weight: 700; margin-top: 6px;">UPI ID: ${w.bankDetails.upiId}</div>`;
            } else {
                payDetailsHtml = `
                    <div>Preferred Type: <strong>Bank Transfer</strong></div>
                    <div style="margin-top: 6px; padding: 12px; background: rgba(0,0,0,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 0.85rem; line-height: 1.6;">
                        <div>Holder: <strong>${w.bankDetails.accountName}</strong></div>
                        <div>Account No: <strong>${w.bankDetails.accountNumber}</strong></div>
                        <div>IFSC: <strong>${w.bankDetails.ifscCode}</strong></div>
                    </div>
                `;
            }

            pane.innerHTML = `
                <h3 style="font-weight: 700; margin-bottom: 20px;">Cashout Details</h3>
                <div style="font-size: 0.9rem; margin-bottom: 20px; line-height: 1.6;">
                    <div>User: <strong>${w.userName}</strong> (${w.userEmail})</div>
                    <div>Requested Amount: <strong>${formatRupees(w.amount)}</strong></div>
                    <div>Requested At: <strong>${new Date(w.createdAt).toLocaleString('en-IN')}</strong></div>
                </div>

                <div class="glass-card" style="margin-bottom: 24px;">
                    ${payDetailsHtml}
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Rejection Reason (if rejecting)</label>
                    <input class="form-input" type="text" id="with-reject-reason" placeholder="Incorrect account number, KYC mismatch, etc.">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button class="btn-destructive" onclick="rejectWithdrawal()" style="flex: 1;">Reject Request</button>
                    <button class="btn-primary" onclick="confirmWithdrawal()" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow);">Confirm Completed</button>
                </div>
            `;
        }

        async function confirmWithdrawal() {
            if (!selectedWithdrawalId) return;
            const conf = confirm('Confirm that payout transfer has been successfully processed?');
            if (!conf) return;

            try {
                await apiRequest(`/api/admin/withdrawals/${selectedWithdrawalId}/confirm`, { method: 'POST' });
                Toast.show('Withdrawal marked as completed.');
                fetchWithdrawals();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        async function rejectWithdrawal() {
            if (!selectedWithdrawalId) return;
            const reason = document.getElementById('with-reject-reason').value.trim();
            if (!reason) {
                Toast.show('Please enter a rejection reason', 'error');
                return;
            }

            try {
                await apiRequest(`/api/admin/withdrawals/${selectedWithdrawalId}/reject`, {
                    method: 'POST',
                    body: { reason }
                });
                Toast.show('Withdrawal request rejected. Funds returned to user.');
                fetchWithdrawals();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 4. Referral Bonus Claims Split Pane
        let selectedClaimId = null;
        async function fetchClaims() {
            try {
                const data = await apiRequest('/api/referral-bonus/admin/pending?page=1&limit=20');
                const container = document.getElementById('claims-queue-list');
                container.innerHTML = '';
                document.getElementById('claims-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a claim to review user roadmap milestone metrics and process</div>';

                if (data.claims.length === 0) {
                    container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;">No pending claims found.</div>';
                    return;
                }

                let foundSelected = false;
                data.claims.forEach(c => {
                    const row = document.createElement('div');
                    row.className = 'pane-item';
                    if (selectedClaimId === c.id) {
                        row.classList.add('active');
                        foundSelected = true;
                    }
                    row.innerHTML = `
                        <div style="font-weight: 700; font-size: 0.9rem;">${c.userName}</div>
                        <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Claim: <strong style="color: var(--foreground);">${formatRupees(c.amount)}</strong></div>
                    `;
                    row.addEventListener('click', () => {
                        document.querySelectorAll('#claims-queue-list .pane-item').forEach(x => x.classList.remove('active'));
                        row.classList.add('active');
                        selectedClaimId = c.id;
                        renderClaimDetails(c);
                    });
                    container.appendChild(row);
                });
                if (!foundSelected) {
                    selectedClaimId = null;
                    document.getElementById('claims-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a claim to review user roadmap milestone metrics and process</div>';
                }
            } catch (err) {
                console.error('fetchClaims failed:', err);
                Toast.show(err.message || 'Failed to load claims', 'error');
            }
        }

        function renderClaimDetails(c) {
            const pane = document.getElementById('claims-details-pane');
            pane.innerHTML = `
                <h3 style="font-weight: 700; margin-bottom: 20px;">Claim Verification</h3>
                <div style="font-size: 0.9rem; margin-bottom: 24px; line-height: 1.6;">
                    <div>Claimant Account: <strong>${c.userName}</strong> (${c.userEmail})</div>
                    <div>Milestone Bonus: <strong>${formatRupees(c.amount)}</strong></div>
                    <div>Earned from Friend: <strong>${c.referredUserName}</strong> (${c.referredUserEmail})</div>
                    <div>Requested: <strong>${new Date(c.createdAt).toLocaleString('en-IN')}</strong></div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Rejection Reason (if rejecting)</label>
                    <input class="form-input" type="text" id="claim-reject-reason" placeholder="KYC mismatch, inactive referred user, etc.">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button class="btn-destructive" onclick="rejectClaim()" style="flex: 1;">Reject Claim</button>
                    <button class="btn-primary" onclick="approveClaim()" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow);">Approve Payout</button>
                </div>
            `;
        }

        async function approveClaim() {
            if (!selectedClaimId) return;
            try {
                await apiRequest(`/api/referral-bonus/admin/claims/${selectedClaimId}/approve`, { method: 'POST' });
                Toast.show('Commission claim approved and credited.');
                fetchClaims();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        async function rejectClaim() {
            if (!selectedClaimId) return;
            const reason = document.getElementById('claim-reject-reason').value.trim();
            if (!reason) {
                Toast.show('Please enter a rejection reason', 'error');
                return;
            }

            try {
                await apiRequest(`/api/referral-bonus/admin/claims/${selectedClaimId}/reject`, {
                    method: 'POST',
                    body: { reason }
                });
                Toast.show('Claim rejected.');
                fetchClaims();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 5. User Accounts & Audit Drawer
        let auditUserTargetId = null;
        let auditPage = 1;
        async function fetchUsers() {
            const search = document.getElementById('user-search').value;
            try {
                const data = await apiRequest(`/api/admin/users?page=1&limit=20&search=${encodeURIComponent(search)}`);
                const body = document.getElementById('users-table-body');
                body.innerHTML = '';

                if (data.users.length === 0) {
                    body.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 32px 0;">No user accounts found.</td></tr>';
                    return;
                }


                data.users.forEach(u => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid var(--border)';
                    
                    const statusText = u.isActive ? 'Active' : 'Suspended';
                    const statusColor = u.isActive ? 'var(--success)' : 'var(--destructive)';
                    const safeName = (u.name || 'Anonymous').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const displayName = u.name || 'Anonymous User';

                    tr.innerHTML = `
                        <td style="padding: 12px;">
                            <div style="font-weight: 700;">${displayName}</div>
                            <div style="font-size: 0.75rem; color: var(--muted);">${u.email}</div>
                        </td>
                        <td style="padding: 12px; font-size: 0.85rem;">${u.mobileNumber || '-'}</td>
                        <td style="padding: 12px; font-weight: 800;">${formatRupees(u.walletBalance)}</td>
                        <td style="padding: 12px; font-weight: 700; color: ${statusColor};">${statusText}</td>
                        <td style="padding: 12px; display: flex; gap: 8px;">
                            <button class="btn-secondary" onclick="openAuditDrawer('${u.id}', '${safeName}')" style="padding: 6px 12px; font-size: 0.75rem;">Audit</button>
                            <button class="btn-secondary" onclick="toggleUserStatus('${u.id}', ${u.isActive})" style="padding: 6px 12px; font-size: 0.75rem;">${u.isActive ? 'Suspend' : 'Activate'}</button>
                            <button class="btn-destructive" onclick="deleteUser('${u.id}', '${safeName}')" style="padding: 6px 12px; font-size: 0.75rem;">Delete</button>
                        </td>
                    `;
                    body.appendChild(tr);
                });
            } catch (err) {
                console.error('fetchUsers failed:', err);
                Toast.show(err.message || 'Failed to load users', 'error');
            }
        }

        async function toggleUserStatus(id, isActive) {
            try {
                await apiRequest(`/api/admin/users/${id}/status`, {
                    method: 'PATCH',
                    body: { isActive: !isActive }
                });
                Toast.show('User status toggled successfully.');
                fetchUsers();
            } catch (err) {
                console.error('Toggle user status error:', err);
                Toast.show(err.message || 'Failed to toggle user status', 'error');
            }
        }

        async function deleteUser(id, name) {
            const conf = confirm(`Permanently delete account "${name}" and all historical requests? THIS ACTION IS IRREVERSIBLE.`);
            if (!conf) return;

            try {
                await apiRequest(`/api/admin/users/${id}`, { 
                    method: 'DELETE',
                    body: {}
                });
                Toast.show('User deleted successfully.');
                fetchUsers();
                fetchStatistics();
            } catch (err) {
                console.error('Delete user error:', err);
                Toast.show(err.message || 'Failed to delete user', 'error');
            }
        }

        async function openAuditDrawer(id, name) {
            auditUserTargetId = id;
            auditPage = 1;
            document.getElementById('audit-user-details').innerHTML = `<h3>${name}'s Audit Log</h3>`;
            document.getElementById('audit-drawer-overlay').classList.add('active');
            fetchAuditTransactions();
        }

        function closeAuditDrawer() {
            document.getElementById('audit-drawer-overlay').classList.remove('active');
            auditUserTargetId = null;
        }

        async function fetchAuditTransactions() {
            if (!auditUserTargetId) return;

            try {
                const data = await apiRequest(`/api/admin/users/${auditUserTargetId}/transactions?page=${auditPage}&limit=10`);
                const container = document.getElementById('audit-transactions-list');
                container.innerHTML = '';

                if (data.transactions.length === 0) {
                    container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;">No transaction logs recorded.</div>';
                    return;
                }

                data.transactions.forEach(t => {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                    const isCredit = t.type === 'deposit';
                    const color = isCredit ? 'var(--success)' : 'var(--destructive)';
                    const prefix = isCredit ? '+' : '-';
                    const date = new Date(t.createdAt).toLocaleDateString('en-IN');

                    row.innerHTML = `
                        <div>
                            <span style="font-weight: 700; font-size: 0.85rem; text-transform: capitalize;">${t.type}</span>
                            <span style="font-size: 0.7rem; color: var(--muted); display: block;">${date} | Status: ${t.status}</span>
                        </div>
                        <span style="font-weight: 800; color: ${color};">${prefix}${formatRupees(t.amount)}</span>
                    `;
                    container.appendChild(row);
                });
            } catch (err) {
                console.error('fetchAuditTransactions failed:', err);
                Toast.show(err.message || 'Failed to load transactions', 'error');
            }
        }

        // 6. Yield Assets (CRUD & scheduling)
        let scheduleTargetProduct = null;
        
        // Escape a string so it is safe to interpolate into HTML text/attributes.
        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, ch => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));
        }

        async function fetchProducts() {
            try {
                const data = await apiRequest('/api/products/admin/all');
                const list = document.getElementById('products-list');
                list.innerHTML = '';

                data.products.forEach(p => {
                    const card = document.createElement('div');
                    card.className = 'glass-card';
                    // Use escapeHtml on all interpolated text/attribute values so product names
                    // containing apostrophes/quotes (e.g. "Aero's Gold") don't break the markup
                    // or the click handlers.
                    card.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span class="badge-roi">${escapeHtml(p.dailyRewardPercent)}% ROI</span>
                            <span style="font-size: 0.75rem; color: ${p.isActive ? 'var(--success)' : 'var(--muted)'};">${p.isActive ? 'Active' : 'Draft'}</span>
                        </div>
                        <img src="${escapeHtml(p.imageUrl)}" alt="${escapeHtml(p.name)}" style="width: 100%; height: 140px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px;">
                        <h3 style="font-weight: 700; margin-bottom: 4px;">${escapeHtml(p.name)}</h3>
                        <p style="color: var(--muted); font-size: 0.8rem; margin-bottom: 12px;">Price: <strong>${formatRupees(p.price)}</strong> | Duration: ${escapeHtml(p.durationDays)} Days</p>

                        <div style="display: flex; flex-direction: column; gap: 8px; border-top: 1px solid var(--border); padding-top: 16px;">
                            <button class="btn-primary" data-action="schedule" style="font-size: 0.8rem; padding: 8px 12px;">📅 Schedule Ad Links</button>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn-secondary" data-action="edit" style="flex: 1; font-size: 0.8rem; padding: 8px;">Edit</button>
                                <button class="btn-destructive" data-action="delete" style="flex: 1; font-size: 0.8rem; padding: 8px;">Delete</button>
                            </div>
                        </div>
                    `;
                    // Bind handlers via addEventListener (closure captures the product object safely)
                    // instead of inline onclick with string interpolation that breaks on quotes.
                    card.querySelector('[data-action="schedule"]').addEventListener('click', () => openAdScheduler(p.id, p.durationDays));
                    card.querySelector('[data-action="edit"]').addEventListener('click', () => editProduct(p.id, p.name, p.price, p.durationDays, p.dailyRewardPercent, p.adWatchSeconds));
                    card.querySelector('[data-action="delete"]').addEventListener('click', () => deleteProduct(p.id, p.name));
                    list.appendChild(card);
                });
            } catch (err) {
                console.error('fetchProducts failed:', err);
                Toast.show(err.message || 'Failed to load products', 'error');
            }
        }

        function openProductModal() {
            document.getElementById('product-form').reset();
            document.getElementById('edit-product-id').value = '';
            document.getElementById('product-modal-title').innerText = 'Create Yield Product';
            document.getElementById('prod-file-group').style.display = 'block';
            document.getElementById('product-modal').classList.add('active');
        }

        function closeProductModal() {
            document.getElementById('product-modal').classList.remove('active');
        }

        function editProduct(id, name, price, duration, reward, seconds) {
            document.getElementById('edit-product-id').value = id;
            document.getElementById('prod-name').value = name;
            document.getElementById('prod-price').value = price / 100; // paise to rupees
            document.getElementById('prod-duration').value = duration;
            document.getElementById('prod-reward').value = reward;
            document.getElementById('prod-seconds').value = seconds;

            document.getElementById('product-modal-title').innerText = 'Edit Yield Product';
            document.getElementById('prod-file-group').style.display = 'none'; // hide file input on update
            document.getElementById('product-modal').classList.add('active');
        }

        formatCurrencyInput(document.getElementById('prod-price'));

        document.getElementById('product-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('prod-submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Saving...';

            const editId = document.getElementById('edit-product-id').value;
            const price = parseFloat(document.getElementById('prod-price').value);

            if (editId) {
                // Update Product API
                try {
                    await apiRequest(`/api/products/admin/${editId}`, {
                        method: 'PUT',
                        body: {
                            name: document.getElementById('prod-name').value,
                            price: Math.round(price * 100),
                            durationDays: parseInt(document.getElementById('prod-duration').value),
                            dailyRewardPercent: parseFloat(document.getElementById('prod-reward').value),
                            adWatchSeconds: parseInt(document.getElementById('prod-seconds').value)
                        }
                    });
                    Toast.show('Product updated successfully!');
                    closeProductModal();
                    fetchProducts();
                } catch (err) {
                    Toast.show(err.message, 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Save Product';
                }
            } else {
                // Create Product API
                const formData = new FormData(document.getElementById('product-form'));
                formData.set('price', Math.round(price * 100)); // Overwrite in paise

                try {
                    await apiRequest('/api/products/admin/create', {
                        method: 'POST',
                        body: formData,
                        isMultipart: true
                    });
                    Toast.show('Product created successfully!');
                    closeProductModal();
                    fetchProducts();
                } catch (err) {
                    Toast.show(err.message, 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Save Product';
                }
            }
        });

        async function deleteProduct(id, name) {
            const conf = confirm(`Are you sure you want to delete "${name}"?`);
            if (!conf) return;

            try {
                await apiRequest(`/api/products/admin/${id}`, { method: 'DELETE' });
                Toast.show('Product deleted successfully.');
                fetchProducts();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // Ad links scheduler grid
        async function openAdScheduler(productId, duration) {
            scheduleTargetProduct = productId;
            try {
                const data = await apiRequest(`/api/products/admin/${productId}/ad-links`);
                const grid = document.getElementById('ad-scheduler-grid');
                grid.innerHTML = '';

                const linksMap = {};
                data.links.forEach(l => { linksMap[l.dayNumber] = l.videoUrl; });

                for (let i = 1; i <= duration; i++) {
                    const rowLabel = document.createElement('label');
                    rowLabel.innerText = `Day ${i}`;
                    rowLabel.style.fontWeight = 'bold';
                    
                    const rowInput = document.createElement('input');
                    rowInput.className = 'form-input';
                    rowInput.type = 'url';
                    rowInput.placeholder = 'https://example.com/video-url.mp4';
                    rowInput.value = linksMap[i] || '';
                    rowInput.setAttribute('data-day', i);

                    grid.appendChild(rowLabel);
                    grid.appendChild(rowInput);
                }

                document.getElementById('ad-scheduler-modal').classList.add('active');
            } catch (err) {
                Toast.show('Failed to load ad link schedules', 'error');
            }
        }

        function closeAdScheduler() {
            document.getElementById('ad-scheduler-modal').classList.remove('active');
            scheduleTargetProduct = null;
        }

        async function saveAdSchedule() {
            if (!scheduleTargetProduct) return;
            const inputs = document.querySelectorAll('#ad-scheduler-grid input');
            const links = [];

            inputs.forEach(inp => {
                const url = inp.value.trim();
                if (url) {
                    links.push({
                        dayNumber: parseInt(inp.getAttribute('data-day')),
                        videoUrl: url
                    });
                }
            });

            try {
                await apiRequest(`/api/products/admin/${scheduleTargetProduct}/ad-links`, {
                    method: 'PUT',
                    body: { links }
                });
                Toast.show('Product ad links schedule saved!');
                closeAdScheduler();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        }

        // 7. System Settings
        async function fetchSettings() {
            try {
                // QR & UPI Details
                const qrData = await apiRequest('/api/deposits/payment-details');
                if (qrData.qrCodeUrl) {
                    document.getElementById('settings-qr-preview').src = qrData.qrCodeUrl;
                    document.getElementById('settings-qr-preview').style.display = 'block';
                }
                document.getElementById('settings-upi-id').value = qrData.upiId || '';

                // Backup global ad
                const adData = await apiRequest('/api/products/daily-ad');
                document.getElementById('settings-global-ad').value = adData.ad?.videoUrl || '';

                // Required referrals count
                const stats = await apiRequest('/api/referrals/stats');
                document.getElementById('settings-req-referrals').value = stats.requiredReferrals;

                // Load Payment Tiers list
                const pmData = await apiRequest('/api/admin/payment-methods');
                const tiersList = document.getElementById('payment-methods-list');
                tiersList.innerHTML = '';

                pmData.methods.forEach(m => {
                    const row = document.createElement('form');
                    row.className = 'glass-card';
                    row.style.cssText = 'padding: 16px; display: grid; grid-template-columns: 100px 1fr 1fr 80px; gap: 16px; align-items: center;';
                    row.innerHTML = `
                        <div style="font-weight: 800;">${m.label} Tier</div>
                        <input class="form-input" type="text" name="upiId" placeholder="UPI ID" value="${m.upiId || ''}">
                        <input class="form-input" type="file" name="qrImage" accept="image/*">
                        <button type="submit" class="btn-primary" style="padding: 8px;">Save</button>
                    `;
                    row.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const formData = new FormData(row);
                        try {
                            await apiRequest(`/api/admin/payment-methods/${m.id}`, {
                                method: 'POST',
                                body: formData,
                                isMultipart: true
                            });
                            Toast.show(`${m.label} tier details saved successfully!`);
                            fetchSettings();
                        } catch (err) {
                            Toast.show(err.message, 'error');
                        }
                    });
                    tiersList.appendChild(row);
                });

            } catch (err) {
                console.error('fetchSettings failed:', err);
                Toast.show(err.message || 'Failed to load settings', 'error');
            }
        }

        // Settings Forms submits
        document.getElementById('settings-qr-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                await apiRequest('/api/admin/qr-code', {
                    method: 'POST',
                    body: formData,
                    isMultipart: true
                });
                Toast.show('QR code image uploaded.');
                fetchSettings();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        });

        document.getElementById('settings-config-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                // Save Primary UPI ID
                await apiRequest('/api/admin/upi-id', {
                    method: 'PUT',
                    body: { upiId: document.getElementById('settings-upi-id').value }
                });

                // Save Global daily ad
                await apiRequest('/api/products/admin/daily-ad', {
                    method: 'POST',
                    body: { videoUrl: document.getElementById('settings-global-ad').value }
                });

                // Save Required referrals count
                await apiRequest('/api/admin/settings', {
                    method: 'PUT',
                    body: { requiredReferrals: parseInt(document.getElementById('settings-req-referrals').value) }
                });

                Toast.show('System settings saved successfully!');
                fetchSettings();
            } catch (err) {
                Toast.show(err.message, 'error');
            }
        });

        // 8. Logout
        document.getElementById('logout-btn').addEventListener('click', async () => {
            const btn = document.getElementById('logout-btn');
            btn.disabled = true;
            btn.innerText = 'Logging out...';
            try { localStorage.removeItem('theme'); } catch(e) {}
            try {
                await Promise.race([
                    apiRequest('/api/auth/logout', { method: 'POST' }),
                    new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 4000))
                ]);
            } catch(e) {}
            window.location.href = '/login';
        });

        // Initial setup load
        fetchStatistics();
    </script>
</body>
</html>
