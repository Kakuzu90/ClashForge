# 07 — Database Schema

PostgreSQL 16. Conventions used throughout:

- **Primary keys:** `bigserial id` for internal joins. Anything exposed in a URL also has a
  `ulid` (26-char, sortable, non-enumerable) or a natural key (`username`, `tag`).
- **Timestamps:** `created_at`, `updated_at` (`timestamptz`) on every table. `deleted_at` only where
  soft deletes are genuinely used.
- **Enums:** stored as `varchar` + a `CHECK` constraint, mapped to PHP backed enums. Not native
  Postgres enums — adding a value to a native enum is a migration-time lock we do not want.
- **Money:** `integer` minor units + `char(3)` currency. Phase 6 only.
- **JSON:** `jsonb`, always with a `DEFAULT '{}'::jsonb NOT NULL` where the column is required.
- **Counters:** denormalised integer columns updated in transactions or by aggregation jobs, never
  computed with `COUNT(*)` in list views.
- **Deletes:** `ON DELETE CASCADE` only for owned child rows (media variants, taggables, likes).
  Moderation, audit and financial records use `ON DELETE RESTRICT` or nullable FKs — history must
  survive user deletion.

Phase markers: **[M]** MVP, **[P2]** phase 4–5, **[P3]** phase 6–7.

---

## Auth & identity

### `users` [M]
Authentication identity and platform-level status. Deliberately thin — profile data lives in
`profiles` so that hot auth queries stay narrow.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| ulid | char(26) | non-enumerable external id |
| username | citext | public handle: 3 to 20 of `[a-z0-9_]`, stored lowercase, unique ignoring case; anonymised rows instead use the internal `deleted_user_{ulid}` name (39 chars, lowercase). The original name stays permanently reserved via `username_history` |
| email | citext | |
| email_verified_at | timestamptz null | required for writes |
| pending_email | citext null | an email change waiting for its link (FR-AUTH-8); a new request replaces it, confirming or cancelling clears it. Not unique: an address may be pending on several accounts, the first to confirm gets it |
| pending_email_requested_at | timestamptz null | when the pending change was asked for |
| password | varchar(255) null | bcrypt/argon2id; required for a live account, null only on an anonymised tombstone |
| remember_token | varchar(100) null | remember-me recaller; cycled on logout, password reset, password change and session revocation |
| role | varchar(20) | `user`\|`moderator`\|`admin`\|`super_admin`, default `user` |
| status | varchar(20) | `active`\|`restricted`\|`suspended`\|`banned`\|`pending_deletion` |
| status_reason | varchar(255) null | user-visible sanction reason |
| status_expires_at | timestamptz null | timed restriction/suspension |
| verified_accounts_count | int default 0 | denormalised; counts the user's `verified` and `disputed` rows. Written only by Auth's `UserStatusService::syncVerifiedAccounts()` (P2-02). Not the profile's verified badge, which follows the cards the viewer may see (P2-22). The featured account is `coc_accounts.is_featured`, not a column here |
| last_login_at / last_login_ip_hash | timestamptz / varchar(64) null | IP stored as HMAC-SHA256 keyed by `IP_HASH_SALT` (`App\Support\Privacy\IpHash`) |
| username_changed_at | timestamptz null | enforces the 30-day rule |
| verification_notice_key | char(26) null | P1-16: guarded/hidden dispatch ULID; changes per queued notice so stale jobs cannot clear or send a newer dispatch; cleared on anonymisation |
| verification_reminder_queued_at / verification_reminder_sent_at | timestamptz null | P1-16: durable day-3 notice dispatch and completed-send receipt; guarded, hidden, cleared on anonymisation |
| verification_warning_queued_at / verification_warning_sent_at | timestamptz null | P1-16: durable day-27 warning dispatch and completed-send receipt; purge requires successful send and at least three days after both enqueue and send; guarded, hidden, cleared on anonymisation |
| two_factor_secret / two_factor_recovery_codes / two_factor_confirmed_at | text null (encrypted) / text null (encrypted) / timestamptz null | [P2] |
| deletion_requested_at | timestamptz null | starts the 30-day window |
| deletion_previous_status | varchar(20) null | `active` or `restricted` before the request; cancellation restores it with the existing sanction reason/end, respecting expiry |
| created_at / updated_at / deleted_at | timestamptz | |

During the grace period `status = pending_deletion` hides the account; `deleted_at` stays null
so password sign-in can find and restore it. Anonymisation sets `deleted_at` and the banned
tombstone together. Postgres CHECKs allow only public username shapes or the exact internal
tombstone name, and allow a null password only on a deleted, banned row.

**Unique:** `username`, `email`, `ulid`.
**Indexes:** `(status)` partial `WHERE status <> 'active'`; `(role)` partial `WHERE role <> 'user'`;
`(deletion_requested_at)` partial not-null; `(created_at)`.
P1-16 adds `(created_at, id)` partial `WHERE email_verified_at IS NULL AND deleted_at IS NULL
AND role = 'user' AND status <> 'pending_deletion'` for lifecycle scans.

### `username_history` [M]
Holds released usernames so old profile URLs redirect and handles cannot be sniped instantly.
Created with deletion reservations in P1-11; username changes use it since P1-09. A deletion
reservation lasts forever, holds the name against everyone and its profile URL returns 404. A
changed-away name is held for `platform.auth.username_reservation_days` (90) against every account but
the one that released it, and its URL redirects to that account's current name meanwhile (latest
release wins).

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| user_id | bigint FK → users | cascade |
| username | citext | |
| released_at | timestamptz | reserved 90 days from here |
| reserved_forever | bool default false | true for the original username on account anonymisation; never expires |

**Unique:** `(username, released_at)`. **Index:** `(username)`.

### `sessions` [M]
Laravel's database session table, plus columns for the session-management UI.

