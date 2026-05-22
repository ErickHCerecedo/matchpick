#!/bin/bash
set -e

php artisan config:cache
php artisan route:cache
php artisan migrate --force

php artisan serve --host=0.0.0.0 --port=8000
