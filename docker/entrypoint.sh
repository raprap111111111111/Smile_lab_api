#!/usr/bin/env bash
set -e

# Render assigns a dynamic port via $PORT (defaults to 10000 on Render)
export PORT=${PORT:-10000}
echo "🚀 [Startup] Initializing Smile Lab API on port ${PORT}..."

# Replace ${PORT} placeholder in Nginx config
if [ -f /etc/nginx/templates/default.conf.template ]; then
    envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/sites-available/default
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default 2>/dev/null || true
fi

# Ensure storage and cache directories exist and have proper permissions
mkdir -p /var/www/html/storage/app/public \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run storage symlink
echo "🔗 [Storage] Ensuring public storage link..."
php artisan storage:link --force || true

# Check Passport encryption keys
if [ -z "$PASSPORT_PRIVATE_KEY" ] && [ ! -f /var/www/html/storage/oauth-private.key ]; then
    echo "🔑 [Passport] No keys found in environment or storage. Generating new Passport encryption keys..."
    php artisan passport:keys --force --no-interaction || true
    chown www-data:www-data /var/www/html/storage/oauth-*.key 2>/dev/null || true
fi

# Wait for database availability (up to 30s)
if [ -n "$DB_HOST" ]; then
    echo "⏳ [Database] Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    MAX_RETRIES=30
    COUNT=0
    until php -r "
        \$h = getenv('DB_HOST');
        \$p = getenv('DB_PORT') ?: '3306';
        \$conn = @fsockopen(\$h, (int)\$p, \$errno, \$errstr, 2);
        if (is_resource(\$conn)) {
            fclose(\$conn);
            exit(0);
        }
        exit(1);
    " 2>/dev/null; do
        COUNT=$((COUNT+1))
        if [ $COUNT -ge $MAX_RETRIES ]; then
            echo "⚠️ [Database] Could not connect to database after 30 seconds. Proceeding anyway..."
            break
        fi
        sleep 1
    done
    echo "✅ [Database] Connection established."
fi

# Run database migrations
echo "📦 [Database] Running database migrations..."
php artisan migrate --force --no-interaction || {
    echo "⚠️ [Database] Migration failed or database not ready. Continuing startup..."
}

# Cache configurations for production performance
echo "⚡ [Cache] Optimizing config, routes, and views..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Define graceful shutdown
cleanup() {
    echo "🛑 [Shutdown] Stopping services..."
    kill -QUIT $(cat /var/run/php-fpm.pid 2>/dev/null) 2>/dev/null || true
    kill -QUIT $(cat /var/run/nginx.pid 2>/dev/null) 2>/dev/null || true
    exit 0
}
trap cleanup SIGINT SIGTERM

# Start PHP-FPM in daemon mode
echo "🐘 [PHP-FPM] Starting PHP-FPM..."
php-fpm -D -g /var/run/php-fpm.pid

# Start Nginx in foreground
echo "🌐 [Nginx] Starting Nginx on port ${PORT}..."
nginx -g "daemon off;"
