# AeroPay - Wallet Management System (PHP)

A secure, closed-loop financial application written in clean, dependency-free PHP. Consists of a premium client dashboard and a robust administration pay panel.

## Key Features

1. **User Dashboard**:
   - **Marketplace**: Buy yield assets to secure daily percentage returns.
   - **Overview**: Real-time balance monitor and transaction ledgers.
   - **Deposits**: Multi-method deposit wizards with payment proof uploads.
   - **Withdrawals**: Bank transfer and UPI payouts with a premium "Swipe-to-withdraw" slider widget, automatically enforcing customizable referral requirements.
   - **Ad Watching**: Custom countdown ad player overlay with reward credit handlers and particle effects.
   - **Referrals**: Referral milestone roadmap tracking and commission payouts.

2. **Admin Command Center**:
   - High-level system statistics (payouts, active users).
   - Deposit approval queues with picture magnifier to verify transactions.
   - Withdrawal request completion and rejection logs.
   - User account search, suspension control, and slide-out transaction audits.
   - Yield Asset editor (CRUD) with day-by-day video ad schedules.
   - Configurable settings (primary QR Code image, global backup ad link, primary UPI ID).

---

## Local Development

Start the local server using PHP's built-in web server:

```bash
php -S localhost:8000 router.php
```

Open [http://localhost:8000](http://localhost:8000) in your browser.

### Utility Scripts

- **Run Database Migrations**: Applies idempotent schema upgrades for wallet ledger, audit logs, soft deletes, and payout metadata:
  ```bash
  php migrate.php
  ```
- **Reset Admin Credentials**: Sets `admin@example.com` password to `admin123` (creates the admin account if it does not exist):
  ```bash
  php reset_admin.php
  ```
- **Database Schema Audit**: Prints columns of all system tables in the console:
  ```bash
  php check_db.php
  ```

---

## Production Deployment

This project is fully ready for deployment on Apache or Nginx:
- **Apache**: The provided `.htaccess` file handles clean URL rewrites automatically.
- **Environment Settings**: Copy your database connection details into `.env.local` in the root folder.
