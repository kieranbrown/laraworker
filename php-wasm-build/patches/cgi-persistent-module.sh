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
#   Uses a static bool guard with goto — the same pattern already used extensively
#   in cgi_main.c (goto parent_out, goto fastcgi_request_done, etc.).
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
# Patch 1: After variable declarations, add a static guard that re-initializes
#           critical locals and jumps to the request handling section.
#
# Patch 2: Before zend_first_try, add the jump label and set the flag.
#
# Patch 3: Guard all php_module_shutdown() and sapi_shutdown() calls with
#           #ifndef __EMSCRIPTEN__ so the module persists across requests.
#
# Patch 4: Guard php_ini_builder_deinit() (UB on uninitialized local) and
#           php_ini_path_override free (potential double-free on repeat calls).

awk '
# ── Patch 1: Static guard after variable declarations ──
# All locals are declared at the top of main() (C89 style). On repeat calls,
# goto skips their initializers, leaving indeterminate values. We must reset
# every local used in the request path.
/^\tint skip_getopt = 0;$/ {
    print
    print ""
    print "#ifdef __EMSCRIPTEN__"
    print "\tstatic int _wasm_module_started = 0;"
    print "\tif (_wasm_module_started) {"
    print "\t\t/* Reset locals — goto skips their initializers */"
    print "\t\texit_status = SUCCESS;"
    print "\t\tfree_query_string = 0;"
    print "\t\tcgi = 0;"
    print "\t\tfastcgi = 0;"
    print "\t\tbehavior = PHP_MODE_STANDARD;"
    print "\t\tno_headers = 0;"
    print "\t\tscript_file = NULL;"
    print "\t\trequest = NULL;"
    print "\t\tbenchmark = 0;"
    print "\t\tskip_getopt = 0;"
    print "\t\tgoto wasm_handle_request;"
    print "\t}"
    print "#endif"
    next
}

# ── Patch 2: Label before zend_first_try ──
# The label must be OUTSIDE zend_first_try because zend_first_try expands to
# setjmp() setup — we need that to run on every call for proper exception handling.
/^\tzend_first_try \{$/ {
    print ""
    print "#ifdef __EMSCRIPTEN__"
    print "\t_wasm_module_started = 1;"
    print "wasm_handle_request: ;"
    print "#endif"
    print ""
    print
    next
}

# ── Patch 3: Guard php_module_shutdown() ──
# 3 occurrences: request_startup failure, fopen failure, end of main().
# All must be guarded to keep the module alive across requests.
/php_module_shutdown\(\);/ {
    print "#ifndef __EMSCRIPTEN__"
    print
    print "#endif"
    next
}

# ── Patch 4: Guard sapi_shutdown() ──
# 2 occurrences: fopen failure path, end of main().
/sapi_shutdown\(\);/ {
    print "#ifndef __EMSCRIPTEN__"
    print
    print "#endif"
    next
}

# ── Patch 5: Guard php_ini_builder_deinit (UB on uninitialized local) ──
# ini_builder is a local in main(). On repeat calls (via goto), it is
# uninitialized. Calling deinit on garbage is undefined behavior.
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

if ! grep -q 'wasm_handle_request' "${CGI_MAIN}.patched"; then
    echo "  ERROR: Jump label not found in patched file"
    exit 1
fi

mv "${CGI_MAIN}.patched" "$CGI_MAIN"

echo "  [1/1] Patched main() for persistent module across requests"
echo "=== CGI persistent module patch complete ==="
