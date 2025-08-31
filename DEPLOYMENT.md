# 🚀 KrishiGhor Deployment Guide

## 🌐 **Deploy Your KrishiGhor Application to Production**

This guide will help you deploy your KrishiGhor application to the web using Render, Railway, or Heroku.

## 🎯 **Option 1: Deploy to Render (Recommended)**

### **Step 1: Prepare Your Repository**

1. **Initialize Git** (if not already done):
   ```bash
   git init
   git add .
   git commit -m "Initial commit for deployment"
   ```

2. **Push to GitHub**:
   ```bash
   git remote add origin https://github.com/YOUR_USERNAME/krishighor.git
   git branch -M main
   git push -u origin main
   ```

### **Step 2: Deploy on Render**

1. **Go to Render**: https://render.com
2. **Sign up/Login** with your GitHub account
3. **Click "New +"** → **"Web Service"**
4. **Connect your GitHub repository**
5. **Configure the service**:
   - **Name**: `krishighor`
   - **Environment**: `PHP`
   - **Build Command**: `composer install --no-dev --optimize-autoloader`
   - **Start Command**: `vendor/bin/heroku-php-apache2 public/`
   - **Root Directory**: Leave empty (or `/`)

6. **Add Environment Variables**:
   ```
   DB_HOST=db.moozvhfbkhbepmjadijj.supabase.co
   DB_PORT=5432
   DB_NAME=postgres
   DB_USER=postgres
   DB_PASSWORD=system307projectG7
   DB_SSL_MODE=require
   APP_NAME=KrishiGhor
   APP_ENV=production
   APP_DEBUG=false
   APP_TIMEZONE=Asia/Dhaka
   JWT_SECRET=your-super-secret-jwt-key-2025-production
   ENCRYPTION_KEY=your-32-char-encryption-key-2025-production
   ```

7. **Click "Create Web Service"**

### **Step 3: Wait for Deployment**

- Render will automatically build and deploy your app
- You'll get a URL like: `https://krishighor.onrender.com`

## 🚂 **Option 2: Deploy to Railway**

### **Step 1: Prepare for Railway**

1. **Install Railway CLI**:
   ```bash
   npm install -g @railway/cli
   ```

2. **Login to Railway**:
   ```bash
   railway login
   ```

### **Step 2: Deploy**

1. **Initialize Railway project**:
   ```bash
   railway init
   ```

2. **Deploy**:
   ```bash
   railway up
   ```

3. **Get your URL**:
   ```bash
   railway domain
   ```

## 🦸 **Option 3: Deploy to Heroku**

### **Step 1: Install Heroku CLI**

Download from: https://devcenter.heroku.com/articles/heroku-cli

### **Step 2: Deploy**

1. **Login to Heroku**:
   ```bash
   heroku login
   ```

2. **Create Heroku app**:
   ```bash
   heroku create krishighor-app
   ```

3. **Add PostgreSQL addon** (optional, since you're using Supabase):
   ```bash
   heroku addons:create heroku-postgresql:mini
   ```

4. **Set environment variables**:
   ```bash
   heroku config:set DB_HOST=db.moozvhfbkhbepmjadijj.supabase.co
   heroku config:set DB_PORT=5432
   heroku config:set DB_NAME=postgres
   heroku config:set DB_USER=postgres
   heroku config:set DB_PASSWORD=system307projectG7
   heroku config:set DB_SSL_MODE=require
   heroku config:set APP_NAME=KrishiGhor
   heroku config:set APP_ENV=production
   heroku config:set APP_DEBUG=false
   ```

5. **Deploy**:
   ```bash
   git push heroku main
   ```

## 🔧 **Pre-Deployment Checklist**

### **✅ Code Ready**
- [ ] All files committed to Git
- [ ] Database schema created in Supabase
- [ ] Environment variables configured
- [ ] `.htaccess` file updated
- [ ] `composer.json` created

### **✅ Database Ready**
- [ ] Supabase tables created
- [ ] Sample data inserted
- [ ] Database connection tested
- [ ] SSL connection working

### **✅ Security Ready**
- [ ] Production environment variables set
- [ ] Debug mode disabled
- [ ] Sensitive files protected
- [ ] SSL certificates configured

## 🚨 **Post-Deployment Steps**

### **1. Test Your Live Application**
- Visit your deployed URL
- Test all major features
- Verify database connections
- Check for any errors

### **2. Set Up Custom Domain (Optional)**
- **Render**: Go to Settings → Custom Domains
- **Railway**: Use Railway CLI or dashboard
- **Heroku**: `heroku domains:add yourdomain.com`

### **3. Monitor Performance**
- Check application logs
- Monitor database performance
- Set up error tracking
- Monitor uptime

## 🔍 **Troubleshooting Common Issues**

### **Issue: Database Connection Failed**
- Verify Supabase credentials
- Check IP whitelist in Supabase
- Ensure SSL is enabled

### **Issue: 500 Internal Server Error**
- Check application logs
- Verify environment variables
- Check file permissions

### **Issue: Assets Not Loading**
- Verify `.htaccess` configuration
- Check file paths
- Ensure static files are accessible

## 📊 **Performance Optimization**

### **1. Enable Caching**
- Browser caching via `.htaccess`
- Database query optimization
- Static asset compression

### **2. CDN Setup**
- Use Cloudflare for static assets
- Optimize image delivery
- Enable HTTP/2

### **3. Database Optimization**
- Monitor query performance
- Add missing indexes
- Use connection pooling

## 🌟 **Your Live Application**

Once deployed, your KrishiGhor application will be available at:
- **Render**: `https://krishighor.onrender.com`
- **Railway**: `https://krishighor.railway.app`
- **Heroku**: `https://krishighor-app.herokuapp.com`

## 🎉 **Congratulations!**

Your KrishiGhor application is now live on the web! Farmers and buyers can access it from anywhere in the world.

---

**Need Help?** Check the troubleshooting section or contact the deployment platform's support.
