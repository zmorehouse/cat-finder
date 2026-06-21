#!/usr/bin/env bash
set -e

# ---------------------------------------------------------------------------
# Railway / production entrypoint.
# Runs DB migrations, the hourly scheduler, and the web dashboard in one
# container.
# ---------------------------------------------------------------------------

# Generate an ephemeral APP_KEY if one wasn't provided via env.
if [ -z "${APP_KEY}" ]; then
  export APP_KEY="$(php artisan key:generate --show)"
fi

# Ensure the SQLite database file exists when using the sqlite driver.
DB_CONNECTION="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONNECTION" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-$(pwd)/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_FILE")"
  touch "$DB_FILE"
fi

php artisan migrate --force

# Cache config/routes/views for performance (safe: app reads via config()).
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run Laravel's scheduler in the background. This triggers the hourly
# app:check-adoptions command (see routes/console.php).
php artisan schedule:work &

# Serve the dashboard on the port Railway provides.
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8080}"
