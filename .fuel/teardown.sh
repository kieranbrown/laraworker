#!/usr/bin/env bash
set -euo pipefail

PROJECT_PATH="${FUEL_PROJECT_PATH:-.}"
PLAYGROUND_DIR="$PROJECT_PATH/playground"

# Attempt to delete deployed Cloudflare Worker (best-effort)
if [ -d "$PLAYGROUND_DIR" ] && [ -f "$PLAYGROUND_DIR/artisan" ]; then
    echo "Cleaning up deployed worker..."
    php "$PLAYGROUND_DIR/artisan" laraworker:delete --force --no-interaction 2>/dev/null || true
fi

# Clean up playground if it exists
if [ -d "$PLAYGROUND_DIR" ]; then
    "$PROJECT_PATH/scripts/playground-teardown.sh"
fi

echo "Teardown complete."
