# AI Playbook: how to drive this project

Your cheat sheet for coming back to Clash Commons. Every prompt below works in **Claude Code**
and **Codex**; the Claude column uses slash commands, the Codex column names the workflow file.

---

## 0. First time on a machine / fresh clone

```bash
git config core.hooksPath .githooks            # enable the pre-commit guard + fast checks
npx skills add miqdadbadjuber/anti-slop        # only if .agents/skills/ is missing
```

Optional: `uipro init --ai codex` if you want ui-ux-pro-max inside Codex (Claude already has it).

First run of the app (after P0-01):

```bash
cp src/.env.example src/.env                                  # fresh clone only
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate          # fresh clone only
docker compose up -d                                          # app, web, node (Vite), queue, scheduler, db, mailpit
docker compose exec app php artisan migrate
docker compose exec node npm run build && docker compose --profile ssr up -d ssr   # SSR renderer
```

App: http://localhost:8080 · Mailpit: http://localhost:8025 · extras: `--profile storage` (MinIO), `--profile tools` (Adminer).

Laravel Boost MCP: nothing to start by hand; each tool launches it on demand, but the `app` container
must be running (`docker compose up -d`).
- **Claude Code:** reads `.mcp.json` in the repo; approve it once.
- **Codex (CLI and VS Code extension):** shares `~/.codex/config.toml`; already added on this machine.
  On a new machine, add it (absolute `-f` path so it works from any cwd) and reload VS Code:

```toml
[mcp_servers.laravel-boost]
command = "docker"
args = ["compose", "-f", "/absolute/path/to/ClashForge/docker-compose.yml", "exec", "-T", "app", "php", "artisan", "boost:mcp"]
```

---

## 1. Where am I? (start of every session)

| Want | Prompt |
|---|---|
| Status of the project | `Summarise tasks/BOARD.md: what's done, in progress, and next unblocked.` |
| Resume unfinished work | `Resume the in-progress task on the board. Read its task file Notes first.` |
| What changed recently | `Summarise git log and uncommitted changes since the last session.` |

The agent automatically reads `AGENTS.md` (Codex) / `CLAUDE.md` → `AGENTS.md` (Claude). You don't
need to paste context.

---

## 2. The main loop (one task)

| Step | Claude Code | Codex |
|---|---|---|
| 1. Plan the task | `/next-task` or `/next-task P1-03` | `Run next-task` / `Run next-task for P1-03` |
| 2. Answer open questions | reply in chat; the agent updates the task file | same |
| 3. Build it | `/implement tasks/phase-1/P1-03-profiles-avatar.md` | `Run implement for tasks/phase-1/P1-03-profiles-avatar.md` |
| 4. (auto) verify | runs inside implement | runs inside implement |
| 5. antislop findings (UI tasks) | `Fix antislop findings 1, 3, 4` / `Skip all antislop findings` | same |
| 6. Close it | `Mark P1-03 done on the board and commit.` | same |

You can also attach the workflow file instead of the slash command:
`@docs/ai/workflows/next-task.md P1-03` (Claude) or `Follow docs/ai/workflows/next-task.md for P1-03` (Codex).

**What implement does for you:** schema → domain → policy → request → controller + Vue page →
tests → `verify` (checks + reviews + antislop audit) → syncs any spec divergences → sets status
`review`.

---

## 3. Everyday prompts

| Situation | Prompt |
|---|---|
| Re-run checks only | `/verify` · Codex: `Run verify` |
| Only the fast checks | `Run scripts/check.sh --fast and fix failures.` |
| Task too big | `Split P3-01 into two tasks per the template and update the board.` |
| Change scope mid-task | `Move <thing> out of scope for P2-02; note it as a follow-up task on the board.` |
| Bug in finished work | `Bug: <symptom>. Find the cause, fix it with a failing test first, run verify.` |
| Explain code | `Explain how <feature> flows from route to Vue page. Don't edit anything.` |
| Security check on demand | `Run security-review on the current diff.` |
| Spec conformance on demand | `Run spec-review on the current diff.` |

---

## 4. UI and design

