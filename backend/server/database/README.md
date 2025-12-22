# Database Setup

## PostgreSQL Database Schema

This directory contains the database schema and migration scripts for the Manual Wallet Manager.

## Setup Instructions

1. Install PostgreSQL (version 12 or higher)
2. Create a new database:
   ```bash
   createdb manual_wallet_manager
   ```

3. Run the initial migration:
   ```bash
   psql -d manual_wallet_manager -f server/database/migrations/001_initial_schema.sql
   ```

## Schema Overview

- **users**: User accounts with wallet balance
- **deposit_requests**: Deposit requests with payment proofs
- **withdrawal_requests**: Withdrawal requests with bank details
- **admin_settings**: Admin configuration (QR code, UPI ID)

## Indexes

Indexes are created on:
- users.email (unique)
- deposit_requests.user_id, status, created_at
- withdrawal_requests.user_id, status, created_at
