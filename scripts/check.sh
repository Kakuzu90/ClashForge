#!/usr/bin/env bash
# Single entry point for all checks — used by AI agents (verify workflow), .githooks/pre-commit and CI.
# Usage: scripts/check.sh [--fast]
#   --fast  style + static checks only (pre-commit); default runs everything.
# Runs commands in the docker compose services when they are up, otherwise on the host (CI).
set -uo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
src="$root/src"
fast=0; [ "${1:-}" = "--fast" ] && fast=1
failed=0; results=()

if [ ! -f "$src/artisan" ]; then
  echo "SKIPPED: src/ is not scaffolded yet (Phase 0, P0-01)."
  exit 0
fi

in_service() { # service, command...
  local svc=$1; shift
  if docker compose -f "$root/docker-compose.yml" ps --status running -q "$svc" 2>/dev/null | grep -q .; then
    docker compose -f "$root/docker-compose.yml" exec -T "$svc" "$@"
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
run "phpstan"   app ./vendor/bin/phpstan analyse --no-progress
run "deptrac"   app ./vendor/bin/deptrac analyse --no-progress
run "typecheck" node npm run typecheck
run "lint"      node npm run lint

if [ $fast -eq 0 ]; then
  run "pest"            app php artisan test --parallel
  run "ts types"        app php artisan typescript:transform
  run "wayfinder"       app php artisan wayfinder:generate
  echo "==> generated files up to date"
  if git -C "$root" diff --quiet -- src/resources/js/types src/resources/js/routes src/resources/js/actions; then
    results+=("pass  generated files up to date")
  else
    results+=("FAIL  generated files up to date (regenerated output differs — commit it)"); failed=1
  fi
  run "vitest"          node npm run test
  run "build (client+ssr)" node npm run build
fi

echo; printf '%s\n' "${results[@]}"
exit $failed
