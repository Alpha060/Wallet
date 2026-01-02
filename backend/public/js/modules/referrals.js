import api from '../services/api.js';
import { showToast, formatDate, formatAmount, escapeHtml } from '../utils/helpers.js';

/**
 * Referrals Module
 * Handles all referral-related functionality including bonuses
 */
export class ReferralsModule {
  constructor() {
    this.referralCode = null;
    this.referrals = [];
    this.totalReferrals = 0;
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
      // Don't show error toast - bonuses might not be available yet
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

    // Update referrals list
    this.renderReferralsList();
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
   * Render referrals list
   */
  renderReferralsList() {
    const referralsList = document.getElementById('referralsList');
    if (!referralsList) return;

    if (this.referrals.length === 0) {
      referralsList.innerHTML = `
        <div class="empty-referrals">
          <div class="empty-referrals-icon">👥</div>
          <p>No referrals yet</p>
          <p style="margin-top: 0.5rem; font-size: 0.875rem;">Share your referral code to start earning!</p>
        </div>
      `;
      return;
    }

    referralsList.innerHTML = this.referrals.map(referral => `
      <div class="referral-item">
        <div class="referral-user-info">
          <div class="referral-avatar">
            ${this.getInitials(referral.name)}
          </div>
          <div class="referral-details">
            <h4>${escapeHtml(referral.name)}</h4>
            <p>${escapeHtml(referral.email)}</p>
          </div>
        </div>
        <div class="referral-date">
          ${formatDate(referral.joinedAt)}
        </div>
      </div>
    `).join('');
  }

  /**
   * Render unclaimed bonuses
   */
  renderUnclaimedBonuses() {
    const bonusesList = document.getElementById('unclaimedBonusesList');
    if (!bonusesList) return;

    if (this.unclaimedBonuses.length === 0) {
      bonusesList.innerHTML = `
        <div class="empty-bonuses">
          <div class="empty-bonuses-icon">🎁</div>
          <p>No unclaimed bonuses</p>
          <p style="margin-top: 0.5rem; font-size: 0.875rem;">Earn 5% bonus when your referrals deposit!</p>
        </div>
      `;
      return;
    }

    bonusesList.innerHTML = this.unclaimedBonuses.map(bonus => `
      <div class="bonus-item" data-bonus-id="${bonus.id}">
        <div class="bonus-info">
          <div class="bonus-avatar">
            ${this.getInitials(bonus.referredUserName)}
          </div>
          <div class="bonus-details">
            <h4>${escapeHtml(bonus.referredUserName)}</h4>
            <p>Deposited ${formatAmount(bonus.depositAmount)}</p>
            <p class="bonus-date">${formatDate(bonus.createdAt)}</p>
          </div>
        </div>
        <div class="bonus-amount-section">
          <p class="bonus-amount">${formatAmount(bonus.bonusAmount)}</p>
          <button class="btn btn-primary btn-sm claim-bonus-btn" data-bonus-id="${bonus.id}">
            Claim
          </button>
        </div>
      </div>
    `).join('');

    // Add click handlers for claim buttons
    bonusesList.querySelectorAll('.claim-bonus-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const bonusId = e.target.dataset.bonusId;
        this.claimBonus(bonusId);
      });
    });
  }

  /**
   * Claim a bonus
   */
  async claimBonus(bonusId) {
    try {
      const btn = document.querySelector(`.claim-bonus-btn[data-bonus-id="${bonusId}"]`);
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Claiming...';
      }

      await api.claimBonus(bonusId);

      // Reload bonus data
      await this.loadBonusData();
    } catch (error) {
      console.error('Error claiming bonus:', error);
      // Re-enable button on error
      const btn = document.querySelector(`.claim-bonus-btn[data-bonus-id="${bonusId}"]`);
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Claim';
      }
    }
  }

  /**
   * Get initials from name
   */
  getInitials(name) {
    if (!name || name === 'Anonymous') return '?';
    const parts = name.split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
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

    // Share referral link button
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
      showToast('Referral code copied to clipboard!', 'success');
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
        showToast('Referral code copied to clipboard!', 'success');
      } catch (err) {
        showToast('Failed to copy referral code', 'error');
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
    const shareText = `Join me on this platform! Use my referral code: ${this.referralCode}`;

    // Check if Web Share API is available (mobile devices)
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Join with my referral code',
          text: shareText,
          url: referralLink
        });
        showToast('Shared successfully!', 'success');
      } catch (error) {
        if (error.name !== 'AbortError') {
          // Fallback to copy link
          this.copyReferralLink(referralLink);
        }
      }
    } else {
      // Fallback to copy link
      this.copyReferralLink(referralLink);
    }
  }

  /**
   * Copy referral link to clipboard
   */
  async copyReferralLink(link) {
    try {
      await navigator.clipboard.writeText(link);
      showToast('Referral link copied to clipboard!', 'success');
    } catch (error) {
      showToast('Failed to copy referral link', 'error');
    }
  }
}

export default new ReferralsModule();
