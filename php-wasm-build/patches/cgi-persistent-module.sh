#!/bin/bash
# CGI Persistent Module Patch
#
# Patches cgi_main.c to persist the PHP module (and OPcache) across requests
# instead of doing full php_module_startup()/php_module_shutdown() per request.
#
# Background:
#   PhpCgiBase.mjs calls the WASM-exported main() for each HTTP request.
#   Without this patch, every call does:
#     php_module_startup() → process request → php_module_shutdown()
#   This destroys OPcache's shared memory each time, making it harmful (~2.5x slower).
#
#   With this patch:
#   - First call: full startup, then handle request
#   - Subsequent calls: skip startup, jump directly to request handling
#   - php_module_shutdown()/sapi_shutdown() never run between requests
#
# Approach:
#   Wraps the one-time startup code in an if(!_wasm_module_started) block.
#   On the first call, all startup runs and sets the flag. On subsequent
#   calls, startup is skipped entirely — only the request handling in
#   zend_first_try runs.
#
#   This avoids goto (which can confuse Asyncify's stack transformation)
#   and uses a simple if-guard instead.
#
# Usage: Called from the Makefile patched target via Docker.
#   bash cgi-persistent-module.sh <php_version>

set -euo pipefail

PHP_VERSION="${1:-8.5}"
PHP_SRC="third_party/php${PHP_VERSION}-src"
CGI_MAIN="$PHP_SRC/sapi/cgi/cgi_main.c"

echo "=== Applying CGI persistent module patch ==="

if [ ! -f "$CGI_MAIN" ]; then
    echo "  ERROR: $CGI_MAIN not found"
    exit 1
fi

# Single-pass awk script that applies all modifications.
#
# Patch 1: After the last local declaration (skip_getopt), open an if-guard
#           that wraps ALL startup code. First call runs startup; subsequent
#           calls skip directly to zend_first_try (request handling).
#
# Patch 2: Before zend_first_try, close the if-guard and set the flag.
#
# Patch 3: Guard all php_module_shutdown() and sapi_shutdown() calls with
#           #ifndef __EMSCRIPTEN__ so the module persists across requests.
#
# Patch 4: Guard php_ini_builder_deinit() (UB on uninitialized local) and
#           php_ini_path_override free (potential double-free on repeat calls).

awk '
# ── Patch 1: Open if-guard after variable declarations ──
# All locals are declared at the top of main() (C89 style). We add the
# static flag and open an if-block that wraps all one-time startup code.
# Local initializers (skip_getopt = 0, etc.) still run on every call since
# they are ABOVE the if-guard.
/^\tint skip_getopt = 0;$/ {
    print
    print ""
    print "#ifdef __EMSCRIPTEN__"
    print "\tstatic int _wasm_module_started = 0;"
    print "\tif (!_wasm_module_started) {"
    print "#endif"
    next
}

# ── Patch 2: Close if-guard before zend_first_try ──
# Set the flag and close the if-block. Everything after this (zend_first_try
# and request handling) runs on EVERY call.
/^\tzend_first_try \{$/ {
    print ""
    print "#ifdef __EMSCRIPTEN__"
    print "\t_wasm_module_started = 1;"
    print "\t} /* !_wasm_module_started */"
    print "#endif"
    print ""
    print
    next
}

# ── Patch 3: Guard php_module_shutdown() ──
# All occurrences must be guarded to keep the module alive across requests.
/php_module_shutdown\(\);/ {
    print "#ifndef __EMSCRIPTEN__"
    print
    print "#endif"
    next
}

# ── Patch 4: Guard sapi_shutdown() ──
/sapi_shutdown\(\);/ {
    print "#ifndef __EMSCRIPTEN__"
    print
    print "#endif"
    next
}

# ── Patch 5: Guard php_ini_builder_deinit (UB on uninitialized local) ──
# ini_builder is initialized inside the if-guard. On subsequent calls
# (when the if-guard is skipped), ini_builder is uninitialized. Calling
# deinit on it is undefined behavior.
/php_ini_builder_deinit\(&ini_builder\);/ {
    print "#ifndef __EMSCRIPTEN__"
    print
    print "#endif"
    next
}

# ── Patch 6: Guard php_ini_path_override free ──
# Set once during getopt (-c option). Freeing it on every request would
# leave a dangling pointer. In WASM, -c is never passed, but guard anyway.
/cgi_sapi_module\.php_ini_path_override\)/ {
    print "#ifndef __EMSCRIPTEN__"
    print
    getline; print
    getline; print
    print "#endif"
    next
}

# Default: print line unchanged
{ print }
' "$CGI_MAIN" > "${CGI_MAIN}.patched"

# Verify the patch produced output and contains our markers
if [ ! -s "${CGI_MAIN}.patched" ]; then
    echo "  ERROR: Patched file is empty"
    exit 1
fi

if ! grep -q '_wasm_module_started' "${CGI_MAIN}.patched"; then
    echo "  ERROR: Static guard not found in patched file"
    exit 1
fi

if ! grep -q '!_wasm_module_started' "${CGI_MAIN}.patched"; then
    echo "  ERROR: If-guard not found in patched file"
    exit 1
fi

mv "${CGI_MAIN}.patched" "$CGI_MAIN"

echo "  [1/1] Patched main() for persistent module across requests"
echo "=== CGI persistent module patch complete ==="