`id (varchar PK)`, `user_id (bigint null, index)`, `ip_hash (varchar(64) null)`, `user_agent`,
`payload (text)`, `last_activity (int, index)`, `device_label (varchar(100) null)`,
`country_code (char(2) null, from Cloudflare's CF-IPCountry via a trusted proxy; null locally)`,
`created_at (timestamptz null)`. No raw IP is stored. The absolute-lifetime clock lives in the
session payload (`auth.signed_in_at`).

### `password_reset_tokens` [M]
Laravel default: `email (PK)`, `token`, `created_at`.

### `feature_flags` [P2]
`key (varchar PK)`, `enabled (bool)`, `rollout_percentage (smallint)`, `description`, `updated_by`.

---

## Users & profiles

### `profiles` [M]
Public presentation of a user. 1:1 with `users`; split so profile writes never touch the auth row.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| user_id | bigint FK → users | cascade, **unique** |
| display_name | varchar(50) null | falls back to username |
| bio | varchar(500) null | plain text, stored unescaped, escaped on render |
| avatar_media_id | bigint null FK → media | `ON DELETE SET NULL` |
| country_code | char(2) null | ISO-3166-1 alpha-2: the ICU region list minus regions that are not countries (EU, UN, XK, ZZ, …) |
| languages | varchar(5)[] | ISO-639-1, max 3, `CHECK (array_length <= 3)`; JSON on SQLite (tests), both through `AsStringList` |
| timezone | varchar(64) null | |
| socials | jsonb default '{}' | `{youtube, twitch, discord, x}` as handles, never URLs (YouTube `@handle`, Twitch login, Discord username, X handle), each validated on write; links are built on render from fixed `https://` hosts, Discord shows as text |
| search_vector | tsvector | username (A, `simple`) + display_name (A, `simple`) + bio (C, `english`), kept by triggers on `profiles` and on `users.username`, not a generated column, since `username` is on `users` (P3-05). Postgres only |
| created_at / updated_at | timestamptz | |

**Indexes:** GIN on `search_vector`; `(country_code)`; GIN on `languages`.

### `privacy_settings` [M]
Separated from `profiles` because it is read on nearly every authorization decision and written
rarely — and because it should be trivially cacheable as one small row.

`user_id (PK, FK → users)`, `profile_visibility (public|members|private)`,
`show_coc_accounts (bool)`, `show_clan (bool)`, `show_activity (bool)`,
`allow_recruitment_contact (bool)`, `allow_marketplace_contact (bool)`, `searchable (bool)`.
Defaults: `public`; `show_coc_accounts`, `show_clan`, `show_activity`, `allow_recruitment_contact`
and `searchable` true; `allow_marketplace_contact` false.

### `user_stats` [M]
Denormalised counters so profile pages are a single row read.

`user_id (PK, FK)`, `bases_published`, `total_base_likes`, `total_base_copies`,
`total_base_views`, `comments_posted`, `followers_count` [P2], `following_count` [P2],
`recomputed_at`.

### `follows` [P2]
`id`, `follower_id (FK users)`, `followee_id (FK users)`, `created_at`.
**Unique:** `(follower_id, followee_id)`. **Check:** `follower_id <> followee_id`.
**Indexes:** `(followee_id, created_at)`, `(follower_id, created_at)`.

---

## Clash of Clans accounts

### `coc_accounts` [M]
A CoC player tag attached to a website user. The central trust object of the platform.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| ulid | char(26) | external id |
| user_id | bigint null FK → users | null when `released`; `ON DELETE SET NULL` so history survives |
| tag | varchar(15) | normalised, with leading `#` |
| tag_normalized | varchar(14) | uppercase, no `#` — the real uniqueness key |
| status | varchar(20) | `unverified`\|`verified`\|`disputed`\|`suspended`\|`released` |
| verified_at | timestamptz null | |
| verification_method | varchar(20) null | `api_token`\|`admin` |
| ign | varchar(30) | current in-game name |
| th_level | smallint null | the game stats below are nullable: a field the API stops sending reads as null ([23 §5](23-edge-cases.md)) |
| builder_hall_level | smallint null | |
| xp_level | smallint | |
| trophies / best_trophies / builder_trophies | int | |
| war_stars | int | |
| attack_wins / defense_wins | int | |
| donations / donations_received | int | |
| clan_tag | varchar(15) null | denormalised for filtering |
| clan_id | bigint null FK → clans | `ON DELETE SET NULL`; set in the same write as `clan_tag` (attach, sync), null outside a clan (P2-13) |
| clan_role | varchar(20) null | `member`\|`admin`\|`coLeader`\|`leader` |
| league_id / league_name / league_icon_url | int null / varchar(50) null / text null | `league_id` resolves to our self-hosted emblem in the `game/` pack; the API URL is kept as a fallback. Never copied into `media` ([18 §2.3](18-design-system.md)) |
| troops / heroes / spells / hero_equipment | jsonb default '[]' | raw-ish API shape, normalised keys |
| achievements | jsonb default '[]' | subset we display |
| labels | jsonb default '[]' | player labels from the API |
| raw_payload | jsonb null | last full API response, for debugging and new-field backfill |
| api_synced_at | timestamptz null | freshness for the "last updated" label |
| api_sync_failures | smallint default 0 | consecutive 404s from the sync; at `coc.sync.not_found_stale` (3) the account shows as `stale` and its owner is told once. Other API failures back off through `sync_states` and leave it alone (P2-09) |
| last_viewed_at | timestamptz null | last signed-in view of the account page, written at most hourly; within `coc.sync.hot_viewed_hours` (24) the account syncs on the hot tier ([09 §6](09-coc-api-integration.md), P2-20) |
| is_featured | bool default false | one per user, enforced by partial unique |
| images_count | smallint default 0 | quota guard (max 5) |
| created_at / updated_at | timestamptz | no `deleted_at`: ownership history is never deleted ([13 §1](13-claiming-workflow.md)) |

