#!/usr/bin/env bash
# Continuous stress test for Laraworker memory limits and OPcache.
#
# Reproduces "Worker exceeded resource limits" (Error 1102) and monitors
# OPcache hit rates to verify the cgi-persistent-module patch is working.
#
# Usage:
#   ./scripts/stress-test.sh [URL]                        # default 120 requests
#   ./scripts/stress-test.sh https://laraworker.kswb.dev  # production
#   ./scripts/stress-test.sh http://localhost:8787         # local wrangler dev
#   ./scripts/stress-test.sh [URL] --requests=200         # custom count
#   ./scripts/stress-test.sh [URL] --delay=0.5            # delay between requests
#   ./scripts/stress-test.sh [URL] --log=/tmp/stress.csv  # save CSV log

set -euo pipefail

# --- Defaults ---
BASE_URL="${1:-https://laraworker.kswb.dev}"
# Strip trailing slash
BASE_URL="${BASE_URL%/}"

NUM_REQUESTS=120
DELAY=0.1
LOG_FILE=""
OPCACHE_INTERVAL=10  # Check OPcache stats every N requests

# --- Parse args ---
for arg in "$@"; do
    case "$arg" in
        --requests=*) NUM_REQUESTS="${arg#*=}" ;;
        --delay=*) DELAY="${arg#*=}" ;;
        --log=*) LOG_FILE="${arg#*=}" ;;
        --opcache-interval=*) OPCACHE_INTERVAL="${arg#*=}" ;;
        http://*|https://*) BASE_URL="${arg%/}" ;;
    esac
done

# --- Pages to cycle through ---
PAGES=("/" "/performance" "/architecture" "/features")

# --- Counters ---
TOTAL=0
SUCCESS=0
FAILURES=0
RESOURCE_LIMIT_ERRORS=0
OTHER_ERRORS=0
TOTAL_TIME=0

# OPcache snapshots stored in temp file: "request_num hits misses" per line
OPCACHE_SNAPSHOTS_FILE=$(mktemp)
trap "rm -f $OPCACHE_SNAPSHOTS_FILE" EXIT

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- CSV log ---
if [ -n "$LOG_FILE" ]; then
    echo "request,page,status,time_ms,error,opcache_hits,opcache_misses" > "$LOG_FILE"
fi

log_csv() {
    if [ -n "$LOG_FILE" ]; then
        echo "$1" >> "$LOG_FILE"
    fi
}

# --- Fetch and display OPcache stats via Inertia JSON ---
# Prints stats to stdout and appends snapshot to temp file.
# Args: $1 = request number for the snapshot label
fetch_opcache_stats() {
    local req_num="${1:-0}"
    local response
    response=$(curl -s --max-time 30 \
        -H "X-Inertia: true" \
        -H "X-Inertia-Version: " \
        "$BASE_URL/performance" 2>/dev/null) || {
        echo -e "  ${YELLOW}Could not fetch OPcache stats (curl failed)${NC}"
        return 1
    }

    local hits misses cached_scripts used_mem free_mem
    hits=$(echo "$response" | grep -o '"hits":[0-9]*' | head -1 | cut -d: -f2)
    misses=$(echo "$response" | grep -o '"misses":[0-9]*' | head -1 | cut -d: -f2)
    cached_scripts=$(echo "$response" | grep -o '"cachedScripts":[0-9]*' | head -1 | cut -d: -f2)
    used_mem=$(echo "$response" | grep -o '"usedMemory":[0-9]*' | head -1 | cut -d: -f2)
    free_mem=$(echo "$response" | grep -o '"freeMemory":[0-9]*' | head -1 | cut -d: -f2)

    # Fallback if parsing failed
    hits="${hits:-0}"
    misses="${misses:-0}"
    cached_scripts="${cached_scripts:-0}"
    used_mem="${used_mem:-0}"
    free_mem="${free_mem:-0}"

    local total=$((hits + misses))
    local hit_rate=0
    if [ "$total" -gt 0 ]; then
        hit_rate=$((hits * 100 / total))
    fi

    local used_mb total_mb
    used_mb=$(awk "BEGIN {printf \"%.1f\", $used_mem / 1048576}")
    total_mb=$(awk "BEGIN {printf \"%.1f\", ($used_mem + $free_mem) / 1048576}")

    # Store snapshot
    echo "$req_num $hits $misses" >> "$OPCACHE_SNAPSHOTS_FILE"

    printf "  ${CYAN}OPcache:${NC} hits=%s misses=%s rate=${BOLD}%s%%${NC} scripts=%s mem=%sMB/%sMB\n" \
        "$hits" "$misses" "$hit_rate" "$cached_scripts" "$used_mb" "$total_mb"

    # Return CSV for log
    if [ -n "$LOG_FILE" ]; then
        log_csv "$req_num,/performance (opcache check),200,0,,$hits,$misses"
    fi
}

# --- Header ---
echo ""
echo "============================================"
echo -e "  ${BOLD}Laraworker Stress Test${NC}"
echo "  $(date)"
echo "  Target: $BASE_URL"
echo "  Requests: $NUM_REQUESTS (delay: ${DELAY}s)"
echo "  Pages: ${PAGES[*]}"
if [ -n "$LOG_FILE" ]; then
    echo "  Log: $LOG_FILE"
