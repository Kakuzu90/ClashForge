---
id: P2-04
title: Build the PlayerCard and the CoC account page with progression grids
phase: 2
status: review
depends_on: [P2-02, P2-13, P0-06]
---

# PlayerCard and account page

## Spec refs
- Core: specs/18 §4 (PlayerCard, ThBadge, StatBlock, ClanChip, VerifiedBadge, GameAsset), §3 "Town Hall badge ramp", §6 "CoC account detail (`/accounts/{ulid}`)"
- Plus:
  - specs/18 §2.3 (assets unmodified, through `GameAssetResolver`, with fallbacks)
  - specs/08 (snapshots for the deltas); specs/09 §6 (data age, stale after three 404s)
  - specs/04 §2 ("View public content"); specs/07 `privacy_settings` (`show_coc_accounts`, `show_clan`); specs/17 §2 (unverified accounts are not trustworthy data)
  - specs/25 Phase 2 "Account UI"
- FR: FR-COC-14 (render from stored data, labelled with its age); FR-PROFILE-4 (show accounts, show clan) on this page
- Edge cases:
  - specs/23 §2: private clan shows "not shared"; IGN without history; a banned owner's account is hidden.
  - specs/23 §5: a field removed by the API shows "not available"; TH18 needs one config line; unknown units.

## Scope
- **Domain**:
  - `AccountReadModel::page(viewer, ulid)` returns an `AccountPageData` (404 first, Q2), with stat blocks and deltas (Q4), the clan via `ClanReadModel` (hidden when `show_clan` is off), the data age and stale state (Q5), and `can` flags.
  - `ProgressionGrid` builds the grid groups in catalogue order from `config/assets.php` (Q3). Each unit carries its `GameAssetData`, level, max level and a maxed flag.
