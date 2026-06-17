#!/bin/bash
set -e

php artisan config:cache
php artisan migrate --force
php artisan db:seed --force

# Laravel scheduler — runs in background within the same container
php artisan schedule:work &

php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
