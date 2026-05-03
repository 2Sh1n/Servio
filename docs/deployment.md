# Servio Deployment Guide

This guide explains the basic deployment plan for Servio.

Servio has four apps:

```txt
servio/
├── apps/
│   ├── serviopanel/           # CodeIgniter backend, admin panel, APIs
│   ├── servio-web/            # Next.js web frontend
│   ├── servio-customer-app/   # Flutter customer app
│   └── servio-provider-app/   # Flutter provider app
```

## Deployment Overview

Recommended deployment setup:

```txt
Backend/Admin Panel:  VPS, shared hosting, cPanel, or cloud server
Web Frontend:         Vercel, VPS, or Node.js hosting
Customer App:         Google Play Store / Apple App Store
Provider App:         Google Play Store / Apple App Store
Database:             MySQL or MariaDB
Uploads/Storage:      Server storage or cloud storage
```

## 1. Backend / Admin Panel Deployment

Backend folder:

```txt
apps/serviopanel
```

This is the CodeIgniter app. It contains:

- Admin panel
- Partner panel
- Customer APIs
- Provider APIs
- Payment webhooks
- File uploads
- Database connection

### Backend Server Requirements

The server needs:

```txt
PHP 8.1+
Composer
MySQL or MariaDB
Apache or Nginx
PHP extensions required by CodeIgniter and project packages
```

Common PHP extensions:

```txt
intl
mbstring
json
curl
mysqli
openssl
fileinfo
gd
zip
```

### Backend Deployment Steps

Upload this folder to the server:

```txt
apps/serviopanel
```

Install Composer dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

Create the production `.env` file:

```bash
cp env .env
```

Edit `.env`:

