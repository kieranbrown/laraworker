#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLAYGROUND_DIR="$PROJECT_ROOT/playground"

if [ ! -d "$PLAYGROUND_DIR" ]; then
    echo "Nothing to tear down — playground/ does not exist."
    exit 0
fi

# Attempt to delete deployed Cloudflare Worker (best-effort)
if [ -f "$PLAYGROUND_DIR/artisan" ]; then
    echo "Attempting to delete deployed worker..."
    php "$PLAYGROUND_DIR/artisan" laraworker:delete --force --no-interaction 2>/dev/null || true
fi

echo "Removing playground/..."
rm -rf "$PLAYGROUND_DIR"
echo "Playground removed."
