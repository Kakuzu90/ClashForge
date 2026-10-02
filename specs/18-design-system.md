# 18 — UI/UX & Design System

## 1. Direction

A **game companion**, not a dashboard. The feel should be: bold, chunky, tactile, rewarding.
Surfaces that celebrate a player (cards, badges, stats, profiles) lean into the game vibe.
Surfaces where people work (forms, tables, admin, moderation) stay clean, dense and boring —
on purpose.

**Our visual identity is original; game content is identified with game assets.** The platform's
branding, navigation, UI components, badges, illustrations and overall look are drawn from the
genre's *conventions* (chunky buttons, resource counters, rarity tiers) and are ours. Clash of
Clans assets are used only where they identify Clash of Clans content — a troop, a hero, a spell,
a piece of equipment, a Town Hall, a clan badge, a league emblem. The full rule is §2.

## 2. Asset usage policy

Two categories of asset, two different rules. Getting this wrong is a legal risk (R2/A19 in
[24](24-risks-and-assumptions.md)), so it is stated once, here, and enforced in review.

### 2.1 Clash of Clans assets — permitted, narrowly

Supercell's Fan Content Policy permits the use of Clash of Clans assets to display or identify
Clash of Clans content. On this platform that means:

| Asset | Permitted use | Where it appears |
|---|---|---|
| Troop, hero, spell, hero-equipment, pet and siege machine icons (both villages) | Identifying that unit in a progression grid, a level chip or a base requirement | Account detail, PlayerCard, base metadata |
| Town Hall and Builder Hall imagery | Identifying a TH or BH level alongside the numeral | TH badge, base card, filters |
| Clan badges | Identifying a specific clan | ClanChip, clan cards, recruitment cards |
| League emblems | Identifying a player's or clan's league | PlayerCard, stat blocks, recruitment filters |
| Hero equipment / building imagery | Identifying the item being referred to | Progression grids |

**Conditions, all of which are requirements, not guidance:**

1. **Identification only.** A game asset may only appear where it labels the specific game entity
   it depicts. It is never decoration, never a background, never a mascot, never part of a layout
   that would work identically with a shape.
2. **Unmodified.** Assets are used as supplied — no recolouring, cropping, compositing, filters,
   outlines, distortion, animation or derivative artwork, unless Supercell expressly permits it.
   Proportional scaling and standard lossless format conversion for delivery are not modifications.
3. **Never in our identity.** Not in the logo, favicon, app icon, navigation chrome, marketing
   pages, social cards, email templates, loading states, empty-state illustrations, achievement
   badges or any component that represents *Clash Commons* rather than *Clash of Clans*.
4. **Never implying endorsement.** No Supercell logo or wordmark in our branding or domain, and no
   arrangement that suggests an official relationship.
5. **Never monetised.** A game asset may not appear in a paid placement, a marketplace listing's
   own branding, or any surface where the platform charges for the asset's presence.
6. **Attribution and disclaimer.** A visible footer disclaimer on every page: *"This material is
   unofficial and is not endorsed by Supercell. For more information see Supercell's Fan Content
   Policy."* — the wording Supercell's policy asks for, linked to that policy.
7. **Revocable.** Fan content permission can be withdrawn. Every game asset is referenced through
   one resolver (§2.3), so removing or replacing the whole category is a single change.

### 2.2 Our assets — always original

Logo, wordmark, favicon, colour system, typography choices, UI components, our own icon set,
verified/featured/role badges, rarity treatments, empty-state illustrations, achievement art,
marketing imagery and the overall visual identity are created for this platform. No Supercell
font, no Supercell colour lift, no redraw or "inspired-by" trace of Supercell art, no UI sprite
reuse.

Where a genre convention overlaps with an original asset — a chunky gold button, a tiered badge —
the convention is the influence and the execution is ours ([18 §3](18-design-system.md) tokens).

### 2.3 How game assets are handled technically

- **One resolver.** `GameAssetResolver` returns a URL + accessible name for a unit, TH level, clan
  badge or league emblem. No template ever hardcodes a game-asset path. Turning the category off,
  swapping to originals, or changing the delivery origin is then one class.
- **Two sources, invisible to callers.**
  - *Curated catalogue* (troop, hero, spell, equipment, pet and siege machine icons, Town Hall and
    Builder Hall imagery, and league emblems once a pack carries them): a finite, versioned set
    that staff download from Supercell's Fan Kits and upload to R2 under `game/{version}/`,
    **byte-for-byte**, served from our CDN. Procedure and rules in
    [10 §11](10-media-storage.md).
  - *League emblems*: pack 1 ships none, so they come from the API's `iconUrls` (allowlisted, see
    below) until a pack maps emblems to league-tier ids ([10 §11.1](10-media-storage.md)).
  - *Clan badges*: referenced from the API's own `badgeUrls` and stored as URLs
    (`clans.badge_urls` in [07](07-database-schema.md)). One badge per clan, unbounded and mutable
    — mirroring thousands of them would be a sync problem with no upside.
