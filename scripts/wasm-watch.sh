#!/bin/bash

# PHPX Build Watcher
# Watches for file changes and automatically rebuilds

# Parse command line arguments
DEV_MODE=0
for arg in "$@"; do
    if [ "$arg" = "--dev" ] || [ "$arg" = "--development" ]; then
        DEV_MODE=1
        export PHPX_DEV_MODE=1
    fi
done

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     PHPX Build Watcher Started        ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
echo ""

if [ $DEV_MODE -eq 1 ]; then
    echo -e "${GREEN}✓ Development mode enabled${NC}"
    echo -e "  AI source maps will be generated for debugging"
    echo ""
fi

# Check if inotifywait is installed
if ! command -v inotifywait &> /dev/null; then
    echo -e "${RED}✗ Error: inotifywait is not installed${NC}"
    echo ""
    echo "Install it with:"
    echo "  Ubuntu/Debian: sudo apt-get install inotify-tools"
    echo "  Fedora/RHEL:   sudo yum install inotify-tools"
    echo "  Arch:          sudo pacman -S inotify-tools"
    exit 1
fi

# Get project root directory
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo -e "${GREEN}✓ Watching for changes in:${NC}"
echo -e "  • ${BLUE}src/${NC} (PHP/PHPX files)"
echo -e "  • ${BLUE}composer.json${NC} (dependencies)"
echo -e "  • ${BLUE}composer.lock${NC} (dependency lock)"
echo ""
echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
echo ""

# Run initial build
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}Running initial build...${NC}"
php scripts/wasm-pack.php && php scripts/wasm-export.php
BUILD_STATUS=$?

if [ $BUILD_STATUS -eq 0 ]; then
    echo -e "${GREEN}✓ Initial build completed successfully${NC}"
else
    echo -e "${RED}✗ Initial build failed${NC}"
fi
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Flag to track if rebuild is needed
REBUILD_PENDING=0
LAST_BUILD_TIME=0

# Function to trigger rebuild
trigger_rebuild() {
    local CURRENT_TIME=$(date +%s)
    local TIME_DIFF=$((CURRENT_TIME - LAST_BUILD_TIME))

    # Debounce: wait at least 2 seconds between builds
    if [ $TIME_DIFF -lt 2 ]; then
        REBUILD_PENDING=1
        return
    fi

    REBUILD_PENDING=0
    LAST_BUILD_TIME=$CURRENT_TIME

    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}⟳ Change detected - rebuilding...${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    php scripts/wasm-pack.php && php scripts/wasm-export.php
    BUILD_STATUS=$?

    if [ $BUILD_STATUS -eq 0 ]; then
        echo -e "${GREEN}✓ Build completed successfully at $(date +%H:%M:%S)${NC}"
    else
        echo -e "${RED}✗ Build failed at $(date +%H:%M:%S)${NC}"
    fi
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${YELLOW}Watching for changes...${NC}"
}

# Watch for changes
inotifywait -m -r \
    -e modify,create,delete,move \
    --exclude '(\.git|node_modules|vendor|build|public/build|test-results|\.swp|~)' \
    --format '%w%f %e' \
    src/ composer.json composer.lock 2>/dev/null | while read FILE EVENT
do
    # Filter out temporary files and editor backups
    if [[ "$FILE" =~ \.(swp|swx|tmp|~)$ ]]; then
        continue
    fi

    echo -e "${BLUE}▶${NC} Changed: $(basename "$FILE")"
    trigger_rebuild
done

# Handle pending rebuild on exit
trap 'if [ $REBUILD_PENDING -eq 1 ]; then trigger_rebuild; fi' EXIT
