#!/usr/bin/env bash
set -euo pipefail

PROJECT_PATH="${FUEL_PROJECT_PATH:-.}"
cd "$PROJECT_PATH"

# --- PHP dependencies ---
composer install --no-interaction

# --- Node dependencies (for stubs/worker build tooling) ---
bun install

echo "Setup complete."
