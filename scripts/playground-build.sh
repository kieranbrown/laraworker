#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLAYGROUND_DIR="$PROJECT_ROOT/playground"

if [ ! -d "$PLAYGROUND_DIR" ]; then
    echo "Error: playground/ does not exist. Run playground-setup.sh first." >&2
    exit 1
fi

echo "Building laraworker..."
php "$PLAYGROUND_DIR/artisan" laraworker:build --no-interaction

echo "Build complete!"
