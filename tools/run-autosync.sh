#!/usr/bin/env bash
set -euo pipefail
nohup /bin/bash /var/www/imdc/tools/auto-commit-push.sh >/var/www/imdc/storage/logs/auto-sync.log 2>&1 &
echo "auto-sync started (log: /var/www/imdc/storage/logs/auto-sync.log)"
