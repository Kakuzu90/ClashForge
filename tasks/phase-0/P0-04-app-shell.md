---
id: P0-04
title: Build the app shell: persistent layouts, navigation, root meta view, shared props and footer disclaimer
phase: 0
status: done
depends_on: [P0-03]
---

# Build the app shell: persistent layouts, navigation, root meta view, shared props and footer disclaimer

## Spec refs
- Core: specs/18 §5 (layout by breakpoint, grid), §2.1 condition 6 (footer disclaimer), §8 (skip link, landmarks, one h1), §9 (footer on every page incl. admin); specs/25 Phase 0 "App shell"
- Plus: specs/06 §2 (SSR, root view emits title/description/canonical/OG/JSON-LD); specs/11 "Data exposure via page props" (shared props allowlist); specs/04 (nav visibility comes from `can` flags, never `role`); specs/03 NFR-UX-1/4, NFR-PRIV-5; `DESIGN.md`
- FR: none (foundation)
- Edge cases: none

## Scope
- **Layouts** (`resources/js/Layouts/`, Inertia persistent layouts):
  - `PublicLayout`: sticky top bar (wordmark, search, bell slots), content max-width 1200px with 16px/24px gutters, footer.
  - `AppLayout`: `PublicLayout` + mobile bottom tab nav (56px + safe-area inset) → tablet top nav → desktop left sidebar (240px, collapsible to 64px, state remembered per browser after mount).
  - `AdminLayout`: intentionally plain (body font, dense, no lift/glow), same footer.
  - All: skip-to-content link first, `header`/`nav`/`main`/`footer` landmarks, `UiToaster` mounted once, `min-h-dvh`.
- **Navigation** from one typed config (`resources/js/navigation.ts`): Home · Bases · Recruit · Market (hidden until Phase 6; Search in its slot) · Profile. Items render only when their route exists (owner decision), carry an optional `can` key checked against shared `auth.can`; active state from the current URL; `aria-current="page"`.
- **Global footer** component: the fan-content disclaimer verbatim from specs/18 §2.1 (6), linked to Supercell's Fan Content Policy, non-dismissible, on every layout.
- **Wordmark**: "Clash Commons" as text in the display font (no logo asset exists; antislop R-23 / specs/18 §2.1 (3)).
- **Root view meta** (`resources/views/app.blade.php`): `App\Support\Seo\PageMeta` (title, description, canonical, OG image/type, JSON-LD) passed as view data by controllers via a small helper; sensible defaults when absent; preload of the display font woff2 (deferred from P0-03).
- **Shared props** (`HandleInertiaRequests::share`, specs/11 allowlist): `auth` (`user` summary or null, `can` map), `flash` (success/error), `unreadCount` (null until notifications exist), client-safe `features`. Typed by a `SharedPropsData` DTO → generated TS; pages read them via a `usePageProps()` helper.
- **Home page** uses `PublicLayout`; resolves antislop audit-001 findings (100vh → `dvh`, no task IDs in visitor copy).
- **Dev previews**: `/dev/layouts/{public,app,admin}` (404 in production) so each layout can be seen and clicked through.
- Config keys added: none.

## Out of scope
- Real search, notifications dropdown, user menu, sign-in/sign-up (Phase 1 tasks)
- Filters bottom sheet / sticky filter panel (with the first listing page)
- PWA manifest and offline page (NFR-UX-8; separate task)
- Signature components

## Acceptance criteria
- Functional: each layout renders at 375px, 768px and 1280px per specs/18 §5; Home uses PublicLayout; footer disclaimer on every layout with the exact wording and link.
- Authorization: nav items with a `can` key hide when the flag is false; shared props expose nothing beyond the specs/11 allowlist.
- Accessibility: skip link first focusable, landmarks present, one `h1` per page, keyboard reaches every nav item with a visible ring, touch targets 44px.
- SSR: public pages render layout + meta server-side; meta tags present in HTML with SSR stopped.
- Persistent layout: navigating between pages keeps layout state (sidebar collapse, scroll of nav).

## Tests
- Feature: shared props shape via `assertInertia` (guest: `auth.user` null, no private keys); root view outputs title/description/canonical/OG from `PageMeta` and defaults; `/dev/layouts/*` 404 in production.
- Unit: `PageMeta` (defaults, canonical absolute URL, JSON-LD encoding is escaped).
- Vitest: nav config filtering by `can`, active item gets `aria-current`, footer disclaimer text and link, sidebar collapse toggles `aria-expanded`.

## Notes

### Decisions
- Text wordmark until a logo is designed (no invented asset).
- Sidebar collapse is a per-browser convenience (localStorage read after mount, SSR-safe).

- Nav items for unbuilt pages: the config lists all five, but an item renders only once its route exists (route name present in Wayfinder output); Phase 0 shows Home only (owner, 2026-09-30; antislop R-24).

### Open questions
None.

### Implementation notes
- Shared props are nested (`auth.user`, `auth.can`) via `SharedPropsData` → `AuthData` → `AuthUserData`; `role` deliberately omitted (Vue never reads it). `user` stays null until P1-01; `unreadCount` null until P1-07.
- `PageMeta::page()` renders the page with the meta as root-view data and `meta.title` as a prop; layouts render `PageTitle` so the client title matches the server after hydration (without it, pages lacking `<Head>` reset the title to the app name).
- With SSR on, the HTML carries two `<title inertia>` tags (root view + SSR head) with the same text; Inertia's head manager leaves one after hydration. Crawlers see the root-view title first.
- JSON-LD is emitted with `@json` (hex-escapes `<`, `>`, `&`, quotes), covered by a test with a `</script>` payload.
- The display-font preload uses `Vite::asset()`; `app.ts` globs the woff2 so it is in the manifest.
- Nav: `visibleNavItems` keeps items that have an `href` and pass their optional `can` key. Phase 0 shows Home only (owner decision).
- Verified in the browser: 375px bottom tab bar, 768px top nav row, 1280px sidebar (collapse persists across reloads, 64px), admin layout plain; skip link first, one h1, header/main/footer landmarks, no horizontal overflow, one `<title>` after hydration. With SSR stopped the meta tags are still in the HTML.
- Resolves audit-001 findings (Home uses `min-h-dvh` via the layout; no task IDs in visitor copy).

### antislop
- audit-003: 12 findings, all fixed with owner approval (see report follow-up); audit-001 findings confirmed resolved.
- R-31: nav icons use a 2.5px stroke to match the chunky, depth-bordered UI, and a tinted fill when active so state is a shape change, not only hue.

### Spec sync
- synced → specs/19 §1 (`Components/shell`, `Components/dev`, `navigation.ts`)
- synced → specs/11 (shared props shape, no role)
- synced → specs/06 §2 (`PageMeta::page()`, `meta.title`)
- synced → specs/18 §5 (nav items appear once their page exists)
- rules → docs/ai/rules/frontend.md, backend.md