**Constraints:**
- `UNIQUE (tag_normalized) WHERE status IN ('verified','disputed')` — partial unique index: *only one
  holder globally* (a disputed row still holds the tag, [13 §2](13-claiming-workflow.md)), while
  multiple unverified claims may coexist.
- `UNIQUE (user_id, tag_normalized)` — a user cannot attach the same tag twice.
- `UNIQUE (user_id) WHERE is_featured` — one featured account per user.
- `CHECK (th_level BETWEEN 1 AND 30)`, `CHECK (images_count <= 5)`.

**Indexes:** `(user_id, status)`; `(tag_normalized)`; `(clan_id)`; `(th_level, trophies DESC)`;
`(api_synced_at)` for the sync scheduler; GIN on `to_tsvector(ign)`; GIN on `heroes`/`troops` only
if equipment search ships.

### `coc_account_claims` [M]
Every attempt to attach a tag, successful or not. The forensic record behind disputes.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| coc_account_id | bigint null FK → coc_accounts | null if the attempt never created an account row |
| tag_normalized | varchar(14) | always present |
| user_id | bigint FK → users | claimant; `ON DELETE RESTRICT` (forensic history) |
| method | varchar(20) | `api_token`\|`dispute`\|`admin` |
| status | varchar(20) | `pending`\|`succeeded`\|`failed`\|`rejected`\|`superseded` |
| failure_reason | varchar(100) null | `invalid_token`\|`already_claimed`\|`api_error`\|`rate_limited`\|`not_found` |
| ip_hash / user_agent | varchar(64) / varchar(255) | abuse detection |
| created_at | timestamptz | |

**Indexes:** `(tag_normalized, created_at DESC)`; `(user_id, created_at DESC)`;
`(status)` partial `WHERE status = 'pending'`.

**Lifecycle:** an attach writes `pending`; each verification attempt writes its own `succeeded` or
`failed` row; on success the verifier's `pending` rows for that account become `succeeded`, and a
superseded holder's own `pending` rows become `superseded`. A refused attach writes a `failed` row
with no account; past a limit only the first refusal of the window is written. Every column is set
by the service (nothing fillable).