- **Never through the media pipeline.** Game assets have no `media` row, no quota, no sweeper, no
  variants. The re-encode in [10 §4](10-media-storage.md) would be a modification, so the pipeline
  is the wrong tool by definition.
- **Never transformed in delivery either.** CDN image resizing/optimisation is off for the `game/`
  prefix. Assets are sized with CSS, not by transforming the file.
- **Versioned and immutable.** `game/{version}/` is never edited in place; a corrected asset means
  a new pack version, activated by one config value and revertible the same way.
- **Provenance.** A committed `manifest.json` records slug, display name, category, source, byte
  size and SHA-256 per asset, and a weekly job verifies the bucket still matches it — so
  "unmodified" is demonstrable, not asserted.
- **Fallback always exists.** Unknown units and missing assets render our own placeholder plus the
  label; nothing depends on an asset being present ([09 §8](09-coc-api-integration.md)).
- **Kill switch.** `config('assets.enabled') = false` makes the resolver return placeholders
  everywhere. That is condition 7 of §2.1 in executable form, and it is the category switch: game
  assets are one category, so there are no per-kind flags.
- **Remote URLs are allowlisted.** URLs the CoC API supplies (clan badges, the league-icon
  fallback) render only when they are `https` on an allowlisted host (`assets.remote_hosts`,
  `api-assets.clashofclans.com`), with no userinfo or port; anything else resolves to the
  placeholder.
- **Accessibility.** Every game asset carries an accessible name (the unit, TH level, clan or
  league name) and never carries meaning alone — the numeral or label is always present.

### 2.4 User uploads

In-game screenshots and replay videos uploaded by users are **user content**, not platform design
assets. They are fan content under the same policy, they are subject to the media pipeline and
moderation like any other upload, and they are never reused as site chrome, marketing imagery or
component artwork.

## 3. Design tokens

Delivered as CSS custom properties on `:root`, consumed by Tailwind through `@theme`.
Dark is the default and the only theme at launch; light is a Phase 7 option, which is why every
colour is referenced through a semantic token, never a raw hex, anywhere in a component.

### Colour — raw palette

```css
--clr-navy-950: #0A0D18;  /* deepest, page edges, modals scrim */
--clr-navy-900: #0E1220;  /* background */
--clr-navy-800: #161B2E;  /* surface */
--clr-navy-700: #1E2540;  /* surface raised */
--clr-navy-600: #2A3150;  /* border */
--clr-navy-500: #3A4468;  /* border strong / disabled text */

--clr-gold-400: #FFD24D;  /* primary hover / highlight */
--clr-gold-500: #F5B800;  /* primary */
--clr-gold-600: #C99400;  /* primary press / bottom border */
--clr-gold-700: #8A6600;  /* primary deep */

--clr-purple-400: #C77BFF;
--clr-purple-500: #B04CFF; /* accent */
--clr-purple-600: #8A2FD4;

--clr-green-400: #6BE8A3;
--clr-green-500: #3DDC84;  /* success */
--clr-green-600: #2AA862;

--clr-red-400:   #FF7A7A;
--clr-red-500:   #FF4D4D;  /* danger */
--clr-red-600:   #CC3333;

--clr-blue-500:  #4DA6FF;  /* info */
--clr-orange-500:#FF8A3D;  /* warning */

--clr-text:      #F2F4FA;
--clr-text-dim:  #B8C0D9;
--clr-text-muted:#8A93B2;
--clr-text-on-gold: #1A1400;  /* AA on gold-500 */
```

### Colour — semantic tokens

```css
--bg-page, --bg-surface, --bg-surface-raised, --bg-surface-hover, --bg-scrim
--border-subtle, --border-default, --border-strong, --border-focus
--text-primary, --text-secondary, --text-muted, --text-inverse
--brand-primary, --brand-primary-hover, --brand-primary-press, --brand-primary-shadow
--accent, --accent-hover
--state-success, --state-danger, --state-warning, --state-info
--badge-verified, --badge-featured
```

Added during implementation (P0-03): `--border-control` (form control outlines, `--clr-text-muted`,
≥3:1 on every surface per WCAG 1.4.11; `--border-strong` is 1.8:1 and stays decorative),
`--text-on-gold`, `--accent-shadow`, `--state-success-shadow` / `--state-danger-shadow` (button depth
borders) and `--state-success-text` / `--state-danger-text` (the 400 shades, for state text on
surfaces). Buttons on danger/success fills use `--text-on-gold` (≥5.6:1); white fails AA there.

