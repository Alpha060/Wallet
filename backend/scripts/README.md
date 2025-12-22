# Admin Initialization Script

## Purpose
This script designates a registered user as an admin by setting the `isAdmin` flag in the database.

## Prerequisites
- The user must already be registered in the system
- Database must be running and accessible
- Environment variables must be configured (`.env` file)

## Usage

### Method 1: Using npm script (recommended)
```bash
cd backend
npm run init-admin
```

### Method 2: Direct execution
```bash
cd backend
node scripts/initAdmin.js
```

## How it works

1. The script will check if `ADMIN_EMAIL` is set in your `.env` file
2. If set, it will ask if you want to use that email
3. Otherwise, you'll be prompted to enter an email address
4. The script verifies the user exists in the database
5. If the user is already an admin, it will notify you and exit
6. Otherwise, it will ask for confirmation before setting the admin flag
7. Upon confirmation, the user's `isAdmin` flag is set to `true`

## Example

```
=== Admin Initialization Script ===

Admin email from environment: admin@example.com
Use this email? (y/n): y

Set "admin@example.com" as admin? (y/n): y

✓ Success! User "admin@example.com" is now an admin.
```

## Environment Variables

Add this to your `.env` file (optional):
```
ADMIN_EMAIL=admin@example.com
```

## Notes

- Only one admin is required, but multiple admins can be created by running the script multiple times
- The first user registered should typically be designated as admin
- Admin users have access to all admin endpoints for managing deposits and withdrawals
