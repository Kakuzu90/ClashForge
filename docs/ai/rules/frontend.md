---
paths:
  - "src/resources/js/**"
  - "src/resources/css/**"
  - "src/resources/views/**"
---

# Frontend rules

- Load `specs/18-design-system.md` for any UI work.
- Vue 3 SFCs, `<script setup lang="ts">`, Composition API. Pages in `Pages/<Area>/<Action>.vue`,
  persistent layouts in `Layouts/` (set with `defineOptions({ layout })`), design system in
  `Components/{ui,game,admin}/` (`UiButton`, `GamePlayerCard`, `AdminTable`), app chrome in
  `Components/shell/`, composables `use<Thing>` in `Composables/`. Nav items live in `navigation.ts`.
- Prop types come from `types/generated.d.ts`; routes from Wayfinder (`routes/`, `actions/`, `wayfinder/`). Never
  hand-edit either — regenerate. Never hardcode URLs.
- Server state changes only via Inertia `router` / `useForm` / partial reloads (`only:`), deferred
  and merge props. No direct `fetch`/axios to app routes (presigned uploads in `useUpload` excepted).
- Vue never decides authorization: show/hide from `can` flags only; never read `role`.
- No business rules (quotas, eligibility, prices) in components.
- SSR-safe: no `window`/`document`/`localStorage` outside `onMounted`.
- Tailwind 4 semantic tokens only; arbitrary colour values are banned. Mobile-first, works at 375 px.
- Every component: keyboard reachable, visible focus ring, labels, `aria-*` per `specs/18` §8;
  empty/loading/error states designed.
- Motion per `specs/18` §7, disabled under `prefers-reduced-motion`, including page transitions.
- Game assets only through `<GameAsset>` with resolver output from props — no asset paths in `.vue`.
- No `v-html` outside `<SanitizedMarkdown>`. Root Blade view: `{{ }}` only.
- Public-page JS budget < 120 KB gzipped: code-split per page, no heavy deps without approval.
- New UI variants go into `/dev/components` in the same change.

## Third-party UI skills

Precedence: **`specs/18-design-system.md` + `DESIGN.md`** (direction) → **antislop** (filter) →
**ui-ux-pro-max** (advice). A lower layer never overrides a higher one.

### antislop (after mode, once per UI task)

- Do not load antislop while implementing. It audits the changed UI and copy once, in `verify`,
  via `docs/ai/workflows/antislop-audit.md` (the `antislop-auditor` agent in Claude Code).
- While implementing, follow `DESIGN.md` and spec 18; that is what keeps the audit short.
- Direction comes from `DESIGN.md`; its owner decisions are settled and are not re-asked.
- Any **new** conflict between spec 18 and an antislop rule: name the element and the rule, ask the
  user, and record the answer as a new row in `DESIGN.md` → Owner decisions.
- Write the one-line reason (R-31) for new visual choices in the task file Notes, not in code.
- R-02 (no em dash) covers user-facing copy only, per `DESIGN.md`.
- Findings are fixed only by number, after the user approves them.

### ui-ux-pro-max (optional advice)

Skill output is advice, never an override.

- **Allowed:** UX and accessibility guidance (focus, forms, errors, live regions, touch targets),
  Vue / Tailwind implementation details (`--stack vue`, `--stack html-tailwind`), chart guidance,
  and reviewing finished pages against UX best practice.
- **Not allowed:** `--design-system` or `--persist` (no `design-system/` folder or `MASTER.md`);
  palettes, fonts, styles or motion presets that differ from spec 18; shadcn/React output;
  Google-hosted fonts.
- When a suggestion conflicts with spec 18, `DESIGN.md`, antislop or these rules, drop it. If it looks like a genuine
  improvement to the design system, propose it to the user as a spec 18 change — do not apply it.