- **Policy**: `CocAccountPolicy::view` (Q2).
- **UI**: `AccountController@show` at `GET /accounts/{ulid}`. Public, SSR, `PageMeta`; unverified rows are `noindex`.
  - `Pages/Accounts/Show.vue`: PlayerCard hero → status banner (unverified / disputed / stale / API down) → stat blocks → progression grids → sync footer with "updated {age}" (the refresh button is P2-20's).
  - New components in `Components/game/`: `ThBadge` (tier map in config), `ClanChip`, `VerifiedBadge`, `StatBlock` (delta chip, count-up honouring reduced motion), `PlayerCard` (`hero` / `standard` / `compact`; states verified, unverified, disputed, stale, skeleton), `ProgressionGrid`. Each one also goes on `/dev/components`.
  - Links: the CoC account verified notice's link goes to the page, and its email button reads "View your account" (from P2-11).
- Config: `coc.display.stale_hours`, `coc.display.delta_days`, and the TH tier map (`platform.th_tiers`, or a tokens file).

## Out of scope
- PlayerCards on profiles (own Accounts tab, public connected accounts, featured hero card, verified badge in the cover) → P2-22 (Q1).
- Account images and the gallery (FR-COC-11) → P2-23 (Q1).
- Manual refresh (P2-20), detach and featured (P2-14), the `mini` variant and credited bases (P3-01), the "viewed" sync-tier input (Q6).

## Acceptance criteria
- Functional: FR-COC-14. The page renders fully from stored data when the API is down, with an amber banner and the data age.
- Authorization: Q2 matrix; every hidden case returns the same 404 as an unknown ulid.
- Edge cases:
  - Unknown unit: placeholder with its name, appended to its group.
  - TH above the tier map: top tier.
  - Null fields read "not available"; a private clan reads "not shared".
  - Disputed accounts show the "under review" ribbon.
- States: loading (grid skeleton), empty group hidden, stale, API down.

## Tests
- Feature (`assertInertia`): the page props for each status, each Q2 viewer case (404s identical), `show_clan` off drops the clan, private fields absent (`raw_payload`, `api_sync_failures`, claims), query count ≤ 25.
- Security: IDOR (another user's unverified row is 404), a ULID enumeration check that reveals nothing.
- Unit: `ProgressionGrid` ordering (catalogue, API-name mapping, unknown appended, super troops), delta selection, the stale rule.
- Vitest: `ThBadge` tier mapping, `StatBlock` delta sign and reduced motion, `PlayerCard` state classes.

## Notes

### Open questions
Resolved by the owner, 2026-10-03 (all as recommended). Q1 and Q6 synced → tasks/BOARD.md (P2-20, P2-22, P2-23).
1. P2-04 is the components plus the account page. Profiles → P2-22 (depends on P2-04, P1-04); account images → P2-23 (depends on P2-04, P0-05).
2. The owner sees their own rows in any status except `released`. Anyone else sees `verified` / `disputed` rows only when they may view the owner's profile, `show_coc_accounts` is on, and the owner is neither banned nor pending deletion. Everything else, staff included, gets the unknown-ulid 404.
3. API name → catalogue slug: lowercase, drop dots and apostrophes, spaces to hyphens, strip " Spell", plus `assets.aliases` for exceptions. Unlisted units go last in their group, alphabetically; super troops only while active, after the base troops; Builder Base is its own group.
4. A delta is the current value minus the newest snapshot at least `coc.display.delta_days` (7) old, with no chip without one. It covers trophies, war stars, XP level and best trophies.
5. Stale means `api_sync_failures ≥ coc.sync.not_found_stale`, or `api_synced_at` older than `coc.display.stale_hours` (168). The API-down banner follows `CocApiStatus`.
6. The page stays read-only. The "viewed in the last 24 h" tier input moves to P2-20 (a throttled `last_viewed_at` write); sync into specs/09 §6 at implement.

### Decisions and divergences (implement, 2026-10-03)
1. **Who sees the page.** `CocAccountPolicy::view` (Q2) asks `PrivacyPolicyResolver` for the owner's visibility and `show_coc_accounts`. A suspended owner's verified account stays visible, as their profile does; banned and pending-deletion owners hide it. Staff get no bypass. Unknown, hidden and malformed ulids all get the same 404. synced → specs/04 §3.
2. **Grids.** `ProgressionGrid` builds them, using a new GameAssets `GameAssetCatalogue` (name → slug, `assets.aliases`, list positions). synced → specs/05 §2, specs/18 §6.
   - Groups: heroes, hero equipment, pets, troops (elixir, dark, then unlisted), active super troops, siege machines, spells, Builder Base.
   - The owner's `excluded_units` list hides super troops from the troop grid; active ones get their own group, and guardians never show.
   - Builder Base keeps the API's order, the game's, since the catalogue has no Builder Base lists. This diverges from Q3's "unlisted alphabetically" for that group only.
   - A new pet or siege machine missing from the catalogue sorts with the troops until it is listed.
   - The grids are a deferred prop behind their skeleton.
3. **Stat blocks.** Trophies, best trophies, war stars and XP level (Q4). A zero delta shows no chip. `UiStatBlock` gains `delta` / `deltaLabel` and a "Not available" null value. The count-up stays with P3-04. synced → specs/18 §4.
4. **TH tier map.** It lives in `useThTier.ts`, one line per tier, with levels above the top in tier 7. No PHP config key. synced → specs/18 §3.
5. **Card states.**
   - Unverified, stale and suspended cards mute our chrome and text only; game assets are never filtered or faded (specs/18 §2.1 (2)). Vitest checks this. R-31: muting the frame keeps the state readable without touching the asset.
   - A hidden clan reads "Clan not shared".
   - Built: `hero`, `standard`, `compact`. `mini` comes with P3-01 and the avatar with P2-22.
   - synced → specs/18 §4, specs/23 §2.
6. **Page banners.**
   - API down (with the data age, beside the site banner).
   - Account stale (only while the API is up). Never synced also counts as stale.
   - Not found in game: the copy says the account stays verified and makes no promise to keep checking, since a frozen sync stops.
   - Under review: the owner gets the verify action, which ends the review (specs/13 §5).
   - Suspended: owner only.
   - Unverified: owner, with the verify action.
   - synced → specs/18 §6, specs/09 §6 "Display".
7. **Indexing.** The page is indexable only for a verified account on a public, searchable profile. Disputed and unverified accounts are `noindex`.
8. **Verified notice.** The notice carries the account ulid (`account` param) and links to the page; older notices keep no link. `RenderedNotificationData::actionLabel` sets the email button text: "View your account" here, "View upload settings" for media, "Open Clash Commons" by default. synced → specs/16 §2.
9. **Card cache key.** `account:{ulid}:card` (specs/21 §3) is not used: the page reads one row and one snapshot, and the card varies by viewer. synced → specs/21 §3.
10. **Config.** `coc.display.stale_hours` (168), `coc.display.delta_days` (7), and an empty `assets.aliases`. synced → specs/09 §11.
11. **"Viewed" tier input.** It moved to P2-20 (Q6). synced → specs/09 §6, tasks/BOARD.md.

### Follow-ups
- **Timing difference (security review, low, accepted).** An unknown ulid answers in one query; a hidden one also loads the owner and privacy. Only someone who already holds the ulid could tell them apart (80 random bits). Fold the owner and privacy filters into one query if this ever matters.
