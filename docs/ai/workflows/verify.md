# Workflow: verify

## 1. Checks
Run `scripts/check.sh` (the same script CI and the pre-commit hook use). Fix the first failure and
re-run until green. If a check is skipped (e.g. `src/` not scaffolded yet), say so; never report a
skipped check as passed.

## 2. Reviews
- **Spec review** — `docs/ai/workflows/spec-review.md`, only if the diff touches more than one
  `app/Domain` module, a policy/Gate, a migration, or Inertia shared props. Otherwise report
  `spec review: skipped (single module, no policy/schema/shared-props change)`.
- **Security review** — `docs/ai/workflows/security-review.md`, only if the change adds or alters a
  surface that takes user input, files, or crosses a trust boundary (auth, admin, uploads, CoC
  tokens, props exposure).

Run each review with fresh eyes: in Claude Code, delegate to the `spec-guardian` and
`security-reviewer` agents (in parallel). In tools without subagents, perform the review as a
separate pass, re-reading the diff from scratch rather than relying on implementation memory.

## 3. antislop audit (UI tasks only)
If the change touches UI or user-facing copy, run `docs/ai/workflows/antislop-audit.md` after §1 is
green. In Claude Code, delegate it to the `antislop-auditor` agent (in parallel with §2); in other
tools, run it as a separate pass. Present the numbered findings and stop: fix only the numbers the
user approves, then re-run §1. The task stays `review` until then.

**R-35 click-through** (run the app, click every interactive element at 375px and desktop, check
the console) is not run per task. It runs once at the end of each phase, alongside the phase's
accessibility pass, and its evidence goes into an antislop audit report.

## 4. Report
One line per check (pass/fail/skipped), the antislop audit report path and findings (UI tasks),
and the confirmed review findings. Fix confirmed findings
before handing back, unless they are out of scope — then list them as follow-ups.
