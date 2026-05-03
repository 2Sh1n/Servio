# SERVIO Local Setup Guide (XAMPP + Flutter)

The project contains four main parts:

- `serviopanel/` - PHP backend + admin/partner/customer routes
- `servioweb/` - Next.js web frontend
- `client/` - Flutter customer app
- `provider/` - Flutter provider app


## 1. Prerequisites

Install these first:

### Required
- **XAMPP** (Apache + MySQL + PHP)
- **Flutter SDK**
- **Android Studio** or VS Code with Flutter/Dart plugins
- **Git**
- **Composer**
- **Node.js + npm**

### Recommended
- A real Android device or Android emulator
- Postman for testing APIs
- A code editor such as VS Code

---

## 2. Suggested local folder structure

Place the full project inside your XAMPP `htdocs` directory.

Example:

```text
F:/XAMPP/htdocs/SERVIO/
  client/
  provider/
  serviopanel/
  servioweb/
```

If your XAMPP is installed on `C:`, then use:

```text
C:/xampp/htdocs/SERVIO/
```

---

## 3. Start XAMPP services

Open the XAMPP Control Panel and start:

- **Apache**
- **MySQL**

You can ignore FileZilla, Mercury, and Tomcat unless you specifically use them.

---

## 4. Backend setup (`serviopanel`)

The `serviopanel` folder looks like a CodeIgniter 4 application with:

- `.env`
- `spark`
- `public/`
- `app/Config/Routes*.php`
- payment controllers and admin/partner/customer routes

That means the backend should be served from the **`public`** folder.

### 4.1 Open the backend folder

```bash
cd F:/XAMPP/htdocs/SERVIO/serviopanel
```

Or:

```bash
cd C:/xampp/htdocs/SERVIO/serviopanel
```

### 4.2 Install PHP dependencies

If the project already includes a full `vendor/` folder, it may run immediately. Even so, a clean install is safer:

```bash
composer install
```

### 4.3 Configure `.env`

Open:

```text
serviopanel/.env
```

Make sure these values are correct for local development:

```env
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost/SERVIO/serviopanel/public/'

database.default.hostname = localhost
database.default.database = servio
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Notes:
- XAMPP MySQL usually uses `root` with an empty password by default.
- If your file uses `app_baseURL` or slightly different key names, keep the format already used by the project and only replace the values.
- If there are Redis, queue, mail, or payment settings in `.env`, leave them disabled or set them to sandbox values for local testing.

### 4.4 Create the database

Open **phpMyAdmin** and create a database, for example:

```text
servio
```

### 4.5 Import the schema/data

Use one of these approaches:

#### Option A - Recommended
Import the SQL file that came with the original purchased package.

This is usually the safest option because marketplace projects often depend on preloaded:
- settings
- system tables
- categories
- payment configuration rows
- language rows
- demo/admin defaults

#### Option B - If no SQL file is available
Try migrations:

```bash
php spark migrate
```

If the project includes seeders and you know which one to use, run them after migration.

```bash
php spark db:seed SeederName
```

> If migration succeeds but the admin panel still looks broken or empty, that usually means the app expects the original SQL dump rather than only migrations.

### 4.6 Backend URL options

You can run the backend in either of these ways.

#### Option A - Simple URL
Use the project directly through XAMPP:

```text
http://localhost/SERVIO/serviopanel/public/
```

#### Option B - Cleaner local virtual host (recommended)
Create a local host such as:

```text
http://servio.local/
```

Point it to:

```text
F:/XAMPP/htdocs/SERVIO/serviopanel/public
```

Example Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName servio.local
    DocumentRoot "F:/XAMPP/htdocs/SERVIO/serviopanel/public"
    <Directory "F:/XAMPP/htdocs/SERVIO/serviopanel/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Then add this to your Windows hosts file:

```text
127.0.0.1 servio.local
```

After that, restart Apache.

### 4.7 Test the backend

Open in your browser:

```text
http://localhost/SERVIO/serviopanel/public/
```

or:

```text
http://servio.local/
```

Also test the admin and partner areas if available.

---

## 5. Web frontend setup (`servioweb`)

The `servioweb` folder looks like a Next.js application and includes:

- `.env`
- `.env.local`
- `package.json`
- `next.config.mjs`
- `server.js`

### 5.1 Open the web folder

```bash
cd F:/XAMPP/htdocs/SERVIO/servioweb
```

### 5.2 Install dependencies

Even if `node_modules/` already exists, reinstalling is cleaner:

```bash
npm install
```

### 5.3 Configure the web environment

Open:

```text
servioweb/.env.local
```

Set the backend/API URL to your local backend.

Example:

```env
NEXT_PUBLIC_API_URL=http://localhost/SERVIO/serviopanel/public/
```

If you are using the cleaner virtual host:

```env
NEXT_PUBLIC_API_URL=http://servio.local/
```

If the project uses different variable names, keep the existing variable names and only replace the values.

### 5.4 Run the web app

Start the development server:

```bash
npm run dev
```

Then open:

```text
http://localhost:3000
```

If the package is configured around `server.js`, check the scripts in `package.json`. If `npm run dev` does not work, try the package's defined scripts or:

```bash
node server.js
```

---

## 6. Flutter customer app setup (`client`)

The `client` folder is the Flutter customer app.

### 6.1 Open the app folder

```bash
cd F:/XAMPP/htdocs/SERVIO/client
```

### 6.2 Install Flutter packages

```bash
flutter pub get
```

### 6.3 Check API configuration

From the project tree, the app most likely stores API endpoints in:

```text
client/lib/utils/api/apiUri.dart
client/lib/utils/api/apiClient.dart
client/lib/utils/api/apiParam.dart
```

Open those files and set the backend base URL.

#### If using Android emulator
Use:

```text
http://10.0.2.2/SERVIO/serviopanel/public/
```

because Android emulator cannot use `localhost` to reach your PC.

#### If using a physical phone on the same Wi-Fi
Use your PC's LAN IP, for example:

```text
http://192.168.1.50/SERVIO/serviopanel/public/
```

#### If using a virtual host like `servio.local`
That usually works in the browser on your PC, but mobile devices and emulators often cannot resolve it unless you configure DNS/hosts correctly. For mobile testing, an IP-based URL is safer.

### 6.4 Firebase and push notifications

The tree shows Firebase-related files such as:

- `client/lib/firebase_options.dart`
- backend `public/firebase_config.json`

If login, push notification, or Google sign-in features fail locally, check:
- Firebase project configuration
- Android package name
- SHA-1/SHA-256 fingerprints
- whether local API URLs match allowed domains/settings

### 6.5 Run the customer app

Check devices:

```bash
flutter devices
```

Run the app:

```bash
flutter run
```

If more than one device is connected:

```bash
flutter run -d <device_id>
```

---

## 7. Flutter provider app setup (`provider`)

The `provider` folder is the Flutter provider/vendor app.

### 7.1 Open the app folder

```bash
cd F:/XAMPP/htdocs/SERVIO/provider
```

### 7.2 Install Flutter packages

```bash
flutter pub get
```

### 7.3 Set the API base URL

Look in files like:

```text
provider/lib/utils/api/apiUri.dart
provider/lib/utils/api/apiClient.dart
```

Use the same backend base URL logic as the customer app:

- Android emulator: `http://10.0.2.2/SERVIO/serviopanel/public/`
- Physical device: `http://YOUR_PC_IP/SERVIO/serviopanel/public/`

