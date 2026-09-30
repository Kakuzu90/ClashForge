#!/usr/bin/env bash
# PreToolUse (Edit|Write): delegate to the shared path guard (also used by .githooks/pre-commit).
set -uo pipefail

path=$(jq -r '.tool_input.file_path // empty')
[ -z "$path" ] && exit 0
root="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
exec "$root/scripts/guard-paths.sh" "${path#"$root/"}"
