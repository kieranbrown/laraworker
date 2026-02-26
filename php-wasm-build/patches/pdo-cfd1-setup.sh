#!/bin/bash
# PDO-CFD1 Extension Setup
#
# Clones the pdo-cfd1 and vrzno extensions into the PHP source tree's ext/ directory.
# These must be in place before ./configure runs so that --enable-pdo-cfd1 and
# --enable-vrzno flags can find their config.m4 files.
#
# Dependencies:
#   - pdo-cfd1: PDO driver for Cloudflare D1 (bridges PDO to D1's JS API via EM_ASM)
#   - vrzno: JS<->PHP value bridge (required by pdo-cfd1 for zval/JS type conversion)
#
# Both extensions are compiled statically into the WASM binary.
# Source: seanmorris/pdo-cfd1 and seanmorris/vrzno on GitHub.
#
# Usage: Called from the Makefile patched target via Docker.
#   bash pdo-cfd1-setup.sh <php_version>

set -euo pipefail

PHP_VERSION="${1:-8.5}"
PHP_SRC="third_party/php${PHP_VERSION}-src"

echo "=== Setting up pdo-cfd1 and vrzno extensions ==="

# ── Step 1: Clone vrzno (JS<->PHP bridge, required by pdo-cfd1) ──
#
# pdo-cfd1 includes "../vrzno/php_vrzno.h" and calls vrzno_fetch_object().
# The vrzno extension must be in the adjacent ext/ directory at compile time.
if [ ! -d "third_party/vrzno" ]; then
    echo "  [1/4] Cloning vrzno extension..."
    git clone https://github.com/seanmorris/vrzno.git third_party/vrzno \
        --branch master --single-branch --depth 1
else
    echo "  [1/4] SKIP: vrzno already cloned"
fi

# ── Step 2: Copy vrzno into PHP source tree ──
if [ -d "$PHP_SRC" ]; then
    echo "  [2/4] Copying vrzno into $PHP_SRC/ext/vrzno/"
    cp -TLprfv third_party/vrzno/ "$PHP_SRC/ext/vrzno/" > /dev/null 2>&1
else
    echo "  [2/4] ERROR: PHP source tree not found at $PHP_SRC"
    exit 1
fi

# ── Step 3: Clone pdo-cfd1 (PDO driver for Cloudflare D1) ──
if [ ! -d "third_party/pdo-cfd1" ]; then
    echo "  [3/4] Cloning pdo-cfd1 extension..."
    git clone https://github.com/seanmorris/pdo-cfd1.git third_party/pdo-cfd1 \
        --branch master --single-branch --depth 1
else
    echo "  [3/4] SKIP: pdo-cfd1 already cloned"
fi

# ── Step 4: Copy pdo-cfd1 into PHP source tree ──
# The extension directory uses underscore (pdo_cfd1) per PHP convention,
# matching the extension name in config.m4's PHP_NEW_EXTENSION call.
echo "  [4/4] Copying pdo-cfd1 into $PHP_SRC/ext/pdo_cfd1/"
cp -TLprfv third_party/pdo-cfd1/ "$PHP_SRC/ext/pdo_cfd1/" > /dev/null 2>&1

echo "=== pdo-cfd1 and vrzno setup complete ==="
