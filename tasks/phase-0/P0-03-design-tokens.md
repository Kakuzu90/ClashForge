---
id: P0-03
title: Implement design tokens, self-hosted fonts, Ui* primitives and the /dev/components gallery
phase: 0
status: done
depends_on: [P0-01]
---

# Implement design tokens, self-hosted fonts, Ui* primitives and the /dev/components gallery

## Spec refs
- Core: specs/18 §3 (tokens: colour, typography, spacing/radius/depth/motion/z), §4 Primitives, §7 (motion + reduced motion), §8 (accessibility), §9 (implementation notes)
- Plus: specs/03 §8 (NFR-UX-1…6), NFR-PERF-5/6 (CLS, JS budget); `DESIGN.md` (dials, owner decisions)
- FR: none (foundation)
- Edge cases: none

## Scope
- **Tokens** in `resources/css/app.css` `@theme` (specs/18 §3):
  - Tailwind's default palette reset (`--color-*: initial`), so only our tokens exist; raw palette + semantic tokens (`bg-*`, `border-*`, `text-*`, `brand-*`, `accent`, `state-*`, `badge-*`) + `th-tier-1..7`.
  - Fonts (`display`, `body`, `mono`), type scale (`display`, `h1`–`h3`, `stat`, `body`, `sm`, `xs`, `tag`), spacing 1–12, radii, depth/shadow, easing, durations, z-index.
  - Base layer: dark page background, body text, 2px `--border-focus` ring with 2px offset on `:focus-visible`, and the single `prefers-reduced-motion` block from specs/18 §7.
- **Fonts**, self-hosted through Vite (no Google CDN): Lilita One, Inter, JetBrains Mono via `@fontsource` packages, latin + latin-ext subsets, `font-display: swap` (specs/18 §3, specs/06 §5).
- **Ui primitives** in `resources/js/Components/ui/` (Phase 0 set from specs/25; others come with the first feature that needs them):
  - `UiButton`: primary/secondary/ghost/danger/success; sm/md/lg; `block`; `icon-only` (requires `aria-label`); states incl. loading (spinner, width-locked); depth press per §7.
  - `UiInput` + `UiTextarea`: prefix/suffix/counter; error (`aria-invalid`, `aria-describedby`), disabled, readonly; always labelled.
  - `UiCard`: flat/raised/interactive (hover lift)/feature; focus-within, selected.
  - `UiPill`: neutral/category/th/status/removable; selected.
  - `UiBadge`: verified/featured/role/rarity, each with a text label (colour never alone).
  - `UiAvatar`: 24–128, verified ring, image → initials fallback, loading.
  - `UiModal`: centred on desktop, bottom sheet on mobile; focus trap, restore focus, `Esc` closes, scrim.
  - `UiToast` (+ `useToast`): info/success/danger/reward; `role="status"` (danger `role="alert"`).
  - `UiSkeleton`: text-line/card/avatar/stat/media; shimmer off under reduced motion.
  - `UiEmptyState`: illustration slot, title, body, primary action.
  - No third-party UI kit (bundle budget: 90.5 KB baseline of 120 KB); a small `useFocusTrap` composable instead.
- **Gallery** `/dev/components` (non-production only; the controller returns 404 in production): every variant and state above, rendered from one `Dev/Components` page (specs/18 §9).
- **Token lint**: `npm run lint` fails on arbitrary colour values in `.vue`/`.ts` class strings (`[#…]`, `[rgb(…)]`, `[hsl(…)]`) (specs/18 §9).
- Config keys added: none.

## Out of scope
- Select, checkbox/radio/toggle, tooltip, dropdown, tabs, pagination, progress, alert, icon set, GameAsset (later tasks; GameAsset is P0-06)
- Signature components (PlayerCard, BaseCard, …)
- Layouts, navigation, footer disclaimer (P0-04)
- Font preload tag in the root view (P0-04, with the app shell)

## Acceptance criteria
- Functional: `/dev/components` renders every variant and state; returns 404 in production.
- Authorization: n/a (dev-only route, no data).
- Contrast: spec 18 §3 commitments verified with `contrast-check.py` and recorded in Notes.
- Keyboard: every interactive primitive reachable, visible focus ring, modal traps and restores focus, `Esc` closes.
- Reduced motion: transforms/loops off, transitions ≤50ms opacity.
- States: loading/disabled/error variants present where the inventory lists them.
- Public-page JS stays under 120 KB gzipped (gallery is its own chunk).

## Tests
- Feature (`assertInertia`): `/dev/components` renders `Dev/Components` outside production; 404 when `app.env=production`.
- Vitest: `UiButton` (icon-only without label warns/fails, loading locks width and disables), `UiInput` (error wires `aria-invalid` + `aria-describedby`), `UiModal` (focus trap, `Esc`, focus restore), `useToast` (queue, roles), `UiAvatar` (initials fallback).
- Lint: a fixture class string with `bg-[#fff]` fails the token lint.

## Notes

### Decisions
- Fonts via `@fontsource` npm packages: bundled and served by our own build, so still self-hosted with no third-party request.
- No headless UI library; hand-built primitives + `useFocusTrap` to protect the JS budget.
- Close/spinner glyphs are simple inline SVGs drawn for us; the full original icon set is a later task.

### Open questions
None.

### Implementation notes
- Contrast (contrast-check.py): text/page 16.96 · text-dim/surface 9.41 · muted/surface 5.61 · muted/raised 4.95 · on-gold/gold 10.26 · on-gold/gold-400 12.74 · on-gold/red-500 5.61 · on-gold/green-500 10.29 · on-gold/purple-500 4.65 · focus gold-400/surface 11.85 · red-400/surface 6.76 · green-400/surface 11.13 · blue/surface 6.68 · orange/surface 7.28. `border-strong`/surface is 1.79, hence the new `--border-control` for inputs.
- R-31 reasons: dark text on danger/success buttons (white fails AA); pills keep text in `fg` and put the tone on border + 15% tint so contrast never depends on hue; spacing reuses Tailwind's 4px step (identical px values to `--space-*`).
- Tailwind default colours, fonts, radii, shadows and type scale are reset in `@theme`; only spec 18 tokens exist.
- Reduced motion handled once in `app.css` (animations cut, transitions ≤50ms opacity, transforms off, shimmer static).
- `check.sh` "generated files" now compares regenerated output with the working tree (not HEAD), so new routes don't fail verify before commit.
- `.vite/` dep cache ignored by ESLint, Prettier and git; ESLint `vue/require-default-prop` off for optional TS props.
- Verified in the browser at 375px and desktop: no horizontal overflow, fonts load, bottom sheet on mobile, focus trapped/restored, Esc closes, toasts push and stack.
- Bundle: app entry 98.4 KB gz (was 90.5; Vue runtime pieces shared with the gallery chunk), gallery chunk 8.6 KB, Home page ≈99 KB of the 120 KB budget.
- Open item for Bases: specs/18 mentions per-category pill hues but defines no tokens; add them with the Bases task.

### antislop
- audit-002: 13 findings, all fixed with owner approval (see report follow-up). New owner decision (touch targets) in `DESIGN.md`.
- ESLint: `_`-prefixed unused vars allowed (attrs splitting).

### Spec sync
- synced → specs/18 §8 (touch targets via `.hit-target`)
- synced → specs/18 §3 (added semantic tokens, Tailwind names, spacing = Tailwind 4px step)
- synced → specs/18 §9 (gallery 404 in production)

