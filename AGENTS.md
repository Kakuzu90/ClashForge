# Clash Commons

Clash of Clans community platform. Laravel 12 / PHP 8.3 modular monolith, Inertia 2 + Vue 3
(TypeScript) + Tailwind 4 with SSR on public pages, PostgreSQL 16 for data + cache + queue +
session, Cloudflare R2 + CDN for media.

This file is the shared instruction set for every coding agent (Codex, Claude Code, others).
Tool-specific wiring lives in `.claude/` (Claude Code); everything else is tool-neutral.

## Source of truth

- `specs/` is the plan. `specs/README.md` holds the **locked decisions** and the **load map** — read
  it first on every task. Never contradict a locked decision; if a task seems to need that, stop
  and ask.
- Always in context: `specs/README.md`, `specs/05-architecture.md`, `specs/19-module-structure.md`.
  Add `specs/04-roles-and-permissions.md` when the task adds or changes a write path, policy or role.
  Load the rest **by task**, per the load map / `specs/25` §4.
  Do not bulk-load the specs directory.
- `tasks/` is the work queue. `tasks/BOARD.md` is the index; one file per task, built from
  `tasks/_template.md`.
- If the implementation diverges from a spec, update the spec in the same change (implement → Finish).

## Repo layout

```
specs/            planning docs (source of truth)
DESIGN.md         design direction + antislop owner decisions (points to specs/18)
tasks/            BOARD.md + one task file per unit of work
docs/ai/rules/    path-scoped coding rules (read before touching matching paths)
docs/ai/workflows/ step-by-step procedures, invoked by name
scripts/          check.sh (all checks), guard-paths.sh (protected paths)
.githooks/        pre-commit — enable with: git config core.hooksPath .githooks
src/              the Laravel app (created in Phase 0) — app/Domain, app/Http, resources/js, tests
.claude/          Claude Code wiring (skills/agents/rules/hooks point back to docs/ai and scripts)
.agents/skills/   third-party skills (antislop), managed by `npx skills` + skills-lock.json — never hand-edit
```

## Path rules

Before editing files under a path, read its rule file:

| Paths | Rules |
|---|---|
| `src/app/**`, `src/routes/**`, `src/config/**` | `docs/ai/rules/backend.md` |
| `src/resources/js/**`, `src/resources/css/**`, `src/resources/views/**` | `docs/ai/rules/frontend.md` |
| `src/database/**`, `src/app/Domain/*/Models/**` | `docs/ai/rules/database.md` |
| `src/tests/**` | `docs/ai/rules/tests.md` |
| `specs/**`, `tasks/**` | `docs/ai/rules/specs.md` |

## Workflows

When asked to "run <name>" (or the Claude Code `/<name>` equivalent), follow the file exactly:

| Name | File | Purpose |
|---|---|---|
| next-task | `docs/ai/workflows/next-task.md` | Pick the next unblocked task and write its task file |
| implement | `docs/ai/workflows/implement.md` | Build a task file in the `specs/19` §8 order, then sync specs |
| verify | `docs/ai/workflows/verify.md` | `scripts/check.sh` + spec and security reviews |
| spec-review | `docs/ai/workflows/spec-review.md` | Read-only conformance review of a diff |
| security-review | `docs/ai/workflows/security-review.md` | Read-only security review of a diff |
| antislop-audit | `docs/ai/workflows/antislop-audit.md` | antislop after-mode audit of changed UI/copy |

Loop: next-task → implement (runs verify + spec sync) → mark the task `done` on the board.
Commit only when asked.

## Commands (run from repo root once `src/` exists)

```
scripts/check.sh                                           # everything (same as CI)
scripts/check.sh --fast                                    # style + static only (pre-commit)
docker compose exec app php artisan test --filter=<name>   # a single Pest test
docker compose exec app php artisan typescript:transform   # regenerate TS types from DTOs/enums
docker compose exec app php artisan wayfinder:generate     # regenerate TS route helpers
```

## Non-negotiables

Details live in the `docs/ai/rules/` files and the specs; these are the ones never to miss.
- Business logic only in `app/Domain/*/Services|Actions`; no cross-module `Models` access.
- Every write path is authorized by a Policy; Inertia props are `Data` DTOs only, never models.
- Locked decisions in `specs/README.md` hold (no Redis calls, no payments/trading, unmodified game
  assets via `GameAssetResolver`).
- Protected paths (`scripts/guard-paths.sh`): generated TS files, `.env*`, `specs/project.md`,
  vendored skills — regenerate or update, never hand-edit.
- Nothing is done without tests, and never `--no-verify` without the user's approval.

<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, read `.agents/skills/antislop/SKILL.md` (core) and then the skill for the task:
- UI / visual: `.agents/skills/antislop-ui/SKILL.md`
- Copy & text: `.agents/skills/antislop-copywriting/SKILL.md`
- People: `.agents/skills/antislop-human/SKILL.md`
- Mobile / responsive: `.agents/skills/antislop-layoutmobile/SKILL.md`
- Code comments: `.agents/skills/antislop-code/SKILL.md`
Direction: `DESIGN.md` (dials and owner decisions) backed by `specs/18-design-system.md`. Owner decisions recorded in `DESIGN.md` are already answered under R-37: do not re-ask them.
Project default mode: **after** (owner decision 2026-09-30, to save tokens). Do not load antislop while implementing; it runs once per UI task as the `antislop-audit` workflow inside `verify`, on the changed files only. Treat this as the session instruction unless the user explicitly picks another antislop mode in the conversation; do not ask the mode question. Announce `antislop active: after (project default).` at the start of the audit output. Audit reports go to `anti-slop/`.
R-02 (em dash) applies to user-facing copy only; see the scope row in `DESIGN.md`.
R-35 click-through runs once at phase end, not per task (see `docs/ai/workflows/verify.md`).
To update antislop: `npx skills update -p`, then review the diff of `.agents/skills/` before committing.
<!-- antislop:end -->