**Tailwind names** (`resources/css/app.css`; Tailwind's default palette is removed, so only these
exist): `page`, `surface`, `surface-raised`, `surface-hover`, `scrim` · `line-subtle`, `line`,
`line-strong`, `control`, `focus` · `fg`, `fg-secondary`, `fg-muted`, `fg-inverse`, `fg-on-gold` ·
`brand`(`-hover`/`-press`/`-shadow`), `accent`(`-hover`/`-shadow`) · `success`, `danger`
(`-shadow`, `-fg`), `warning`, `info` · `verified`, `featured` · `th-1`…`th-7`.

**Contrast commitments (WCAG AA verified, not assumed):**
- `--text-primary` on `--bg-page` ≈ 15.8:1
- `--text-muted` on `--bg-surface` ≥ 4.5:1 — muted text is never used below 14px
- `--text-on-gold` on `--brand-primary` ≥ 8:1
- Focus ring vs adjacent surface ≥ 3:1
- Every status colour is paired with a text label or icon; colour never carries meaning alone.

### Typography

```css
--font-display: 'Lilita One', 'Arial Black', system-ui, sans-serif;  /* headings, badges, stats */
--font-body:    'Inter', system-ui, -apple-system, sans-serif;
--font-mono:    'JetBrains Mono', ui-monospace, monospace;           /* player tags */
```

Self-hosted, `font-display: swap`, subset to latin + latin-ext, preloaded (display woff2 only).

| Token | Size / line-height | Weight | Font | Use |
|---|---|---|---|---|
| `--text-display` | 40/44 (mobile 32/36) | 400 | display | Page hero titles |
| `--text-h1` | 32/38 (28/34) | 400 | display | Section titles |
| `--text-h2` | 24/30 | 400 | display | Card group headers |
| `--text-h3` | 20/26 | 600 | body | Card titles |
| `--text-stat` | 28/30 | 400 | display | Big numbers, tabular-nums |
| `--text-body` | 16/24 | 400 | body | Default |
| `--text-sm` | 14/20 | 400 | body | Secondary |
| `--text-xs` | 12/16 | 600 | body | Labels, pills, uppercase +0.04em |
| `--text-tag` | 14/18 | 500 | mono | Player tags |

Display font is never used below 16px (it loses legibility) and never for paragraphs.

### Spacing, radius, elevation, motion

```css
--space-1..12: 4 8 12 16 20 24 32 40 48 64 80 96 (px)   /* = Tailwind's 4px step: 1 2 3 4 5 6 8 10 12 16 20 24 */

--radius-sm: 8px;    /* inputs, pills */
--radius-md: 10px;   /* buttons */
--radius-lg: 12px;   /* cards */
--radius-xl: 16px;   /* modals, feature cards */
--radius-full: 999px;

/* Depth is a solid bottom border that compresses on press — not a soft shadow. */
--depth-btn: 4px;       --depth-btn-press: 1px;
--depth-card: 3px;      --depth-card-hover: 5px;
--shadow-modal: 0 24px 48px -12px rgba(0,0,0,.7);
--shadow-glow-gold: 0 0 24px -4px rgba(245,184,0,.45);   /* highlights only, never text */

--ease-pop:   cubic-bezier(.34,1.56,.64,1);   /* overshoot, for rewards */
--ease-out:   cubic-bezier(.16,1,.3,1);
--dur-fast: 120ms; --dur-base: 200ms; --dur-slow: 400ms;

--z-base:0 --z-dropdown:100 --z-sticky:200 --z-modal:300 --z-toast:400
```

### Town Hall badge ramp

A distinct colour per TH range, always accompanied by the number — colour is decoration, the
numeral is the information. The badge frame, ring and tier colour are **ours**; the optional Town
Hall image inside it is a game asset used unmodified to identify that TH level (§2.1). The badge
must remain legible and correct with the game asset absent, because it renders without one wherever
the asset is unavailable or the category is switched off.

| TH range | Token | Colour | Rationale |
|---|---|---|---|
| 1–4 | `--th-tier-1` | `#8A93B2` slate | Beginner |
| 5–7 | `--th-tier-2` | `#6BA84F` green | Early |
| 8–10 | `--th-tier-3` | `#4DA6FF` blue | Mid |
| 11–12 | `--th-tier-4` | `#B04CFF` purple | Late |
| 13–14 | `--th-tier-5` | `#FF8A3D` orange | Advanced |
| 15–16 | `--th-tier-6` | `#F5B800` gold | Endgame |
| 17+ | `--th-tier-7` | `#FF4D4D` + gold ring | Current max |

