#!/usr/bin/env bash
set -e
git fetch origin
git diff --stat origin/main...HEAD | sed '1i Summary of changes vs main:' > codex_changes_summary.txt
git diff origin/main...HEAD > codex_changes_full.diff
git add codex_changes_summary.txt codex_changes_full.diff
git commit -m "chore(codex): refresh change reports" || true
git push origin "$(git rev-parse --abbrev-ref HEAD)"
