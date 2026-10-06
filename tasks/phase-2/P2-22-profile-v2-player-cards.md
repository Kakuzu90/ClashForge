---
id: P2-22
title: Show PlayerCards, the featured hero card, the verified badge and war stars on profiles
phase: 2
status: done
depends_on: [P2-04, P1-04]
---

# Profile v2: PlayerCards on profiles

## Spec refs
- Core: specs/18 §6 "Player profile (`/u/{username}`)", §4 PlayerCard, VerifiedBadge, StatBlock
- Plus:
  - specs/04 §3 (`CocAccountPolicy::view`: who sees which account); specs/07 `privacy_settings` (`show_coc_accounts`, `show_clan`), `users.verified_accounts_count`, `coc_accounts.is_featured`
  - specs/13 §6 (featured: one per user, earliest-verified fallback); specs/17 §2 (unverified data is not trustworthy); specs/21 §3 (`profile:{username}`, `account:{ulid}:card`)
  - specs/11 "Data exposure via page props", "Account enumeration"; specs/05 §2 (PlayerAccounts `AccountReadModel`, Users `PublicProfileReadModel`)
  - specs/25 Phase 2 "Profile v2"
- FR: FR-PROFILE-5 (verified badge, featured account, connected verified accounts, stats); FR-PROFILE-4 (show accounts, show clan take effect on the profile); FR-COC-12 (featured is the default profile card); FR-COC-14 (cards render from stored data with their age)
- Edge cases: specs/23 §2 "clan private / hidden" ("Clan not shared"), "owner is banned" (the profile already 404s); specs/23 §5 null fields read "Not available"

