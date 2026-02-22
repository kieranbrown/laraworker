#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLAYGROUND_DIR="$PROJECT_ROOT/playground"

FORCE=false
for arg in "$@"; do
    case "$arg" in
        --force) FORCE=true ;;
    esac
done

# Check dependencies
for cmd in composer bun; do
    if ! command -v "$cmd" &>/dev/null; then
        echo "Error: '$cmd' is not installed or not in PATH." >&2
        exit 1
    fi
done

# Handle existing playground
if [ -d "$PLAYGROUND_DIR" ]; then
    if [ "$FORCE" = true ]; then
        echo "Removing existing playground (--force)..."
        rm -rf "$PLAYGROUND_DIR"
    else
        echo "Error: playground/ already exists. Use --force to reset, or run playground-teardown.sh first." >&2
        exit 1
    fi
fi

echo "Creating fresh Laravel project in playground/..."
composer create-project laravel/laravel "$PLAYGROUND_DIR" --no-interaction --quiet

echo "Adding laraworker path repository..."
composer config repositories.laraworker path "$PROJECT_ROOT" --working-dir="$PLAYGROUND_DIR"

echo "Requiring kieranbrown/laraworker..."
composer require kieranbrown/laraworker "@dev" --no-interaction --working-dir="$PLAYGROUND_DIR"

echo "Running laraworker:install..."
php "$PLAYGROUND_DIR/artisan" laraworker:install --no-interaction

echo "Installing npm dependencies..."
(cd "$PLAYGROUND_DIR" && bun install)

echo "Restoring autoloader..."
composer dump-autoload --working-dir="$PLAYGROUND_DIR" --quiet

echo ""
echo "Playground setup complete!"
echo "Next steps:"
echo "  scripts/playground-build.sh   — Build for Cloudflare Workers"
echo "  scripts/playground-dev.sh     — Start local dev server"
echo "  scripts/playground-teardown.sh — Remove playground"
