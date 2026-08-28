# Kuwalee SiteFlow - Complete Backend (Phases 2-11)

This cumulative Laravel 11 backend includes authentication, organisation isolation, RBAC, projects/sites, reports, labour, materials, equipment/fuel, BOQ/measurement/billing/payments, documents/compliance, dashboards, PDF exports, security headers, rate limiting, security regression tests and performance indexes.

## Quick start (Mac)

```bash
cp .env.example .env
composer install
php artisan key:generate
# Configure DB_* values in .env
php artisan migrate --seed
php artisan test
php artisan serve
```

## Production validation

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan route:list --json > storage/app/route-list.json
php scripts/audit_routes.php storage/app/route-list.json
php artisan test
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
```

Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies, restricted CORS/Sanctum domains, private storage, queue workers, scheduler and tested backups before deployment.
