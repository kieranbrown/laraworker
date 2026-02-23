#!/bin/bash
# OPcache WASM Support Patches
#
# Patches the PHP source tree to enable OPcache shared memory in Emscripten.
# Applied after the base php-wasm patches and before configure.
#
# Background:
#   OPcache needs shared memory to store compiled opcodes. During cross-compilation
#   for Emscripten, the autoconf runtime tests for mmap/sysvipc can't execute, so
#   they default to "no". Emscripten provides a single-process mmap() implementation
#   that works for OPcache (no inter-process sharing needed in WASM).
#
#   WordPress Playground uses the same approach (PR #2400, July 2025).
#
# Usage: Called from the Makefile patched target via Docker.
#   bash opcache-wasm-support.sh <php_version>

set -euo pipefail

PHP_VERSION="${1:-8.5}"
PHP_SRC="third_party/php${PHP_VERSION}-src"

echo "=== Applying OPcache WASM support patches ==="

# ── Patch 1: Force mmap(MAP_ANON) shared memory detection ──
#
# PHP's configure uses AC_RUN_IFELSE to test mmap(). Cross-compilation can't run
# the test binary, so the result defaults to "no" for non-Linux hosts.
# Emscripten's host alias (wasm32-unknown-emscripten) doesn't match *linux*,
# so we force the cache variable to "yes".
#
# Emscripten's mmap() allocates from the WASM linear heap. In our single-process
# environment, this is sufficient — OPcache only needs process-local memory to
# cache opcodes across requests within the same WASM instance.
if [ -f "$PHP_SRC/ext/opcache/config.m4" ]; then
  sed -i 's/php_cv_shm_mmap_anon=no/php_cv_shm_mmap_anon=yes/g' \
    "$PHP_SRC/ext/opcache/config.m4"
  echo "  [1/2] Forced php_cv_shm_mmap_anon=yes in config.m4"
else
  echo "  [1/2] SKIP: config.m4 not found (expected for PHP $PHP_VERSION)"
fi

# ── Patch 2: Add unistd.h include for getpid() ──
#
# zend_accelerator_debug.c uses getpid() for log messages but only includes
# <process.h> on Windows. Emscripten provides getpid() in <unistd.h>.
# Without this include, the compiler emits "call to undeclared function 'getpid'".
ACCEL_DEBUG="$PHP_SRC/ext/opcache/zend_accelerator_debug.c"
if [ -f "$ACCEL_DEBUG" ]; then
  if ! grep -q 'include <unistd.h>' "$ACCEL_DEBUG"; then
    sed -i '/# include <process.h>/a\
#else\
# include <unistd.h>' "$ACCEL_DEBUG"
    echo "  [2/2] Added unistd.h include for getpid() in zend_accelerator_debug.c"
  else
    echo "  [2/2] SKIP: unistd.h already present in zend_accelerator_debug.c"
  fi
else
  echo "  [2/2] SKIP: zend_accelerator_debug.c not found"
fi

echo "=== OPcache WASM patches complete ==="
