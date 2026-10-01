---
id: P1-04
title: Add privacy settings, user_stats and the public profile at /u/{username}
phase: 1
status: done
depends_on: [P1-03]
---

# Add privacy settings and the public profile

## Spec refs
- Core: specs/07 `privacy_settings`, `user_stats`; specs/04 §2 ("Edit own profile / privacy" ○), §3 (write gate: settings writes open to restricted); specs/18 §6 "Player profile", "Settings"
- Plus: specs/17 §6 (SEO: SSR, title, meta, canonical, `Person` JSON-LD); specs/05 §2 (Users owns both tables; `PrivacyPolicyResolver`, `PublicProfileReadModel`); specs/08 (1:1, cascade; `user_stats` written by listeners, nightly recompute); specs/21 §3 (`profile:{username}` 5 min, `user:{id}:privacy` 1 h, `CacheInvalidator`), L3 request memo; specs/11 "Account enumeration" (`private` → 404), "Data exposure via page props", "Mass assignment"; specs/19 §4 (`/u/{username}`, `/settings/*`)
- FR: FR-PROFILE-4 (privacy settings), FR-PROFILE-5 (public profile), FR-PROFILE-6 (socials rendered with `rel="nofollow ugc noopener"`)
- Edge cases: specs/23 §1 "username released and re-registered" (only the lookup side: a missing username 404s; redirects are P1-09)

## Scope
- **Migrations / models / factories** (07, 08): `privacy_settings` (`user_id` PK/FK cascade, `profile_visibility` varchar + CHECK `public|members|private`, six bools with the defaults in Notes 4) and `user_stats` (`user_id` PK/FK cascade, the five counters `default 0`, `followers_count`/`following_count` [P2] left out, `recomputed_at` null). Both backfill one row per existing user. Models `Domain/Users/Models/PrivacySettings`, `UserStats`; factories; `UserFactory` creates both rows, like the profile.
- **Domain** (`Domain/Users`):
  - Enum `ProfileVisibility`.
  - `ProfileService::createFor()` also creates the privacy and stats rows (still idempotent for P1-08).
  - `PrivacySettingsService::update(User, UpdatePrivacyData)` → `CacheInvalidator::privacy($userId)` + `::profile($username)`.
  - `PrivacyPolicyResolver::canView(?User $viewer, owner)`: `public` → anyone; `members` → signed-in viewers; `private` → the owner only. Reads through `user:{id}:privacy` (1 h) and memoises per request (21 L3).
  - `PublicProfileReadModel` → `PublicProfileData` (username, display name, avatar 512/128 URLs, bio, country, languages, social links from `SocialLinks::links()`, member-since, stats, `isOwn`). The viewer-independent part is cached as `profile:{username}` (5 min); visibility is checked on every request before it is read.
- **Policy + Form Request**: `PrivacySettingsPolicy::update` (own only, account-write standing, 04 §3); `UpdatePrivacyRequest` validates only the five exposed fields (visibility, show accounts, show clan, recruitment contact, searchable).
- **HTTP + UI**:
  - `GET /u/{username}` (public, SSR, no auth). Username lookup goes through an Auth public service (Users may not read `users` models). A missing username, a hidden profile and a banned or pending-deletion owner all return the same 404; a suspended owner's profile stays visible.
  - Page `Profile/Show`: cover band (avatar, display name, `@username`, country, member-since), stat blocks (bases, likes received, copies), bio, socials. Accounts and Bases tabs show their empty states for now (own: attach / publish prompts; others: muted text). A skeleton covers loading.
  - SEO: `PageMeta` title `{display name} (@{username})`, description from the bio, canonical, `Person` JSON-LD; `noindex` unless the profile is `public` and `searchable`.
  - `GET /settings/privacy`, `PATCH /settings/privacy` (`auth`, `account.active`, `global-write`). Page `Settings/Privacy` with a visibility radio group and toggles; `settingsNav` gains "Privacy". The header avatar menu links to your own `/u/{username}`.
- **Jobs**: none. `user_stats` stays at 0 until bases ship; listeners and the nightly recompute arrive with P3-04.
- **Config keys**: none.

## Out of scope
- Verified badge, featured PlayerCard, connected accounts, `show_coc_accounts` / `show_clan` taking effect → P2-04
- Real stat values, published bases list → P3-01/P3-04; bookmarks tab → P3-04; activity, follows → P2 features
- Search filtering on `searchable`, sitemap → P3-05, P3-07
- Username history redirects → P1-09; the rest of the settings sub-nav → P1-05

