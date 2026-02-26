#!/usr/bin/env bash
# Build a minimal PHP 8.5 WASM binary with OPcache for Cloudflare Workers.
#
# Uses the seanmorris/php-wasm sm-8.5 branch with Docker-managed Emscripten.
# The Makefile orchestrates Docker containers for each build step — run this
# script from the HOST (not inside a container).
#
# Requirements:
#   - Docker (orbstack, rancher, or docker desktop)
#   - seanmorris/phpwasm-emscripten-builder image (pulled or built locally)
#     docker pull seanmorris/phpwasm-emscripten-builder:latest
#
# Usage:
#   cd php-wasm-build && ./build.sh
#   # Or from project root:
#   bash php-wasm-build/build.sh
#
# Build time: ~20-60 minutes depending on machine.
# Output:     php-wasm-build/php8.5-cgi-worker.mjs + .wasm

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$(mktemp -d /tmp/php-wasm-build-XXXXXX)"

echo "=== PHP WASM Builder ==="
echo "Builder dir : $BUILD_DIR"
echo ""

# Clone the sm-8.5 branch of seanmorris/php-wasm
echo "Cloning seanmorris/php-wasm (sm-8.5 branch)..."
git clone \
  --branch sm-8.5 \
  --single-branch \
  --depth 1 \
  https://github.com/seanmorris/php-wasm.git \
  "$BUILD_DIR"

# Copy our build settings into the build directory
# The Makefile reads PHP_VERSION, OPTIMIZE, ASYNCIFY, etc. from .env
cp "$SCRIPT_DIR/.php-wasm-rc" "$BUILD_DIR/.env"

# ── Patch scripts ──
# Copy our patch scripts into the build directory and inject them into the
# Makefile's patch flow. Scripts run inside Docker (via DOCKER_RUN) after the
# base php-wasm patches are applied but before configure runs.
#
# opcache-wasm-support.sh — Fixes Emscripten cross-compilation for OPcache:
#   1. Force mmap(MAP_ANON) shared memory detection to succeed
#   2. Add missing <unistd.h> include for getpid() in OPcache debug logging
#
# cgi-persistent-module.sh — Patches cgi_main.c for persistent module across requests.
#
# pdo-cfd1-setup.sh — Clones pdo-cfd1 and vrzno extensions into the PHP source tree:
#   - pdo-cfd1: PDO driver for Cloudflare D1 (bridges PDO to D1's JS API)
#   - vrzno: JS<->PHP value bridge (required by pdo-cfd1 for type conversion)
#   Both are statically linked into the WASM binary.
cp "$SCRIPT_DIR/patches/opcache-wasm-support.sh" "$BUILD_DIR/"
cp "$SCRIPT_DIR/patches/cgi-persistent-module.sh" "$BUILD_DIR/"
cp "$SCRIPT_DIR/patches/pdo-cfd1-setup.sh" "$BUILD_DIR/"

# Insert our patch scripts into the Makefile's "patched" target, right after
# git apply runs the base patches. Uses a literal tab for the Makefile recipe.
# Order matters: opcache-wasm-support runs first (fixes build), then
# cgi-persistent-module (patches runtime behavior), then pdo-cfd1-setup
# (clones extensions into ext/ before configure discovers them).
TAB=$'\t'
sed -i.bak "/git apply --no-index patch\/php\${PHP_VERSION}.patch/a\\
${TAB}\${DOCKER_RUN} bash opcache-wasm-support.sh \${PHP_VERSION}\\
${TAB}\${DOCKER_RUN} bash cgi-persistent-module.sh \${PHP_VERSION}\\
${TAB}\${DOCKER_RUN} bash pdo-cfd1-setup.sh \${PHP_VERSION}" \
  "$BUILD_DIR/Makefile"
rm -f "$BUILD_DIR/Makefile.bak"

# ── pdo-cfd1 + vrzno configure flags ──
# The upstream Makefile discovers extension packages via `npm ls -p` (workspace
# symlinks), but we skip `npm install` to keep the build fast. Instead, inject
# the configure flags directly. The patch script above already places the source
# in ext/, so configure will find the config.m4 files.
sed -i.bak '1 a\
CONFIGURE_FLAGS+= --enable-pdo-cfd1 --enable-vrzno\
EXTRA_FLAGS+= -D WITH_PDO_CFD1=1 -D WITH_VRZNO=1' \
  "$BUILD_DIR/Makefile"
rm -f "$BUILD_DIR/Makefile.bak"

echo ""
echo "Running build (make worker-cgi-mjs from host)..."
echo "The Makefile will spawn Docker containers for each compile step."
echo ""

# Run make from HOST — this is critical. The Makefile uses DOCKER_RUN to spawn
# containers for each build step. Running make inside a container causes
# Docker-in-Docker recursion. PHP_CONFIGURE_DEPS=null skips the empty
# dependency step (which would otherwise trigger `make all` by default).
(cd "$BUILD_DIR" && make worker-cgi-mjs ENV_FILE=.env PHP_CONFIGURE_DEPS=null)

echo ""
echo "Build complete."

# Find and measure the output WASM file
WASM_FILE=$(find "$BUILD_DIR/packages/php-cgi-wasm" -name "*.wasm" | head -1)
if [ -z "$WASM_FILE" ]; then
  echo "ERROR: No .wasm file found"
  exit 1
fi

WASM_UNCOMPRESSED=$(wc -c < "$WASM_FILE")
WASM_GZIPPED=$(gzip -c "$WASM_FILE" | wc -c)
WASM_UNCOMPRESSED_MB=$(echo "scale=2; $WASM_UNCOMPRESSED/1048576" | bc)
WASM_GZIPPED_MB=$(echo "scale=2; $WASM_GZIPPED/1048576" | bc)

echo ""
echo "=== Binary Size Report ==="
echo "  File         : $(basename "$WASM_FILE")"
echo "  Uncompressed : ${WASM_UNCOMPRESSED} bytes (${WASM_UNCOMPRESSED_MB} MB)"
echo "  Gzipped      : ${WASM_GZIPPED} bytes (${WASM_GZIPPED_MB} MB)"
echo ""

BUDGET_BYTES=$((3 * 1024 * 1024))
if [ "$WASM_GZIPPED" -lt "$BUDGET_BYTES" ]; then
  echo "  ✅ FITS within 3 MB budget"
else
  echo "  ❌ EXCEEDS 3 MB budget"
fi

# Copy output to php-wasm-build/
echo ""
echo "Copying output to $SCRIPT_DIR..."

# Copy all output files (mjs wrappers + wasm binary)
find "$BUILD_DIR/packages/php-cgi-wasm" \
  -name "php8.5-cgi-worker.*" \
  -o -name "PhpCgi*.mjs" \
  -o -name "OutputBuffer.mjs" \
  -o -name "fsOps.mjs" \
  -o -name "resolveDependencies.mjs" \
  -o -name "_Event.mjs" \
  -o -name "webTransactions.mjs" \
  -o -name "breakoutRequest.mjs" \
  -o -name "parseResponse.mjs" \
  -o -name "msg-bus.mjs" \
  2>/dev/null | while read -r f; do
  cp "$f" "$SCRIPT_DIR/"
done

echo "Cleaning up build directory..."
rm -rf "$BUILD_DIR"

echo ""
echo "Done! Output:"
ls -lh "$SCRIPT_DIR/"*.wasm "$SCRIPT_DIR/"*.mjs 2>/dev/null || true
