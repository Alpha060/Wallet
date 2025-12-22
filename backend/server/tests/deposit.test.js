import { describe, it, before, after } from 'node:test';
import assert from 'node:assert';
import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';
import authService from '../services/authService.js';
import depositService from '../services/depositService.js';
import userRepository from '../repositories/userRepository.js';
import depositRepository from '../repositories/depositRepository.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

describe('Deposit Service Tests', () => {
  let testUser;
  let adminUser;
  let testDepositId;

  before(async () => {
    // Create test user
    const userResult = await authService.register('deposituser@example.com', 'password123');
    testUser = userResult.user;

    // Create admin user
    const adminResult = await authService.register('depositadmin@example.com', 'password123');
    adminUser = adminResult.user;
    await userRepository.updateAdminStatus(adminUser.id, true);
  });

  after(async () => {
    // Cleanup
    if (testDepositId) {
      await depositRepository.delete(testDepositId);
    }
    if (testUser) await userRepository.delete(testUser.id);
    if (adminUser) await userRepository.delete(adminUser.id);
  });

  describe('Create Deposit Request', () => {
    it('should create deposit with valid payment proof', async () => {
      const mockFile = {
        filename: 'test-payment-proof.jpg',
        mimetype: 'image/jpeg',
        size: 1024 * 100 // 100KB
      };

      const deposit = await depositService.createDepositRequest(
        testUser.id,
        50000, // ₹500
        mockFile,
        'TXN123456'
      );

      assert.ok(deposit.id, 'Deposit ID should be returned');
      assert.strictEqual(deposit.userId, testUser.id);
      assert.strictEqual(deposit.amount, 50000);
      assert.strictEqual(deposit.status, 'pending');
      assert.ok(deposit.paymentProofUrl);

      testDepositId = deposit.id;
    });

    it('should reject deposit with amount less than minimum', async () => {
      const mockFile = {
        filename: 'test-payment-proof.jpg',
        mimetype: 'image/jpeg',
        size: 1024 * 100
      };

      await assert.rejects(
        async () => await depositService.createDepositRequest(testUser.id, 50, mockFile),
        { message: 'Minimum deposit amount is ₹1 (100 paise)' }
      );
    });

    it('should reject deposit without payment proof', async () => {
      await assert.rejects(
        async () => await depositService.createDepositRequest(testUser.id, 50000, null),
        { message: 'Payment proof is required' }
      );
    });
  });

  describe('Approve Deposit', () => {
    let depositToApprove;

    before(async () => {
      const mockFile = {
        filename: 'approve-test.jpg',
        mimetype: 'image/jpeg',
        size: 1024 * 100
      };
      depositToApprove = await depositService.createDepositRequest(
        testUser.id,
        100000, // ₹1000
        mockFile
      );
    });

    after(async () => {
      if (depositToApprove) await depositRepository.delete(depositToApprove.id);
    });

    it('should approve deposit and increase balance', async () => {
      const initialBalance = (await userRepository.findById(testUser.id)).walletBalance;

      const result = await depositService.approveDeposit(depositToApprove.id, adminUser.id);

      assert.strictEqual(result.deposit.status, 'approved');
      assert.strictEqual(result.newBalance, initialBalance + 100000);

      const updatedUser = await userRepository.findById(testUser.id);
      assert.strictEqual(updatedUser.walletBalance, initialBalance + 100000);
    });

    it('should reject approving already processed deposit', async () => {
      await assert.rejects(
        async () => await depositService.approveDeposit(depositToApprove.id, adminUser.id),
        { message: 'Deposit request has already been processed' }
      );
    });
  });

  describe('Reject Deposit', () => {
    let depositToReject;

    before(async () => {
      const mockFile = {
        filename: 'reject-test.jpg',
        mimetype: 'image/jpeg',
        size: 1024 * 100
      };
      depositToReject = await depositService.createDepositRequest(
        testUser.id,
        50000,
        mockFile
      );
    });

    after(async () => {
      if (depositToReject) await depositRepository.delete(depositToReject.id);
    });

    it('should reject deposit without changing balance', async () => {
      const initialBalance = (await userRepository.findById(testUser.id)).walletBalance;

      const result = await depositService.rejectDeposit(
        depositToReject.id,
        adminUser.id,
        'Invalid payment proof'
      );

      assert.strictEqual(result.status, 'rejected');
      assert.strictEqual(result.rejectionReason, 'Invalid payment proof');

      const updatedUser = await userRepository.findById(testUser.id);
      assert.strictEqual(updatedUser.walletBalance, initialBalance);
    });
  });
});