### 7.4 Run the provider app

```bash
flutter run
```

---

## 8. Recommended local startup order

When developing locally, start the system in this order:

1. Start **Apache** and **MySQL** in XAMPP
2. Confirm the backend loads in the browser
3. Start the web frontend with `npm run dev`
4. Start the customer Flutter app
5. Start the provider Flutter app

This makes it easier to isolate whether a problem is coming from:
- the backend
- the web frontend
- the mobile apps
- or incorrect environment/API configuration

---

## 9. Local URLs cheat sheet

### Browser on your PC
- Backend: `http://localhost/SERVIO/serviopanel/public/`
- Admin: depends on backend routes
- Web: `http://localhost:3000`

### Android emulator
- Backend base URL for Flutter: `http://10.0.2.2/SERVIO/serviopanel/public/`

### Physical device on same network
- Backend base URL for Flutter: `http://YOUR_PC_IP/SERVIO/serviopanel/public/`

---

## 10. Common problems and fixes

### Problem: `404` or `Page Not Found` on backend
Fixes:
- Make sure Apache is serving `serviopanel/public`
- Make sure `mod_rewrite` is enabled
- Make sure `.htaccess` is allowed with `AllowOverride All`
- Try the full `/public/` URL first

### Problem: Flutter app cannot connect to backend
Fixes:
- Do not use `localhost` inside Android emulator
- Use `10.0.2.2` for emulator
- Use your PC IP for a real phone
- Check Windows firewall rules
- Make sure Apache is running

### Problem: API returns database errors
Fixes:
- Check database name, username, password, and port in `.env`
- Confirm tables were imported correctly
- If there is no SQL dump, try migrations

### Problem: Web app loads but data is empty
Fixes:
- Check `.env.local`
- Confirm the API base URL is correct
- Open browser devtools and inspect failed network requests

### Problem: Payment pages fail locally
Fixes:
- Use sandbox/test keys only
- Disable payment methods you are not testing
- Many gateways require callback URLs and public domains

### Problem: Notifications or social login fail
Fixes:
- Recheck Firebase setup
- Confirm Android app IDs and SHA keys
- Confirm backend notification settings

---

## 11. Recommended developer workflow

### Backend
- Edit PHP logic in `serviopanel/app/`
- Admin/partner/customer routes appear to be separated already
- Keep `.env` in development mode locally

### Customer app
- Work in `client/lib/`
- Keep API endpoint configuration centralized

### Provider app
- Work in `provider/lib/`
- Reuse the same local backend base URL

### Web
- Work in `servioweb/`
- Keep `.env.local` pointing at the same backend you use for mobile testing

---

## 12. Minimal run checklist

Use this as a quick checklist after setup:

- [ ] Apache is running
- [ ] MySQL is running
- [ ] Backend opens in browser
- [ ] Database imported or migrated
- [ ] `servioweb/.env.local` points to local backend
- [ ] `client` API base URL points to emulator IP or LAN IP
- [ ] `provider` API base URL points to emulator IP or LAN IP
- [ ] `npm run dev` works in `servioweb`
- [ ] `flutter run` works in `client`
- [ ] `flutter run` works in `provider`

---

## 13. One-line summary

To run the full system locally:
- serve `serviopanel/public` through XAMPP,
- connect it to a local MySQL database,
- point `servioweb` to that backend,
- point both Flutter apps to that same backend using `10.0.2.2` or your PC IP,
- then run the web and mobile apps separately.

