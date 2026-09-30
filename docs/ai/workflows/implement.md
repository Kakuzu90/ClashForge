# Workflow: implement

Input: a `tasks/phase-N/*.md` file. If missing, use the board row marked `in-progress`, else stop
and ask.

## Before coding
1. Read the task file and only the specs it cites, plus the always-in-context specs listed in
   `AGENTS.md` (04 only for write paths, policies or roles). Do not load unrelated specs.
2. Read the rule files in `docs/ai/rules/` for every path you will touch (table in `AGENTS.md`).
3. Set the task and board status to `in-progress`.
4. If the task has unresolved open questions, stop and ask.
5. UI or user-facing copy in scope → read `DESIGN.md` (dials + owner decisions). Do not load the
   antislop skills; they run once in `verify`.

## Build order (`specs/19` §8)
1. Migration + model + factory (models inside `app/Domain/<Module>/Models`).
2. Enums (backed, with `label()`/`color()`) and value objects for new invariants.
3. Service/Action owning the transaction; dispatch domain events after commit.
4. Policy, registered centrally.
5. Form Request.
6. Controller → `Inertia::render('<Area>/<Action>', [...DTO props])` or redirect. Per-resource
   `can` flags computed by the policy.
7. Regenerate TS types and Wayfinder routes; build the Vue page in `resources/js/Pages/` from
   existing `Ui*`/`Game*`/`Admin*` components. New variants → `/dev/components` in the same change.
   Implement empty / loading / error states.
8. Tests: Pest feature tests with `assertInertia` (happy path, authorization, validation), unit
   tests for value objects/scoring/parsers, security tests when required, Vitest for composables
   or components with logic.
9. Config keys for every limit, weight and window; asserted by a test reading config.

## While coding
- Follow the `AGENTS.md` non-negotiables and the relevant `docs/ai/rules/` files.
- Record decisions and divergences in the task file **Notes**.
- Stay inside **Scope**; anything else goes into Notes as a follow-up, not into the diff.

## Finish
1. Run the `verify` workflow. Set status to `review` when it is green.
2. **Spec sync.** For each divergence in the task Notes: find the owning spec via the load map in
   `specs/README.md`, grep all of `specs/` for other mentions, and edit them in place (keep
   `[NN](file)` / `§` references valid; never touch `specs/project.md`). If a divergence would
   change a **locked decision**, stop and ask instead. Replace each synced note with
   `synced → specs/NN §x`.
3. Report: files changed, tests added, spec edits (one line each).
