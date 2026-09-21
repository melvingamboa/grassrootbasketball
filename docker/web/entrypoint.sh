#!/usr/bin/env sh
set -eu

if [ ! -d node_modules ] || [ ! -f node_modules/.package-lock.json ]; then
    npm ci
fi

exec "$@"
