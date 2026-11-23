#!/bin/bash

set -u

SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
cd "$SCRIPT_DIR"

PORT=${PORT:-8080}
URL="http://127.0.0.1:$PORT/"
PHP_BIN=${PHP_BIN:-php}

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "Could not find PHP. Set PHP_BIN to the PHP executable path."
    echo "Example: PHP_BIN=/usr/bin/php ./start_portal.sh"
    exit 1
fi

if [ ! -f "router.php" ]; then
    echo "Could not find router.php in $SCRIPT_DIR."
    echo "Keep start_portal.sh in the GreensideHomesHOA project folder."
    exit 1
fi

if command -v curl >/dev/null 2>&1 && curl --silent --show-error --max-time 2 --output /dev/null "$URL" 2>/dev/null; then
    echo "A server is already responding at $URL."
    if command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$URL" >/dev/null 2>&1 &
    elif command -v gio >/dev/null 2>&1; then
        gio open "$URL" >/dev/null 2>&1 &
    else
        echo "Open $URL in your browser."
    fi
    exit 0
fi

cleanup() {
    if [ -n "${SERVER_PID:-}" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT INT TERM

echo "Starting the Greenside Homes portal..."
echo "Address: $URL"
echo "Press Ctrl+C to stop the server."
echo

"$PHP_BIN" -S "127.0.0.1:$PORT" -t "$SCRIPT_DIR" "$SCRIPT_DIR/router.php" &
SERVER_PID=$!

READY=false
for _ in $(seq 1 50); do
    if command -v curl >/dev/null 2>&1; then
        if curl --silent --show-error --max-time 1 --output /dev/null "$URL" 2>/dev/null; then
            READY=true
            break
        fi
    elif (echo >/dev/tcp/127.0.0.1/"$PORT") 2>/dev/null; then
        READY=true
        break
    fi

    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        echo "The PHP server stopped before it was ready."
        exit 1
    fi
    sleep 0.1
done

if [ "$READY" = true ]; then
    if command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$URL" >/dev/null 2>&1 &
    elif command -v gio >/dev/null 2>&1; then
        gio open "$URL" >/dev/null 2>&1 &
    else
        echo "Open $URL in your browser."
    fi
else
    echo "The server did not become ready. Check PHP's output above."
fi

wait "$SERVER_PID"
