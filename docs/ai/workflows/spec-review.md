# Workflow: spec-review

Review a diff for conformance with the Clash Commons specs. Read-only: do not edit files.

Inputs: the task file path (if given) and the current diff (`git diff` + `git diff --cached`, or
`git diff master...HEAD`).

Read: `specs/README.md`, `specs/05-architecture.md`, `specs/19-module-structure.md`, the task file,
and the specs it cites; add `specs/04-roles-and-permissions.md` if the diff touches a policy, Gate,
role or write path.

Check, citing file:line and the violated spec section:
1. **Scope** — every change maps to the task's Scope; nothing from Out of scope.
2. **Acceptance** — each FR id, authorization rule, edge case and UI state in the task is
   implemented and tested.
3. **Locked decisions** — no Redis/driver-specific calls, no payments, no trading/gambling, game
   assets only via `GameAssetResolver`/`<GameAsset>`, no Livewire/Alpine, no new datastore, no
   `design-system/` folder or visual choices (colours, fonts, motion) outside `specs/18`.
4. **Boundaries** — no cross-module `Models` imports; `Domain` never imports `App\Http`; edge/leaf
   modules import no other Domain; business logic not in controllers or Vue.
5. **Inertia** — props are `Data` DTOs only (no models/`toArray()`); page component names match
   `resources/js/Pages`; `can` flags come from policies; generated TS files untouched by hand.
6. **Conventions** — naming table in `specs/19` §3, config keys instead of magic numbers, backed
   enums, `CarbonImmutable`/`Date::now()`, events after commit, idempotent jobs.
7. **Tests** — feature tests with `assertInertia` for happy path, authorization and validation;
   security tests where required.
8. **Spec drift** — behaviour that differs from the specs without a Notes entry.

Output: findings, most severe first, each `CONFIRMED` or `PLAUSIBLE`, with a one-line fix. If
nothing is wrong, say "No findings." No praise, no summary of the diff.
