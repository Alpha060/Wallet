<?php
// src/views/user/layout.php - Shared shell layout for all regular user views
initSession();
$user = requireAuth();
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<script>document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark');</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/app.css?v=2">
    <script src="/app.js?v=2"></script>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0a0c">
    <link rel="apple-touch-icon" href="/images/aeropay-logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <style>
        /* Shared layout-specific styles */
        .sidebar-icon {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            margin-right: 8px;
            color: var(--muted);
            transition: color 0.2s ease;
        }
        .nav-link.active .sidebar-icon,
        .nav-link:hover .sidebar-icon {
            color: var(--primary);
        }
        .nav-link {
            font-size: 0.8rem !important;
            padding: 8px 12px !important;
            gap: 8px !important;
        }
        .sidebar-category-header {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--muted-light);
            letter-spacing: 0.05em;
            padding: 14px 12px 6px 12px;
            user-select: none;
        }
        
        /* Dropdown item styling */
        .dropdown-item:hover {
            background: rgba(255,255,255,0.03) !important;
            color: var(--primary) !important;
        }
        .dark .dropdown-item:hover {
            background: rgba(255,255,255,0.03) !important;
        }
    </style>
</head>
<body>
    <canvas id="confetti-canvas" style="position: fixed; inset: 0; pointer-events: none; z-index: 9999; display: none;"></canvas>

    <div class="dashboard-container">
        <!-- Desktop Sidebar -->
        <aside class="sidebar">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding: 4px;">
                <img src="/images/aeropay-logo.png" alt="AeroPay Logo" style="width: 32px; height: 32px; object-fit: contain;">
                <h2 style="font-weight: 800; font-size: 1.3rem; letter-spacing: -0.02em;">Aero<span style="color: var(--primary);">Pay</span></h2>
            </div>

            <div class="glass-card" style="padding: 16px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)); ?>
                </div>
                <div style="overflow: hidden;">
                    <div style="font-size: 0.85rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--foreground);"><?= htmlspecialchars($user['name'] ?: 'User'); ?></div>
                    <div style="font-size: 0.75rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($user['email']); ?></div>
                </div>
            </div>

             <nav class="sidebar-nav" style="display: flex; flex-direction: column; gap: 4px; flex: 1; margin-top: 0 !important;">
                <div class="sidebar-category-header"><?= __('Overview') ?></div>
                <a href="/dashboard" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <?= __('Overview') ?>
                </a>
                <a href="/marketplace" class="nav-link <?= $activePage === 'marketplace' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                    <?= __('Marketplace') ?>
                </a>

                <div class="sidebar-category-header"><?= __('Finance') ?></div>
                <a href="/deposit" class="nav-link <?= $activePage === 'deposit' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                    <?= __('Deposit Funds') ?>
                </a>
                <a href="/withdraw" class="nav-link <?= $activePage === 'withdraw' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                    <?= __('Withdraw') ?>
                </a>
                <a href="/investments" class="nav-link <?= $activePage === 'investments' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    <?= __('My Investments') ?>
                </a>

                <div class="sidebar-category-header"><?= __('Utilities') ?></div>
                <a href="/referrals" class="nav-link <?= $activePage === 'referrals' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <?= __('Referrals') ?>
                </a>
                <a href="/history" class="nav-link <?= $activePage === 'history' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= __('History') ?>
                </a>
                <a href="/settings" class="nav-link <?= $activePage === 'settings' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <?= __('Settings') ?>
                </a>
            </nav>

            <div style="margin-top: auto; display: flex; flex-direction: column;">
                <button class="btn-secondary" id="logout-btn" style="width: 100%; border-color: rgba(239, 68, 68, 0.2); color: var(--destructive); display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <?= __('Logout') ?>
                </button>
            </div>
        </aside>

        <!-- Sidebar Backdrop Overlay -->
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

        <!-- Mobile Header -->
        <header class="mobile-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button id="mobile-menu-trigger" style="background: none; border: none; font-size: 1.5rem; color: var(--foreground); cursor: pointer;">☰</button>
                <h3 style="font-weight: 700; margin: 0;"><?= htmlspecialchars($title ?? 'AeroPay') ?></h3>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button class="lang-toggle-btn" style="background: none; border: none; cursor: pointer; padding: 6px; color: var(--muted); display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 36px; height: 36px; border: 1px solid var(--border);"></button>
                <button class="theme-toggle-btn" id="theme-toggle-mobile" style="background: none; border: none; cursor: pointer; padding: 6px; color: var(--muted); display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 36px; height: 36px; border: 1px solid var(--border);"></button>
                
                <!-- Mobile Dropdown Anchor -->
                <div style="position: relative;" class="profile-dropdown-container">
                    <button id="profile-dropdown-trigger-mobile" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; padding: 4px;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; border: 1px solid var(--border);">
                            <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)); ?>
                        </div>
                    </button>
                    <!-- Mobile Menu Dropdown options -->
                    <div id="profile-dropdown-menu-mobile" style="display: none; position: absolute; top: 44px; right: 0; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: 0 10px 30px rgba(0,0,0,0.15); width: 180px; z-index: 100; padding: 8px 0;">
                        <button onclick="openChangePasswordModal()" style="display: flex; align-items: center; gap: 10px; width: 100%; background: none; border: none; text-align: left; padding: 10px 16px; color: var(--foreground); cursor: pointer; font-size: 0.85rem; font-weight: 600;" class="dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <?= __('Change Password') ?>
                        </button>
                        <hr style="border: 0; border-top: 1px solid var(--border); margin: 6px 0;">
                        <button class="dropdown-logout-btn" style="display: flex; align-items: center; gap: 10px; width: 100%; background: none; border: none; text-align: left; padding: 10px 16px; color: var(--destructive); cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <?= __('Logout') ?>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Workspace Content -->
        <main class="main-content">
            <!-- Desktop Top Navigation Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;" class="desktop-top-bar">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button id="desktop-menu-trigger" style="background: none; border: none; font-size: 1.3rem; color: var(--foreground); cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 36px; height: 36px; border: 1px solid var(--border);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <div>
                        <h1 style="font-weight: 800; font-size: 1.8rem; margin: 0; color: var(--foreground);"><?= htmlspecialchars(__($title ?? 'AeroPay')) ?></h1>
                        <p style="color: var(--muted); font-size: 0.85rem; margin-top: 4px; margin-bottom: 0;"><?= htmlspecialchars(__($description ?? 'Your premium wallet overview')) ?></p>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button class="lang-toggle-btn" style="background: none; border: none; cursor: pointer; padding: 8px; color: var(--muted); display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 40px; height: 40px; border: 1px solid var(--border);"></button>
                    <button class="theme-toggle-btn" id="theme-toggle" style="background: none; border: none; cursor: pointer; padding: 8px; color: var(--muted); display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 40px; height: 40px; border: 1px solid var(--border);"></button>
                    
                    <!-- Desktop Dropdown Anchor -->
                    <div style="position: relative;" class="profile-dropdown-container">
                        <button id="profile-dropdown-trigger" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; padding: 4px;">
                            <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; border: 1px solid var(--border);">
                                <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)); ?>
                            </div>
                        </button>
                        <!-- Dropdown options -->
                        <div id="profile-dropdown-menu" style="display: none; position: absolute; top: 48px; right: 0; background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-sm); box-shadow: 0 10px 30px rgba(0,0,0,0.15); width: 180px; z-index: 100; padding: 8px 0;">
                            <button onclick="openChangePasswordModal()" style="display: flex; align-items: center; gap: 10px; width: 100%; background: none; border: none; text-align: left; padding: 10px 16px; color: var(--foreground); cursor: pointer; font-size: 0.85rem; font-weight: 600;" class="dropdown-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <?= __('Change Password') ?>
                            </button>
                            <hr style="border: 0; border-top: 1px solid var(--border); margin: 6px 0;">
                            <button class="dropdown-logout-btn" style="display: flex; align-items: center; gap: 10px; width: 100%; background: none; border: none; text-align: left; padding: 10px 16px; color: var(--destructive); cursor: pointer; font-size: 0.85rem; font-weight: 600;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                <?= __('Logout') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?= $content ?>
        </main>
    </div>

    <!-- Change Password Modal -->
    <div class="modal-overlay" id="change-password-modal">
        <div class="modal-content" style="max-width: 440px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-weight: 800; color: var(--foreground); margin: 0;"><?= __('Change Password') ?></h3>
                <button onclick="closeChangePasswordModal()" style="background: none; border: none; font-size: 1.4rem; color: var(--muted); cursor: pointer;">✕</button>
            </div>
            <form id="change-password-form">
                <div class="form-group">
                    <label class="form-label"><?= __('Current Password') ?></label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="cp-current" required placeholder="<?= __('Enter current password') ?>">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('New Password') ?></label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="cp-new" required placeholder="<?= __('Enter new password') ?>" minlength="6">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('Confirm New Password') ?></label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="cp-confirm" required placeholder="<?= __('Re-enter new password') ?>">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 28px;">
                    <button type="button" class="btn-secondary" onclick="closeChangePasswordModal()" style="flex: 1; padding: 12px 20px;"><?= __('Cancel') ?></button>
                    <button type="submit" class="btn-primary" id="cp-submit-btn" style="flex: 1; padding: 12px 20px; color: #000000; font-weight: 700;"><?= __('Update Password') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- app.js loaded in head -->
    <script>
        // Sidebar & Backdrop
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const mobileMenuTrigger = document.getElementById('mobile-menu-trigger');
        const desktopMenuTrigger = document.getElementById('desktop-menu-trigger');

        function openMobileSidebar() {
            sidebar.style.display = 'flex';
            backdrop.style.display = 'block';
            // Force reflow for animation
            void sidebar.offsetWidth;
            sidebar.classList.add('open');
            backdrop.classList.add('active');
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
            setTimeout(() => {
                if (!sidebar.classList.contains('open')) {
                    sidebar.style.display = '';
                    backdrop.style.display = 'none';
                }
            }, 300);
        }

        if (mobileMenuTrigger) {
            mobileMenuTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                if (sidebar.classList.contains('open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', closeMobileSidebar);
        }

        if (desktopMenuTrigger && sidebar) {
            desktopMenuTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const isCollapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
                sidebar.style.display = isCollapsed ? 'none' : 'flex';
            });
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                sidebar.classList.add('collapsed');
                sidebar.style.display = 'none';
            }
        }

        // Global Theme Toggle Handler with SVG Icons
        const toggleBtn = document.getElementById('theme-toggle');
        const toggleBtnMobile = document.getElementById('theme-toggle-mobile');

        function updateTogglerIcons(isDark) {
            const moonSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>`;
            const sunSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>`;
            
            const currentIcon = isDark ? sunSvg : moonSvg;
            if (toggleBtn) toggleBtn.innerHTML = currentIcon;
            if (toggleBtnMobile) toggleBtnMobile.innerHTML = currentIcon;
        }

        const handleThemeToggle = () => {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateTogglerIcons(isDark);
        };

        if (toggleBtn) toggleBtn.addEventListener('click', handleThemeToggle);
        if (toggleBtnMobile) toggleBtnMobile.addEventListener('click', handleThemeToggle);

        // Sync initial state icon
        updateTogglerIcons(document.documentElement.classList.contains('dark'));

        // Dropdown Menu triggers (Desktop & Mobile)
        const dpTrigger = document.getElementById('profile-dropdown-trigger');
        const dpMenu = document.getElementById('profile-dropdown-menu');
        const dpTriggerMobile = document.getElementById('profile-dropdown-trigger-mobile');
        const dpMenuMobile = document.getElementById('profile-dropdown-menu-mobile');

        if (dpTrigger && dpMenu) {
            dpTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                dpMenu.style.display = dpMenu.style.display === 'block' ? 'none' : 'block';
            });
        }
        if (dpTriggerMobile && dpMenuMobile) {
            dpTriggerMobile.addEventListener('click', (e) => {
                e.stopPropagation();
                dpMenuMobile.style.display = dpMenuMobile.style.display === 'block' ? 'none' : 'block';
            });
        }

        document.addEventListener('click', () => {
            if (dpMenu) dpMenu.style.display = 'none';
            if (dpMenuMobile) dpMenuMobile.style.display = 'none';
        });

        // Global Logout Script
        const logoutActions = [
            document.getElementById('logout-btn'),
            ...document.querySelectorAll('.dropdown-logout-btn')
        ];

        logoutActions.forEach(btn => {
            if (btn) {
                btn.addEventListener('click', async () => {
                    btn.disabled = true;
                    btn.innerText = '<?= __('Logging out...') ?>';
                    try {
                        await Promise.race([
                            apiRequest('/api/auth/logout', { method: 'POST' }),
                            new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 3000))
                        ]);
                    } catch(e) {}
                    window.location.href = '/login';
                });
            }
        });

        // Change Password Modal
        function openChangePasswordModal() {
            document.getElementById('change-password-modal').classList.add('active');
            document.getElementById('change-password-form').reset();
            // Close any open dropdowns
            if (dpMenu) dpMenu.style.display = 'none';
            if (dpMenuMobile) dpMenuMobile.style.display = 'none';
        }

        function closeChangePasswordModal() {
            document.getElementById('change-password-modal').classList.remove('active');
        }

        document.getElementById('change-password-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const currentPassword = document.getElementById('cp-current').value;
            const newPassword = document.getElementById('cp-new').value;
            const confirmPassword = document.getElementById('cp-confirm').value;

            if (newPassword !== confirmPassword) {
                Toast.show('<?= __('New passwords do not match') ?>', 'error');
                return;
            }

            const btn = document.getElementById('cp-submit-btn');
            btn.disabled = true;
            btn.innerText = '<?= __('Updating...') ?>';

            try {
                await apiRequest('/api/auth/password', {
                    method: 'PUT',
                    body: { currentPassword, newPassword }
                });
                Toast.show('<?= __('Password updated successfully!') ?>');
                closeChangePasswordModal();
            } catch (err) {
                Toast.show(err.message || '<?= __('Failed to update password') ?>', 'error');
            } finally {
                btn.disabled = false;
                btn.innerText = '<?= __('Update Password') ?>';
            }
        });
    </script>
</body>
</html>