## Scope
- **Domain** (PlayerAccounts): `AccountReadModel::forProfile(?User $viewer, int $ownerId): ProfileAccountsData` — `cards` (list of `PlayerCardData`), `featured` (`?PlayerCardData`), `warStars` (`?int`), `verified` (bool). One query for the rows, one batched `ClanReadModel::summaries()` for the clans (no N+1). Visibility mirrors `CocAccountPolicy::view`: the owner gets every row but `released`; anyone else gets `verified` / `disputed` rows only with `show_coc_accounts` on (the profile itself was already cleared by `PrivacyPolicyResolver`). `clanHidden` follows `show_clan` for others, as on the account page. Order: featured first, then newest (as `own()`).
- **Users**: `PublicProfileViewData` gains `ownerId` (server-side only, never a prop). The cached `profile:{username}` view-model is unchanged: account data is read per request (Q5).
- **HTTP**: `ProfileShowPageData` replaces `ownAccounts` with `accounts: ProfileAccountsData`; `OwnCocAccountData` stays for the attach flow.
- **UI** (`Pages/Profile/Show.vue`):
  - Cover band: `GameVerifiedBadge` beside the name when `accounts.verified` (Q1).
  - Featured `GamePlayerCard` `hero` under the cover band (Q2). `GamePlayerCard` gets a heading-level prop (the profile's `h1` is the user's name) and a link to `/accounts/{ulid}` on the hero when it is not the page header.
  - Stat blocks: bases, likes received, copies, + "War stars" across accounts (Q3); 2 × 2 on mobile, 4 across from `sm`.
  - Accounts tab: `standard` cards in a grid (1 col mobile, 2 from `md`). Own: every row, a "Verify" link under unverified cards (outside the card's link overlay), "Attach another account", the attach empty state when none. Others: the visible cards, or "No public accounts.".
  - `ProfileSkeleton` gains a hero-card and card-grid skeleton (`card: null`).
  - `/dev/components`: the profile-context `GamePlayerCard` (hero with link, non-`h1` heading).

## Out of scope
- Account images / gallery → P2-23. Setting the featured account stays on the account page (P2-14).
- The PlayerCard avatar slot and `mini` variant → P3-01 (Q4). Home feed featured card → P3-03.
- Activity / Bookmarks tabs, follow button (P5-01, P3-04); real base / like / copy counts (P3-04).

## Acceptance criteria
- Functional: FR-PROFILE-5 for accounts, badge, featured card and war stars; FR-COC-14 (stale cards dim with "Data from … ago"; nothing calls the API).
- Authorization: every card another viewer gets is one `CocAccountPolicy::view` allows (its link never 404s); unverified, suspended and released rows reach the owner only (released: nobody); `show_coc_accounts` off hides cards, hero, badge and war stars from others; staff get no bypass.
- Edge cases: `show_clan` off → "Clan not shared" for others; null war stars count as 0 in the sum; a featured row the viewer may not see shows no hero and no substitute.
- States: own empty (attach CTA), others empty (muted), loading skeletons, stale and disputed cards, 375 px and desktop.

## Tests
- Feature (`assertInertia`): owner / member / guest props; `show_coc_accounts` and `show_clan` matrix; featured hero; war stars sum (verified + disputed only); query count ≤ 15.
- Security: a consistency test that each others-view card passes `CocAccountPolicy::view`; props carry no `raw_payload`, `api_sync_failures`, user ids, claims or privacy flags; `OwnAccountsOnProfileTest` rewritten for cards.
- Unit: `forProfile` visibility and ordering.
- Vitest: `GamePlayerCard` heading level and hero link; `Profile/Show` badge / hero / war-stars rendering and empty states.

## Notes

### Open questions
Resolved by the owner, 2026-10-06 (all as recommended). synced → specs/07, specs/18 §4 §6, specs/21 §3 (see Decisions).
1. **Verified badge.** Shown when the viewer sees at least one `verified` / `disputed` account (computed from the rows already loaded), so it follows `show_coc_accounts`: an owner who hides their accounts shows no badge to others; the owner always sees theirs. Sync specs/07 (`verified_accounts_count` no longer the badge's source). Recommended.
2. **Featured placement.** The hero card shows the featured row; it also appears first in the Accounts tab with its star (the tab is the full list). Recommended.
3. **War stars stat.** Sum of `war_stars` over the viewer-visible `verified` / `disputed` rows (the owner's unverified and suspended rows excluded, specs/17 §2). The block is left out when there are none, so others with hidden accounts see the three platform stats. Recommended.
4. **PlayerCard avatar.** specs/18 §4 deferred "the avatar" to P2-22. Recommend no avatar on profile cards (the cover band already shows it) and moving the slot to `mini` with P3-01, where a card attributes a base to a person; sync specs/18 §4.
5. **Caching.** Account data stays out of `profile:{username}` and is read per request (two queries), since syncs change it often. Drop "account verify/detach" from the `profile:{username}` invalidators in specs/21 §3 (never wired, nothing cached to bust). Recommended.

### Decisions and divergences (implement, 2026-10-06)
1. **Read.** `AccountReadModel::forProfile(?User, int $ownerId)`: rows (with owner) + one batched clan read, each row also passed through `CocAccountPolicy::view`. The unused `own()` list is removed (`ownRow()` stays for the verify pages). synced → specs/05 §2.
2. **Badge, war stars, hero** (Q1–Q3) as resolved. `verified_accounts_count` no longer drives the profile badge. synced → specs/07 `users`, specs/18 §6.
3. **Components.** `GamePlayerCard` gains `heading` (`h1`/`h2`/`h3`) and `linked`; the profile hero is an `h2` that links to the account page, in a section labelled "Featured account". `GameVerifiedBadge` gains `label`; the cover band reads "Verified player: owns a Clash of Clans account proven with the in-game API token". No avatar slot (Q4). synced → specs/18 §4.
4. **Layout.** Stats are 3 across without war stars, 2 × 2 then 4 across with them; cards 1 column, 2 from `md`. synced → specs/18 §6. The "Verify" link sits under the card, outside its link overlay. R-31: the hero reuses the account page's hero card so the featured account reads as the same object in both places.
5. **Caching** (Q5): no account data in `profile:{username}`. synced → specs/21 §3.

### Review fixes (verify, 2026-10-06)
- Spec (low, test gap): the `forProfile` viewer × status matrix and ordering is `tests/Feature/PlayerAccounts/ProfileAccountsReadModelTest.php` (Feature, not Unit: the read needs the database; `tests/Unit` does not boot Laravel).
- Spec (low, test gap): staff get no bypass, covered for moderator and admin in the read-model test and `ProfileAccountsExposureTest`.
- Security: no findings.
- antislop audit-042: 1 finding (R-32, the card link had no focus ring), approved and fixed: the link takes the 2px focus outline.
- Owner request (2026-10-06): the About bio fills the content width (the `max-w-prose` cap is gone) and long words wrap (`break-words`).
- Owner request (2026-10-06): the featured account uses the account page's profile panel (`GameAccountProfile`, now with optional `stats`, `donations`, `linked` and `label`) without the troops donated / received strip; `PlayerCardData` gains `bestTrophies` for it. The `standard` list card shows the XP level (new `GameXpBadge`, shared with the panel) instead of the Town Hall, drops the stats and clan row, keeps one line per field and puts the "Verify" link in an `actions` slot, so all cards are the same height. The `heading` / `linked` props from Decision 3 are gone. synced → specs/18 §4, §6.
- Owner request (2026-10-06): `GameFeaturedBadge` (icon, like `GameVerifiedBadge`) replaces the "Featured" text badge on PlayerCards and the account panel; league names are `whitespace-nowrap` ("Golem League 19" broke before "19"). synced → specs/18 §4.
