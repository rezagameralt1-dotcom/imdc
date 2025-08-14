#!/usr/bin/env bash
set -euo pipefail

# --- تنظیمات ---
ZIP_PATH="${1:-}"
PROJECT_ROOT="/var/www/imdc"
BACKUP_DIR="$PROJECT_ROOT/_import_backups"
TMP_DIR="$(mktemp -d)"
EXCLUDES=(
  ".env" ".env.*" "vendor/" "node_modules/" "public/build/"
  "public/storage" "storage/" ".git/" ".idea/" ".vscode/"
)

usage() {
  echo "Usage: $0 /path/to/archive.zip"
  exit 1
}

require_bin() {
  for b in "$@"; do
    command -v "$b" >/dev/null 2>&1 || { echo "✗ نیاز به '$b' است. نصبش کن و دوباره اجرا کن."; exit 1; }
  done
}

[[ -n "$ZIP_PATH" ]] || usage
require_bin unzip rsync git

echo "→ آرشیو: $ZIP_PATH"
echo "→ پروژه: $PROJECT_ROOT"
echo "→ دایرکتوری موقت: $TMP_DIR"

# 1) باز کردن زیپ داخل TMP
unzip -q "$ZIP_PATH" -d "$TMP_DIR"

# اگر داخل آرشیو یک پوشه ریشه وجود داشته باشد، همان را روت در نظر بگیر
if [ $(find "$TMP_DIR" -maxdepth 1 -type d | wc -l) -gt 2 ]; then
  SRC_DIR="$TMP_DIR"
else
  SRC_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d -print -quit)"
  SRC_DIR="${SRC_DIR:-$TMP_DIR}"
fi

echo "→ سورس ایمپورت: $SRC_DIR"

# 2) ساخت فایل exclude برای rsync
EX_FILE="$TMP_DIR/.import_excludes"
: > "$EX_FILE"
for p in "${EXCLUDES[@]}"; do
  echo "$p" >> "$EX_FILE"
done

# 3) بکاپ قبل از اعمال تغییرات
mkdir -p "$BACKUP_DIR"
STAMP="$(date +%F-%H%M%S)"
CUR_BACKUP="$BACKUP_DIR/$STAMP"
mkdir -p "$CUR_BACKUP"

echo "→ گرفتن بکاپ گیت از وضعیت فعلی"
git -C "$PROJECT_ROOT" diff > "$CUR_BACKUP/diff-before.patch" || true
git -C "$PROJECT_ROOT" status > "$CUR_BACKUP/status-before.txt" || true

# 4) Dry-Run برای نمایش تغییرات
echo "→ پیش‌نمایش تغییرات (Dry-Run):"
rsync -avh --dry-run --delete --exclude-from="$EX_FILE" "$SRC_DIR"/ "$PROJECT_ROOT"/ | sed 's/^/   /'

# 5) اعمال واقعی
echo "→ در حال کپی فایل‌ها..."
rsync -avh --delete --exclude-from="$EX_FILE" "$SRC_DIR"/ "$PROJECT_ROOT"/

# 6) نصب‌های لازم (در صورت وجود package.json یا composer.json)
if [ -f "$PROJECT_ROOT/package.json" ]; then
  echo "→ npm ci یا npm i (بر اساس وجود lock)"
  if [ -f "$PROJECT_ROOT/package-lock.json" ]; then
    npm ci --prefix "$PROJECT_ROOT"
  else
    npm i --prefix "$PROJECT_ROOT"
  fi
  echo "→ vite build"
  npm run --prefix "$PROJECT_ROOT" build || true
fi

if [ -f "$PROJECT_ROOT/composer.json" ]; then
  echo "→ composer install (بدون dev در پروDUCTION)"
  sudo -u www-data composer install -d "$PROJECT_ROOT" --no-interaction
  sudo -u www-data php "$PROJECT_ROOT/artisan" optimize:clear || true
fi

# 7) خلاصه تغییرات و کامیت
echo "→ وضعیت گیت بعد از ایمپورت:"
git -C "$PROJECT_ROOT" status

# اگر چیزی تغییر کرده بود، کامیت و پوش کن
if ! git -C "$PROJECT_ROOT" diff --quiet || ! git -C "$PROJECT_ROOT" diff --cached --quiet; then
  echo "→ افزودن و کامیت تغییرات"
  git -C "$PROJECT_ROOT" add -A
  git -C "$PROJECT_ROOT" commit -m "chore(import): sync from Windows archive"
  echo "→ Push به ریموت"
  git -C "$PROJECT_ROOT" push
else
  echo "✓ تغییری برای کامیت وجود ندارد."
fi

echo "✓ اتمام ایمپورت. بکاپ‌ها در: $CUR_BACKUP"
