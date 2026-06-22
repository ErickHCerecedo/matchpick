#!/bin/bash
set -e

php artisan config:cache
php artisan migrate --force

# Keep schedule:work alive — restart automatically if it exits for any reason
(while true; do
    echo "[scheduler] starting php artisan schedule:work"
    php artisan schedule:work || true
    echo "[scheduler] exited, restarting in 5s..."
    sleep 5
done) &

php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
