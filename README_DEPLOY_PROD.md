# DigitalCity — Production Checklist

## 1) Environment
Create a real `.env` based on `backend/.env.example.prod`:
- Set `APP_KEY` (run in backend folder): `php artisan key:generate --show` and paste the value.
- Fill DB, Redis, Mail credentials.
- Set `APP_URL` and `SESSION_DOMAIN` to your domain.

## 2) Storage & Media
Either:
- Use `/media/{path}` route we ship (works now), or
- Create a symlink for public storage:
  ```bash
  php artisan storage:link
  ```

## 3) Build Frontend Assets
If you use Vite in this repo:
```bash
npm install
npm run build
```
Then ensure your Blade layout includes `@vite`. (Already present in the base.)

## 4) Cache & Optimize
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 5) Queue (if enabled)
If `QUEUE_CONNECTION=database` or `redis`:
```bash
php artisan queue:table && php artisan migrate --force   # once
php artisan queue:work --daemon --tries=3 --max-time=3600
```

## 6) HTTPS
- We force HTTPS in production via `URL::forceScheme('https')`. Ensure your reverse proxy / web server forwards `X-Forwarded-Proto` correctly.
- For Apache/Nginx, configure proper TLS and HSTS if desired.

## 7) Backups & Logs
- Logs are rotated daily, keep 14 days (config/logging.php).
- Schedule DB/file backups with your favorite tool (e.g., `pg_dump`, `mysqldump`).

Good luck! 🚀

