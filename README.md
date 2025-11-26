# Optima Depot Stock (Laravel Package)

Features: IN/OUT at 20°C, 0.3% allowance for IN, dip->volume with strapping CSV, invoices, payments, dashboards, imports-ready.
**Note:** Temperature correction uses a simplified approximation; swap with API/ASTM tables in production.

## Install
1. Add the path repo or publish to Packagist, then:
```
composer require optima/depot-stock
```
2. Publish & migrate:
```
php artisan vendor:publish --tag=depot-stock-config
php artisan vendor:publish --tag=depot-stock-views
php artisan vendor:publish --tag=depot-stock-migrations
php artisan migrate
```
3. Protect routes with your `auth` middleware and (optionally) roles.

## Routes
- `/depot/dashboard`
- `/depot/transactions`
- `/depot/dips`
- `/depot/invoices`
- `/depot/payments`

## Strapping CSV
CSV columns: `height_cm,volume_l` (one row per cm).
