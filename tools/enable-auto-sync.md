# Enable auto-commit & push as a user service (optional)

## 1) Make script executable
```bash
chmod +x /var/www/imdc/tools/auto-commit-push.sh
```

## 2) Create user systemd units
```bash
mkdir -p ~/.config/systemd/user
cat > ~/.config/systemd/user/imdc-auto-sync.service <<'UNIT'
[Unit]
Description=IMDC auto commit & push

[Service]
Type=simple
ExecStart=/bin/bash /var/www/imdc/tools/auto-commit-push.sh
WorkingDirectory=/var/www/imdc
Restart=always
RestartSec=5

[Install]
WantedBy=default.target
UNIT

cat > ~/.config/systemd/user/imdc-auto-sync.timer <<'UNIT'
[Unit]
Description=IMDC auto commit & push timer

[Timer]
OnBootSec=5sec
OnUnitActiveSec=1min
AccuracySec=5s
Unit=imdc-auto-sync.service

[Install]
WantedBy=timers.target
UNIT

systemctl --user daemon-reload
systemctl --user enable --now imdc-auto-sync.timer
```

## 3) Allow user services to run (if needed)
```bash
loginctl enable-linger $USER
```

> اگر نمی‌خوای با systemd کار کنی، می‌تونی فقط اسکریپت را دستی اجرا کنی:
> ```bash
> bash /var/www/imdc/tools/auto-commit-push.sh
> ```

**یادآوری**: برای اینکه Push بدون رمز انجام شود، یا در VS Code به GitHub Sign-In بده، یا از SSH استفاده کن.
