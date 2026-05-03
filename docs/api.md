# Servio API Guide

This file documents the basic API structure for Servio.

The API is handled by the backend/admin panel app:

```txt
apps/serviopanel
```

The web app and both Flutter apps connect to this backend API.

## API Base URL

Local development example:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1
```

If your local folder is different, adjust it.

Example if Apache points directly to `serviopanel/public`:

```txt
http://localhost/serviopanel/public/index.php/api/v1
```

Production example:

```txt
https://your-backend-domain.com/api/v1
```

If `index.php` is still required in production:

```txt
https://your-backend-domain.com/index.php/api/v1
```

## Apps That Use the API

```txt
apps/servio-web
apps/servio-customer-app
apps/servio-provider-app
```

## Main API Groups

The backend contains several API groups:

```txt
Customer API: /api/v1
Provider API: /partner/api/v1
Webhook API:  /api/webhooks
```

## Customer API

Base:

```txt
/api/v1
```

Used by:

```txt
servio-web
servio-customer-app
```

Common customer API functions include:

```txt
get_settings
get_providers
get_services
place_order
get_orders
update_order_status
invoice-download
get_categories
get_sub_categories
get_language_list
get_language_json_data
```

Example:

```txt
POST /api/v1/get_settings
```

Full local URL example:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1/get_settings
```

## Provider API

Base:

```txt
/partner/api/v1
```

Used by:

```txt
servio-provider-app
```

Common provider API functions include:

```txt
login
register
get_user_info
get_statistics
get_orders
update_order_status
get_services
manage_service
delete_service
get_withdrawal_request
send_withdrawal_request
get_notifications
get_categories
get_settings
```

Example:

```txt
POST /partner/api/v1/login
```