| Situation | Prompt |
|---|---|
| antislop audit of a page on demand | `Run antislop-audit on src/resources/js/Pages/Bases/Show.vue.` |
| antislop audit of the whole app | `Run an antislop after-mode audit of all UI.` |
| Phase end click-through (R-35) | `Phase <N> is complete: run the accessibility pass and antislop R-35 click-through on the phase's main flows.` |
| antislop flags something you want to keep | `Keep <element>; record it as an owner decision in DESIGN.md.` |
| Change a design choice | `Change <token/component> in specs/18 to <new>. Update DESIGN.md if it touches an owner decision.` |
| UX/accessibility advice (Claude) | `Use ui-ux-pro-max for accessibility guidance on the base composer form. Spec 18 wins on visuals.` |

Precedence to remember: **spec 18 + DESIGN.md → antislop → ui-ux-pro-max**.

---

## 5. Specs and decisions

| Situation | Prompt |
|---|---|
| Specs are wrong | `specs/<NN> §x is wrong: <correction>. Update it and every spec that references it.` |
| Change a locked decision | `I want to change locked decision <n> to <new>. List every spec affected first, then wait.` |
| Code and spec disagree | `Code and specs/<NN> disagree on <thing>. Tell me which is right per the other specs.` |
| New feature not in specs | `Add <feature> to the specs first (FR ids in 02, schema in 07, edge cases in 23), then add tasks to the board.` |

---

## 6. Where things live

| Path | What |
|---|---|
| `specs/` | The plan and source of truth. `specs/README.md` = locked decisions + load map |
| `tasks/BOARD.md` | Task index and status |
| `tasks/phase-N/*.md` | One task file each (from `tasks/_template.md`) |
| `AGENTS.md` | Shared agent instructions (Codex + Claude) |
| `CLAUDE.md` | Imports AGENTS.md + Claude-only notes |
| `DESIGN.md` | Design direction, antislop levels, owner decisions |
| `docs/ai/workflows/` | next-task, implement, verify, spec-review, security-review, antislop-audit |
| `docs/ai/rules/` | Path-scoped coding rules (backend, frontend, database, tests, specs) |
| `scripts/check.sh` | All checks (`--fast` for pre-commit) |
| `.claude/` | Claude wrappers: skills, agents, hooks, settings |
| `.agents/skills/` | Vendored antislop skills (update with `npx skills update -p`) |
| `anti-slop/` | antislop audit reports |

**Edit shared content in `AGENTS.md`, `DESIGN.md` and `docs/ai/`**, never in the `.claude/` wrappers.

---

## 7. Guardrails you may hit

| Message | Meaning | Do |
|---|---|---|
| "is generated: run … instead of editing" | Agent tried to edit generated TS types/routes | Let it regenerate (`typescript:transform`, `wayfinder:generate`) |
| "env files are off-limits" | Agent tried to touch `.env` | Edit `.env.example` / `config/` yourself |
| "is a vendored skill" | Agent tried to edit antislop files | `npx skills update -p` |
| "original brief must not be edited" | `specs/project.md` is frozen | Edit the numbered specs instead |
| pre-commit "checks failed" | `check.sh --fast` failed on staged `src/` files | Ask the agent to fix; don't `--no-verify` |
| "SKIPPED: src/ is not scaffolded" | Phase 0 P0-01 not done yet | Run `/next-task P0-01` |

---

## 8. Keeping token use low

- One task per session when you can; start a new session for the next task.
- Let the load map pick specs; don't say "read all the specs".
- antislop runs once per UI task in its own context; the R-35 click-through runs only at phase end.
- Spec review auto-skips small single-module changes.
- For quick questions, say `Don't edit anything` so the agent skips workflows.

---

## 9. Phase 0 reminder (first real work)

P0-01 must prove the assumed tooling: `docker-compose.yml` with `app`, `node`, `ssr` services,
Deptrac, TypeScript transformer, Wayfinder, CI calling `scripts/check.sh`. If any command in
`scripts/check.sh` or `.claude/settings.json` turns out different, fix them in that task.
