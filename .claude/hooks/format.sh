#!/usr/bin/env bash
# PostToolUse (Edit|Write): format the touched file. Silent no-op until src/ is scaffolded.
set -uo pipefail

path=$(jq -r '.tool_input.file_path // empty')
[ -z "$path" ] && exit 0
root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
src="$root/src"
[[ "$path" == "$src/"* ]] || exit 0
rel="${path#"$src/"}"

app_running() { docker compose -f "$root/docker-compose.yml" ps --status running -q "$1" 2>/dev/null | grep -q .; }

case "$path" in
  *.php)
    [ -x "$src/vendor/bin/pint" ] || exit 0
    if command -v php >/dev/null 2>&1; then
      (cd "$src" && ./vendor/bin/pint -q "$rel")
    elif app_running app; then
      docker compose -f "$root/docker-compose.yml" exec -T app ./vendor/bin/pint -q "$rel"
    fi ;;
  *.vue|*.ts|*.js|*.css|*.json)
    [ -d "$src/node_modules/prettier" ] || exit 0
    if command -v npx >/dev/null 2>&1; then
      (cd "$src" && npx --no-install prettier --write --log-level silent "$rel")
    elif app_running node; then
      docker compose -f "$root/docker-compose.yml" exec -T node npx --no-install prettier --write --log-level silent "$rel"
    fi ;;
esac
exit 0
