#!/bin/sh
set -e

cd /var/www/html

# Lien symbolique storage
php artisan storage:link --force 2>/dev/null || true

# Publier config Cloudinary
php artisan vendor:publish --provider="CloudinaryLabs\CloudinaryLaravel\CloudinaryServiceProvider" --force 2>/dev/null || true

# Cache config/routes pour la prod
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations
php artisan migrate --force --no-interaction 2>/dev/null || true

# Lancer PHP-FPM + Nginx via supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
