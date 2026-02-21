#!/usr/bin/env bash
set -euo pipefail

# --- Fuel env vars ---
PORT="${FUEL_PORT_BASE:-8000}"
PROJECT_PATH="${FUEL_PROJECT_PATH:-.}"

cd "$PROJECT_PATH"

# --- PHP dependencies ---
composer install --no-interaction

# --- .env setup ---
if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

# Set APP_URL with correct port
sed -i.bak "s|^APP_URL=.*|APP_URL=http://localhost:${PORT}|" .env && rm -f .env.bak

# --- App key ---
php artisan key:generate --no-interaction

# --- Database ---
touch database/database.sqlite
php artisan migrate --force --no-interaction

# --- Frontend dependencies ---
bun install

# --- Build frontend assets ---
bun run build

# --- Print key URLs for detection ---
echo "App URL: http://localhost:${PORT}"
echo "Database: database/database.sqlite"

echo "Setup complete."
