# Deployment Guide - Render.com

## 🚀 Quick Deploy to Render (5-7 Day Free Test)

### Step 1: Sign Up on Render
1. Go to https://render.com
2. Click "Get Started for Free"
3. Sign up with GitHub (recommended) or email
4. Verify your email

### Step 2: Connect Your GitHub Repository
1. After login, click "New +"
2. Select "Blueprint"
3. Click "Connect GitHub"
4. Authorize Render to access your repositories
5. Select your repository: **Alpha060/Wallet**

### Step 3: Deploy Using Blueprint
1. Render will automatically detect the `render.yaml` file
2. Review the services:
   - **wallet-db** (PostgreSQL Database - Free)
   - **kyc-wallet-app** (Web Service - Free)
3. Click "Apply"
4. Wait 5-10 minutes for deployment

### Step 4: Your App is Live! 🎉
Once deployed, you'll get a URL like:
```
https://kyc-wallet-app.onrender.com
```

## 📋 What Gets Deployed

### Database (PostgreSQL)
- **Name**: wallet-db
- **Plan**: Free (90 days)
- **Region**: Singapore
- **Storage**: 1GB
- **Auto-created**: wallet_db database

### Web Service
- **Name**: kyc-wallet-app
- **Plan**: Free (750 hours/month)
- **Region**: Singapore
- **Auto-deploy**: On every git push to main branch
- **SSL**: Automatic HTTPS

## 🔐 Default Admin Credentials

After deployment, login with:
- **Email**: admin@example.com
- **Password**: Admin@123456

⚠️ **IMPORTANT**: Change these immediately after first login!

## ⚙️ Environment Variables (Auto-Configured)

These are automatically set by Render:
- `NODE_ENV=production`
- `PORT=10000`
- `DATABASE_URL` (from wallet-db)
- `DB_HOST` (from wallet-db)
- `DB_PORT` (from wallet-db)
- `DB_NAME` (from wallet-db)
- `DB_USER` (from wallet-db)
- `DB_PASSWORD` (from wallet-db)
- `JWT_SECRET` (auto-generated)

## 🎯 Post-Deployment Steps

### 1. Initialize Admin User
The admin user will be created automatically on first run.

### 2. Test Your App
Visit your Render URL and:
- [ ] Login with admin credentials
- [ ] Create a test user
- [ ] Upload KYC documents
- [ ] Test deposit/withdrawal
- [ ] Test PWA installation

### 3. Generate PWA Icons
1. Visit: `https://your-app.onrender.com/icons/create-placeholder-icons.html`
2. Download all icons
3. Upload to your repository in `backend/public/icons/`
4. Push to GitHub (auto-deploys)

## 📊 Monitoring Your App

### View Logs
1. Go to Render Dashboard
2. Click on "kyc-wallet-app"
3. Click "Logs" tab
4. See real-time logs

### Check Database
1. Go to Render Dashboard
2. Click on "wallet-db"
3. Click "Connect" to get connection details
4. Use any PostgreSQL client to connect

## ⚠️ Important Notes

### Free Tier Limitations
- **App sleeps after 15 min inactivity**
  - First request takes 30-50 seconds to wake up
  - Subsequent requests are fast
- **Database expires after 90 days**
  - Perfect for your 5-7 day test
  - Export data before expiry if needed
- **750 hours/month**
  - More than enough for testing

### Performance Tips
- Keep the app awake by pinging it every 10 minutes
- Use UptimeRobot (free) to ping your app
- Or use this cron job: https://cron-job.org

## 🔄 Updating Your App

### Method 1: Git Push (Recommended)
```bash
# Make changes to your code
git add .
git commit -m "Your changes"
git push origin main
```
Render auto-deploys on every push!

### Method 2: Manual Deploy
1. Go to Render Dashboard
2. Click on "kyc-wallet-app"
3. Click "Manual Deploy"
4. Select "Deploy latest commit"

## 🐛 Troubleshooting

### App Not Starting
1. Check logs in Render Dashboard
2. Verify all environment variables are set
3. Check database connection

### Database Connection Failed
1. Verify wallet-db is running
2. Check DATABASE_URL is set correctly
3. Wait a few minutes for database to initialize

### 502 Bad Gateway
- App is starting up (wait 30-60 seconds)
- Or app crashed (check logs)

### Images Not Uploading
- Free tier has limited disk space
- Images are stored temporarily
- Use cloud storage (Cloudinary) for production

## 📱 Testing PWA

### On Mobile
1. Open your Render URL in mobile browser
2. Look for "Install App" button
3. Add to home screen
4. Test offline functionality

### On Desktop
1. Open in Chrome/Edge
2. Click install icon in address bar
3. Or use the "Install App" button

## 💾 Backup Your Data

Before the 90-day database expiry:

### Export Database
```bash
# Get connection string from Render Dashboard
pg_dump "your-database-url" > backup.sql
```

### Download Uploads
```bash
# Download all uploaded files
# (Note: Free tier doesn't persist uploads on restart)
```

## 🎉 Success Checklist

- [ ] App deployed successfully
- [ ] Database connected
- [ ] Admin login works
- [ ] User registration works
- [ ] KYC upload works
- [ ] Deposit/withdrawal works
- [ ] PWA installs on mobile
- [ ] PWA installs on desktop
- [ ] Offline mode works

## 🚀 Next Steps (After Testing)

If everything works well:
1. Upgrade to paid plan for:
   - No sleep time
   - Persistent storage
   - Better performance
   - Longer database retention

2. Or migrate to VPS:
   - Full control
   - Better performance
   - Lower long-term cost
   - Custom domain

## 📞 Support

- **Render Docs**: https://render.com/docs
- **Render Community**: https://community.render.com
- **Status Page**: https://status.render.com

---

**Your app is now live and ready for testing! 🎊**
