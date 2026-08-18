#!/usr/bin/env bash
# Development server. For production use nginx/apache with public/ as docroot.
set -euo pipefail
HOST="${1:-127.0.0.1}"
PORT="${2:-8080}"
cd "$(dirname "$0")"
echo "AlmasryLog → http://$HOST:$PORT"
exec php -S "$HOST:$PORT" -t public
