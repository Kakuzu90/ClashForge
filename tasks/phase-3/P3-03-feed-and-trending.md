---
id: P3-03
title: Build the base feed (filters, sorts, keyset pages), the trending job, the home feed and /bases
phase: 3
status: done
depends_on: [P3-01]
---

# Feed and trending

## Spec refs
- Core: specs/17 §4 (keyset pagination, ≤50 per page, anonymous max page 100, index-covered filters), §5 (`trending_score` formula, 15-minute recompute), §6 (home feed anonymous and signed in, "New bases", cache times); specs/21 §2 (L4 counters), §3 (`feed:trending:th{n}:p{page}`, `feed:new:p{page}`, `feed:category:{cat}:th{n}:p{page}`), §4 (`CacheInvalidator`, publish busts feeds), §5 (the authenticated feed is not cached per user)
- Plus: specs/20 §2 (`RecomputeTrendingJob`: bases active in 7 days every 15 min, full pass nightly), §4 schedule (`bases:recompute-trending`); specs/22 §6 (feed page 3–6 queries, < 40 ms; recompute of 10k bases 2–5 s); specs/18 §4 (BaseCard, ThBadge, ResourceCounter, PlayerCard `mini`), §5 (mobile filters as a bottom sheet, desktop sticky left panel, 3–4 column grid), §6 Home feed (hero, TH chip row, sort tabs, grid, load-more, empty / loading / error); specs/12 §6 and §7 (suspended and banned authors' content hidden); specs/07 `base_layouts`, `base_metrics` (indexes); specs/03 NFR-PERF-2, NFR-PERF-7
- FR: FR-BASE-12, FR-BASE-13
- Edge cases: specs/23 §3 (two users, same hash: trending penalises duplicate clusters; author banned: bases hidden, not deleted)

## Scope
- **Domain** (Bases):
  - `BaseFeedQuery` (a read model): published, `public`, not deleted, the author not hidden (Q3); filters TH, category, tag, min likes, has video; sorts newest, trending, most liked, most copied. Keyset pagination with an opaque, validated cursor, 24 per page (config), anonymous viewers stop at `bases.feed.max_pages`. Eager-loads the author, the credited account (for the `mini` PlayerCard) and the card screenshot or video poster.
  - `BaseCardData` (Inertia DTO): ulid, slug, title, TH, category, hasVideo, cover image (url, width, height) or null, likes / copies / views, the author (username, display name, avatar), and the credited account (name, TH) or null.
  - `TrendingService` + `bases:recompute-trending`: the specs/17 §5 formula from `base_metrics`, with weights and the decay in `bases.trending.*`, and the duplicate penalty (Q4). Every 15 minutes for active bases, nightly for all; chunked, writes `score_updated_at`.
  - `Bases\Support\CacheInvalidator::baseFeeds()` on `BasePublished`; the trending run bumps the feed key version.
- **UI:**
  - `/` (Home/Index): hero (existing) → sticky TH chip row → Trending / New / Most copied tabs → card grid → "Load more" (Inertia merge).
  - `/bases` (Bases/Index): the full FR-BASE-13 filters (desktop: sticky left panel; mobile: bottom sheet) and four sorts; the filter state lives in the URL query.
  - Components: `GameBaseCard` (default, no-image fallback; processing and staff-hidden states wait for P3-08 and P3-06), `UiResourceCounter`, `GamePlayerCard` `mini`; added to `/dev/components`. The bottom nav and the top bar gain "Bases".
  - States: empty ("No bases match these filters" + reset + "browse all TH levels"), 6–9 card skeletons, inline error card with retry.
- **Config:** `bases.feed.{per_page, max_pages, cache_ttl_trending, cache_ttl_new}`, `bases.trending.{like, copy, comment, view, offset_hours, exponent, active_days, duplicate_penalty}`.

## Out of scope
- TH, category, combined and tag landing pages, plus the profile's Bases tab (split to P3-10); the base detail page, copy-link redirect and view counting (split to P3-11).
- Likes, bookmarks and the card's bookmark toggle (P3-04); search (P3-05); JSON-LD and sitemap (P3-07); followed authors (Phase 5).

## Acceptance criteria
- Functional: FR-BASE-12 (score recomputed on schedule, never live); FR-BASE-13 (every filter and sort, combinable).
- Authorization: public pages, no write path. Unlisted, private, processing, hidden, removed and deleted bases never appear. Neither do bases by a suspended, banned or deleting author, while a restricted author's do (specs/04 §1).
- Edge cases: duplicate-cluster penalty; a banned author's bases come back on unban, untouched; a tampered or foreign cursor gives a validation error, not a 500.
- States: empty, loading, error as above, at 375 px and desktop.

## Tests
- Feature: `assertInertia` for `/` and `/bases` (prop shape, no private fields such as `layout_hash`, `base_link`, `flagged_reason` or `user_id`); each filter and sort; keyset paging without repeats; the anonymous page cap; the visibility matrix; the query count ≤ 25; feed caching and the bust on publish.
- Unit: the trending formula, decay and penalty; the cursor encoding and validation.
- Command: `bases:recompute-trending` (active set, nightly full pass, chunking).
- Vitest: the filter state ↔ URL query and load-more merging.

## Notes

### Open questions
Resolved by the owner, 2026-10-07 (all as recommended); specs synced at implement → Finish.
1. **Split** (synced → specs/25 §4). The specs/25 "Discovery" workstream does not fit one task. Recommended: this task (feed, trending, `/` and `/bases`), then **P3-10** (TH / category / combined / tag landing pages plus the profile's Bases tab) and **P3-11** (the base detail page `/bases/{slug}`, related bases, copy-link redirect and counter, view dedupe and aggregation). P3-04, P3-07 and P3-08 would also depend on P3-11 (comments and the after-publish redirect live on the detail page). Rows added to the board.
2. **Home vs `/bases`.** specs/18 §6 gives `/` a TH chip row and three tabs; FR-BASE-13 needs the full filters. Recommended: `/` keeps the specs/18 layout, `/bases` gets every filter and sort, and the Bases nav item points there.
3. **Hiding sanctioned authors** (synced → specs/12 §6, specs/23 §3). The feed must drop bases of suspended, banned and deleting authors, but Bases may not read Auth's models. Recommended: Auth exposes `UserStatusService::hiddenAuthorIds()`, a subquery the feed uses as `whereNotIn('user_id', …)`, with the effective status (a passed `status_expires_at` counts as active). That is one indexed query, and no flags to keep in sync. Unhiding is automatic.
4. **What trending can use now.** specs/17 §5's anti-gaming rules (25 % weight for young or unverified accounts, no self or same-IP interactions) need the like and copy rows that arrive with P3-04 and P3-11. Recommended:
   - This task applies the formula to the `base_metrics` counters and treats "active" as published in the last `active_days` (7).
   - P3-04 adds the weighting and an activity time (appended to its board row).
   - The duplicate-cluster penalty is in now: within one `layout_hash`, only the earliest published base keeps its full score, and later ones are multiplied by `duplicate_penalty` (0.25).
5. **Signed-in home.** specs/17 §6 caches the signed-in home "per TH bucket" and defaults it to the user's TH ±1, while specs/21 §5 says the signed-in feed is not cached per user. Recommended:
   - cache the viewer-independent card list per TH bucket, and add the viewer's own state (likes, bookmarks, P3-04) per request;
   - default the TH chip to the featured verified account's TH ±1, shown as a removable chip, with "All" one tap away;
   - sync specs/17 §6 and 21 §5 to say so.

### Decisions and divergences (implement, 2026-10-07)
1. **Cross-module reads for a card**: one public call per module, each batched, so a page of 24 cards costs about 6 queries (specs/22 §6):
   - Auth: `UserStatusService::hiddenAuthorIds()` (Q3), a subquery for `whereNotIn`. Anonymised accounts are banned, so they are covered too.
   - Users: new `AuthorDirectory::forUsers()` (`AuthorData`: username, display name, avatar).
   - PlayerAccounts: `AccountCredits::creditsFor()` (`CreditedAccountData`) and `featuredThLevel()`.
   - Media: `MediaReadService::firstReadyVariants()`.
   synced → specs/05 §1 (Auth, Users, PlayerAccounts, Media, Bases rows), specs/19 §1.
2. **What a card shows of its author:**
   - The username, always, for attribution.
   - The display name and avatar only when the profile is public (security review). A private or members-only profile is a 404 to strangers, so its name must not appear on a public card.
   - A credit only when the account is still held and the holder's profile is public with `show_coc_accounts`.
   - No privacy row counts as private (specs/21 §3).
   synced → specs/18 §4 (PlayerCard `mini`), specs/05 §1 (`AuthorDirectory`).
3. **Caching:**
   - Every feed key sits under one `feed:version` (specs/21 §4, mechanism 3), not the explicit deletes that §4 mechanism 2 describes.
   - `Bases\Services\CacheInvalidator::baseFeeds()` bumps the version (specs/21 §4's one invalidator per module).
   - `ForgetCachedFeeds` calls it on these events, so hidden content and changed credits leave cached pages at once, as the §4 rule requires:
     - `BasePublished`;
     - `SanctionApplied` / `SanctionLifted`;
     - `CocAccountReleased` / `CocAccountOwnershipTransferred`;
     - the new `Users\Events\PrivacySettingsChanged`;
     - the new `Auth\Events\AccountDeletionRequested`.
   - Every trending run bumps it too.
   - Cached: trending and new orders, filtered by Town Hall and category only, pages 1 to `bases.feed.cache_max_page` (5). Signed-in viewers share the cache (Q5). Tag, likes and video filters run live, and so do deeper pages.
   - Both pages carry the `feed` limiter (`bases.feed.requests_per_minute`, per user, else per IP).
   synced → specs/21 §3, §4, §5; specs/17 §6; specs/05 §2 events; specs/04 §4 (`feed`).
4. **Cursor:**
   - Encrypted with the app key (`Crypt`): the position, the page number and a hash of the feed's filter signature. The internal base id is never readable (security review). A cursor from another feed or a tampered one is a `cursor` field error, and so is an anonymous cursor past `max_pages`.
   - The trending score is compared as `CAST(? AS REAL)`, so ties and floats page exactly on both databases.
   - Cached pages are keyed by page number and position.
   synced → specs/17 §4.
5. **Trending:** an inline command, `bases:recompute-trending` (`--all` nightly at 03:15), scheduled at `11-59/15` to stay off :00, rather than the `RecomputeTrendingJob` of specs/20 §2.
   - It is chunked (`bases.trending.batch_size`), and each chunk is one upsert.
   - "Active" means published in the last `active_days` (Q4).
   - The duplicate penalty only counts published copies when finding the earliest.
   synced → specs/20 §2, §3; specs/17 §5; specs/23 §3.
6. **Pages** (Q2):
   - `/` takes `th`, `sort` (trending, new, copied) and `cursor`, and ignores other parameters.
   - `th=all` turns off the signed-in default, which shows as a removable "TH 15-17, your Town Hall" chip.
   - `/bases` has the full filter form: a sticky 16 rem left panel from `lg`, and a bottom sheet (`UiModal`) below, with an active-filter count on the "Filters" button. The Town Hall chips sit above the grid on both pages.
   - "Load more" is an Inertia merge prop (`cards`, matched on `ulid`), reloaded with `preserveUrl`. It sends the page's filters with the cursor, `th` included, so the signed-in default feed pages on.
   - A sort change on the signed-in default leaves `th` out, so the default and its chip stay.
   synced → specs/18 §6, specs/17 §6, specs/19 URL table.
   - "Bases" joins the nav.
7. **Components:**
   - `GamePlayerMini` is the PlayerCard `mini` variant as its own component. A credit carries a name and Town Hall, not a full `PlayerCardData`.
   - `GameBaseCard` (default, the no-image fallback and the loading skeleton) and `UiResourceCounter` are on `/dev/components`.
   - Visual reasons (R-31): the fallback cover is the Town Hall tier gradient with the numeral, so a card without a screenshot still says what it is. The chip row sticks under the 56 px header, so the filter stays at hand while scrolling.
   - The processing and staff-hidden card states wait for P3-08 and P3-06.
   synced → specs/18 §4.
8. **Cards do not link to a base page yet:** it ships with P3-11, which now also links the card (added to its board row). The author and the credited account already link to their pages.
9. **Validation:** `th` is one level or `a-b` within `bases.th_min`–`th_max`. `tag` is normalised by `TagName` (`Ring Base` → `ring-base`), through a new `BaseFieldRules` (`Data`). `min_likes` runs from 1 to `bases.feed.min_likes_max`. Errors redirect to the unfiltered page. synced → specs/19 §1 (`BaseFieldRules`).

### Review fixes (verify, 2026-10-07)
- antislop audit-047: no findings.
- Spec + security (high/medium): `/` gave a 500 on a bad `category` or `tag`, or on array values, because `HomeFeedRequest::toData()` read fields it had not validated. It now reads `th` and `sort` only, and the `/bases` request uses `tryFrom`. Tested in Feature and Security.
- Spec + security (high): "Load more" on the signed-in default feed failed, because the cursor was signed for `th 15-17` but the reload sent no `th`. The grid now sends the filters with the cursor. Tested in PHP and Vitest.
- Spec (medium): Scope's `CacheInvalidator` was missing; it is added (Decision 3).
- Spec + security (medium/low): cached pages could keep a credit, a display name or a deleting author's bases. Cached feeds are now also dropped on account release or transfer, a privacy change and a deletion request (two new events). Tested.
- Security (low):
  - A private author's display name and avatar showed on cards; now username only (Decision 2).
  - The cursor showed internal ids; it is now encrypted (Decision 4).
  - The feed pages had no rate limit, and deep pages filled the cache. Both pages now have the `feed` limiter, and only the first 5 pages are cached.
- Spec (low): the unban path (`SanctionLifted` brings the bases back, counters intact) is now tested.
- Spec (low): the sort tabs kept the default Town Hall but lost its label; fixed (Decision 6).
- Spec (low), deferred: the specs/17 §4 `EXPLAIN` test ("no seq scan on tables > 10k rows") needs a seeded Postgres table of that size. It joins with the P3-05 search indexes and the load-test seed (NFR-PERF-1). The feed's indexes are those of specs/07.
- Spec (low): the empty state offers "Reset filters", which on `/` is "browse all TH levels", plus a "Browse all bases" link to `/bases`.
- Follow-up for P3-06 and P3-09: hiding or removing a base, or changing its visibility, must call `CacheInvalidator::baseFeeds()` (specs/21 §4 rule).

### Owner feedback (2026-10-07)
- `/` and `/bases` use `AppLayout`, so the header and bottom nav show Home and Bases.
- Town Hall chips: one swipeable row on phones with no vertical scroll; they wrap from `md`.
- `/bases` layout: the filter panel (`lg:sticky top-20`) and the chip row (`sticky top-14`) stay put, and only the cards scroll. The panel has no inner scroll.
- Desktop filters apply on change, with Reset at the top; text fields apply on blur or Enter. The phone sheet keeps "Apply filters".
- Sort and category use the themed `UiSelect` (`searchable`).
- Every filter, Town Hall or sort change scrolls back to the top.
- `BaseFeedSeeder` (local and testing only; runs once, in one transaction) seeds 6 authors and 60 bases, and gives `test_user` a TH 16 account. `DatabaseSeeder` calls it.
synced → specs/18 §6.
