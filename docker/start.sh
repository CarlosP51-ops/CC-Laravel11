#!/bin/sh
set -e

cd /var/www/html

# Lien symbolique storage (ignore si déjà fait)
php artisan storage:link --force 2>/dev/null || true

# Cache config/routes pour la prod
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations — ignore les tables déjà existantes
php artisan migrate --force --no-interaction 2>/dev/null || true

# Lancer PHP-FPM + Nginx via supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
