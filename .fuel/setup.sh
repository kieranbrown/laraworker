#!/usr/bin/env bash
set -euo pipefail

PROJECT_PATH="${FUEL_PROJECT_PATH:-.}"
cd "$PROJECT_PATH"

# --- PHP dependencies ---
if [ -f composer.lock ]; then
    composer install --no-interaction --quiet
else
    composer install --no-interaction
fi

# --- Node dependencies (bun — build tooling, wrangler, vite) ---
bun install

# --- Verify tooling ---
echo "Checking tooling..."
./vendor/bin/pest --version
./vendor/bin/pint --version

echo ""
echo "Setup complete."
echo "Project type: Composer library (no web server needed)"
echo "Project path: $PROJECT_PATH"
echo "Run tests: ./vendor/bin/pest --compact"
echo "Format code: ./vendor/bin/pint --dirty"
echo "Playground setup: scripts/playground-setup.sh"
