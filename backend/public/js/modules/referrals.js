import api from '../services/api.js';
import { showToast, formatDate, formatAmount, escapeHtml } from '../utils/helpers.js';

/**
 * Referrals Module - Modern UI
 * Handles all referral-related functionality including bonuses
 */
export class ReferralsModule {
  constructor() {
    this.referralCode = null;
    this.referrals = [];
    this.totalReferrals = 0;
    this.confirmedReferrals = 0;
    this.requiredReferrals = 5;
    this.unclaimedBonuses = [];
    this.totalUnclaimedAmount = 0;
    this.bonusStats = null;
  }

  /**
   * Initialize referrals view
   */
  async init() {
    await this.loadReferralData();
    await this.loadBonusData();
    this.setupEventListeners();
  }

  /**
   * Load all referral data
   */
  async loadReferralData() {
    try {
      // Load referral code and stats
      const statsData = await api.getReferralStats();
      this.referralCode = statsData.referralCode;
      this.totalReferrals = statsData.totalReferrals;
      this.confirmedReferrals = statsData.confirmedReferrals || 0;
      this.requiredReferrals = statsData.requiredReferrals || 5;

      // Load referrals list
      const referralsData = await api.getMyReferrals();
      this.referrals = referralsData.referrals;

      // Update UI
      this.updateReferralUI();
    } catch (error) {
      console.error('Error loading referral data:', error);
      showToast('Failed to load referral data', 'error');
    }
  }

  /**
   * Load bonus data
   */
  async loadBonusData() {
    try {
      // Load unclaimed bonuses
      const unclaimedData = await api.getUnclaimedBonuses();
      this.unclaimedBonuses = unclaimedData.bonuses;
      this.totalUnclaimedAmount = unclaimedData.totalAmount;

      // Load bonus stats
      this.bonusStats = await api.getBonusStats();

      // Update UI
      this.updateBonusUI();
    } catch (error) {
      console.error('Error loading bonus data:', error);
    }
  }

  /**
   * Update referral UI
   */
  updateReferralUI() {
    // Update referral code display
    const referralCodeText = document.getElementById('referralCodeText');
    if (referralCodeText) {
      referralCodeText.textContent = this.referralCode || '------';
    }

    // Update total referrals count
    const totalReferralsCount = document.getElementById('totalReferralsCount');
    if (totalReferralsCount) {
      totalReferralsCount.textContent = this.totalReferrals;
    }

    // Update confirmed referrals count
    const confirmedReferralsCount = document.getElementById('confirmedReferralsCount');
    if (confirmedReferralsCount) {
      confirmedReferralsCount.textContent = this.confirmedReferrals;
    }

    // Update progress bar and card
    this.updateProgressCard();

    // Update referrals list
    this.renderReferralsList();
  }

  /**
   * Update progress card for withdrawal eligibility
   */
  updateProgressCard() {
    const progressCard = document.querySelector('.progress-card');
    const progressFill = document.getElementById('progressFill');
    const progressIcon = document.getElementById('progressIcon');
    const progressTitle = document.getElementById('progressTitle');
    const progressSubtitle = document.getElementById('progressSubtitle');

    if (!progressCard) return;

    const percentage = Math.min((this.confirmedReferrals / this.requiredReferrals) * 100, 100);
    const isEligible = this.confirmedReferrals >= this.requiredReferrals;

    // Update progress bar
    if (progressFill) {
      progressFill.style.width = `${percentage}%`;
    }

    // Update card state
    if (isEligible) {
      progressCard.classList.add('eligible');
      if (progressIcon) progressIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
      if (progressTitle) progressTitle.textContent = 'Withdrawals Unlocked!';
      if (progressSubtitle) progressSubtitle.textContent = 'You can now withdraw your earnings';
    } else {
      progressCard.classList.remove('eligible');
      const needed = this.requiredReferrals - this.confirmedReferrals;
      if (progressIcon) progressIcon.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
      if (progressTitle) progressTitle.textContent = 'Withdrawal Progress';
      if (progressSubtitle) {
        progressSubtitle.textContent = `${needed} more confirmed referral${needed > 1 ? 's' : ''} needed to unlock withdrawals`;
      }
    }
  }

  /**
   * Update bonus UI
   */
  updateBonusUI() {
    // Update claimable amount
    const claimableAmount = document.getElementById('claimableAmount');
    if (claimableAmount) {
      claimableAmount.textContent = formatAmount(this.totalUnclaimedAmount);
    }

    // Update bonus stats
    if (this.bonusStats) {
      const totalEarned = document.getElementById('totalBonusEarned');
      if (totalEarned) {
        totalEarned.textContent = formatAmount(this.bonusStats.claimedAmount + this.bonusStats.unclaimedAmount);
      }
    }

    // Render unclaimed bonuses
    this.renderUnclaimedBonuses();
  }

