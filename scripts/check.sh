#!/usr/bin/env bash
# Single entry point for all checks — used by AI agents (verify workflow), .githooks/pre-commit and CI.
# Usage: scripts/check.sh [--fast]
#   --fast  style + static checks only (pre-commit); default runs everything.
# Runs in the running compose service, else a one-off container, else on the host (CI).
set -uo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
src="$root/src"
compose=(docker compose -f "$root/docker-compose.yml")
fast=0; [ "${1:-}" = "--fast" ] && fast=1
failed=0; results=()

if [ ! -f "$src/artisan" ]; then
  echo "SKIPPED: src/ is not scaffolded yet (Phase 0, P0-01)."
  exit 0
fi

running() { "${compose[@]}" ps --status running -q "$1" 2>/dev/null | grep -q .; }
have_docker() { docker info >/dev/null 2>&1; }

in_service() { # service, command...
  local svc=$1; shift
  if running "$svc"; then
    "${compose[@]}" exec -T "$svc" "$@"
  elif have_docker; then
    "${compose[@]}" run --rm --no-deps -T "$svc" "$@"
  else
    (cd "$src" && "$@")
  fi
}

run() { # label, service, command...
  local label=$1; shift
  echo "==> $label"
  if in_service "$@"; then results+=("pass  $label"); else results+=("FAIL  $label"); failed=1; fi
}

run "pint"      app ./vendor/bin/pint --test
run "phpstan"   app ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
if [ -n "$(find "$src/app/Domain" -name "*.php" -print -quit 2>/dev/null)" ]; then
  run "phpstan (Domain, L8)" app ./vendor/bin/phpstan analyse -c phpstan-domain.neon --no-progress --memory-limit=1G
fi
run "deptrac"   app ./vendor/bin/deptrac analyse --no-progress
run "typecheck" node npm run typecheck
run "lint"      node npm run lint

if [ $fast -eq 0 ]; then
  run "pest (sqlite)" app php artisan test --parallel
  if running db; then
    run "pest (postgres)" app env DB_CONNECTION=pgsql DB_HOST=db DB_DATABASE=clashforge_test php artisan test
  else
    results+=("skip  pest (postgres): db service not running")
  fi
  # Stale if regenerating changes what is on disk (compared with the working tree, not HEAD).
  gen=(resources/js/types resources/js/routes resources/js/actions resources/js/wayfinder)
  gen_hash() { (cd "$src" && find "${gen[@]}" -type f -exec shasum {} + 2>/dev/null | sort | shasum); }
  before=$(gen_hash)
  run "ts types"  app php artisan typescript:transform
  run "wayfinder" app php artisan wayfinder:generate
  echo "==> generated files up to date"
  if [ "$before" = "$(gen_hash)" ]; then
    results+=("pass  generated files up to date")
  else
    results+=("FAIL  generated files were stale (now regenerated — re-run and commit them)"); failed=1
  fi
  run "vitest"             node npm run test
  run "build (client+ssr)" node npm run build
fi

echo; printf '%s\n' "${results[@]}"
exit $failed