Implemented as one component reading a tier map, so a new TH level is a config line.

## 4. Component inventory

### Primitives

| Component | Variants | States |
|---|---|---|
| **Button** | primary (gold), secondary (surface+border), ghost, danger, success | default, hover, active(compressed), focus-visible, disabled, loading(spinner, width-locked) |
| | sizes: sm 32px, md 40px, lg 48px; `block`, `icon-only` (square, aria-label required) | |
| **Input / Textarea** | default, with-prefix, with-suffix, with-counter, date (native picker), password (show/hide toggle inside the field: `aria-pressed`, "Show password" / "Hide password", 44px hit area; owner decision, 2026-10-02) | default, focus, error, disabled, readonly |
| **Select** | native-styled, searchable (Vue combobox) | same |
| **Checkbox / Radio / Toggle** | — | default, checked, indeterminate, focus, disabled |
| **Pill / Tag** | neutral, category (per-category hue), th, status, removable | default, hover, selected |
| **Badge** | verified (gold check), featured (purple star), role (mod/admin), rarity | — |
| **Avatar** | 24/32/48/64/96/128, with verified ring | image, initials fallback, loading |
| **Card** | flat, raised, interactive (hover lift), feature | default, hover, focus-within (interactive only), selected |
| **Modal / Sheet** | centered modal (desktop), bottom sheet (mobile) | open, closing; focus-trapped |
| **Toast** | info, success, danger, **reward** (gold, animated) | enter, idle, exit; the server's `flash.success` / `flash.error` after a save or redirect arrive as success / danger toasts from the layout's toaster (`useFlashToasts`), so forms show no inline "Saved." (2026-10-02) |
| **Tooltip** | top/bottom/left/right | — |
| **Dropdown menu** | — | keyboard navigable (WAI-ARIA menu button: Enter/Space/Down opens on the first item, Up on the last; arrows, Home, End move; Escape closes and returns focus; Tab and an outside click close) |
| **Tabs** | underline, pill | active, focus |
| **Pagination** | numbered, load-more, infinite-sentinel | loading |
| **Progress** | bar, ring, upload-progress | determinate, indeterminate |
| **Steps** | numbered steps of a short fixed flow (`UiSteps`, the attach flow; P2-11) | done, current (`aria-current="step"`), upcoming |
| **Skeleton** | text-line, card, avatar, stat, media | shimmer (disabled under reduced-motion) |
| **Empty state** | with illustration slot, title, body, primary action | — |
| **Alert / Banner** | info, warning, danger, maintenance (a wrench outline, so the kind reads from the shape as well as the colour) | dismissible (`dismissible` adds a close button; the parent decides what dismissing means). The site's Clash of Clans API banner sits at the top of every layout's main area and stays dismissed for the rest of the tab's session while that outage lasts (P2-10) |
| **Icon** | 16/20/24, from one original outline+solid set — platform iconography only | — |
| **GameAsset** | unit / th / clan-badge / league; sizes 24/32/48/64 | loaded, fallback (our placeholder shape per kind + short text: initials or the TH numeral), missing (image error → fallback). Renders assets unmodified via `GameAssetResolver` (§2.3): scaled only, never rounded, bordered or filtered; accessible name required |

### Signature components (the ones that carry the product's identity)

**PlayerCard** — a CoC account rendered like a collectible card.
Variants: `hero` (profile header, full art treatment), `standard` (grid), `compact` (list row),
`mini` (inline attribution on a base card).
Anatomy: TH badge (corner, tier-coloured), avatar/IGN, player tag in mono, league emblem,
trophy/war-star/XP stat blocks, clan chip with role, verified badge, featured star, last-synced
timestamp.
States: verified · unverified (desaturated, "unverified" label) · disputed ("under review" ribbon) ·
stale (dimmed with "data from 3 days ago") · loading skeleton.

**BaseCard** — screenshot-led.
Anatomy: 16:9 screenshot with a subtle top-to-bottom scrim, TH badge (top-left), category pill
(top-right), video-present icon, title (2-line clamp), creator mini-card, resource-counter row
(likes/copies/views), bookmark toggle (top-right on hover/always on touch).
States: default · hover (lift 2px, border brightens) · processing (skeleton + "processing video") ·
hidden-by-moderation (staff only, red border) · no-image fallback (generated gradient + TH numeral).

**ThBadge** — hexagon-ish chunky badge (original shape), tier colour, numeral always visible,
sizes sm/md/lg. Optional unmodified Town Hall image slot resolved through `GameAssetResolver`;
falls back to numeral-only.

**StatBlock** — large `--text-stat` number with `tabular-nums`, icon above or left, label below,
optional delta chip (`+142` green / `-30` red) comparing to the previous snapshot.
Counts up on first view (respecting reduced-motion).

