@AGENTS.md

## Claude Code specifics

- Workflows are available as skills: `/next-task`, `/implement`, `/verify`.
- Reviews in `verify` run as subagents: `spec-guardian` (spec-review), `security-reviewer`
  (security-review) and `antislop-auditor` (antislop-audit, UI tasks) — launch them in parallel.
- `.claude/rules/*` are symlinks to `docs/ai/rules/*` and load automatically by path.
- Hooks: protected-path guard before edits, Pint/Prettier after edits.
- `.claude/skills/antislop*` are symlinks to `.agents/skills/` (installed by `npx skills`); the
  antislop pointer block lives in `AGENTS.md`, imported above.
- Edit shared content in `docs/ai/`, `DESIGN.md` and `AGENTS.md`, never in the `.claude/` wrappers.
