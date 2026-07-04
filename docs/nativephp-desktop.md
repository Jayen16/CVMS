# NativePHP Desktop Setup

This project includes NativePHP desktop scaffolding so the clinic can run the Laravel app as a local desktop app backed by SQLite for offline use.

## Recommended local configuration

- `DB_CONNECTION=sqlite`
- `OFFLINE_ENABLED=true`
- `OFFLINE_LOCAL_CONNECTION=sqlite`
- `OFFLINE_REMOTE_CONNECTION=mysql`
- `NATIVEPHP_APP_ID=ph.gov.indang.cvms`
- `NATIVEPHP_UPDATER_ENABLED=false`

## Development

- `composer native:dev` runs the desktop app together with Vite.
- `php artisan native:run --no-interaction` launches the desktop shell directly.

## Build

- `php artisan native:build win` builds a Windows desktop package.

## Notes

- The desktop shell uses the existing Laravel and Livewire codebase.
- Offline queueing and sync continue to use the current Laravel outbox flow.
- Remote sync still depends on the clinic server connection configured in `.env`.
