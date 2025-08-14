# DigitalCity — Ops Notes

## Healthcheck
- `GET /healthz` → JSON with app, db, cache, storage status.

## Export / Import
- Export content + files:
  ```bash
  php artisan digitalcity:export
  php artisan digitalcity:export --dir=exports/weekly
  ```
- Import back:
  ```bash
  php artisan digitalcity:import storage/app/exports/<yourfile>.zip
  php artisan digitalcity:import storage/app/exports/<yourfile>.zip --no-assets
  ```

## Admin Activity
- Prune old logs (default 90 days):
  ```bash
  php artisan digitalcity:prune-activity
  php artisan digitalcity:prune-activity --days=30
  ```

## Scheduler
- Ensure cron (Linux) runs each minute:
  ```cron
  * * * * * php /path/to/backend/artisan schedule:run >> /dev/null 2>&1
  ```
- On Windows dev, run manually from time to time:
  ```powershell
  php artisan schedule:run
  ```

## Caching / Optimization
```bash
php artisan route:clear && php artisan route:cache
php artisan config:clear && php artisan config:cache
php artisan view:clear && php artisan view:cache
```

## Public API
- `GET /api/v1/posts?per_page=10&q=foo`
- `GET /api/v1/pages?per_page=10&q=foo`
- `GET /api/search?q=term` (simple search endpoint)