fi
echo "============================================"
echo ""

# --- Initial OPcache check ---
echo -e "${BOLD}Initial OPcache state:${NC}"
fetch_opcache_stats 0 || true
echo ""

# --- Main loop ---
echo -e "${BOLD}Sending $NUM_REQUESTS requests...${NC}"
echo ""

TIMES=()
for i in $(seq 1 "$NUM_REQUESTS"); do
    # Cycle through pages
    PAGE_IDX=$(( (i - 1) % ${#PAGES[@]} ))
    PAGE="${PAGES[$PAGE_IDX]}"
    URL="$BASE_URL$PAGE"

    # Make request
    ERROR_MSG=""

    RESPONSE=$(curl -s -w "\n%{http_code}\n%{time_total}" --max-time 60 "$URL" 2>&1) || true

    # Parse response: body is everything except last 2 lines
    TIME_TOTAL=$(echo "$RESPONSE" | tail -1)
    HTTP_CODE=$(echo "$RESPONSE" | tail -2 | head -1)
    BODY=$(echo "$RESPONSE" | sed '$d' | sed '$d')

    # Convert time to ms
    TIME_MS=$(awk "BEGIN {printf \"%.0f\", $TIME_TOTAL * 1000}" 2>/dev/null || echo "0")
    TIMES+=("$TIME_MS")
    TOTAL=$((TOTAL + 1))
    TOTAL_TIME=$((TOTAL_TIME + TIME_MS))

    # Classify response
    if [ "$HTTP_CODE" = "200" ]; then
        SUCCESS=$((SUCCESS + 1))
        printf "  %3d  %-16s  ${GREEN}%s${NC}  %4dms\n" "$i" "$PAGE" "$HTTP_CODE" "$TIME_MS"
        log_csv "$i,$PAGE,$HTTP_CODE,$TIME_MS,,,"
    elif echo "$BODY" | grep -qi "exceeded resource limits\|error 1102\|Worker exceeded" 2>/dev/null; then
        RESOURCE_LIMIT_ERRORS=$((RESOURCE_LIMIT_ERRORS + 1))
        FAILURES=$((FAILURES + 1))
        ERROR_MSG="Worker exceeded resource limits"
        printf "  %3d  %-16s  ${RED}%s${NC}  %4dms  ${RED}** RESOURCE LIMIT **${NC}\n" "$i" "$PAGE" "$HTTP_CODE" "$TIME_MS"
        log_csv "$i,$PAGE,$HTTP_CODE,$TIME_MS,$ERROR_MSG,,"
    elif [ "$HTTP_CODE" = "000" ]; then
        FAILURES=$((FAILURES + 1))
        OTHER_ERRORS=$((OTHER_ERRORS + 1))
        ERROR_MSG="Connection failed / timeout"
        printf "  %3d  %-16s  ${RED}%s${NC}  %4dms  ${RED}%s${NC}\n" "$i" "$PAGE" "$HTTP_CODE" "$TIME_MS" "$ERROR_MSG"
        log_csv "$i,$PAGE,$HTTP_CODE,$TIME_MS,$ERROR_MSG,,"
    elif [ "$HTTP_CODE" != "200" ]; then
        FAILURES=$((FAILURES + 1))
        OTHER_ERRORS=$((OTHER_ERRORS + 1))
        # Try to extract error from body
        ERROR_MSG=$(echo "$BODY" | grep -oiE '(error [0-9]+|exceeded|limit|internal server)' | head -1)
        ERROR_MSG="${ERROR_MSG:-HTTP $HTTP_CODE}"
        printf "  %3d  %-16s  ${YELLOW}%s${NC}  %4dms  ${YELLOW}%s${NC}\n" "$i" "$PAGE" "$HTTP_CODE" "$TIME_MS" "$ERROR_MSG"
        log_csv "$i,$PAGE,$HTTP_CODE,$TIME_MS,$ERROR_MSG,,"
    fi

    # Periodic OPcache check
    if [ $((i % OPCACHE_INTERVAL)) -eq 0 ] && [ "$i" -lt "$NUM_REQUESTS" ]; then
        echo ""
        echo -e "  ${CYAN}--- OPcache check after $i requests ---${NC}"
        fetch_opcache_stats "$i" || true
        echo ""
    fi

    # Delay between requests
    if [ "$i" -lt "$NUM_REQUESTS" ]; then
        sleep "$DELAY"
    fi
done

# --- Final OPcache check ---
echo ""
echo -e "${BOLD}Final OPcache state:${NC}"
fetch_opcache_stats "$NUM_REQUESTS" || true

# --- Compute stats ---
AVG_TIME=0
if [ "$TOTAL" -gt 0 ]; then
    AVG_TIME=$((TOTAL_TIME / TOTAL))
fi

# Sort times for percentiles
SORTED_TIMES=($(printf '%s\n' "${TIMES[@]}" | sort -n))
P50_IDX=$(( ${#SORTED_TIMES[@]} * 50 / 100 ))
P95_IDX=$(( ${#SORTED_TIMES[@]} * 95 / 100 ))
P99_IDX=$(( ${#SORTED_TIMES[@]} * 99 / 100 ))
MIN_TIME="${SORTED_TIMES[0]:-0}"
MAX_TIME="${SORTED_TIMES[${#SORTED_TIMES[@]}-1]:-0}"
P50_TIME="${SORTED_TIMES[$P50_IDX]:-0}"
P95_TIME="${SORTED_TIMES[$P95_IDX]:-0}"
P99_TIME="${SORTED_TIMES[$P99_IDX]:-0}"

# --- Summary ---
echo ""
echo "============================================"
echo -e "  ${BOLD}Stress Test Results${NC}"
echo "============================================"
echo ""
echo "  Requests"
echo "  --------"
echo -e "  Total:      $TOTAL"
echo -e "  Success:    ${GREEN}$SUCCESS${NC}"
echo -e "  Failed:     ${RED}$FAILURES${NC}"
if [ "$RESOURCE_LIMIT_ERRORS" -gt 0 ]; then
    echo -e "    Resource limit errors: ${RED}${BOLD}$RESOURCE_LIMIT_ERRORS${NC}"
fi
if [ "$OTHER_ERRORS" -gt 0 ]; then
    echo -e "    Other errors:          ${RED}$OTHER_ERRORS${NC}"
fi
echo ""
echo "  Latency"
echo "  -------"
echo "  min:  ${MIN_TIME}ms"
echo "  avg:  ${AVG_TIME}ms"
echo "  p50:  ${P50_TIME}ms"
echo "  p95:  ${P95_TIME}ms"
echo "  p99:  ${P99_TIME}ms"
echo "  max:  ${MAX_TIME}ms"

# --- OPcache trend ---
SNAPSHOT_COUNT=$(wc -l < "$OPCACHE_SNAPSHOTS_FILE" | tr -d ' ')
if [ "$SNAPSHOT_COUNT" -ge 2 ]; then
    echo ""
    echo "  OPcache Trend"
    echo "  -------------"
    echo "  Request  Hits     Misses   Hit Rate"

    while IFS=' ' read -r req hits misses; do
        total=$((hits + misses))
        rate=0
        if [ "$total" -gt 0 ]; then
            rate=$((hits * 100 / total))
        fi
        printf "  %-8s %-8s %-8s %s%%\n" "$req" "$hits" "$misses" "$rate"
    done < "$OPCACHE_SNAPSHOTS_FILE"

    # Analyze OPcache health across snapshots
    MAX_HITS=$(awk '{print $2}' "$OPCACHE_SNAPSHOTS_FILE" | sort -n | tail -1)
    MIN_HITS=$(awk '{print $2}' "$OPCACHE_SNAPSHOTS_FILE" | sort -n | head -1)
    MAX_MISSES=$(awk '{print $3}' "$OPCACHE_SNAPSHOTS_FILE" | sort -n | tail -1)
    ZERO_HIT_COUNT=$(awk '$2 == 0' "$OPCACHE_SNAPSHOTS_FILE" | wc -l | tr -d ' ')

    echo ""
    if [ "$MAX_HITS" -eq 0 ]; then
        echo -e "  ${RED}${BOLD}OPcache hits are ZERO across all snapshots — cache is completely broken!${NC}"
        echo -e "  ${RED}Scripts are compiled every request but never reused.${NC}"
        echo -e "  ${RED}The cgi-persistent-module patch is not preventing module shutdown.${NC}"
    elif [ "$ZERO_HIT_COUNT" -gt 0 ]; then
        echo -e "  ${YELLOW}Some snapshots show 0 hits ($ZERO_HIT_COUNT of $SNAPSHOT_COUNT) — new/cold isolates.${NC}"
        echo -e "  ${YELLOW}Max hits seen: $MAX_HITS (warm isolates do cache, but cold ones start from zero).${NC}"
    else
        echo -e "  ${GREEN}All snapshots show OPcache hits (min=$MIN_HITS, max=$MAX_HITS).${NC}"
        echo -e "  ${GREEN}Note: Hit count variation is normal — CF routes to multiple isolates.${NC}"
    fi
    echo -e "  Misses range up to $MAX_MISSES (each cold isolate compiles ~857 scripts)."
fi

echo ""
echo "============================================"

# --- Exit code ---
if [ "$RESOURCE_LIMIT_ERRORS" -gt 0 ]; then
    echo -e "${RED}${BOLD}REPRODUCED: Worker exceeded resource limits ($RESOURCE_LIMIT_ERRORS times in $TOTAL requests)${NC}"
    echo ""
    exit 1
elif [ "$FAILURES" -gt 0 ]; then
    echo -e "${YELLOW}${BOLD}ERRORS: $FAILURES failures in $TOTAL requests (but no resource limit errors)${NC}"
    echo ""
    exit 1
else
    echo -e "${GREEN}All $TOTAL requests succeeded — no resource limit errors reproduced.${NC}"
    echo ""
    exit 0
fi
