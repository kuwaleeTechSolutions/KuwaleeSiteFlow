#!/usr/bin/env bash
set -euo pipefail
command -v php >/dev/null || { echo 'PHP 8.3+ is required'; exit 2; }
command -v composer >/dev/null || { echo 'Composer is required'; exit 2; }
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan route:list --json > storage/app/route-list.json
php scripts/audit_routes.php storage/app/route-list.json
php artisan test
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
