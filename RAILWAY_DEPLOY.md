# 🚀 Railway Deployment Guide

## Why Railway is Better
- ✅ $5 free credit (enough for 5-7 days testing)
- ✅ PostgreSQL included automatically
- ✅ Faster deployment (2-3 minutes)
- ✅ Better performance (no sleep)
- ✅ Automatic SSL
- ✅ Custom domains
- ✅ One-click deploy from GitHub

## 📝 Quick Deploy (3 minutes)

### Step 1: Sign Up on Railway
👉 https://railway.app
1. Click **"Start a New Project"**
2. Sign up with **GitHub** (recommended)
3. Authorize Railway to access your repositories

### Step 2: Deploy from GitHub
1. Click **"Deploy from GitHub repo"**
2. Select **Alpha060/Wallet**
3. Click **"Deploy Now"**

### Step 3: Add PostgreSQL Database
1. Click **"+ New"** in your project
2. Select **"Database"**
3. Choose **"PostgreSQL"**
4. Database will be created automatically

### Step 4: Configure Environment Variables
Railway auto-detects most variables, but verify these are set:

**Required Variables:**
```
NODE_ENV=production
DATABASE_URL=(auto-generated from PostgreSQL)
JWT_SECRET=(auto-generated)
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=Admin@123456
```

### Step 5: Your App is Live! 🎉
You'll get a URL like:
```
https://wallet-production-xxxx.up.railway.app
```

## 🔐 Default Admin Credentials

**Admin Account:**
- Email: `admin@example.com`
- Password: `Admin@123456`

⚠️ **IMPORTANT**: Change these immediately after first login!

## ✅ Test Your Deployment

After deployment (2-3 minutes):
- [ ] Visit your Railway URL
- [ ] Login with admin credentials
- [ ] Register a test user
- [ ] Upload KYC documents
- [ ] Test deposit/withdrawal flow
- [ ] Install PWA on mobile/desktop
- [ ] Test offline functionality

## 🎯 Important URLs

Replace `your-app-url` with your actual Railway URL:

- **Your App**: https://your-app-url.up.railway.app
- **PWA Test**: https://your-app-url.up.railway.app/pwa-test.html
- **Icon Generator**: https://your-app-url.up.railway.app/icons/create-placeholder-icons.html
- **Health Check**: https://your-app-url.up.railway.app/health
- **API Health**: https://your-app-url.up.railway.app/api/health

## 📊 Railway Dashboard Features

### View Logs
1. Go to Railway Dashboard
2. Click on your service
3. Click "Deployments" tab
4. Click "View Logs"

### Monitor Usage
1. Click "Metrics" tab
2. See CPU, Memory, Network usage
3. Monitor your $5 credit usage

### Custom Domain (Optional)
1. Click "Settings" tab
2. Scroll to "Domains"
3. Click "Generate Domain" for free subdomain
4. Or add your custom domain

## 💰 Cost Breakdown

**Free $5 Credit Usage:**
- Web Service: ~$0.10/day (if always running)
- PostgreSQL: ~$0.05/day
- **Total**: ~$0.15/day = **33 days** of testing!

Much better than Render's limitations!

## 🔄 Auto-Deploy Setup

Railway automatically deploys when you push to GitHub:

```bash
# Make changes
git add .
git commit -m "Your updates"
git push origin main
```

Deploy happens in 1-2 minutes! 🚀

## 📱 PWA Features

### Generate Icons
1. Visit: `https://your-app-url.up.railway.app/icons/create-placeholder-icons.html`
2. Download all icons
3. Replace placeholder icons in your repo
4. Push to GitHub for auto-deploy

### Install PWA
**Desktop:** Look for install button in browser address bar
**Mobile:** Use "Add to Home Screen" or install button on page

## 🐛 Troubleshooting

### App Not Starting
1. Check logs in Railway Dashboard
2. Verify environment variables are set
3. Check if database is connected

### Database Connection Issues
1. Ensure PostgreSQL service is running
2. Check DATABASE_URL is automatically set
3. Wait 1-2 minutes for database initialization

### 502/503 Errors
- App is starting up (wait 30 seconds)
- Check logs for errors
- Verify build completed successfully

## ⚡ Performance Tips

### Keep App Fast
- Railway doesn't sleep (unlike Render)
- First request is always fast
- No need for uptime monitoring

### Monitor Usage
- Check metrics regularly
- $5 credit lasts 30+ days for testing
- Upgrade to paid plan if needed

## 🔒 Security Features

Railway automatically provides:
- ✅ HTTPS/SSL certificates
- ✅ Environment variable encryption
- ✅ Private networking between services
- ✅ DDoS protection
- ✅ Automatic security updates

## 🎉 Success Checklist

- [ ] App deployed successfully
- [ ] Database connected and migrations ran
- [ ] Admin user created automatically
- [ ] Login works with admin credentials
- [ ] User registration works
- [ ] KYC upload functionality works
- [ ] Deposit/withdrawal flow works
- [ ] PWA installs on devices
- [ ] Offline mode functions
- [ ] Auto-deploy from GitHub works

## 🚀 Next Steps After Testing

If everything works perfectly:

### Option 1: Continue with Railway
- Add custom domain
- Upgrade to paid plan for production
- Set up monitoring and alerts

### Option 2: Migrate to VPS
- Export database
- Deploy to your own server
- Better long-term cost control

## 📞 Support Resources

- **Railway Docs**: https://docs.railway.app
- **Railway Discord**: https://discord.gg/railway
- **Status Page**: https://status.railway.app

## 💡 Pro Tips

1. **Custom Domain**: Railway provides free subdomains
2. **Environment Variables**: Use Railway's built-in secret management
3. **Database Backups**: Railway handles automatic backups
4. **Scaling**: Easy horizontal scaling when needed
5. **Monitoring**: Built-in metrics and logging

---

**Your KYC Management System is now ready for Railway deployment! 🎊**

**Total setup time: 3-5 minutes**
**Testing period: 30+ days with $5 credit**