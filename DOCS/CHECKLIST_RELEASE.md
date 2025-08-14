# DigitalCity — Release Checklist (v2)

## Before tag
- [ ] `.env` for production created (use `.env.example.prod` as base).
- [ ] `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Storage link: `php artisan storage:link`
- [ ] Queue worker configured (Supervisor/Systemd) or `php artisan queue:work --tries=3`
- [ ] Mail provider set and tested (Admin → Tools → Send test mail)
- [ ] Caches warmed: `php artisan digitalcity:cache:all`
- [ ] DB backup cron or external policy in place
- [ ] Robots allowed in prod; blocked elsewhere (X-Robots-Tag header + robots.txt)

## After deploy
- [ ] Run migrations & seeders
- [ ] Warm caches again
- [ ] Verify health endpoint `/healthz`
- [ ] Verify public pages `/`, `/blog`, `/contact`
- [ ] Verify admin: dashboard, users, posts, assets, settings, logs
- [ ] Verify API: `/api/v1/posts`, `/api/v1/pages`, `/api/v2/posts`
- [ ] Smoke test export/import commands

