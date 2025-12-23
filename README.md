# Wallet Management System

A complete wallet management system with deposit/withdrawal functionality, built as a Progressive Web App (PWA).

<!-- Force Railway Deploy: v1.1 -->

## Features

### Authentication & Security
- JWT-based authentication with bcrypt password hashing
- Role-based access control (User/Admin)
- Session management with "Keep me logged in"
- Password recovery flow
- Rate limiting protection

### User Features
- User registration and profile management
- KYC information (Aadhar, PAN, DOB, Mobile)
- Digital wallet with balance tracking
- Deposit requests with payment proof upload
- Withdrawal requests with bank details
- Transaction history

### Admin Features
- User management (view, search, activate/deactivate)
- Deposit approval/rejection with proof verification
- Withdrawal processing
- QR code and UPI ID settings
- Statistics dashboard

### PWA
- Installable on mobile and desktop
- Offline support with service worker
- App-like experience

## Tech Stack

- Node.js + Express
- PostgreSQL
- Vanilla JavaScript (ES6 modules)
- CSS3 with CSS variables

## Prerequisites

- Node.js v18+
- PostgreSQL v14+

## Installation

1. Clone and install:
```bash
git clone <repository-url>
cd wallet-management-system/backend
npm install
```

2. Configure environment:
```bash
cp .env.example .env
```

Edit `.env`:
```env
PORT=3000
DATABASE_URL=postgresql://user:password@localhost:5432/wallet_db
JWT_SECRET=your_secret_key
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=Admin@123
```

3. Create database:
```bash
psql -U postgres -c "CREATE DATABASE wallet_db"
```

4. Start server (auto-runs migrations):
```bash
npm start
```

5. Create admin user:
```bash
node scripts/initAdmin.js
```

## Usage

Access the app at `http://localhost:3000`

### Default Admin
- Email: `admin@example.com` (or your ADMIN_EMAIL)
- Password: `Admin@123` (or your ADMIN_PASSWORD)

## Project Structure

```
backend/
├── public/
│   ├── css/           # Stylesheets
│   ├── js/            # Frontend JavaScript
│   ├── pages/         # HTML pages
│   └── service-worker.js
├── server/
│   ├── database/      # DB config & migrations
│   ├── middleware/    # Auth, rate limit, upload
│   ├── repositories/  # Data access layer
│   ├── routes/        # API endpoints
│   ├── services/      # Business logic
│   └── server.js
├── scripts/           # Utility scripts
└── uploads/           # User uploads
```

## API Endpoints

### Auth
- `POST /api/auth/register` - Register user
- `POST /api/auth/login` - Login
- `GET /api/auth/me` - Get current user
- `PUT /api/auth/profile` - Update profile

### Wallet
- `GET /api/wallet/balance` - Get balance
- `GET /api/wallet/transactions` - Transaction history

### Deposits
- `POST /api/deposits` - Create deposit request
- `GET /api/deposits` - Get user deposits

### Withdrawals
- `POST /api/withdrawals` - Create withdrawal request
- `GET /api/withdrawals` - Get user withdrawals

### Admin
- `GET /api/admin/users` - List users
- `PUT /api/admin/users/:id/status` - Update user status
- `GET /api/admin/deposits` - List all deposits
- `PUT /api/admin/deposits/:id` - Approve/reject deposit
- `GET /api/admin/withdrawals` - List all withdrawals
- `PUT /api/admin/withdrawals/:id` - Approve/reject withdrawal
- `GET /api/admin/settings` - Get admin settings
- `PUT /api/admin/settings` - Update QR/UPI settings

## Scripts

```bash
npm start                    # Start server
node scripts/initAdmin.js    # Create admin user
node scripts/clearAndReinitialize.js  # Reset database
```

## Database Schema

### users
- id, email, password_hash, name
- is_admin, is_active, wallet_balance
- mobile_number, aadhar_number, date_of_birth, pan_number
- created_at, updated_at

### deposit_requests
- id, user_id, amount, transaction_id
- payment_proof_url, status, rejection_reason
- created_at, processed_at, processed_by

### withdrawal_requests
- id, user_id, amount, bank_details (JSONB)
- status, rejection_reason
- created_at, processed_at, processed_by

### admin_settings
- id, qr_code_url, upi_id, updated_at

## Deployment

### Render.com (Recommended)

1. Create PostgreSQL database on Render
2. Create Web Service pointing to this repo
3. Set environment variables:
   - `DATABASE_URL` - From Render PostgreSQL
   - `JWT_SECRET` - Your secret key
   - `NODE_ENV` - production
   - `ADMIN_EMAIL` - Admin email
   - `ADMIN_PASSWORD` - Admin password

### Manual Deployment

1. Set up PostgreSQL database
2. Configure environment variables
3. Run `npm install`
4. Run `npm start`
5. Set up reverse proxy (Nginx) with SSL

## License

ISC

---

**Built with Node.js, Express, PostgreSQL, and vanilla JavaScript**
