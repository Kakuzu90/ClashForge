#!/usr/bin/env bash
# Rejects changes to protected paths. Shared by the Claude Code PreToolUse hook and .githooks/pre-commit.
# Usage: scripts/guard-paths.sh <path>...   Exit 2 (with a message on stderr) if any path is protected.
set -uo pipefail

status=0
for path in "$@"; do
  case "$path" in
    *resources/js/types/generated.d.ts|*resources/js/routes/*|*resources/js/actions/*)
      # Allowed only when produced by the generators; set ALLOW_GENERATED=1 in that case.
      if [ "${ALLOW_GENERATED:-0}" != "1" ]; then
        echo "$path is generated: run 'php artisan typescript:transform' / 'php artisan wayfinder:generate' instead of editing." >&2
        status=2
      fi ;;
    .agents/skills/*|*/.agents/skills/*|.claude/skills/antislop*|*/.claude/skills/antislop*|skills-lock.json|*/skills-lock.json)
      # Third-party skills: changed only by 'npx skills update -p'; set ALLOW_VENDORED=1 in that case.
      if [ "${ALLOW_VENDORED:-0}" != "1" ]; then
        echo "$path is a vendored skill: update with 'npx skills update -p', never by hand." >&2
        status=2
      fi ;;
    *.env.example) ;;
    .env|*/.env|.env.*|*/.env.*)
      echo "$path: env files are off-limits; edit .env.example and config/ instead." >&2
      status=2 ;;
    specs/project.md|*/specs/project.md)
      echo "$path is the original brief and must not be edited." >&2
      status=2 ;;
  esac
done
exit $status
