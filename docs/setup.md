# Servio Local Setup Guide

This guide explains how to run Servio locally after cloning the repository.

## Project Structure

```txt
servio/
├── apps/
│   ├── serviopanel/           # CodeIgniter backend, admin panel, APIs
│   ├── servio-web/            # Next.js web frontend
│   ├── servio-customer-app/   # Flutter customer app
│   └── servio-provider-app/   # Flutter provider app
├── docs/
│   ├── setup.md
│   ├── deployment.md
│   ├── api.md
│   └── database.md
├── .github/
│   └── workflows/
├── .gitignore
└── README.md
```

## Requirements

Install these first:

- PHP 8.1 or higher
- Composer
- MySQL or MariaDB
- Node.js 18 or higher
- npm
- Flutter SDK
- Git
- XAMPP, Laragon, MAMP, or another local PHP server

## 1. Clone the Repository

```bash
git clone https://github.com/YOUR_USERNAME/servio.git
cd servio
```

Replace `YOUR_USERNAME` with your GitHub username.

## 2. Backend Setup

The backend/admin panel is here:

```txt
apps/serviopanel
```

Go to the backend folder:

```bash
cd apps/serviopanel
```

Install PHP dependencies:

```bash
composer install
```

Create the backend environment file:

```bash
cp env .env
```

Open `.env` and set your database configuration:

```env
CI_ENVIRONMENT = development

database.default.hostname = localhost
database.default.database = servio
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Create the database in MySQL:

```sql
CREATE DATABASE servio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import your existing database SQL file if the project already has one.

If migrations are available, run:

```bash
php spark migrate
```

## 3. Backend Local URL

If using XAMPP or Apache, the backend URL may look like this:

```txt
http://localhost/servio/apps/serviopanel/public
```

The API URL may look like this:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

If using CodeIgniter's built-in server, run:

```bash
php spark serve
```

Then the backend URL is:

```txt
http://localhost:8080
```

And the API URL is:

```txt
http://localhost:8080/api/v1
```

## 4. Web Frontend Setup

The web frontend is here:

```txt
apps/servio-web
```

Open a new terminal and go to the web folder:

```bash
cd apps/servio-web
```

Install dependencies:

```bash
npm install
```

Create the frontend environment file:

```bash
cp .env.example .env.local
```

If `.env.example` does not exist, create `.env.local` manually.

Add your backend API URL:

```env
NEXT_PUBLIC_API_URL=http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

If using `php spark serve`, use this instead:

```env
NEXT_PUBLIC_API_URL=http://localhost:8080/api/v1
```

Run the web app:

```bash
npm run dev
```

The web app should now run at:

```txt
http://localhost:3000
```

If port `3000` is already used, Next.js may run on another port like:

```txt
http://localhost:3001
http://localhost:3004
```

## 5. Customer Flutter App Setup

The customer app is here:

```txt
apps/servio-customer-app
```

Go to the folder:

```bash
cd apps/servio-customer-app
```

Install Flutter dependencies:

```bash
flutter pub get
```

Run the app:

```bash
flutter run
```

Before running, check the app config and make sure the API URL points to your local backend.

Example API URL:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

For Android emulator, `localhost` may need to be changed to:

```txt
http://10.0.2.2/servio/apps/serviopanel/public/index.php/api/v1
```

## 6. Provider Flutter App Setup

The provider app is here:

```txt
apps/servio-provider-app
```

Go to the folder:

```bash
cd apps/servio-provider-app
```

Install Flutter dependencies:

```bash
flutter pub get
```

Run the app:

```bash
flutter run
```

Before running, check the app config and make sure the API URL points to your local backend.

## 7. Common Local URLs

Backend with Apache/XAMPP:

```txt
http://localhost/servio/apps/serviopanel/public
```

Backend API with Apache/XAMPP:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

Backend with CodeIgniter Spark:

```txt
http://localhost:8080
```

Backend API with CodeIgniter Spark:

```txt
http://localhost:8080/api/v1
```

Web frontend:

```txt
http://localhost:3000
```

## 8. Common Problems

### CORS error from the web app

Example error:

```txt
Access to XMLHttpRequest has been blocked by CORS policy
```

This means the web app is running on one URL, but the backend is not allowing requests from that URL.

Example:

```txt
Frontend: http://localhost:3004
Backend:  http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

Fix the backend CORS configuration so it allows the frontend origin.

For local development, allow:

```txt
http://localhost:3000
http://localhost:3001
http://localhost:3004
```

### API URL is wrong

Check this file:

```txt
apps/servio-web/.env.local
```

Make sure this value is correct:

```env
NEXT_PUBLIC_API_URL=http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

After changing `.env.local`, restart the web server:

```bash
npm run dev
```

### Image uploads do not show

Check that these folders exist and are writable:

```txt
apps/serviopanel/public/backend/assets/profile/
apps/serviopanel/public/backend/assets/banner/
apps/serviopanel/public/uploads/partner/
```

Also check that the database stores the correct image filename.

Provider profile images usually come from:

```txt
users.image
```

Provider banner images usually come from:

```txt
partner_details.banner
```

Provider additional images usually come from:

```txt
partner_details.other_images
```

### Composer dependencies missing

Run:

```bash
cd apps/serviopanel
composer install
```

### Node dependencies missing

Run:

```bash
cd apps/servio-web
npm install
```

### Flutter dependencies missing

Run this inside each Flutter app folder:

```bash
flutter pub get
```

## 9. Files That Should Not Be Committed

Do not commit:

```txt
.env
.env.local
vendor/
node_modules/
build/
.next/
storage logs
uploaded user files
database dumps
```

These should be ignored using `.gitignore`.

## 10. Basic Startup Order

Start things in this order:

1. Start MySQL.
2. Start Apache/XAMPP or run `php spark serve`.
3. Confirm the backend API works.
4. Start the web app with `npm run dev`.
5. Run the Flutter apps if needed.

## 11. Quick Start Commands

Backend:

```bash
cd apps/serviopanel
composer install
php spark serve
```

Web:

```bash
cd apps/servio-web
npm install
npm run dev
```

Customer app:

```bash
cd apps/servio-customer-app
flutter pub get
flutter run
```

Provider app:

```bash
cd apps/servio-provider-app
flutter pub get
flutter run
```