```env
CI_ENVIRONMENT = production

app.baseURL = https://your-backend-domain.com/

database.default.hostname = localhost
database.default.database = your_database_name
database.default.username = your_database_user
database.default.password = your_database_password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Import the production database.

Set the web server document root to:

```txt
apps/serviopanel/public
```

Do **not** point the domain to the root `serviopanel` folder. It should point to the `public` folder.

Correct:

```txt
apps/serviopanel/public
```

Wrong:

```txt
apps/serviopanel
```

### Backend File Permissions

Make sure these folders are writable:

```txt
apps/serviopanel/writable/
apps/serviopanel/public/uploads/
apps/serviopanel/public/backend/assets/profile/
apps/serviopanel/public/backend/assets/banner/
apps/serviopanel/public/uploads/partner/
```

Example Linux permissions:

```bash
chmod -R 775 writable
chmod -R 775 public/uploads
chmod -R 775 public/backend/assets/profile
chmod -R 775 public/backend/assets/banner
chmod -R 775 public/uploads/partner
```

## 2. Web Frontend Deployment

Web folder:

```txt
apps/servio-web
```

This is the Next.js web app.

### Web Environment File

Create a production environment file:

```txt
apps/servio-web/.env.production
```

Add:

```env
NEXT_PUBLIC_API_URL=https://your-backend-domain.com/api/v1
```

If your backend still uses `index.php`, use:

```env
NEXT_PUBLIC_API_URL=https://your-backend-domain.com/index.php/api/v1
```

### Deploy to Vercel

Recommended option for the web frontend.

In Vercel:

```txt
Root Directory: apps/servio-web
Framework: Next.js
Build Command: npm run build
Output Directory: .next
Install Command: npm install
```

Add the environment variable in Vercel:

```env
NEXT_PUBLIC_API_URL=https://your-backend-domain.com/api/v1
```

Then deploy.

### Deploy to VPS

Go to the web folder:

```bash
cd apps/servio-web
```

Install dependencies:

```bash
npm install
```

Build the app:

```bash
npm run build
```

Start the app:

```bash
npm start
```

For production, use PM2:

```bash
npm install -g pm2
pm2 start npm --name servio-web -- start
pm2 save
```

## 3. CORS Setup

The backend must allow requests from the web frontend domain.

Example:

```txt
Frontend: https://your-web-domain.com
Backend:  https://your-backend-domain.com
```

If CORS is not configured, the browser will block API requests.

Common error:

```txt
Access to XMLHttpRequest has been blocked by CORS policy
```

For local development, allow:

```txt
http://localhost:3000
http://localhost:3001
http://localhost:3004
```

For production, allow:

```txt
https://your-web-domain.com
```

Do not use `*` in production if the API uses authentication, cookies, or sensitive headers.

## 4. Database Deployment

Use MySQL or MariaDB.

Basic deployment steps:

1. Create a production database.
2. Create a database user.
3. Import the SQL dump.
4. Update backend `.env`.
5. Test admin login.
6. Test API endpoints.
7. Test image uploads.

Example production database config:

```env
database.default.hostname = localhost
database.default.database = servio_prod
database.default.username = servio_user
database.default.password = strong_password_here
database.default.DBDriver = MySQLi
database.default.port = 3306
```

## 5. Flutter Customer App Deployment

Customer app folder:

```txt
apps/servio-customer-app
```

Before building, update the API base URL inside the app config.

Use the production API URL:

```txt
https://your-backend-domain.com/api/v1
```

Build Android APK:

```bash
flutter build apk --release
```

Build Android App Bundle:

```bash
flutter build appbundle --release
```

Build iOS:

```bash
flutter build ios --release
```

The Android App Bundle is usually used for Google Play Store:

```txt
build/app/outputs/bundle/release/app-release.aab
```

## 6. Flutter Provider App Deployment

Provider app folder:

```txt
apps/servio-provider-app
```

Before building, update the API base URL inside the app config.

Use the production API URL:

```txt
https://your-backend-domain.com/api/v1
```

Build Android APK:

```bash
flutter build apk --release
```

Build Android App Bundle:

```bash
flutter build appbundle --release
```

Build iOS:

```bash
flutter build ios --release
```

## 7. Payment Webhooks

Payment gateways need webhook URLs that point to the backend.

Example webhook URLs:

```txt
https://your-backend-domain.com/api/webhooks/stripe
https://your-backend-domain.com/api/webhooks/paystack
https://your-backend-domain.com/api/webhooks/razorpay
https://your-backend-domain.com/api/webhooks/paypal
https://your-backend-domain.com/api/webhooks/flutterwave
https://your-backend-domain.com/api/webhooks/xendit
https://your-backend-domain.com/api/webhooks/cashfree
```

If your backend requires `index.php`, use:

```txt
https://your-backend-domain.com/index.php/api/webhooks/stripe
```

## 8. Firebase / Push Notifications

Firebase service worker must be reachable from the web app.

If Firebase messaging fails, check:

```txt
firebase-messaging-sw.js
```

Make sure it is available at the correct frontend or backend URL depending on the project setup.

Common error:

```txt
Messaging: We are unable to register the default service worker.
```

Check:

1. Firebase config values.
2. Service worker file exists.
3. Service worker URL returns JavaScript, not HTML.
4. Browser can access the service worker URL.
5. HTTPS is enabled in production.

## 9. Image Uploads

The backend serves uploaded images.

Important folders:

```txt
apps/serviopanel/public/backend/assets/profile/
apps/serviopanel/public/backend/assets/banner/
apps/serviopanel/public/uploads/partner/
```

If images show as `default.png`, check:

1. File exists on server.
2. File path in database is correct.
3. Folder permissions are correct.
4. API returns the correct image URL.
5. Browser can open the image URL directly.

## 10. Production Checklist

Before going live:

```txt
[ ] Backend .env uses CI_ENVIRONMENT = production
[ ] Backend baseURL is correct
[ ] Database credentials are correct
[ ] Web NEXT_PUBLIC_API_URL is correct
[ ] CORS allows the web domain
[ ] Upload folders are writable
[ ] Payment gateway keys are production keys
[ ] Payment webhooks are configured
[ ] Firebase keys are production keys
[ ] Admin login works
[ ] Customer login works
[ ] Provider login works
[ ] Booking works
[ ] Image upload works
[ ] Email/SMS/notification settings work
[ ] Database backup exists
```

## 11. Recommended Production Domains

Example clean setup:

```txt
Admin/API: https://panel.servio.com
Web:       https://servio.com
```

API URL used by web and apps:

```txt
https://panel.servio.com/api/v1
```

Webhook base URL:

```txt
https://panel.servio.com/api/webhooks
```

## 12. Deployment Rule

Do not deploy directly from random local changes.

Recommended flow:

```bash
git status
git add .
git commit -m "Describe the change"
git push origin main
```

Then deploy from GitHub or pull the latest code on the server.

## 13. Rollback Plan

If deployment breaks:

1. Check server logs.
2. Check backend `.env`.
3. Check database connection.
4. Check API URL in web/app config.
5. Revert to the previous Git commit if needed.

Rollback command:

```bash
git log --oneline
git checkout PREVIOUS_COMMIT_HASH
```

Or revert safely:

```bash
git revert BAD_COMMIT_HASH
git push origin main
```