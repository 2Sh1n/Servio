# Servio Database Guide

This file explains the database setup for Servio.

Servio uses one main backend database for:

```txt
apps/serviopanel
```

The web app and Flutter apps do not use their own databases directly. They communicate with the backend API, and the backend reads/writes to the database.

---

## 1. Database Engine

Use:

```txt
MySQL
```

or:

```txt
MariaDB
```

Recommended local setup:

```txt
XAMPP
WAMP
Laragon
MAMP
```

Recommended production setup:

```txt
MySQL 8+
MariaDB 10+
```

---

## 2. Database Name

Recommended database name:

```txt
servio
```

You can use another name, but then you must update the backend `.env` file.

---

## 3. Create the Database

In phpMyAdmin or MySQL, create a database:

```sql
CREATE DATABASE servio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 4. Backend Database Config

Database configuration is inside:

```txt
apps/serviopanel/.env
```

Example local database config:

```env
database.default.hostname = localhost
database.default.database = servio
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Example production database config:

```env
database.default.hostname = your-database-host
database.default.database = servio
database.default.username = your-database-user
database.default.password = your-database-password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

---

## 5. Import Existing Database

If you already have a `.sql` backup file, import it into the `servio` database.

Using phpMyAdmin:

```txt
1. Open phpMyAdmin
2. Select the servio database
3. Click Import
4. Choose the .sql file
5. Click Go
```

Using terminal:

```bash
mysql -u root -p servio < database.sql
```

Replace `database.sql` with your actual SQL backup file name.

---

## 6. Run Migrations

The backend is CodeIgniter 4.

Go to:

```bash
cd apps/serviopanel
```

Run migrations:

```bash
php spark migrate
```

If your project uses the browser migration route, open:

```txt
http://localhost/servio/apps/serviopanel/public/migrate
```

Production example:

```txt
https://your-backend-domain.com/migrate
```

Use migration routes carefully. Leaving migration routes publicly accessible is risky.

---

## 7. Rollback Migrations

To rollback using CLI:

```bash
php spark migrate:rollback
```

If your project uses the browser rollback route:

```txt
http://localhost/servio/apps/serviopanel/public/rollback
```

Production example:

```txt
https://your-backend-domain.com/rollback
```

Do not expose rollback routes in production unless they are protected.

---

## 8. Important Tables

Common important tables:

```txt
users
users_groups
partner_details
partner_subscriptions
services
categories
orders
order_services
transactions
cart
addresses
promo_codes
settings
languages
translated_partner_details
partner_timings
services_ratings
```

Actual table names may vary depending on migrations and installed modules.

---

## 9. Orders and Bookings

Main booking data is stored in:

```txt
orders
```

Service line items are stored in:

```txt
order_services
```

A booking can contain one or more services.

Important fields in `orders` usually include:

```txt
id
partner_id
user_id
address_id
date_of_service
starting_time
ending_time
duration
status
payment_method
payment_status
total
final_total
visiting_charges
created_at
parent_id
custom_job_request_id
```

Important fields in `order_services` usually include:

```txt
id
order_id
service_id
service_title
price
discount_price
quantity
tax_percentage
tax_amount
sub_total
status
```

---

## 10. Provider Images

Provider profile and banner image filenames are usually stored in database fields connected to:

```txt
users.image
partner_details.banner
partner_details.other_images
```

Important upload paths:

```txt
apps/serviopanel/public/backend/assets/profile
apps/serviopanel/public/backend/assets/banner
apps/serviopanel/public/uploads/partner
```

If images show as `default.png`, check:

```txt
1. The database has the correct filename.
2. The uploaded file exists in the correct folder.
3. The backend image URL helper points to the correct folder.
4. The file permissions allow the web server to read the image.
```

---

## 11. Backup Database

Before major changes, export the database.

Using phpMyAdmin:

```txt
1. Select database
2. Click Export
3. Choose SQL
4. Download the file
```

Using terminal:

```bash
mysqldump -u root -p servio > servio_backup.sql
```

For production, include the date:

```bash
mysqldump -u your_user -p servio > servio_backup_YYYY_MM_DD.sql
```

Example:

```bash
mysqldump -u root -p servio > servio_backup_2026_01_15.sql
```

---

## 12. Restore Database Backup

Using terminal:

```bash
mysql -u root -p servio < servio_backup.sql
```

Warning: restoring a backup may overwrite existing data.

---

## 13. Database Migration Safety Rules

Before running migrations:

```txt
[ ] Backup the database
[ ] Confirm you are using the correct environment
[ ] Confirm .env database credentials are correct
[ ] Test locally first
[ ] Do not run rollback in production unless required
```

---

## 14. Local Database Checklist

Use this checklist when setting up locally:

```txt
[ ] MySQL/MariaDB is running
[ ] Database named servio exists
[ ] SQL backup imported, if available
[ ] apps/serviopanel/.env has correct database config
[ ] php spark migrate works
[ ] Backend loads without database error
[ ] Admin login works
[ ] API get_settings works
```

Test API:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1/get_settings
```

---

## 15. Production Database Checklist

Use this checklist before deployment:

```txt
[ ] Production database created
[ ] Production database user created
[ ] Strong database password set
[ ] Database imported or migrated
[ ] apps/serviopanel/.env uses production database credentials
[ ] CI_ENVIRONMENT is production
[ ] Writable folder permissions are correct
[ ] Upload folders exist
[ ] Admin panel loads
[ ] API works
[ ] Booking creation works
[ ] Payment transaction records save correctly
```

---

## 16. Common Database Problems

### Database connection failed

Check:

```txt
database.default.hostname
database.default.database
database.default.username
database.default.password
database.default.port
```

Also confirm MySQL is running.

---

### Table does not exist

Run migrations:

```bash
php spark migrate
```

Or import the correct SQL backup.

---

### Unknown column error

This means the code expects a column that does not exist in your database.

Fix:

```txt
1. Run migrations
2. Check if your database backup is outdated
3. Compare the table structure with the expected code
```

---

### Images uploaded but not showing

Check database fields:

```txt
users.image
partner_details.banner
partner_details.other_images
```

Check upload folders:

```txt
public/backend/assets/profile
public/backend/assets/banner
public/uploads/partner
```

Open the image URL directly in the browser. If it gives 404, the file path or filename is wrong.

---

### Booking limit not working

Check the `orders` table.

The booking limit logic usually depends on:

```txt
user_id
created_at
status
parent_id
```

Make sure `created_at` is being saved correctly.

---

### Same-service booking restriction not working

Check these tables:

```txt
orders
order_services
```

The logic should check if the same user has an existing unfinished booking for the same `service_id`.

Usually unfinished statuses include:

```txt
awaiting
confirmed
rescheduled
started
booking_ended
```

Usually completed or cancelled bookings should not block a new booking:

```txt
completed
cancelled
```

---

## 17. Do Not Commit Database Dumps

Do not commit large or private SQL files to GitHub.

Do not commit:

```txt
*.sql
*.sql.gz
database backups
production dumps
customer data
payment records
```

Keep database backups outside Git, or store them securely.