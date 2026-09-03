# Production Docker deployment

This stack targets one Ubuntu 24.04 EC2 host (such as `t3.small`). The host needs only Docker Engine and Compose. Nginx publishes HTTP on port 80; MySQL is private to the Compose network and uses the named `mysql_data` volume. Laravel storage uses `laravel_storage`, so uploads survive image rebuilds.

## First deployment

```sh
git clone YOUR_REPOSITORY_URL cvms
cd cvms
git pull --ff-only
cp .env.docker.example .env.docker
nano .env.docker                 # set APP_URL, strong unique DB passwords, and service credentials
docker compose --env-file .env.docker run --no-deps --rm app php artisan key:generate --show
# Put the displayed base64 key in APP_KEY in .env.docker; never commit this file.
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f --tail=100 nginx app
docker compose --env-file .env.docker exec app php artisan migrate --force
docker compose --env-file .env.docker exec app php artisan storage:link
docker compose --env-file .env.docker exec app php artisan optimize:clear
docker compose --env-file .env.docker exec app php artisan config:cache
docker compose --env-file .env.docker exec app php artisan route:cache
docker compose --env-file .env.docker exec app php artisan view:cache
```

The key-generation command may be run before the stack starts because `app` is built independently of the database. It prints a key and does not write a secret into the image. Do not run seeders or reset commands on production. The scheduler runs the existing daily reminder and five-minute central-sync schedules; the worker runs the existing database queue.

## Operations

```sh
# Shells and logs
docker compose --env-file .env.docker exec app sh
docker compose --env-file .env.docker exec mysql mysql -uroot -p
docker compose --env-file .env.docker exec mysql mysql -u"$DB_USERNAME" -p "$DB_DATABASE"
docker compose --env-file .env.docker restart

# Later updates (preserve volumes)
git pull --ff-only
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker exec app php artisan migrate --force
docker compose --env-file .env.docker exec app php artisan optimize:clear
docker compose --env-file .env.docker exec app php artisan config:cache

# Stop without deleting database or uploads
docker compose --env-file .env.docker stop
```

For the application-user MySQL shell, export values first so the shell expands them: `set -a; . ./.env.docker; set +a`, then run the second MySQL command. Prefer entering passwords interactively rather than putting them in shell history.

## Backups and restore

```sh
mkdir -p backups
docker compose --env-file .env.docker exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' > backups/cvms-$(date +%F-%H%M).sql
cat backups/FILE.sql | docker compose --env-file .env.docker exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

Store backups outside the EC2 instance as well. Restores overwrite matching data; verify the file and take a fresh backup first.

## HTTPS later

The initial deployment is functional over HTTP. For a single EC2 host, point a DNS name at the instance, open 443, and add Certbot with the Nginx plugin (or replace the edge container with Caddy) using persistent certificate storage. Then set `APP_URL=https://your-hostname` and rebuild Laravel caches after the change. No AWS load balancer is required for this approach.

Only port 80 is published by default. Do not publish port 3306 or add unrestricted proxy trust unless a real reverse proxy is introduced; if one is added, configure its exact private address/range and document it.
