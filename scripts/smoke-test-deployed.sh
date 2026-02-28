#!/usr/bin/env bash
# Smoke test for a deployed Cloudflare Worker.
#
# Validates: HTTP 200s, OPcache hit rate >0% after warmup, X-Request-Count
# incrementing (no module cycling), and no Error 1102.
#
# Usage:
#   ./scripts/smoke-test-deployed.sh https://laraworker.kswb.dev
#   ./scripts/smoke-test-deployed.sh http://localhost:8787
#   ./scripts/smoke-test-deployed.sh https://laraworker.kswb.dev --requests=10
#   ./scripts/smoke-test-deployed.sh https://laraworker.kswb.dev --opcache-debug
#
# The --opcache-debug flag enables OPcache checks via /__opcache-status.
# Requires OPCACHE_DEBUG=true on the Worker. Without it, OPcache checks are skipped.

set -euo pipefail

# --- Defaults ---
BASE_URL=""
NUM_REQUESTS=10
OPCACHE_DEBUG=false

# --- Parse args ---
for arg in "$@"; do
    case "$arg" in
        --requests=*) NUM_REQUESTS="${arg#*=}" ;;
        --opcache-debug) OPCACHE_DEBUG=true ;;
        http://*|https://*) BASE_URL="${arg%/}" ;;
    esac
done

if [ -z "$BASE_URL" ]; then
    echo "Usage: $0 <URL> [--requests=N] [--opcache-debug]" >&2
    exit 1
fi

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BOLD='\033[1m'
NC='\033[0m'

# --- State ---
FAILURES=0
REQUEST_COUNTS=()
HTTP_CODES=()

echo ""
echo "============================================"
echo -e "  ${BOLD}Laraworker Smoke Test${NC}"
echo "  Target: $BASE_URL"
echo "  Requests: $NUM_REQUESTS"
echo "  OPcache debug: $OPCACHE_DEBUG"
echo "============================================"
echo ""

# --- Step 1: Sequential requests, capture status + X-Request-Count ---
echo -e "${BOLD}Sending $NUM_REQUESTS sequential requests...${NC}"

for i in $(seq 1 "$NUM_REQUESTS"); do
    HEADERS_FILE=$(mktemp)
    BODY=$(curl -s -o - -D "$HEADERS_FILE" --max-time 30 "$BASE_URL/" 2>&1) || true
    HTTP_CODE=$(grep -m1 'HTTP/' "$HEADERS_FILE" | grep -oE '[0-9]{3}' | head -1)
    HTTP_CODE="${HTTP_CODE:-000}"

    # Extract X-Request-Count header (case-insensitive)
    REQ_COUNT=$(grep -i '^x-request-count:' "$HEADERS_FILE" | tr -d '\r' | awk '{print $2}' | head -1)
    REQ_COUNT="${REQ_COUNT:-0}"

    REQUEST_COUNTS+=("$REQ_COUNT")
    HTTP_CODES+=("$HTTP_CODE")

    # Check for Error 1102
    if echo "$BODY" | grep -qi "exceeded resource limits\|error 1102" 2>/dev/null; then
        printf "  %2d  HTTP %-3s  X-Request-Count: %-4s  ${RED}ERROR 1102${NC}\n" "$i" "$HTTP_CODE" "$REQ_COUNT"
        FAILURES=$((FAILURES + 1))
    elif [ "$HTTP_CODE" != "200" ]; then
        printf "  %2d  HTTP ${RED}%-3s${NC}  X-Request-Count: %-4s\n" "$i" "$HTTP_CODE" "$REQ_COUNT"
        FAILURES=$((FAILURES + 1))
    else
        printf "  %2d  HTTP ${GREEN}%-3s${NC}  X-Request-Count: %-4s\n" "$i" "$HTTP_CODE" "$REQ_COUNT"
    fi

    rm -f "$HEADERS_FILE"
done

# --- Step 2: Check X-Request-Count is incrementing ---
echo ""
echo -e "${BOLD}Checking X-Request-Count...${NC}"