  /**
   * Render referrals list with new design
   */
  renderReferralsList() {
    const referralsList = document.getElementById('referralsList');
    if (!referralsList) return;

    if (this.referrals.length === 0) {
      referralsList.innerHTML = `
        <div class="referrals-empty">
          <div class="empty-illustration">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.3">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
          <p class="empty-title">No referrals yet</p>
          <p class="empty-text">Share your code to start earning!</p>
        </div>
      `;
      return;
    }

    // Check which referrals have deposited
    const confirmedIds = new Set();
    // We'll mark referrals as confirmed based on bonus data if available
    
    referralsList.innerHTML = this.referrals.map(referral => {
      const initials = this.getInitials(referral.name);
      const isConfirmed = referral.hasDeposited || false;
      
      return `
        <div class="referral-item-v2">
          <div class="referral-avatar-v2">${initials}</div>
          <div class="referral-info-v2">
            <p class="referral-name-v2">${escapeHtml(referral.name)}</p>
            <p class="referral-email-v2">${escapeHtml(referral.email)}</p>
          </div>
          <div class="referral-status">
            <span class="status-badge ${isConfirmed ? 'confirmed' : 'pending'}">
              ${isConfirmed ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>Deposited' : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Pending'}
            </span>
            <span class="referral-date-v2">${this.formatRelativeDate(referral.joinedAt)}</span>
          </div>
        </div>
      `;
    }).join('');
  }

