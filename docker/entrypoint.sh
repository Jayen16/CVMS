#!/bin/sh
set -eu
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ "$(id -u)" = "0" ]; then
    chown -R app:app storage bootstrap/cache
fi
exec "$@"