# On CF, requests may hit different isolates so counts won't strictly increment.
# But if every single response has count=1, the module is cycling on every request.
ALL_ONE=true
MAX_COUNT=0
HAS_HEADER=false

for count in "${REQUEST_COUNTS[@]}"; do
    if [ "$count" != "0" ]; then
        HAS_HEADER=true
    fi
    if [ "$count" != "1" ] && [ "$count" != "0" ]; then
        ALL_ONE=false
    fi
    if [ "$count" -gt "$MAX_COUNT" ] 2>/dev/null; then
        MAX_COUNT="$count"
    fi
done

if [ "$HAS_HEADER" = false ]; then
    echo -e "  ${YELLOW}SKIP: X-Request-Count header not present (older worker version?)${NC}"
elif [ "$ALL_ONE" = true ] && [ "$NUM_REQUESTS" -gt 2 ]; then
    echo -e "  ${RED}FAIL: X-Request-Count is 1 on every response — module cycling detected${NC}"
    echo "  Counts: ${REQUEST_COUNTS[*]}"
    FAILURES=$((FAILURES + 1))
else
    echo -e "  ${GREEN}PASS: X-Request-Count reached $MAX_COUNT (no constant module cycling)${NC}"
    echo "  Counts: ${REQUEST_COUNTS[*]}"
fi

# --- Step 3: OPcache hit rate via /__opcache-status ---
echo ""
echo -e "${BOLD}Checking OPcache...${NC}"

if [ "$OPCACHE_DEBUG" = true ]; then
    OPCACHE_RESPONSE=$(curl -s --max-time 15 "$BASE_URL/__opcache-status" 2>/dev/null) || OPCACHE_RESPONSE=""
    OPCACHE_HTTP=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "$BASE_URL/__opcache-status" 2>/dev/null) || OPCACHE_HTTP="000"

    if [ "$OPCACHE_HTTP" != "200" ]; then
        echo -e "  ${YELLOW}SKIP: /__opcache-status returned HTTP $OPCACHE_HTTP (OPCACHE_DEBUG not enabled on worker?)${NC}"
    else
        # Parse hits and misses from JSON
        HITS=$(echo "$OPCACHE_RESPONSE" | grep -oE '"hits"\s*:\s*[0-9]+' | head -1 | grep -oE '[0-9]+$')
        MISSES=$(echo "$OPCACHE_RESPONSE" | grep -oE '"misses"\s*:\s*[0-9]+' | head -1 | grep -oE '[0-9]+$')
        HITS="${HITS:-0}"
        MISSES="${MISSES:-0}"

        TOTAL=$((HITS + MISSES))
        if [ "$TOTAL" -gt 0 ]; then
            HIT_RATE=$((HITS * 100 / TOTAL))
        else
            HIT_RATE=0
        fi

        echo "  Hits: $HITS  Misses: $MISSES  Hit Rate: ${HIT_RATE}%"

        if [ "$HIT_RATE" -eq 0 ] && [ "$TOTAL" -gt 0 ]; then
            echo -e "  ${RED}FAIL: OPcache hit rate is 0% after $NUM_REQUESTS requests — cache not persisting${NC}"
            FAILURES=$((FAILURES + 1))
        elif [ "$HIT_RATE" -gt 0 ]; then
            echo -e "  ${GREEN}PASS: OPcache hit rate is ${HIT_RATE}%${NC}"
        else
            echo -e "  ${YELLOW}SKIP: No OPcache data available (0 hits + 0 misses)${NC}"
        fi
    fi
else
    echo -e "  ${YELLOW}SKIP: Pass --opcache-debug to check OPcache via /__opcache-status${NC}"
fi

# --- Summary ---
echo ""
echo "============================================"
if [ "$FAILURES" -gt 0 ]; then
    echo -e "  ${RED}${BOLD}FAILED: $FAILURES check(s) failed${NC}"
    echo "============================================"
    echo ""
    exit 1
else
    echo -e "  ${GREEN}${BOLD}PASSED: All checks passed${NC}"
    echo "============================================"
    echo ""
    exit 0
fi