  /**
   * Render unclaimed bonuses with new design
   */
  renderUnclaimedBonuses() {
    const bonusesList = document.getElementById('unclaimedBonusesList');
    if (!bonusesList) return;

    if (this.unclaimedBonuses.length === 0) {
      bonusesList.innerHTML = `
        <div class="bonus-empty">
          <div class="empty-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.4;"><rect x="3" y="8" width="18" height="4" rx="1"></rect><path d="M12 8v13"></path><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"></path><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"></path></svg></div>
          <p class="empty-title">No rewards yet</p>
          <p class="empty-text">Earn 5% when your referrals make deposits</p>
        </div>
      `;
      // Hide claim all button when no bonuses
      const claimAllBtn = document.getElementById('claimAllBtn');
      if (claimAllBtn) claimAllBtn.style.display = 'none';
      return;
    }

    // Show claim all button when there are bonuses
    const claimAllBtn = document.getElementById('claimAllBtn');
    if (claimAllBtn) claimAllBtn.style.display = 'inline-flex';

    bonusesList.innerHTML = this.unclaimedBonuses.map(bonus => {
      const initials = this.getInitials(bonus.referredUserName);
      
      return `
        <div class="bonus-item-v2" data-bonus-id="${bonus.id}">
          <div class="bonus-avatar">${initials}</div>
          <div class="bonus-details">
            <p class="bonus-name">${escapeHtml(bonus.referredUserName)}</p>
            <p class="bonus-deposit">Deposited ${formatAmount(bonus.depositAmount)}</p>
          </div>
          <div class="bonus-right">
            <span class="bonus-value">${formatAmount(bonus.bonusAmount)}</span>
            <button class="claim-btn" data-bonus-id="${bonus.id}">Claim</button>
          </div>
        </div>
      `;
    }).join('');

    // Add click handlers for claim buttons
    bonusesList.querySelectorAll('.claim-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const button = e.target.closest('.claim-btn');
        if (button) {
          const bonusId = button.dataset.bonusId;
          this.claimBonus(bonusId, button);
        }
      });
    });
  }

  /**
   * Claim a bonus
   */
  async claimBonus(bonusId, buttonElement = null) {
    try {
      const btn = buttonElement || document.querySelector(`.claim-btn[data-bonus-id="${bonusId}"]`);
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Claiming...';
        btn.classList.add('claiming');
      }

      await api.claimBonus(bonusId);

      // Replace button with "Claiming..." badge (request sent, pending admin approval)
      if (btn && btn.parentNode) {
        const claimingBadge = document.createElement('span');
        claimingBadge.className = 'claiming-badge';
        claimingBadge.textContent = 'Claiming...';
        btn.parentNode.replaceChild(claimingBadge, btn);
      }

      // Remove this bonus from the local array to prevent re-rendering it with a Claim button
      this.unclaimedBonuses = this.unclaimedBonuses.filter(b => b.id !== bonusId);
      
      // Update the total amount display
      const claimedBonus = this.unclaimedBonuses.find(b => b.id === bonusId);
      if (claimedBonus) {
        this.totalUnclaimedAmount -= claimedBonus.bonusAmount;
      }
      
      // Update claimable amount display
      const claimableAmount = document.getElementById('claimableAmount');
      if (claimableAmount) {
        claimableAmount.textContent = formatAmount(this.totalUnclaimedAmount);
      }

    } catch (error) {
      console.error('Error claiming bonus:', error);
      const btn = buttonElement || document.querySelector(`.claim-btn[data-bonus-id="${bonusId}"]`);
      if (btn && !btn.classList.contains('claiming-badge')) {
        btn.disabled = false;
        btn.textContent = 'Claim';
        btn.classList.remove('claiming');
      }
    }
  }

  /**
   * Claim all bonuses at once
   */
  async claimAllBonuses() {
    if (this.unclaimedBonuses.length === 0) return;

    const claimAllBtn = document.getElementById('claimAllBtn');
    if (claimAllBtn) {
      claimAllBtn.disabled = true;
      claimAllBtn.textContent = 'Claiming...';
    }

    // Store the bonuses to claim (copy the array since we'll modify it)
    const bonusesToClaim = [...this.unclaimedBonuses];

    try {
      // Claim all bonuses sequentially
      for (const bonus of bonusesToClaim) {
        const btn = document.querySelector(`.claim-btn[data-bonus-id="${bonus.id}"]`);
        if (btn) {
          btn.disabled = true;
          btn.textContent = 'Claiming...';
          btn.classList.add('claiming');
        }
        
        try {
          await api.claimBonus(bonus.id);
          
          // Replace button with "Claiming..." badge
          if (btn && btn.parentNode) {
            const claimingBadge = document.createElement('span');
            claimingBadge.className = 'claiming-badge';
            claimingBadge.textContent = 'Claiming...';
            btn.parentNode.replaceChild(claimingBadge, btn);
          }
          
          // Remove from local array
          this.unclaimedBonuses = this.unclaimedBonuses.filter(b => b.id !== bonus.id);
        } catch (err) {
          // If one fails, continue with others but restore the button
          console.error(`Failed to claim bonus ${bonus.id}:`, err);
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Claim';
            btn.classList.remove('claiming');
          }
        }
      }

      // Update totals
      this.totalUnclaimedAmount = this.unclaimedBonuses.reduce((sum, b) => sum + b.bonusAmount, 0);
      const claimableAmount = document.getElementById('claimableAmount');
      if (claimableAmount) {
        claimableAmount.textContent = formatAmount(this.totalUnclaimedAmount);
      }

      // Hide claim all button if all claimed
      if (this.unclaimedBonuses.length === 0 && claimAllBtn) {
        claimAllBtn.style.display = 'none';
      }

    } catch (error) {
      console.error('Error claiming all bonuses:', error);
    } finally {
      if (claimAllBtn) {
        claimAllBtn.disabled = false;
        claimAllBtn.textContent = 'Claim All';
      }
    }
  }

  /**
   * Get initials from name
   */
  getInitials(name) {
    if (!name || name === 'Anonymous') return '?';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
  }

  /**
   * Format date as relative time
   */
  formatRelativeDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} week${Math.floor(diffDays / 7) > 1 ? 's' : ''} ago`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30)} month${Math.floor(diffDays / 30) > 1 ? 's' : ''} ago`;
    return formatDate(dateString);
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Copy referral code button
    const copyBtn = document.getElementById('copyReferralCodeBtn');
    if (copyBtn) {
      copyBtn.addEventListener('click', () => this.copyReferralCode());
    }

    // Claim all button
    const claimAllBtn = document.getElementById('claimAllBtn');
    if (claimAllBtn) {
      claimAllBtn.addEventListener('click', () => this.claimAllBonuses());
    }    // Share referral link button
    const shareBtn = document.getElementById('shareReferralLinkBtn');
    if (shareBtn) {
      shareBtn.addEventListener('click', () => this.shareReferralLink());
    }
  }

  /**
   * Copy referral code to clipboard
   */
  async copyReferralCode() {
    if (!this.referralCode) {
      showToast('Referral code not available', 'error');
      return;
    }

    try {
      await navigator.clipboard.writeText(this.referralCode);
      showToast('Code copied!', 'success');
      
      // Visual feedback
      const copyBtn = document.getElementById('copyReferralCodeBtn');
      if (copyBtn) {
        copyBtn.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        `;
        setTimeout(() => {
          copyBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
          `;
        }, 2000);
      }
    } catch (error) {
      // Fallback for older browsers
      const textArea = document.createElement('textarea');
      textArea.value = this.referralCode;
      textArea.style.position = 'fixed';
      textArea.style.left = '-999999px';
      document.body.appendChild(textArea);
      textArea.select();
      try {
        document.execCommand('copy');
        showToast('Code copied!', 'success');
      } catch (err) {
        showToast('Failed to copy code', 'error');
      }
      document.body.removeChild(textArea);
    }
  }

  /**
   * Share referral link
   */
  async shareReferralLink() {
    if (!this.referralCode) {
      showToast('Referral code not available', 'error');
      return;
    }

    const referralLink = `${window.location.origin}/register?ref=${this.referralCode}`;
    const shareText = `Join me and start earning! Use my referral code: ${this.referralCode}`;

    // Check if Web Share API is available (mobile devices)
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Join with my referral code',
          text: shareText,
          url: referralLink
        });
      } catch (error) {
        if (error.name !== 'AbortError') {
          this.copyReferralLink(referralLink);
        }
      }
    } else {
      this.copyReferralLink(referralLink);
    }
  }

  /**
   * Copy referral link to clipboard
   */
  async copyReferralLink(link) {
    try {
      await navigator.clipboard.writeText(link);
      showToast('Invite link copied!', 'success');
    } catch (error) {
      showToast('Failed to copy link', 'error');
    }
  }
}

export default new ReferralsModule();