**ResourceCounter** — small icon + number pill used for likes, copies, views, comments. Uses
**our** icon set: these count platform actions, not game entities, so no game asset belongs here.
Interactive variant (like, bookmark) pops on activation.

**ClanChip** — clan badge (the clan's own badge image from the API, unmodified, in our frame),
clan name, role label, level. Falls back to a generated initial tile when no badge is available.

**RecruitmentCard** — clan badge or player avatar, title, requirement pills (TH, trophies,
language, war frequency), status indicator dot + label, bumped-at, primary action.

**VerifiedBadge** — gold check in a chunky circle, with a tooltip explaining what verification
means. Never rendered without an accessible label.

**RewardToast** — gold-bordered toast with an overshoot entrance and a subtle glow, used **only**
for genuine achievements: first verification, first base published, milestone reached, badge
earned. Overusing it destroys it; it is limited to a defined event list.

### Admin components (intentionally plain)
DataTable (sortable, filterable, bulk-select, sticky header), FilterBar, DetailPanel,
ActionPanel (with mandatory reason field), AuditTrailList, DiffViewer (before/after JSON),
EvidenceGallery, AssignmentControl, PriorityBadge, SlaIndicator.
These use body font, `--radius-sm`, no lift, no glow, denser spacing (`--space-2` rhythm).
Built so far (P1-06): `AdminTable` (sticky header inside its own keyboard-scrollable region,
skeleton rows that keep the column classes, expandable detail rows; sorting and bulk-select join
when a table needs them), `AdminFilterBar` (a GET search form with Apply and Clear),
`AdminDiffViewer` (each change spelled out in words, colour only backs it up) and `AdminNav`;
`AdminAuditTrailList` (P1-12: the latest entries about one record, who acted and what changed);
`AdminActionPanel` (P1-14: actions from the server's ability flags, each a dialog with reason,
note and a live "What they will see" preview) and `AdminSanctionHistory`; `AdminPanel` (P1-13: a
titled plain box for the dashboard panels, numbers in the body font at `--text-h3`, not StatBlock).

## 5. Layout

### Mobile (< 768px) — the primary target
- Sticky top bar: logo, search icon, notification bell (signed in: a link to `/notifications`, a gold
  count badge capped at "99+", the count in its `aria-label` and a polite live region).
- **Bottom tab navigation** (5 items, 56px + safe-area inset): Home · Bases · Recruit · Market ·
  Profile. Market is hidden until Phase 6; the slot is Search until then. An item appears only once
  its page exists (owner decision, P0-04), so early phases show fewer tabs.
- Single-column content, 16px gutters.
- Filters open as a bottom sheet, not an inline panel.
- Primary actions are reachable with a thumb; destructive actions never are.
- Minimum touch target 44×44.

### Tablet (768–1023px)
Two-column base grid; the bottom nav gives way to the desktop top-bar nav below (owner decision,
2026-10-02); filters become a collapsible inline panel.

### Desktop (≥1024px)
- No sidebar and no second nav row in the member layout: the primary nav sits inside the top bar as
  plain text links (no icons), straight after the wordmark; the current one is gold with a 4px gold
  bar along the bottom edge of the header (owner decision, 2026-10-02).
- Top bar, signed in, left to right: wordmark, primary nav … search (prominent, `/` keyboard shortcut once it
  ships), the staff link (Admin for admins, Reports for moderators), notification bell, user menu.
- Content max-width **1200px**, centred; base grid 3 columns (4 at ≥1440px).
- Filters as a sticky left panel inside the content column on listing pages.

### Grid
12 columns, 24px gutters at desktop, 16px at mobile. Section rhythm `--space-8`.

## 6. Page layouts

Each page below specifies its structure and its three required states
(**empty**, **loading/skeleton**, **error**).

### Home feed (`/`)
Hero strip (logged-out: value proposition + register CTA, "Create your account" since P1-08;
logged-in: featured player card + quick actions) → TH filter chip row (sticky on scroll) → sort tabs (Trending / New / Most Copied) →
base card grid → load-more.
*Empty:* "No bases match these filters" + reset-filters action + a "browse all TH levels" link.
*Loading:* 6–9 base-card skeletons in grid; chip row renders immediately.
*Error:* inline error card with retry; the chrome and navigation stay usable.

### Base detail (`/bases/{slug}`)
Breadcrumb → title + TH badge + category pill → media viewer (screenshot gallery with swipe,
video tab with poster) → **primary action: "Copy base link"** (large, gold, sticky at the bottom on
mobile) → stat row → description → tags → creator card → share row → report link → comments →
related bases.
*Empty (no comments):* "Be the first to comment" + input focus.
*Loading:* media placeholder at the correct aspect ratio (no layout shift), skeleton text lines.
*Error (base processing):* "This base is still processing" with an auto-refresh poll.
*Error (not found/removed):* dedicated page explaining removal vs non-existence, without leaking
which.

### Player profile (`/u/{username}`)
Cover band with avatar, display name, username, verified badge, member-since, country flag,
follow button (P2) → featured PlayerCard (hero variant) → stat blocks (bases, likes received,
copies, war stars across accounts) → tabs: Accounts · Bases · Activity (P2) · Bookmarks (own only)
→ tab content.
*Empty (no accounts):* prompt to attach an account (own profile) / "no public accounts" (others).
Until PlayerCards (P2-04), the own Accounts tab lists the owner's rows (name, tag, status, a
"Verify" link on unverified ones) and shows the attach prompt only when there are none; other
viewers see nothing there yet (P2-11).
*Empty (no bases):* own → "publish your first base" CTA; others → muted message.
*Loading:* cover + avatar skeleton, then tab content skeletons.
*Error (hidden profile):* a `private` profile (to anyone but the owner), a `members` profile (to a
guest) and a banned or pending-deletion owner render the same 404 as an unknown username, so the
page never confirms the account exists ([11](11-security.md)). That page is `Profile/NotFound`:
an empty state with a link home, copy that never names the reason.
*Own profile:* "Edit profile" and "Privacy" buttons in the cover band. The header avatar opens the
account menu: Your profile · Settings · Sign out.

