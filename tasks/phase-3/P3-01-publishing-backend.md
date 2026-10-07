---
id: P3-01
title: Build the base publishing backend (schema, value objects, publish service, publish-on-media-ready)
phase: 3
status: done
depends_on: [P2-02, P0-05]
---

# Publishing backend

## Spec refs
- Core: specs/07 Bases (`base_layouts`, `base_metrics`, `base_tags`, `base_layout_tag`: columns, constraints, indexes); specs/05 §4 (the publish flow, steps 3–6), §5 (transactions, events after commit); specs/10 §3 "Attachment" (ownership, collection, state, quota inside the parent's transaction; "a base whose media is still processing is created in `processing` and published by the `MediaReady` listener"), §8 (2 screenshots ≤5 MB, 1 video)
- Plus: specs/04 §1 (`email_verified_at` and `has_verified_coc_account` gate publishing; restricted users lose content writes), §2 ("Publish / edit / delete base" ○), §4 (`base-publish` 5 / day, 20 / week); specs/19 §1 (Bases module layout: `BaseLink`, `LayoutHash`, `PublishBaseService`, `PublishWhenMediaReady`, `BaseLayoutPolicy`); specs/12 duplicate-layout rule (flag only); specs/11 (input validation)
- FR: FR-BASE-1, FR-BASE-2, FR-BASE-3, FR-BASE-4, FR-BASE-5, FR-BASE-11, FR-BASE-14
- Edge cases: specs/23 §3 (same user republishes a hash → blocked, delete-and-republish caught by the hash and the limit; two users, same hash → both published, the later flagged; screenshot never published → swept at 24 h; video done after the base was deleted → output discarded)

## Scope
- **Migrations, models, factories:**
  - `base_layouts`, `base_metrics`, `base_tags` (citext on Postgres) and `base_layout_tag`, exactly as specs/07, including the partial unique `(user_id, layout_hash) WHERE deleted_at IS NULL`, the CHECKs and the indexes.
  - The `search_vector` GIN index joins with P3-05.
- **Enums:** `BaseCategory` (FR-BASE-3), `BaseStatus`, `BaseVisibility`, `BaseModerationState`.
- **Value objects:** `BaseLink` (FR-BASE-2: https, host `link.clashofclans.com`, `action=OpenLayout`, normalised), `LayoutHash` (extracted from the link's `id`), `ThLevel` (`bases.th_min` to `bases.th_max`), `TagName` (FR-BASE-4: lowercase-kebab, ≤24 chars).
- **`PublishBaseService::handle(User, PublishBaseData)`**, all in one transaction:
  - the policy check, then the publish limit (Open question 4);
  - `BaseLink` → `LayoutHash`; the same user's live hash → `DuplicateLayoutException` (FR-BASE-11). Another user's hash → `moderation_state = flagged`, `flagged_reason = duplicate_layout`;
  - the credited account (Open question 1);
  - `MediaAttachmentService::attach` for `base_screenshot` (≤2) and `base_video` (≤1, accepted once P3-02 opens uploads);
  - status `published` with `published_at` when every item is `ready`, else `processing` (FR-BASE-5);
  - tags (Open question 3), the `base_metrics` row, and `BasePublished` after commit.
- **`PublishWhenMediaReady`** (`MediaReady` listener): under the base's row lock, flips `processing` → `published` once the last item is ready; ignores deleted or already published bases (23 §3).
- **Policy:** `BaseLayoutPolicy::create`: verified email, content writes allowed, a verified (or disputed, still held) CoC account.
- **Config:** `config/bases.php` with `th_min`, `th_max`, `tags_max` (10), `tag_max_length` (24), `title_max` (80), `description_max` (2000), `publish_per_day` (5), `publish_per_week` (20) and `suggested_tags`.

## Out of scope
- `POST /bases` and the composer page `/bases/create`, plus the profile's "publish your first base" CTA (split to P3-08).
- Editing and deleting a base (split to P3-09).
- The base detail page, feed and landing pages (P3-03); video processing and uploads (P3-02); counters and `user_stats.bases_published` (P3-04); search indexing (P3-05).
- Turning a duplicate flag into a review case (P3-06; Open question 2).

## Acceptance criteria
- Functional: FR-BASE-1 to FR-BASE-5, FR-BASE-11, FR-BASE-14 through the service.
- Authorization: an unverified email, a restricted, suspended or banned user, or a user with no verified CoC account is refused. Media and accounts must be the publisher's own.
- Edge cases: the 23 §3 rows above. A base with no media publishes at once. Media that failed refuses the publish.
- States: no UI in this task.

## Tests
- Unit: `BaseLink` (good links, wrong host, http, missing `action`/`id`, extra parameters), `LayoutHash`, `TagName` normalisation, `ThLevel` bounds.
- Feature (service level): publish with and without media; `processing` → `published` on the last `MediaReady`; own duplicate refused; cross-user duplicate flagged; publish limit per day and week; tag creation, reuse and blocked tags; the quota of 2 screenshots; foreign media and foreign accounts refused; the policy matrix; a deleted base ignored by the listener; config keys read from config.

## Notes

### Open questions
Resolved by the owner, 2026-10-07 (all as recommended); specs synced at implement → Finish.
1. **The credited CoC account.** FR-BASE-1 does not list it, while `coc_account_id` is "the credited account" and publishing needs a verified account (04 §1). Recommended: required, one of the publisher's own `verified` or `disputed` accounts, the featured one by default. Crediting a proven account is what sets these bases apart (01 §1). Hiding the credit when the account later changes hands (23 §3) is a read-time rule for P3-03.
2. **Duplicate review.** `report_cases` arrives with Moderation v1. Recommended: this task only sets `moderation_state = flagged` and `flagged_reason = duplicate_layout`, and the base stays published (12, "flag only"). P3-06 turns flagged bases into Low cases; I would add that to P3-06's board row.
3. **Tags.** Recommended:
   - The suggested list is a short curated list in `bases.suggested_tags`, seeded into `base_tags` with `is_suggested = true` (ring-base, island, box, anti-air, anti-ground, anti-dragon, anti-root-rider, anti-hybrid, esports, max-th).
   - Any other valid tag is created on publish as a long-tail tag (`is_suggested = false`).
   - A blocked tag refuses the publish with a field error.
   - `usage_count` moves on publish (and on delete, P3-09).
   - Blocking tags is a staff tool for later (P3-06).
4. **How the publish limit counts.** Recommended: count `base_layouts` rows the user created in the rolling day and week, deleted ones included, under the user's lock, as `coc-dispute-open` does. A refused or invalid publish then never uses one up, and deleting and republishing still counts (23 §3).

### Decisions and divergences (implement, 2026-10-07)
1. **Link and hash:** `BaseLink` stores one canonical form: https, the game's host, the language path (`en` when missing), and only `action` and `id`, so tracking parameters never reach a page. `LayoutHash` is the sha256 of the layout id (fits `varchar(64)`), so two links to one layout match. synced → specs/07 `base_layouts`.
2. **Value objects** stay in `Bases/Support` as specs/19 §1 says. The composer's Form Request gets a `BaseFieldRules` facade in `Data` (P3-08), like Users' `ProfileFieldRules`. synced → specs/19 §1.
3. **Credited account** (Open question 1): PlayerAccounts' public `AccountCredits::creditableId()` picks one of the author's own `verified` or `disputed` rows. With no ULID it picks the featured one, else the newest; anything else is the `account` field error. synced → specs/05 §2, §4; specs/07.
4. **Policy:** `BaseLayoutPolicy::create` requires a verified email, content writes allowed, and `users.verified_accounts_count > 0`. The service runs it again under the author's row lock. synced → specs/05 §4.
5. **Limit** (Open question 4): counted from the author's bases, deleted ones included, under their row lock. A breach is the `base` field error. synced → specs/04 §4.
6. **Tags** (Open question 3): the suggested flag comes from `bases.suggested_tags` when a tag is first created, so there is no separate seeding step. The composer (P3-08) offers the config list. Concurrent creation of one tag uses `insertOrIgnore`. synced → specs/07 `base_tags`.
7. **`BasePublished`** fires when a base becomes visible: at once, or when `PublishWhenMediaReady` flips it. It does not fire at creation in `processing`. The listener checks the uploader's `processing` bases, since `MediaReady` names the uploader, not the parent. synced → specs/05 §4.
8. **Refusals** are field errors: `title`, `description`, `th_level`, `base_link`, `tags`, `screenshots`, `video`, `account` and `base` (the limit). A foreign or unknown upload is the field error "This upload was not found", not a bare 404, as for dispute evidence (P2-16).
9. **Schema:**
   - `search_vector` and its GIN index wait for P3-05.
   - DESC indexes and the CHECKs are Postgres only, as elsewhere.
   - `base_tags.name` is citext on Postgres.
   - Media limits are `bases.screenshots_max` and `videos_max`.
   synced → specs/07, specs/10 §8.
10. **Follow-up:** an attached item that fails processing leaves its base in `processing`. Added to P3-09: replace or remove it. P3-08 shows the state.

### Review fixes (verify, 2026-10-07)
- antislop audit-045: no findings.
- Spec (medium): the migration missed the specs/07 index `base_tags (usage_count DESC) WHERE NOT is_blocked`. Added. `(user_id, published_at)` is now DESC on Postgres, and `trending_score` is `real`.
- Spec (medium): specs/08 §3.2 and 13 §6 null a base's credit when its account changes hands, but nothing did. New `DropLostCredits` (Bases, on `CocAccountReleased` and `CocAccountOwnershipTransferred`) nulls every credit the author no longer holds, checked through `AccountCredits::heldIds()`. The base keeps its author, and `updated_at` stays. Tested for a detach and a token supersede. synced → specs/08 §3.2, specs/05 §1.
- Spec (medium): specs/19 §1 still listed `DuplicateLayoutException`, `Visibility` and only two value objects. Updated: there is no exception, since refusals are field errors (Decision 8). synced → specs/19 §1.
- Spec (low): video and screenshot positions now count within their own collection. Tags are found by `name`, the unique key the insert respects. specs/05 §1 now has the Bases row (it no longer lists `taggables`), and the specs/07 paragraph break is fixed.
- Spec (low) + security (low): a waiting base went live even if its author was sanctioned meanwhile. `publishIfReady()` now locks the author, then the base, and runs the policy again: a suspended, banned, restricted or leaving author's base stays `processing`. Tested. synced → specs/05 §4.
  - Follow-up for P3-09: a restricted author's base stays `processing` after the restriction ends, until P3-09's "replace or remove" path.
- Security (low): two publishes creating the same new tags in opposite order could deadlock. Names are now sorted before the insert.
- Security (low): two authors publishing one layout at the same instant could both stay unflagged. A Postgres advisory lock on the layout hash now serialises them; SQLite has one writer anyway.
- Security (low): added `tests/Security/Bases/PublishBaseSecurityTest.php`. It covers:
  - host confusion, odd but valid links rebuilt canonically, and bad ids;
  - foreign and already-attached uploads, and foreign accounts;
  - mass assignment;
  - sanctions while a base waits;
  - the flag never appearing in the result.
- Spec (low): tests also added for the DB partial unique index (in a savepoint on Postgres), the listener ignoring other collections, and a failed item keeping its base in `processing`.
- `bases.th_max` is 18 in `config/bases.php` (edited on disk during implement); the config test follows it.
