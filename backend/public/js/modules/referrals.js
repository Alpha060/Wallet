import api from '../services/api.js';
import { showToast, formatDate, escapeHtml } from '../utils/helpers.js';

/**
 * Referrals Module
 * Handles all referral-related functionality
 */
export class ReferralsModule {
  constructor() {
    this.referralCode = null;
    this.referrals = [];
    this.totalReferrals = 0;
  }

  /**
   * Initialize referrals view
   */
  async init() {
    await this.loadReferralData();
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
      this.updateUI();
    } catch (error) {
      console.error('Error loading referral data:', error);
      showToast('Failed to load referral data', 'error');
    }
  }

  /**
   * Update UI with referral data
   */
  updateUI() {
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
