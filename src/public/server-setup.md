# KrishiGhor Server Setup Guide

## The Issue
If you're getting `ERR_FILE_NOT_FOUND` when clicking on dashboard links like "Analytics", it's likely because the server isn't properly configured to handle URL routing through `index.php`.

## Quick Fix Solutions

### Option 1: Use PHP Built-in Server (Recommended for Development)
```bash
cd C:\Users\User\Downloads\KrishiGhorv4
php -S localhost:8000 -t public
```

Then access the application at: `http://localhost:8000`

### Option 2: If using XAMPP/WAMP
1. Copy the `KrishiGhorv4` folder to your `htdocs` directory
2. Access via: `http://localhost/KrishiGhorv4/public/`
3. Make sure Apache's `mod_rewrite` is enabled

### Option 3: If using IIS
1. Ensure URL Rewrite module is installed
2. The `.htaccess` file should be automatically converted to `web.config`

## Testing the Fix
1. Visit: `http://localhost:8000/test-routing.php` to check if files are accessible
2. Try clicking on the Analytics link in the admin dashboard
3. If it works, you should see the analytics page with charts

## Common Issues and Solutions

### Issue: Still getting 404 errors
**Solution**: Make sure you're accessing through the web server (`http://localhost:8000`) and NOT opening files directly in browser (`file://`)

### Issue: Charts not loading
**Solution**: Check your internet connection as Chart.js is loaded from CDN

### Issue: Database connection errors
**Solution**: Ensure your Supabase credentials are correct in `src/config/database.php`

## File Structure Check
The following files should exist:
- `public/dashboard/admin/analytics.html` ✓
- `public/dashboard/admin/users.html` ✓
- `public/dashboard/farmer/analytics.html` ✓
- `public/dashboard/buyer/browse.html` ✓

## URL Routing
All URLs like `/dashboard/admin/analytics.html` are handled by `public/index.php` which includes the appropriate HTML file.

The `.htaccess` file ensures all non-file requests are routed through `index.php`.
