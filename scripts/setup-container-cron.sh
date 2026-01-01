#!/usr/bin/env bash
# IMDC: Setup container-based Laravel scheduler cron
# This replaces the host-based schedule:run with a container-based approach

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="${SCRIPT_DIR}/../infra/docker/docker-compose.yml"

if [[ ! -f "$COMPOSE_FILE" ]]; then
    echo "Error: docker-compose.yml not found at $COMPOSE_FILE"
    exit 1
fi

# Check if docker compose is available
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
    echo "Error: docker compose is required"
    exit 1
fi

# Create cron entry for container-based scheduler
CRON_ENTRY="* * * * * cd ${SCRIPT_DIR}/.. && docker compose -f infra/docker/docker-compose.yml exec -T app sh -lc 'cd /var/www/html && php artisan schedule:run' >> storage/logs/scheduler.log 2>&1"

# Check if entry already exists
if crontab -l 2>/dev/null | grep -q "docker compose.*schedule:run"; then
    echo "Container-based cron already exists"
    exit 0
fi

# Add new cron entry
(crontab -l 2>/dev/null; echo "# IMDC Container-based Laravel Scheduler (replaces host-based)"; echo "$CRON_ENTRY") | crontab -

echo "✓ Container-based cron installed"
echo "  Run: crontab -l to verify"