### CoC account detail (`/accounts/{ulid}`)
PlayerCard hero → verification status banner → stat blocks with deltas → hero/troop/spell/equipment
progression grids (level chips, maxed indicator) → custom images gallery → bases credited to this
account → sync status footer ("updated 12 minutes ago" + manual refresh button).
*Empty (no images):* owner sees an upload dropzone; others see nothing.
*Loading:* progression grid skeleton.
*Error (API stale):* amber banner "Game data is temporarily unavailable — showing data from
{time}", content still fully rendered.

### Attach account flow (`/accounts/attach`)
Three steps with a progress indicator: (1) enter tag → confirmation card; (2) in-game token
instructions with an illustrated, original step-by-step and a paste field; (3) success screen with
a RewardToast and a "set as featured" prompt. As built (P2-11, owner decisions 2026-10-02): the
token steps are numbered text until original illustrations exist; the RewardToast shows on the
first verified account only; step 3 says "This is now your featured account" when it became
featured, and the switch prompt joins with P2-14. A user who may not attach (email not
confirmed) sees why on step 1 instead of the form.
*Error states:* tag not found · already verified by someone else (conflict card with both the token
path and the dispute path) · invalid token (with "tokens expire in a few minutes — copy a fresh
one") · API unavailable (retry later, nothing lost).

### Base composer (`/bases/create`)
Step-less single form: media dropzone first (upload begins immediately, progress per file) →
title/description → TH + category selectors (large tactile tiles, not a dropdown) → base link with
live validation and a parsed preview → tags with suggestions → visibility → publish.
*Loading:* per-file upload progress; publish button disabled with a reason ("2 files still
uploading").
*Error:* per-field inline errors, per-file upload errors with retry, and a draft preserved in
`localStorage` so nothing is lost.

### Recruitment listing (`/recruit`) — P2
Toggle: Find a Clan / Find Players → filter bar (bottom sheet on mobile) → recruitment cards →
pagination.
*Empty:* "No posts match" + widen-filters suggestion + "create a post" CTA.
*Loading:* card skeletons.
*Error:* retry card.

### Marketplace listing (`/market`) — P3
Category chip row → listing grid with seller cards → filters.
Always shows a persistent, dismissible **"we do not handle payments"** banner on the index and an
interstitial before a first order.

### Notifications (`/notifications`)
Grouped list, unread highlighted with a gold left border, filter tabs by category, mark-all-read.
Built in P1-07: plain rows until grouping lands (FR-NOTIF-5), each with a "New" label beside the
border so the state is not colour alone; opening a row marks it read and follows its link, and a
read row with nowhere to go is plain text. The tabs are links (`?category=`) for the categories
that have a type: All · Security · Bases in v1, Moderation with P3-06. "Mark all as read" shows
while anything is unread. `NotificationItem` is the row.
*Empty:* "You're all caught up" with an illustration (an original bell with a check); a category
tab says "Nothing in {category}".
*Loading:* row skeletons while a tab or page loads. *Error:* inline alert with the request id
(`useVisitError`).

### Sign-up and email confirmation (`/register`, `/email/*`)
The sign-in card register: a single card form with field hints (username rules, password length),
the invisible Turnstile widget (it shows a line when it could not load) and links to and from
sign-in. Every registration ends on "Check your email". The link opens a page that names the account
with a "Confirm my email" button, then a result (confirmed / already confirmed / link not working,
with a new-link button). A signed-in unverified account sees an info alert at the top of every app
page linking to `/email/verify` (masked address, "Send a new link").

### Settings (`/settings/*`)
Left sub-nav (Profile · Privacy · Accounts · Security · Notifications · Danger zone) with plain,
dense forms. Danger zone is visually separated with a red border and requires password confirmation.
*Danger zone:* `/settings/danger-zone`, a plain red-bordered section in the settings layout.
Explains hiding the profile, signing out every device, the 30-day cancellation window, permanent
username reservation and retained moderation/audit records. A native checkbox confirms the
consequences; an inline current-password field and the danger button request deletion without
leaving the form for password confirmation. Password errors appear inline; the field clears after
each request and is disabled while submitting. Suspended accounts see the explanation
and an unavailable message, with no form; the ability comes from the server policy.
*Profile:* avatar (a picked photo opens a crop dialog first: drag to reposition, zoom slider, wheel or
pinch, arrow keys and +/-, inside a square frame with a round guide; Save uploads the cropped square,
Cancel uploads nothing; owner decision, 2026-10-02), then a Username flat card (current `@name`, the 30-day and 90-day rules, the new-name
field and the current password; instead of the form, the date the next change opens, a link to
confirm the email, or "unavailable while suspended"), then the profile form.
*Security:* Password, Email address (the masked current address, a pending change with "Send the
link again" and "Cancel the change", and the new-email form with the current password, which "Send the link again" uses too) and Where you're signed in, each a flat card. The email-change link lands on
`Settings/EmailChangeConfirm`, shaped like the verification result page.
*Privacy:* a visibility radio group (Everyone · Signed-in members · Only me, each with a one-line
description) and toggles for show accounts, show clan, recruitment contact and search. The
stored `show_activity` and `allow_marketplace_contact` stay off the form until P2 / Phase 6.
*Notifications (P1-15):* global non-security email toggle and per-category email toggles, with
Security displayed as always on. In-app controls and digests join in Phase 5. Show submitting,
success and validation/error states using existing form primitives. The signed unsubscribe page
uses the public layout, requires no sign-in and has a confirmation button; GET changes nothing.
Show success after POST and a generic invalid/expired-link state, without an email address
([16 §5](16-notifications.md); owner decision, 2026-10-01).

### Moderation (`/moderation/reports`)
The moderators' report queue in the member layout (specs/04 §3). Until reports exist (P3-06) it is
the empty state "No open reports".

### Admin (`/admin/*`)
Left nav (Dashboard · Reports · Disputes · Users · Content · Media · Marketplace · System · Logs) →
DataTable views → detail/action panels. From 768px the top bar and left nav stay fixed and only the
content column scrolls; "Back to site" sits at the bottom of the left nav (below 768px, the end of
the folded menu). The user list shows each account's avatar beside the username; status pills never
wrap (owner decision, 2026-10-02). No game styling, no animation beyond 120ms fades,
information-dense, keyboard-first. Each nav item appears once its page exists and the viewer
holds its ability (`auth.can`, `adminNav` in `navigation.ts`); the longest matching link is the
current one.
*Empty:* "No open cases" — a genuinely good state, presented as such.
*Loading:* table row skeletons that preserve column widths. A table's rows arrive as a deferred
prop, so the first response carries the filters and the skeleton shows until the rows load.
*Error:* inline error with the request id, for support correlation (`useVisitError`: a 5xx or a
network failure becomes an inline alert with the `X-Request-Id` instead of Inertia's error modal;
a 429 from `admin-search` becomes a "wait a moment" warning).
*Dashboard (`/admin`):* one panel per FR-ADMIN-5 surface, each its own deferred prop in its own
group, with its own skeleton, empty state ("No failed jobs in the last 24 hours") and inline error;
a failed panel's "Try again" reloads every panel still missing. The Clash of Clans API panel (P2-10) is one of them. Panels sit behind
`view-platform-stats` (admin+) and are left out of the response for anyone else; moderators see an
empty state until the report queue lands (P3-06). Sizes in decimal units (1 GB = 1000 MB), as
the bucket bills. Nothing on it is cached or polled. Its failed-jobs panel links to System Health.
*System Health (`/admin/system`, P2-06):* the same panel pattern behind `view-platform-stats`, one
deferred group per panel: Queues (waiting, delayed, running, oldest wait, alerts as text pills),
Scheduler (heartbeat age; no heartbeat reads as "No heartbeat recorded", not stopped), Clash of
Clans API (breaker plus each key by id, state, reason and since), Failed jobs (every kept failure
by job class and queue, job class and queue only). Durations via `formatDuration`: hours and
days carry the next unit ("3 h 5 min", "2 d 4 h"), minutes and seconds stand alone ("7 min"). The nav item "System" sits between Users and Logs
(owner decision, 2026-10-02).

## 7. Motion

| Interaction | Motion | Duration |
|---|---|---|
| Button press | `translateY(3px)`, bottom border 4px→1px | 80ms |
| Card hover | `translateY(-2px)`, border brightens, depth 3→5px | 200ms `--ease-out` |
| Like / bookmark | Icon scale 1→1.35→1 with `--ease-pop`, counter increments | 400ms |
| Stat count-up | 0 → value, eased, once per page view, only for values > 0 | 800ms |
| Reward toast | Slide + slight overshoot + gold glow fade | 500ms |
| Modal / sheet | Fade scrim 150ms; sheet slides up 250ms `--ease-out` | — |
| Skeleton shimmer | 1.5s linear loop | — |
| Page transition | None. Inertia swaps pages instantly; a top progress bar shows only after 250 ms | — |

`@media (prefers-reduced-motion: reduce)` — all transforms and loops are disabled, transitions drop
to ≤50ms opacity only, count-ups render the final value immediately, and shimmer becomes a static
tint. This is implemented once in the base stylesheet, not per component.

## 8. Accessibility checklist (enforced in review)

- Contrast AA for all text and meaningful non-text (verified against the token pairs above).
- Colour is never the only carrier of meaning — TH badges show numerals, statuses show labels,
  charts show patterns.
- Every interactive element is reachable by keyboard with a visible 2px `--border-focus` ring and a
  2px offset.
- Modals and sheets trap focus, restore it on close, and close on `Esc`.
- Skip-to-content link as the first focusable element.
- Landmarks: `header`, `nav`, `main`, `footer`; one `h1` per page and no skipped heading levels.
- All images have alt text; decorative images use `alt=""`; user-uploaded screenshots default to
  the base title.
- Icon-only buttons carry `aria-label`.
- Live regions: toasts `role="status"`, errors `role="alert"`, the notification count
  `aria-live="polite"`.
- Forms: every input has a `<label>`, errors are linked via `aria-describedby`, and the first error
  receives focus on failed submit.
- Touch targets ≥44×44 with ≥8px separation. Controls whose §4 visual size is smaller keep it and get
  an invisible 44×44 hit area (`.hit-target`); text inputs are 44px tall below `sm` (owner decision in
  `DESIGN.md`).
- Tested with keyboard only and with VoiceOver/NVDA on the five main flows before each phase ships.

## 9. Implementation notes

- Tailwind CSS 4 with `@theme` mapping the semantic tokens; **arbitrary colour values are banned**
  in templates — a lint rule enforces token usage.
- Vue SFC components under `resources/js/Components/{ui,game,admin}/` (`UiButton`, `GamePlayerCard`,
  `AdminTable` …), with a living component
  gallery at `/dev/components` (404 in production) showing every variant and state. This page is
  the design system's actual source of truth and must be updated with each new variant.
- Vue handles local interactivity (menus, sheets, tabs, optimistic like animation). Anything that
  touches server state goes through an Inertia visit, partial reload or `useForm` — never a
  hand-rolled `fetch`/axios call (presigned uploads excepted). Components must be SSR-safe: no
  `window`/`document` access outside `onMounted`.
- Icons: one original 24px outline set, delivered as an inline SVG sprite; no icon font, no
  third-party set that ships brand marks. Platform iconography never mixes with game assets inside
  the same sprite.
- Game assets (units, Town Halls, clan badges, league emblems) are rendered **only** through
  `GameAssetResolver` (§2.3) in PHP — `GameAssetData` carries `{ kind, url, alt, short, width,
  height }`, `url` null meaning "show our placeholder" — and
  the `<GameAsset>` Vue component, unmodified, always with an accessible name, and always with a
  non-asset fallback. A review-checklist item and an ESLint rule ban game-asset paths in `.vue`
  files.
- Illustrations for empty states: original, simple geometric shapes in brand colours — no
  characters, no redraws of game art.
- Images: `loading="lazy"` below the fold, explicit `width`/`height`, `srcset` from media variants,
  `fetchpriority="high"` on the base-detail hero image only.
- Dark-mode-only at launch; every colour already routes through a semantic token, so adding light
  is a token-file addition, not a component rewrite.
- The fan-content disclaimer (§2.1 condition 6) is part of the global footer component, present on
  every page including admin, and is not dismissible.
