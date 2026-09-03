#!/bin/sh
set -eu
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ "$(id -u)" = "0" ]; then
    chown -R app:app storage bootstrap/cache
fi
if [ ! -e public/storage ]; then
    if [ "$(id -u)" = "0" ]; then
        su app -s /bin/sh -c 'php artisan storage:link --force' >/dev/null
    else
        php artisan storage:link --force >/dev/null
    fi
fi
exec "$@"