### `coc_account_disputes` [M]
A contested tag, routed to admins.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK / ulid | |
| coc_account_id | bigint FK → coc_accounts | the currently-held account |
| tag_normalized | varchar(14) | |
| claimant_id | bigint FK → users | the challenger |
| current_holder_id | bigint null FK → users | |
| reason | text | claimant's statement (≤1000) |
| evidence | jsonb default '[]' | append-only, one entry per submission: `{party, note, media: [ulid], at}`; the opening statement is `reason` (P2-03) |
| status | varchar(20) | `open`\|`awaiting_admin`\|`awaiting_claimant`\|`awaiting_holder`\|`resolved_transfer`\|`resolved_denied`\|`resolved_suspended`\|`withdrawn`\|`auto_resolved` (`awaiting_admin`, `resolved_suspended`: owner decision 2026-10-02, P2-03) |
| assigned_admin_id | bigint null FK → users | |
| decision_note | text null | internal |
| decided_by / decided_at | bigint null FK → users / timestamptz null | `decided_at` is set on every close; `decided_by` only for an admin decision |
| closed_by | varchar(20) null | `claimant`\|`holder`\|`admin`\|`token`\|`sweep`; a `sweep` withdrawal counts toward the claimant's bar like a denial |
| awaiting_since | timestamptz | when the current wait began (the holder's 7 days, or the 30 days a party asked for more has) |
| escalated_at | timestamptz null | set when an unanswered dispute goes to the admins |
| holder_reminders_sent | smallint default 0 | reminders sent in the current holder wait (day 3, day 6, specs/16 §2); back to 0 when a new wait starts (P2-18) |
| created_at / updated_at | timestamptz | |

**Unique:** `(tag_normalized, claimant_id) WHERE status IN ('open','awaiting_admin','awaiting_claimant','awaiting_holder')`
— one open dispute per challenger per tag; and `(coc_account_id)` with the same condition — a
disputed account takes no new disputes ([13 §2](13-claiming-workflow.md)). Every user FK restricts.
**Indexes:** `(status, created_at)`; `(assigned_admin_id)`; `(coc_account_id)`; `(claimant_id, status)`.

### `coc_account_snapshots` [M]
Point-in-time progression, and the fallback when the API is down.
**Written only when a tracked value changed** — otherwise this table grows without informing anything.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| coc_account_id | bigint FK → coc_accounts | cascade |
| captured_at | timestamptz | |
| th_level, builder_hall_level, xp_level, trophies, best_trophies, war_stars, attack_wins, defense_wins, donations | int/smallint null | `builder_hall_level` added in P2-09 (a Builder Hall change is progression) |
| clan_tag | varchar(15) null | |
| league_id | int null | |
| heroes / troops / spells / hero_equipment | jsonb | full progression copy |
| source | varchar(20) | `scheduled`\|`manual`\|`verification` |

**Unique:** `(coc_account_id, captured_at)`; `captured_at` is whole seconds, and a second write in
the same second keeps the first. A row is written when a progression value changed (TH / BH
level, XP level, best trophies, war stars, any unit's level, clan tag, league id), never for
trophies, attack / defense wins or donations alone (owner decision, 2026-10-02), and once at
verification (`source = verification`).
**Indexes:** `(coc_account_id, captured_at DESC)`. **Retention:** keep all for 90 days, then one row
per account per day, then one per week after a year (`coc:compact-snapshots`, nightly, P2-21; owner
decisions 2026-10-06): the **last** row of each UTC day or ISO week (Monday to Sunday, UTC) stays,
the state the account ended that period in. `source = verification` rows always stay, and so does
each account's newest row whatever its age. A day or week cut by the 90-day or one-year line is
split there, so every row falls in exactly one bucket. Windows are `coc.snapshots.*`. Partition by
month once past ~5M rows.

### `clans` [P2, read-only stub in M]
Clans referenced by accounts or recruitment posts. Written from API data only. Until the clan sync
(P4-01) a row is a stub made from a member's player payload: tag, name, badge and level, through
Clans' `ClanDirectory::ensure()` in the same write that sets `coc_accounts.clan_tag` (P2-13). The
columns only `/clans/{tag}` returns are null until then. Once `api_synced_at` is set, members'
payloads no longer change the row.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| tag / tag_normalized | varchar(15) / varchar(14) | **unique** on `tag_normalized`; a disbanded and recreated clan has a new tag and is a new row ([23 §6](23-edge-cases.md)) |
| name | varchar(30) | |
| description | text null | |
| badge_urls | jsonb default '{}' | small/medium/large from the API, stored verbatim (`[]` when the API sends none). Referenced, not mirrored (one per clan, mutable); rendered unmodified through the resolver's allowlist, never ingested into the media pipeline ([18 §2.3](18-design-system.md)) |
| level | smallint null | from the player payload; a payload without it keeps the known level |
| points / builder_points / capital_points | int null | null until the clan sync |
| war_frequency / war_win_streak / war_wins / war_losses / war_ties | varchar(20) null / int null | null until the clan sync |
| is_war_log_public | bool null | null until the clan sync |
| war_league_id / war_league_name | int null / varchar(50) null | |
| capital_hall_level | smallint null | |
| members_count | smallint null | drives auto-pause of recruitment posts; null until the clan sync |
| required_th_level / required_trophies | smallint null / int null | null until the clan sync |
| type | varchar(20) null | `open`\|`inviteOnly`\|`closed` (CHECK, Postgres); null until the clan sync |
| location_id / location_name / country_code | int null / varchar(50) null / char(2) null | |
| languages | varchar(5)[] null | platform-supplied, not from the API; added with recruitment (P4-02), its only writer |
| api_synced_at / api_sync_failures | timestamptz null / smallint default 0 | last `/clans/{tag}` fetch; null on a stub |
| tracked_reason | varchar(20) null | `member`\|`recruitment`\|`manual` (CHECK, Postgres) — why we sync it; set by the clan sync (P4-01), null on a stub |
| created_at / updated_at | timestamptz | |

**Indexes:** `(tag_normalized)` unique; `(members_count)`; `(war_league_id)`; `(country_code)`;
`(api_synced_at)`; GIN on `to_tsvector('simple', name)` (Postgres).

### `clan_memberships` [P2]
Historical record of which verified account was in which clan — needed to authorise clan
recruitment posts and to show history.

`id`, `clan_id (FK)`, `coc_account_id (FK)`, `role`, `joined_detected_at`, `left_detected_at null`,
`is_current (bool)`.
**Unique:** `(clan_id, coc_account_id) WHERE is_current`.
**Indexes:** `(coc_account_id, is_current)`, `(clan_id, is_current)`.

---

## Bases

### `base_layouts` [M]

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| ulid | char(26) | used in URLs with a slug |
| slug | varchar(90) | `{ulid}-{title-slug}`, **unique** |
| user_id | bigint FK → users | `ON DELETE CASCADE` (author deletion removes bases) |
| coc_account_id | bigint null FK → coc_accounts | credited account, required at publish: one of the author's `verified` or `disputed` rows, the featured one by default (P3-01); `ON DELETE SET NULL` |
| title | varchar(80) | |
| description | text null | ≤2000 |
| th_level | smallint | |
| category | varchar(20) | `war`\|`cwl`\|`farming`\|`trophy`\|`legend`\|`anti_3_star`\|`anti_2_star`\|`hybrid`\|`progress`\|`troll` |
| base_link | text | validated `link.clashofclans.com` URL, stored canonical: https, the game's host, the language path (`en` when missing), only `action` and `id` (P3-01) |
| layout_hash | varchar(64) | sha256 (hex) of the link's layout `id`, so two links to one layout match; duplicate detection |
| visibility | varchar(10) | `public`\|`unlisted`\|`private` |
| status | varchar(20) | `draft`\|`processing`\|`published`\|`hidden`\|`removed` |
| has_video | bool default false | filter support |
| published_at | timestamptz null | |
| moderation_state | varchar(20) | `clean`\|`flagged`\|`under_review`\|`actioned` |
| flagged_reason | varchar(50) null | e.g. `duplicate_layout` |
| created_at / updated_at / deleted_at | timestamptz | |

**Constraints:** `UNIQUE (user_id, layout_hash) WHERE deleted_at IS NULL` — a user cannot republish
their own identical layout. Cross-user duplicates are *flagged*, not blocked (many people
legitimately share the same popular base).
**Indexes:**
- `(status, visibility, published_at DESC)` — the feed's primary path.
- `(th_level, category, published_at DESC) WHERE status='published' AND visibility='public'`.
- `(user_id, published_at DESC)`.
- `(layout_hash)` — duplicate detection.
- `(coc_account_id)`.
- GIN on `search_vector`: title (A, `english`) + tag names (B, `simple`) + description (C, `english`), kept by triggers on `base_layouts`, `base_layout_tag` and a renamed `base_tags.name` (P3-05). Postgres only.
- CoC accounts are searched through the existing `to_tsvector('simple', ign)` GIN index; no column (P3-05).

Postgres adds the `category`, `visibility`, `status` and `moderation_state` CHECKs and
`th_level BETWEEN 1 AND 30`; the range users may pick is `bases.th_min`–`th_max` (P3-01).

### `base_metrics` [M]
Counters split from `base_layouts` so that high-frequency counter updates do not bloat/lock the row
that every feed query reads.

`base_layout_id (PK, FK, cascade)`, `likes_count`, `comments_count`, `bookmarks_count`,
`views_count`, `copies_count`, `reports_count`, `trending_score (real)`, `score_updated_at`.

**Indexes:** `(trending_score DESC)`, `(likes_count DESC)`, `(copies_count DESC)`.

### `base_tags` [M]
`id`, `name (citext, unique)`, `slug (unique)`, `usage_count`, `is_suggested (bool)`,
`is_blocked (bool)`, `created_by null`. A tag is created on first use, lowercase-kebab ≤24
(`TagName`, FR-BASE-4), suggested when it is on `bases.suggested_tags`; a blocked tag refuses the
publish; `usage_count` counts each base using it (P3-01).
**Index:** `(usage_count DESC) WHERE NOT is_blocked`.

### `base_layout_tag` [M]
Pivot. `base_layout_id`, `base_tag_id`, PK on both, cascade both ways. Index on `(base_tag_id, base_layout_id)`.

> Kept as a simple pivot rather than a polymorphic `taggables` table: only bases are tagged in the
> MVP, and a polymorphic pivot costs index efficiency for a generality we don't need yet.

### `base_likes` [M]
`id`, `base_layout_id (FK, cascade)`, `user_id (FK, cascade)`, `created_at`.
**Unique:** `(base_layout_id, user_id)`. **Index:** `(user_id, created_at DESC)`.

### `base_bookmarks` [M]
Same shape as likes; separate table because the access pattern is different (user-first listing)
and bookmarks are private.
**Unique:** `(user_id, base_layout_id)`. **Index:** `(user_id, created_at DESC)`.

### `base_comments` [M]

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK / ulid | |
| base_layout_id | bigint FK | cascade |
| user_id | bigint FK → users | cascade |
| parent_id | bigint null FK → base_comments | one level only, enforced in the service |
| body | varchar(1000) | |
| status | varchar(20) | `visible`\|`hidden`\|`removed`\|`deleted_by_author` |
| edited_at | timestamptz null | 15-minute window |
| replies_count | smallint default 0 | |
| created_at / updated_at / deleted_at | timestamptz | |

**Indexes:** `(base_layout_id, parent_id, created_at)`; `(user_id, created_at DESC)`;
partial `(status) WHERE status <> 'visible'`.

### `base_view_events` [M]
Raw, append-only, deduped view pings. Aggregated into `base_metrics.views_count` hourly, then pruned
after 30 days. Partition by day once volume justifies it.

`id (bigserial)`, `base_layout_id`, `user_id null`, `visitor_hash (varchar 64)`,
`viewed_at (timestamptz)`, `source (varchar 20)`.
**Unique:** `(base_layout_id, visitor_hash, viewed_date)` via a generated `viewed_date` column —
this *is* the 24h dedupe.
**Index:** `(base_layout_id, viewed_at)`, `(viewed_at)` for pruning.

### `base_copy_events` [M]
Same pattern for copy-link clicks, 1-hour dedupe window.

---

## Recruitment [P2]

### `recruitment_posts`
One table for both directions, discriminated by `type` — the fields overlap heavily (TH, trophies,
language, location, activity) and a single table makes the combined search page one query.

| Column | Type | Notes |
|---|---|---|
| id / ulid | | |
| type | varchar(10) | `player`\|`clan` |
| user_id | bigint FK → users | author |
| coc_account_id | bigint null FK | required when `type='player'` |
| clan_id | bigint null FK → clans | required when `type='clan'` |
| title | varchar(80) | |
| description | text | ≤2000 |
| th_level | smallint null | player: own TH; clan: required TH |
| trophies | int null | player: own; clan: required |
| country_code | char(2) null | |
| languages | varchar(5)[] | |
| activity_level | varchar(20) null | `casual`\|`active`\|`very_active`\|`hardcore` |
| war_preference | varchar(20) null | `never`\|`sometimes`\|`always` |
| cwl_interest | bool null | |
| play_style | varchar(20) null | `casual`\|`competitive`\|`both` |
| preferred_clan_level / preferred_league | smallint null / varchar(30) null | player only |
| war_frequency / cwl_league / capital_hall_level | varchar(20) null / varchar(30) null / smallint null | clan only |
| status | varchar(20) | `open`\|`paused`\|`closed`\|`expired` |
| applications_count | int default 0 | |
| expires_at | timestamptz | 30 days, renewable |
| bumped_at | timestamptz | sort key; rate-limited bump |
| created_at / updated_at / deleted_at | | |

**Constraints:** `UNIQUE (coc_account_id) WHERE type='player' AND status='open'`;
`UNIQUE (clan_id) WHERE type='clan' AND status='open'`;
`CHECK` that the right discriminator column is populated per `type`.
**Indexes:** `(type, status, bumped_at DESC)`; `(type, th_level, status)`;
`(country_code, type, status)`; GIN on `languages`; GIN on `search_vector`.

### `recruitment_applications`
Player → clan post.

`id/ulid`, `recruitment_post_id (FK)`, `applicant_id (FK users)`, `coc_account_id (FK)`,
`message (varchar 500)`, `status (pending|accepted|declined|withdrawn|expired)`,
`responded_by null`, `responded_at null`, `created_at`.
**Unique:** `(recruitment_post_id, applicant_id) WHERE status='pending'`.
**Indexes:** `(recruitment_post_id, status, created_at)`, `(applicant_id, created_at DESC)`.

### `recruitment_interests`
Clan recruiter → player post. Same shape, reversed direction: `recruitment_post_id`,
`clan_id`, `recruiter_id`, `message`, `status (pending|accepted|declined|withdrawn|expired)`.
**Unique:** `(recruitment_post_id, clan_id) WHERE status='pending'`.

---

## Marketplace [P3]

### `seller_profiles`
`user_id (PK, FK)`, `display_name`, `headline`, `bio`, `status (pending|approved|suspended|rejected)`,
`approved_by null`, `approved_at null`, `rating_avg (numeric 3,2)`, `rating_count`,
`orders_completed`, `response_time_hours`, `portfolio_media_count`.
**Index:** `(status)`, `(rating_avg DESC) WHERE status='approved'`.

### `marketplace_listings`
`id/ulid`, `seller_id (FK users)`, `title`, `slug (unique)`, `description`,
`category (base_design|base_review|coaching|clan_graphics|banner|video_editing|tournament_graphics|other)`,
`pricing_model (fixed|range|quote)`, `price_min_minor null`, `price_max_minor null`,
`currency char(3) null`, `delivery_days`, `revisions`, `status (draft|pending_review|active|paused|rejected|removed)`,
`rejection_reason null`, `orders_count`, `rating_avg`, `rating_count`, `created_at/updated_at/deleted_at`.
**Indexes:** `(status, category, created_at DESC)`, `(seller_id, status)`, GIN on `search_vector`.

### `marketplace_orders`
`id/ulid`, `listing_id (FK, restrict)`, `buyer_id (FK users, restrict)`, `seller_id (FK users, restrict)`,
`requirements (text)`, `agreed_price_minor null`, `currency null`,
`status (requested|accepted|declined|in_progress|delivered|completed|cancelled|disputed)`,
`delivery_due_at null`, `delivered_at null`, `completed_at null`, `cancelled_reason null`,
`conversation_id (FK)`, timestamps.
**Indexes:** `(buyer_id, status)`, `(seller_id, status)`, `(status, created_at)`.

### `marketplace_order_events`
Append-only status history: `order_id`, `from_status`, `to_status`, `actor_id`, `note`, `created_at`.
**Index:** `(order_id, created_at)`.

### `marketplace_reviews`
`id`, `order_id (FK, **unique**)`, `listing_id`, `reviewer_id`, `seller_id`, `rating (1..5 CHECK)`,
`body (varchar 1000)`, `status (visible|hidden|removed)`, timestamps.
**Indexes:** `(seller_id, status, created_at DESC)`, `(listing_id, status)`.

### `marketplace_disputes`
`id`, `order_id (FK, unique per open)`, `opened_by`, `reason`, `evidence jsonb`,
`status (open|awaiting_buyer|awaiting_seller|resolved_buyer|resolved_seller|resolved_split|withdrawn)`,
`assigned_admin_id null`, `decision_note null`, `decided_by null`, `decided_at null`.

---

## Messaging [P3]

### `conversations`
`id/ulid`, `type (order|direct)`, `subject_type null`, `subject_id null` (polymorphic anchor, e.g. an
order), `last_message_at`, `status (active|locked|archived)`, timestamps.
**Index:** `(last_message_at DESC)`, `(subject_type, subject_id)`.

### `conversation_participants`
`conversation_id`, `user_id`, `last_read_at`, `muted (bool)`, `left_at null`. PK on both.
**Index:** `(user_id, last_read_at)`.

### `messages`
`id/ulid`, `conversation_id (FK, cascade)`, `sender_id (FK users, restrict)`, `body (varchar 2000)`,
`status (visible|hidden|removed)`, `created_at`, `edited_at null`.
**Index:** `(conversation_id, created_at DESC)`.

---

## Media

### `media` [M]
Single polymorphic table for every uploaded file. One table, because the lifecycle (intent →
upload → validate → process → attach → cleanup) is identical for all of them and must be swept by
one job.

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| ulid | char(26) | used in signed URLs |
| user_id | bigint FK → users | uploader; restrict on delete until swept |
| attachable_type / attachable_id | varchar(60) null / bigint null | null until attached |
| collection | varchar(30) | `avatar`\|`account_image`\|`base_screenshot`\|`base_video`\|`evidence`\|`portfolio` |
| kind | varchar(10) | `image`\|`video` |
| disk | varchar(20) | Laravel disk name (`media`), never the provider name |
| path | text | storage key |
| original_filename | varchar(255) | sanitised, never used as the storage key |
| mime_type | varchar(100) null | detected from the file signature, not declared; null until processed. For a video, the original's type (`video/mp4`, `video/x-m4v` or `video/quicktime`); its renditions are mp4 and WebP |
| extension | varchar(10) null | derived from detected MIME; null until processed |
| size_bytes | bigint | |
| width / height | int null | a video's are the transcoded output's |
| duration_seconds | numeric(6,2) null | video, the output's length |
| checksum_sha256 | char(64) null | dedupe + integrity |
| status | varchar(20) | `pending`\|`uploaded`\|`processing`\|`ready`\|`failed`\|`quarantined`\|`deleting` |
| failure_reason | varchar(100) null | |
| visibility | varchar(10) | `public`\|`private` |
| position | smallint default 0 | ordering within a collection |
| processing_attempts | smallint default 0 | processing runs so far, the job's own retries included; `media:retry-failed` stops at 3 ([10 §9](10-media-storage.md)) |
| processing_started_at | timestamptz null | when the latest processing run claimed the row; with `processed_at`, the media processing p95 on System Health ([20 §6](20-jobs-and-scheduling.md), P3-02) |
| processed_at | timestamptz null | |
| expires_at | timestamptz null | set on `pending`; drives the orphan sweeper |
| created_at / updated_at / deleted_at | timestamptz | |

**Constraints:** `UNIQUE (disk, path)`; enum `CHECK`s on `status`, `collection`, `kind`, `visibility`.
Media may be `ready` before it is attached (processing finishes before the parent form is submitted),
so there is no attachment constraint; unattached rows expire and are swept.
**Indexes:** `(attachable_type, attachable_id, collection, position)`;
`(expires_at) WHERE attachable_id IS NULL` — the sweeper's index;
`(user_id, created_at DESC)`; `(checksum_sha256)`; `(processed_at) WHERE processing_started_at IS NOT NULL`
(the p95 window).

### `media_variants` [M]
Derived renditions (thumb, card, full, poster, transcoded mp4).

`id`, `media_id (FK, cascade)`, `variant (thumb|card|full|poster|video_720p)`, `path`, `width`,
`height`, `size_bytes`, `mime_type`, `created_at`.
**Unique:** `(media_id, variant)`.

### `media_storage_orphans` [M]
First-pass state of `media:reconcile-storage` ([10 §9](10-media-storage.md)): a bucket key with no
`media`/`media_variants` row, deleted when the next consecutive run finds it again at least 24 h
later. Rows for keys that vanished or gained a row are cleared at the end of each run.

`path (text PK)`, `first_seen_at (timestamptz)`, `last_seen_at (timestamptz)`.

---

## Notifications

### `notifications` [M]
Laravel's notification table shape, extended. Kept as its own table (not Laravel's default
`uuid` PK only) so we can index efficiently for the bell.

`id (uuid PK, UUIDv7)`, `type (varchar 60)`, `notifiable_type/notifiable_id`, `data (jsonb)`,
`read_at (timestamptz null)`, `group_key (varchar 100 null)`, `created_at`, `updated_at`.
`type` holds a `NotificationType` value and `data` only `{params}` (scalars): the words and the
link are rendered when read, never stored ([16 §1](16-notifications.md)).
**Indexes:** `(notifiable_type, notifiable_id, created_at DESC, id DESC)` for the centre's order;
partial `(notifiable_id) WHERE read_at IS NULL` for the unread badge;
`(group_key, notifiable_id)` for aggregation ("12 people liked your base").
**Retention:** read notifications older than 90 days and unread older than 180 are pruned
nightly, and each account keeps at most 500 rows, losing its oldest read ones
([16 §7](16-notifications.md)).

### `notification_preferences` [M, P1-15]
`user_id (PK, FK)`, `channel_prefs jsonb` — `{category: {in_app: bool, email: bool}}`,
`non_security_email_enabled (bool default true)`,
`digest_frequency (varchar 10, none|daily|weekly, default none)`, `updated_at (timestamptz null)`.

Email controls ship in P1-15; in-app controls and digests remain Phase 5 (owner decision,
2026-10-01). The global email flag makes unsubscribe apply to future categories too;
Security bypasses it. Preferences are deleted by account anonymisation ([08 §6](08-entity-relationships.md)).
Rows are created on the first preference write or unsubscribe; a missing row reads the catalogue
defaults without a database write.

### `notification_email_deliveries` [M, P1-15]
Completed non-security deliveries, so replayed jobs do not resend an event after cache eviction.
`id (bigserial PK, internal only)`, `user_id (bigint FK, cascade)`, `type (varchar 60)`,
`event_key (varchar 100)`, `sent_at (timestamptz)`.
**Unique:** `(user_id, type, event_key)`. **Index:** `(user_id, sent_at)` for recovering the daily
cap on a cache miss. `event_key` is the failed media ULID for the first caller; no address, body
or secret is stored. The account's receipts are deleted during anonymisation ([08 §6](08-entity-relationships.md)).

---

## Moderation & audit

### `reports` [M]
One row per reporter per target.

| Column | Type | Notes |
|---|---|---|
| id / ulid | | |
| case_id | bigint null FK → report_cases | assigned on triage/grouping |
| reporter_id | bigint FK → users | restrict |
| reportable_type / reportable_id | varchar(60) / bigint | polymorphic target |
| reason_code | varchar(40) | `spam`\|`scam`\|`account_trading`\|`harassment`\|`hate`\|`nsfw`\|`stolen_content`\|`impersonation`\|`false_ownership`\|`off_platform_payment`\|`wrong_category`\|`other` (`App\Domain\Moderation\Enums\ReasonCode`, [12 §2](12-moderation-system.md)) |
| detail | varchar(1000) null | |
| evidence_media_ids | bigint[] | uploaded screenshots |
| target_snapshot | jsonb | copy of the reported content at report time — survives edits/deletes |
| status | varchar(20) | `open`\|`grouped`\|`dismissed`\|`actioned`\|`duplicate` |
| created_at / updated_at | | |

**Unique:** `(reporter_id, reportable_type, reportable_id) WHERE status='open'` — one open report per
person per target.
**Indexes:** `(reportable_type, reportable_id, status)`; `(case_id)`; `(reporter_id, created_at DESC)`;
`(status, created_at) WHERE status='open'`.

### `report_cases` [M]
Grouped workload unit, so ten reports on one base are one job for a moderator.

`id/ulid`, `reportable_type/reportable_id`, `reports_count`, `distinct_reporters_count`,
`priority (smallint)`, `status (open|triaged|assigned|actioned|dismissed|escalated)`,
`assigned_to null FK users`, `assigned_at null`, `resolved_by null`, `resolved_at null`,
`resolution (varchar 40) null`, `internal_note text null`, timestamps.
**Unique:** `(reportable_type, reportable_id) WHERE status NOT IN ('actioned','dismissed')`.
**Indexes:** `(status, priority DESC, created_at)`; `(assigned_to, status)`.

### `moderation_actions` [M]
Immutable record of what a moderator did.

`id`, `case_id null`, `actor_id (FK users, restrict)`, `action (hide|unhide|remove|restore|warn|restrict|suspend|ban|lift|unban|dismiss|escalate|transfer_ownership|approve_seller|reject_listing)`,
`target_type/target_id`, `target_user_id null`, `reason_code`, `note text`, `duration_hours null`,
`metadata jsonb`, `ip_hash`, `created_at`. **No `updated_at`, no deletes**, enforced by a trigger
as for `audit_logs`. `case_id` gets its FK with `report_cases` (P3-06).
- Sanction actions: `suspend` / `ban` target the account (`target_type = user`); `lift` (a
  suspension, later a restriction) and `unban` target the sanction (`user_sanction`, its id), so
  the history finds the lift note. A sanction replaced by a new one gets a `lift` noted "Replaced
  by a new …", and the new action's `metadata.replaced_sanction_id`. Expiry writes no action.
- Ownership disputes (P2-03): an admin's transfer, deny and suspend write `transfer_ownership`,
  `dismiss` and `suspend` with `target_type = coc_account_dispute` and reason `false_ownership`,
  through Moderation's `ModerationActionLog`. Asking a party for more, a holder's release, a token
  and a withdrawal are audit-only.
**Indexes:** `(target_type, target_id, created_at DESC)`; `(actor_id, created_at DESC)`;
`(target_user_id, created_at DESC)`; `(case_id)`.

### `user_sanctions` [M]
Active and historical sanctions, so status checks are one indexed read and expiry is a scheduled job.

`id`, `user_id (FK)`, `type (warning|restriction|suspension|ban)`, `reason_code`,
`public_reason (varchar 255)`, `internal_note`, `issued_by (FK users)`, `starts_at`,
`expires_at null`, `lifted_by null`, `lifted_at null`, `moderation_action_id (FK)`, `created_at`.
Active = `lifted_at` null and `expires_at` null or in the future. Staff lifting sets `lifted_by` /
`lifted_at`; a sanction that runs out keeps them null ("ended"). Every user FK restricts.
**Indexes:** `(user_id, expires_at)`; partial `(expires_at) WHERE expires_at IS NOT NULL AND lifted_at IS NULL`.

### `audit_logs` [M]
Everything privileged or ownership-changing. Append-only; a separate table from
`moderation_actions` because the audiences differ (compliance/forensics vs moderator workflow) and
the retention differs (audit: 2 years; moderation: indefinite).

`id (bigserial)`, `actor_id null FK users` (`ON DELETE RESTRICT`), `actor_role null`, `action (varchar 60)`,
`auditable_type/auditable_id`, `before jsonb null`, `after jsonb null`, `context jsonb`,
`ip_hash`, `user_agent`, `request_id`, `created_at`.
- A null actor is the console or the scheduler; `context.via` names which (`console`), and
  `actor_role` is null too. Staff who acted are anonymised, never deleted, so the FK restricts.
- `auditable_type` is a short subject name (`App\Domain\Audit\Enums\AuditSubject`, e.g. `user`),
  not a class name, so the log survives refactors and Audit stays a leaf module.
- Append-only in the database too: a trigger rejects `UPDATE` and `DELETE` (Postgres also
  `TRUNCATE`; SQLite has the same row triggers for the test suite). The two-year retention drops
  monthly partitions, which row triggers do not block.
- Never holds an IP address, a token or a password in `before`, `after` or `context`: the admin
  viewer shows them as recorded.
- A username change records `user.username_changed`, actor the account itself, with the username
  before/after (P1-09).
- Account anonymisation records `user.anonymised`, with status before/after and the command name,
  no removed profile fields or addresses; the actor is the console (P1-11).
- A failed job retried or deleted from System Health records `failed_job.retried` /
  `failed_job.deleted` on subject `failed_job` (`auditable_id` = `failed_jobs.id`, kept after the
  row goes), one entry per job, with `context` holding the job class, queue, uuid and the action's
  batch size; never the payload or the exception (P2-19).

**Indexes:** `(auditable_type, auditable_id, created_at)`; `(actor_id, created_at)`;
`(action, created_at)`, ascending (Postgres scans them backwards for newest-first). The viewer pages
by `id`, which follows insertion order. Partition by month after year one.

---

## Integration & operations

### `coc_api_requests` [M]
Rolling log of outbound API calls for rate-limit accounting and incident forensics. Pruned at 7 days.

`id`, `endpoint (varchar 60)`, `tag (varchar 16) null`, `status_code null` (a timeout has no
response), `duration_ms`, `was_cached (bool)`, `error_code (varchar 64) null` (the API's `reason`, or
`timeout`, or `deadline` when a manual refresh's own time limit ran out, P2-20), `created_at`. One row per outbound request and per cache hit.
**Indexes:** `(created_at)`, `(endpoint, created_at)`, `(status_code, created_at)`.

### `sync_states` [M]
Per-resource sync bookkeeping so a restarted scheduler resumes correctly.

`id`, `resource_type (coc_account|clan)`, `resource_id`, `last_attempt_at`, `last_success_at`,
`consecutive_failures`, `frozen_attempts` (failed weekly retries since freezing), `next_due_at`
(null: frozen and out of retries, "stopped syncing"), `tier (hot|warm|cold|frozen)`,
`created_at / updated_at`. Owned by CocIntegration (`SyncSchedule`, [05](05-architecture.md)).
Extra index `(last_attempt_at)` for the sync success rate. A resource no longer synced (released,
suspended, unverified) loses its row; a later verification starts a new one.
**Unique:** `(resource_type, resource_id)`. **Index:** `(next_due_at) WHERE next_due_at IS NOT NULL`.

### Framework tables [M]
`jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`, `migrations` —
Laravel defaults, unmodified except the extra session columns noted above.

---

## Schema summary

| Group | Tables | MVP |
|---|---|---|
| Auth & identity | users, username_history, sessions, password_reset_tokens, feature_flags | 4 of 5 |
| Users | profiles, privacy_settings, user_stats, follows | 3 of 4 |
| CoC | coc_accounts, coc_account_claims, coc_account_disputes, coc_account_snapshots, clans, clan_memberships | 4 of 6 |
| Bases | base_layouts, base_metrics, base_tags, base_layout_tag, base_likes, base_bookmarks, base_comments, base_view_events, base_copy_events | 9 of 9 |
| Recruitment | recruitment_posts, recruitment_applications, recruitment_interests | 0 of 3 |
| Marketplace | seller_profiles, marketplace_listings, marketplace_orders, marketplace_order_events, marketplace_reviews, marketplace_disputes | 0 of 6 |
| Messaging | conversations, conversation_participants, messages | 0 of 3 |
| Media | media, media_variants | 2 of 2 |
| Notifications | notifications, notification_preferences, notification_email_deliveries | 3 of 3 |
| Moderation | reports, report_cases, moderation_actions, user_sanctions, audit_logs | 5 of 5 |
| Ops | coc_api_requests, sync_states + framework tables | 2 of 2 |

**MVP: 32 tables + 7 framework tables.**