## Acceptance criteria
- Functional: FR-PROFILE-4, and FR-PROFILE-5 for the fields that exist today. FR-PROFILE-6 for socials.
- Authorization: only the owner edits privacy; restricted accounts can, suspended and pending-deletion accounts cannot (04 §1, §3). Anyone views a `public` profile, signed-in users a `members` one, only the owner a `private` one. Staff get no bypass.
- Edge cases: an unknown username, a hidden profile and a banned or pending-deletion owner give the same 404 body and status (11).
- States: profile empty tabs, loading skeleton, 404; privacy form idle, saving, saved, field errors; 375 px and desktop.

## Tests
- Feature (`assertInertia`): `Profile/Show` props for a guest, a member and the owner; the privacy page and update; `settingsNav`; cache busted on profile and privacy updates.
- Security: the visibility × viewer matrix (guest, member, owner) with identical 404s; props carry no email, role, status, ids or private settings; mass assignment (`user_id`, `role`, `status`) on `PATCH /settings/privacy`; socials rendered as fixed-host links with `rel="nofollow ugc noopener"`; XSS in bio is escaped in SSR output; `noindex` on non-public profiles; status matrix for privacy writes.
- Unit: `PrivacyPolicyResolver`.
- Vitest: the profile tabs component, if it gets logic.

## Notes

### Open questions
Resolved by the owner, 2026-09-30 (all as recommended):
1. A `private` profile returns the same 404 as an unknown username; no "private" card. synced → specs/18 §6.
2. A `members` profile returns 404 to a guest; no sign-in prompt. synced → specs/18 §6, specs/11.
3. A banned or pending-deletion owner's profile returns 404; a suspended owner's stays visible. synced → specs/04 §1.
4. Defaults: `public`, show accounts / clan / activity on, recruitment contact on, marketplace contact off, searchable on. synced → specs/07 `privacy_settings`.
5. The Privacy page shows visibility, show accounts, show clan, recruitment contact and searchable. `show_activity` and `allow_marketplace_contact` are stored but hidden until P2 / Phase 6.
6. Staff get no bypass on `/u/`; they use the admin user detail (P1-06). synced → specs/04 §3.

### Decisions and divergences (implement, 2026-10-01)
1. Config keys `platform.profile.cache_ttl` (300), `privacy_cache_ttl` (3600), `meta_description_max` (160), per specs/19 §5. synced → specs/21 §3, specs/19 §5, specs/17 §6.
2. Auth public service `UserLookupService` (`findListed()`: unknown, unstorable, soft-deleted, banned and pending-deletion names all return null; `usernameOf()`). synced → specs/05 §2.
3. `CacheInvalidator` in `Users/Services` (public); profile key lowercased (citext usernames). synced → specs/05 §2, specs/21 §3.
4. Sync listener `ForgetProfileWhenAvatarReady` on `MediaReady`. synced → specs/05 §2 (events table, sync-listener rule).
5. `PrivacyPolicyResolver` is a `scoped` binding with a memo; a missing row reads as `private`. synced → specs/21 L3, §3.
6. One `Profile/NotFound` page (404, `noindex`) for every miss. synced → specs/11 "Account enumeration", specs/18 §6.
7. Header avatar → own profile; settings reached from it until P1-05. synced → specs/18 §6.
8. Title `@{username}` without a display name; description fallback `{name} on Clash Commons.`. synced → specs/17 §6.
9. Accounts / Bases empty states have no CTA yet. Carried on the board → P2-02, P3-01.
10. StatBlock count-up and `user_stats` invalidation deferred. Carried on the board → P3-04.
11. New primitives in `/dev/components`: `UiTabs`, `UiToggle`, `UiRadioGroup`, `UiStatBlock`; Privacy page shape. synced → specs/18 §6. R-31: the cover band sits on `bg-surface` with the raised depth border so the `surface-raised` avatar reads against it; the selected radio row takes the gold border, like a selected card.
12. SSR XSS covered by a Vitest `renderToString` test of `Profile/Show` plus the PHP root-view, meta and JSON-LD test (no SSR smoke harness yet).

### Review fixes (verify, 2026-10-01)
- Security (low): a cache refill already in flight could write an old privacy row or profile back right after a change. The privacy row is now written through inside the row-locked transaction, and readers only `add` on a miss. Profile entries carry `profile:{username}:version`, so a stale build is rebuilt. synced → specs/21 §3.
- Spec (plausible): unstorable names are refused before the query. `%FF` / `%00` are already rejected by the framework with a 400 for any path, so no 500 is possible.
- Spec (nit): `PublicProfileView` renamed `PublicProfileViewData` (specs/19 §3).
- antislop audit-011: no findings.
