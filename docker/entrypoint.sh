#!/bin/bash

set -e

echo "Starting application setup..."

# Wait for database to be ready (simple check)
if [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "pgsql" ]; then
    echo "Waiting for database connection..."
    until php -r "
        try {
            \$pdo = new PDO(
                '${DB_CONNECTION}:host=${DB_HOST};port=${DB_PORT}',
                '${DB_USERNAME}',
                '${DB_PASSWORD}'
            );
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo 'Database is ready!';
            exit(0);
        } catch (PDOException \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        echo "Database is unavailable - sleeping"
        sleep 2
    done
    echo "Database is up!"
fi

# Run migrations (only if not already run)
if [ -f "/var/www/html/.migrated" ]; then
    echo "Migrations already run, skipping..."
else
    echo "Running migrations..."
    php artisan migrate --force || true
    touch /var/www/html/.migrated
fi

# Clear and cache config (only in production)
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Set permissions
echo "Setting permissions..."
chown -R www:www /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

echo "Application is ready!"

exec "$@"

