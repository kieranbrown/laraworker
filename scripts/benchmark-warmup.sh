#!/usr/bin/env bash
# Benchmark warm request performance for PHP WASM in Cloudflare Workers.
#
# Usage:
#   ./scripts/benchmark-warmup.sh [--requests=N] [--port=PORT] [--no-build]
#
# What it measures:
#   - Cold start: first request time (PHP WASM boot + MEMFS unpack + class preload)
#   - Warm requests: p50/p95/p99 latency after PHP is initialized
#
# Run with and without OPcache to compare:
#   opcache.enable=1  → stubs/worker.ts (current default)
#   opcache.enable=0  → modify stubs/worker.ts temporarily

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLAYGROUND_DIR="$PROJECT_ROOT/playground"

# Defaults
NUM_REQUESTS=30
PORT=8789
NO_BUILD=0

for arg in "$@"; do
    case "$arg" in
        --requests=*) NUM_REQUESTS="${arg#*=}" ;;
        --port=*) PORT="${arg#*=}" ;;
        --no-build) NO_BUILD=1 ;;
    esac
done

SERVER_URL="http://localhost:$PORT"
WRANGLER_LOG="/tmp/wrangler-benchmark-$PORT.log"
WORKERD_PID=""

cleanup() {
    if [ -n "$WORKERD_PID" ] && kill -0 "$WORKERD_PID" 2>/dev/null; then
        kill -9 "$WORKERD_PID" 2>/dev/null || true
    fi
    kill -9 $(lsof -ti :$PORT 2>/dev/null) 2>/dev/null || true
}
trap cleanup EXIT

# Step 1: Build playground
if [ "$NO_BUILD" -eq 0 ]; then
    echo "Building playground..."
    if [ ! -d "$PLAYGROUND_DIR" ]; then
        echo "Error: playground/ not found. Run playground-setup.sh first." >&2
        exit 1
    fi
    php "$PLAYGROUND_DIR/artisan" laraworker:build --no-interaction
    echo "Build complete."
    echo ""
fi

# Remove strip-whitespace cache from assets (wrangler 25MB limit)
ASSETS_DIR="$PLAYGROUND_DIR/.laraworker/dist/assets"
CACHE_FILE="$ASSETS_DIR/.strip-whitespace-cache.json"
if [ -f "$CACHE_FILE" ]; then
    unlink "$CACHE_FILE" 2>/dev/null || rm -f "$CACHE_FILE" || true
fi

# Step 2: Start dev server
echo "Starting wrangler dev on port $PORT..."
kill -9 $(lsof -ti :$PORT 2>/dev/null) 2>/dev/null || true
sleep 1

cd "$PLAYGROUND_DIR/.laraworker"
npx wrangler dev --port "$PORT" > "$WRANGLER_LOG" 2>&1 &
WORKERD_PID=$!
disown "$WORKERD_PID" 2>/dev/null || true

# Wait for server
echo "Waiting for server..."
TIMEOUT=60
ELAPSED=0
while [ $ELAPSED -lt $TIMEOUT ]; do
    if curl -s -o /dev/null -w "" "$SERVER_URL/" 2>/dev/null; then
        break
    fi
    # Check if log shows it's ready without a successful curl
    if grep -q "Ready on http" "$WRANGLER_LOG" 2>/dev/null; then
        break
    fi
    sleep 1
    ELAPSED=$((ELAPSED + 1))
done

if [ $ELAPSED -ge $TIMEOUT ]; then
    echo "Timeout waiting for wrangler dev server" >&2
    echo "Log output:" >&2
    cat "$WRANGLER_LOG" >&2
    exit 1
fi

echo "Server ready."
echo ""

# Step 3: Run benchmark
echo "============================================"
echo "  PHP WASM Warm Request Benchmark"
echo "  $(date)"
echo "  PHP binary: $(grep 'X-Powered-By' "$WRANGLER_LOG" 2>/dev/null | head -1 | sed 's/.*X-Powered-By: //' || echo 'unknown')"
echo "  OPcache: $(grep 'opcache.enable' "$PLAYGROUND_DIR/.laraworker/worker.ts" 2>/dev/null | head -1 | tr -d ' ' || echo 'unknown')"
echo "  Requests: $NUM_REQUESTS warm"
echo "============================================"
echo ""

# All requests (first is cold start)
ALL_TIMES=()
echo "--- Individual Requests ---"
for i in $(seq 1 $((NUM_REQUESTS + 5))); do
    T=$(curl -s -o /dev/null -w "%{time_total}" "$SERVER_URL/" 2>/dev/null)
    ALL_TIMES+=("$T")
    MS=$(echo "$T" | awk '{printf "%.0f", $1 * 1000}')
    if [ "$i" -eq 1 ]; then
        echo "  Request $i (cold start): ${MS}ms"
    elif [ "$i" -le 3 ]; then
        echo "  Request $i (warming):    ${MS}ms"
    else
        echo "  Request $i:              ${MS}ms"
    fi
done

# Split cold and warm
COLD_TIME="${ALL_TIMES[0]}"
WARM_TIMES=("${ALL_TIMES[@]:4}")  # Skip first 4 (cold + warmup)

echo ""
echo "--- Results ---"
echo "Cold start (request 1):  $(echo "$COLD_TIME" | awk '{printf "%.0f", $1 * 1000}')ms"

# Stats for warm requests
printf '%s\n' "${WARM_TIMES[@]}" | awk -v cold="$(echo "$COLD_TIME" | awk '{printf "%.0f", $1 * 1000}')" '
{
    times[NR] = $1
    sum += $1
    if (NR == 1 || $1 < min) min = $1
    if (NR == 1 || $1 > max) max = $1
}
END {
    n = NR
    if (n == 0) { print "No warm requests"; exit }
    mean = sum / n
    for (i = 1; i <= n; i++) {
        for (j = i+1; j <= n; j++) {
            if (times[i] > times[j]) { tmp = times[i]; times[i] = times[j]; times[j] = tmp }
        }
    }
    p50_idx = int(n * 0.50); if (p50_idx >= n) p50_idx = n-1
    p95_idx = int(n * 0.95); if (p95_idx >= n) p95_idx = n-1
    p99_idx = int(n * 0.99); if (p99_idx >= n) p99_idx = n-1
    printf "Warm requests (%d samples):\n", n
    printf "  min:  %dms\n", min * 1000
    printf "  mean: %dms\n", mean * 1000
    printf "  p50:  %dms\n", times[p50_idx] * 1000
    printf "  p95:  %dms\n", times[p95_idx] * 1000
    printf "  p99:  %dms\n", times[p99_idx] * 1000
    printf "  max:  %dms\n", max * 1000
}'

echo ""
echo "Done."
