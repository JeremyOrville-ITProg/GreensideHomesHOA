#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

PORT=8080
URL="http://127.0.0.1:${PORT}/"

echo "Starting Greenside Homes HOA Portal..."
echo "Address: ${URL}"
echo "Press Ctrl+C to stop the server."
echo ""

# Open browser automatically on macOS
if command -v open >/dev/null 2>&1; then
    open "${URL}" &
fi

php -S "127.0.0.1:${PORT}" -t . router.php