Full local URL example:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/partner/api/v1/login
```

## Webhook API

Base:

```txt
/api/webhooks
```

Used by payment gateways.

Webhook routes:

```txt
/api/webhooks/stripe
/api/webhooks/paystack
/api/webhooks/razorpay
/api/webhooks/paypal
/api/webhooks/flutterwave
/api/webhooks/xendit
/api/webhooks/cashfree
```

Production examples:

```txt
https://your-backend-domain.com/api/webhooks/stripe
https://your-backend-domain.com/api/webhooks/paystack
https://your-backend-domain.com/api/webhooks/razorpay
https://your-backend-domain.com/api/webhooks/paypal
https://your-backend-domain.com/api/webhooks/flutterwave
https://your-backend-domain.com/api/webhooks/xendit
https://your-backend-domain.com/api/webhooks/cashfree
```

If `index.php` is required:

```txt
https://your-backend-domain.com/index.php/api/webhooks/stripe
```

## Authentication

Most customer and provider API routes require an authentication token.

The token is usually sent in the request headers.

Example:

```txt
Authorization: Bearer YOUR_TOKEN_HERE
```

Some routes are public, such as:

```txt
get_settings
get_categories
get_language_list
get_language_json_data
get_providers
get_services
login
register
forgot-password
```

Protected routes usually include:

```txt
place_order
get_orders
update_order_status
get_user_info
manage_service
send_withdrawal_request
```

## Request Method

Most API endpoints use:

```txt
POST
```

Some use:

```txt
GET
```

Examples:

```txt
POST /api/v1/get_settings
POST /api/v1/get_providers
POST /api/v1/place_order
POST /api/v1/get_orders
GET  /partner/api/v1/get_country_codes
GET  /partner/api/v1/get_language_list
```

## Common Request Headers

Use these headers when testing APIs:

```txt
Accept: application/json
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN_HERE
```

For form-data requests:

```txt
Accept: application/json
Authorization: Bearer YOUR_TOKEN_HERE
```

Do not manually set `Content-Type: application/json` when uploading files with form-data.

## Common API Response Format

Most API responses follow this structure:

```json
{
  "error": false,
  "message": "Success message",
  "data": []
}
```

Error response example:

```json
{
  "error": true,
  "message": "Something went wrong",
  "data": []
}
```

Paginated response example:

```json
{
  "error": false,
  "message": "Data fetched successfully",
  "data": [],
  "total": "10"
}
```

## Important Customer Endpoints

### Get Settings

```txt
POST /api/v1/get_settings
```

Used to load web/app settings.

### Get Providers

```txt
POST /api/v1/get_providers
```

Common parameters:

```txt
latitude
longitude
slug
partner_id
category_id
category_slug
service_id
search
limit
offset
```

### Get Services

```txt
POST /api/v1/get_services
```

Common parameters:

```txt
provider_slug
category_id
search
limit
offset
```

### Place Order

```txt
POST /api/v1/place_order
```

Common parameters:

```txt
payment_method
status
date_of_service
starting_time
address_id
promo_code_id
at_store
order_note
```

This is where booking creation happens.

### Get Orders

```txt
POST /api/v1/get_orders
```

Common parameters:

```txt
limit
offset
status
id
custom_request_order
download_invoice
```

### Update Order Status

```txt
POST /api/v1/update_order_status
```

Common parameters:

```txt
order_id
status
date
time
```

## Important Provider Endpoints

### Provider Login

```txt
POST /partner/api/v1/login
```

### Provider Orders

```txt
POST /partner/api/v1/get_orders
```

### Update Provider Order Status

```txt
POST /partner/api/v1/update_order_status
```

### Manage Service

```txt
POST /partner/api/v1/manage_service
```

### Delete Service

```txt
POST /partner/api/v1/delete_service
```

### Withdraw Requests

```txt
POST /partner/api/v1/send_withdrawal_request
POST /partner/api/v1/get_withdrawal_request
POST /partner/api/v1/delete_withdrawal_request
```

## Image URLs

The backend returns full image URLs for:

```txt
provider profile image
provider banner image
service image
other provider images
category image
user profile image
```

Important upload folders:

```txt
apps/serviopanel/public/backend/assets/profile
apps/serviopanel/public/backend/assets/banner
apps/serviopanel/public/uploads/partner
apps/serviopanel/public/backend/assets/default.png
```

If the API returns `default.png`, check:

```txt
1. The uploaded file exists.
2. The database value is correct.
3. The backend image helper points to the correct folder.
4. The browser can open the image URL directly.
```

## CORS

The frontend runs on a different origin during development.

Example:

```txt
Frontend: http://localhost:3004
Backend:  http://localhost
```

The backend must allow the frontend origin.

Common CORS error:

```txt
Access to XMLHttpRequest has been blocked by CORS policy
```

For local development, allow:

```txt
http://localhost:3000
http://localhost:3001
http://localhost:3004
```

For production, allow your real web domain:

```txt
https://your-web-domain.com
```

## Local API Testing

Use Postman, Thunder Client, or curl.

Example curl request:

```bash
curl -X POST \
  "http://localhost/servio/apps/serviopanel/public/index.php/api/v1/get_settings" \
  -H "Accept: application/json"
```

Example authenticated request:

```bash
curl -X POST \
  "http://localhost/servio/apps/serviopanel/public/index.php/api/v1/get_orders" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## API Debug Checklist

If an API request fails:

```txt
[ ] Backend server is running
[ ] API base URL is correct
[ ] Route exists in CodeIgniter routes
[ ] .env baseURL is correct
[ ] Database is connected
[ ] CORS allows frontend origin
[ ] Token is valid if route is protected
[ ] Request method is correct
[ ] Required parameters are included
[ ] Upload folders have correct permissions
```

## Common Local API URLs

Depending on your local Apache setup, one of these will be correct:

```txt
http://localhost/servio/apps/serviopanel/public/index.php/api/v1/get_settings
http://localhost/serviopanel/public/index.php/api/v1/get_settings
http://localhost/servio/serviopanel/public/index.php/api/v1/get_settings
```

Use the one that matches your actual folder structure.