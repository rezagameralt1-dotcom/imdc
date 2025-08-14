#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

php artisan imdc:build-release "$@"
echo "Release ZIP created under storage/releases"
