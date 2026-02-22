#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLAYGROUND_DIR="$PROJECT_ROOT/playground"

DEV_SERVER_PID=""
PASS_COUNT=0
FAIL_COUNT=0
TESTS=()

cleanup() {
    if [ -n "$DEV_SERVER_PID" ] && kill -0 "$DEV_SERVER_PID" 2>/dev/null; then
        echo ""
        echo "Stopping dev server (PID $DEV_SERVER_PID)..."
        kill "$DEV_SERVER_PID" 2>/dev/null || true
        wait "$DEV_SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

assert_status() {
    local description="$1"
    local url="$2"
    local expected_status="$3"

    local actual_status
    actual_status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || echo "000")

    if [ "$actual_status" = "$expected_status" ]; then
        echo "  PASS: $description (HTTP $actual_status)"
        PASS_COUNT=$((PASS_COUNT + 1))
        TESTS+=("PASS: $description")
    else
        echo "  FAIL: $description (expected HTTP $expected_status, got HTTP $actual_status)"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        TESTS+=("FAIL: $description")
    fi
}

# --- Step 1: Verify playground exists ---
echo "Checking playground exists..."
if [ ! -d "$PLAYGROUND_DIR" ]; then
    echo "Error: playground/ does not exist. Run playground-setup.sh first." >&2
    exit 1
fi

# --- Step 2: Build ---
echo "Running playground build..."
"$SCRIPT_DIR/playground-build.sh"

# --- Step 3: Start dev server in background ---
echo "Starting dev server..."
php "$PLAYGROUND_DIR/artisan" laraworker:dev &
DEV_SERVER_PID=$!

# --- Step 4: Wait for dev server to be ready ---
echo "Waiting for dev server to be ready..."
TIMEOUT=30
ELAPSED=0
SERVER_URL="http://localhost:8787"

while [ "$ELAPSED" -lt "$TIMEOUT" ]; do
    if curl -s -o /dev/null -w "" "$SERVER_URL" 2>/dev/null; then
        echo "Dev server is ready (${ELAPSED}s)."
        break
    fi
    sleep 1
    ELAPSED=$((ELAPSED + 1))
done

if [ "$ELAPSED" -ge "$TIMEOUT" ]; then
    echo "Error: Dev server did not become ready within ${TIMEOUT}s." >&2
    exit 1
fi

# --- Step 5: Run HTTP checks ---
echo ""
echo "Running smoke tests..."
assert_status "GET / returns 200" "$SERVER_URL/" "200"
assert_status "GET /nonexistent returns 404" "$SERVER_URL/nonexistent" "404"

# --- Step 6: Summary ---
echo ""
echo "========================================="
echo "  Smoke Test Results"
echo "========================================="
for t in "${TESTS[@]}"; do
    echo "  $t"
done
echo ""
echo "  Total: $((PASS_COUNT + FAIL_COUNT))  Passed: $PASS_COUNT  Failed: $FAIL_COUNT"
echo "========================================="

if [ "$FAIL_COUNT" -gt 0 ]; then
    exit 1
fi

echo ""
echo "All smoke tests passed!"
