---
id: P1-03
title: Add profiles with an edit page under /settings/profile and avatar upload through the media pipeline
phase: 1
status: done
depends_on: [P1-02, P0-05]
---

# Add profiles and avatar upload

## Spec refs
- Core: specs/07 `profiles`; specs/10 §3 "Attachment", §5 (avatar variants 512/128/48, square), §7 (avatars public via CDN), §8 (1 avatar ≤2 MB, the old one queued for deletion); specs/18 §4 (Avatar, Input, Textarea, Select), §6 "Settings"
- Plus: specs/04 §2 ("Edit own profile" ○), §3 (policies, write gate: profile writes open to restricted); specs/05 §2 (Users owns `profiles`; `ProfileService`; Media `MediaAttachmentService`); specs/08 (user → profile 1:1, cascade); specs/11 "Stored XSS", "Mass assignment", "Data exposure via page props"; specs/19 §4 (`/settings/*`)
- FR: FR-PROFILE-1 (one profile per user), FR-PROFILE-2 (editable fields), FR-PROFILE-3 (avatar), FR-PROFILE-6 (HTML stripped, safe links)
- Edge cases: specs/23 §4 "upload completes but `complete` is never called", "the same file is uploaded twice"

## Scope
- **Migration / model / factory** (07 `profiles`, 08): `profiles` with `user_id` unique + cascade, `display_name` 50, `bio` 500, `avatar_media_id` (`ON DELETE SET NULL`), `country_code` char(2), `languages` (Postgres `varchar(5)[]` with the `array_length <= 3` CHECK and GIN index; SQLite json), `timezone`, `socials` jsonb `{}`, `(country_code)` index. The migration backfills one row per existing user. Model `Domain/Users/Models/Profile`; `ProfileFactory`; `UserFactory` creates the profile after creating the user, so FR-PROFILE-1 holds in every test.
- **Domain** (`Domain/Users`):
  - Value objects: `CountryCode` (ISO-3166-1, from intl), `LanguageCodes` (ISO-639-1, ≤3, unique), `Timezone` (a PHP timezone identifier), `SocialLinks` (handles only: YouTube `@handle`, Twitch login, X handle, Discord username; links built on render from fixed `https://` hosts, Discord as text). Validation lives in them (05 §3).
  - `ProfileService::createFor(User)` (idempotent; P1-08's `UserRegistered` listener calls it), `update(User, UpdateProfileData)` (HTML stripped from display name and bio, FR-PROFILE-6), `setAvatar(User, mediaUlid)`, `removeAvatar(User)`.
  - `ProfileReadModel` → `ProfileData` (display name falling back to username, avatar variant URLs, fields for the form).
- **Domain** (`Domain/Media`): `MediaAttachmentService::attach(user, mediaUlid, collection, attachable)`, which runs inside the caller's transaction (10 §3). It checks the media is owned by the user (404 first), in the right collection and `ready` or `processing`, then sets `attachable_*`, clears `expires_at` and sets `position`. `detach()` queues the old object for deletion through `MediaLifecycleService` (10 §8).
- **Policy + Form Request**:
  - `ProfilePolicy::update` allows own profile only, for accounts whose status allows account writes (04 §3). A verified email is not required (FR-AUTH-4 does not list profile edits).
  - `MediaPolicy::create` becomes collection-aware: an avatar needs account-write standing, other collections content-write standing. `uploads.intent` and `uploads.complete` move to `account.active` (the P1-02 follow-up). Uploading still requires a verified email.
  - Form requests: `UpdateProfileRequest`, `SetAvatarRequest` (`media` ULID).
- **HTTP + UI** (19 §4, 18 §6):
  - Routes: `GET /settings/profile`, `PATCH /settings/profile`, `PUT /settings/profile/avatar`, `DELETE /settings/profile/avatar`; `auth` + `account.active`.
  - Page `Settings/Profile` on a new `SettingsLayout` (sub-nav from `navigation.ts`, only "Profile" for now; P1-05 adds the rest).
  - Built from `UiAvatar`, `UiInput`, `UiTextarea` (with a 500-character counter), `UiSelect` (country, timezone, up to 3 languages) and `useUpload('avatar')`. States: idle, uploading, processing (a placeholder until the variants are ready), failed, saved, field errors.
  - Shared `auth.user.avatarUrl` gets the 48 px variant; the header shows `UiAvatar` and links to `/settings/profile`.
- **Config keys**: `platform.profile.bio_max` 500, `display_name_max` 50, `languages_max` 3.

## Out of scope
- `privacy_settings`, `user_stats`, `/u/{username}` → P1-04
- Username change, `username_history`, old-URL redirects (FR-PROFILE-7) → P1-09
- `profiles.search_vector`: it cannot be a generated column, because `username` lives on `users`. It lands with Search v1 (P3-05) as a trigger or reindex job.
- The rest of the settings sub-nav (Privacy, Accounts, Security, Notifications, Danger zone) → P1-05
- The `UserRegistered` listener that creates the profile → P1-08

## Acceptance criteria
- Functional: FR-PROFILE-1–3 and FR-PROFILE-6. A new avatar replaces the old one, whose objects are queued for deletion.
- Authorization: only the owner edits a profile. Restricted accounts can edit the profile and upload an avatar, and suspended and pending-deletion accounts cannot. Unverified accounts can edit fields but not upload. Another user's media ULID gets a 404.
- Edge cases: an avatar intent that is never attached expires and is swept (23 §4); re-uploading the same file is allowed.
- States: every form state above, designed at 375 px and desktop; the avatar falls back to initials.

## Tests
- Feature (`assertInertia`):
  - `Settings/Profile` props for the owner.
  - An update saves the fields.
  - Setting an avatar attaches it and queues the previous one for deletion. Removing it works.
  - Validation for each field.
  - The shared `avatarUrl` is set.
- Security:
  - IDOR on the avatar ULID.
  - Mass assignment: `user_id`, `avatar_media_id`, `role`, `status` posted to `PATCH /settings/profile` change nothing.
  - Stored XSS payloads in the display name and bio come out stripped or escaped.
  - A URL or `javascript:` value in a social field is rejected; only a handle is accepted.
  - Status matrix for profile writes and avatar uploads.
- Unit: `CountryCode`, `LanguageCodes`, `Timezone`, `SocialLinks`.
- Vitest: the settings nav resolver; the avatar upload flow component, if it gets logic beyond `useUpload`.

## Notes

### Decisions
- **Releasing an avatar:** a replaced or removed avatar is claimed as `deleting` and deleted by `DeleteMediaObjectsJob` after the transaction commits (`MediaAttachmentService::release`), not soft-deleted. The first version soft-deleted it, which left a removed photo public for 7 days (security review). Quarantined media is never released. synced → specs/10 §3, §8.
- **Plain text (FR-PROFILE-6):** the display name and bio lose tags and HTML comments through a regex (`<…>` starting with a letter, `/` or `!`), repeated until nothing changes. A single pass let `<<b>script>` rebuild `<script>` (spec and security reviews). `strip_tags` would also cut `I <3 clash` at the `<`. Output is escaped on render either way.
- **Layering:** Http may not reference module internals (value objects live in `Domain/Users/Support`), so `UpdateProfileRequest` validates through `Domain/Users/Data/ProfileFieldRules`, and `UpdateProfileData::fromValidated()` builds the value objects. synced → specs/19 §1.
- **Languages column:** `profiles.languages` is `varchar(5)[]` + CHECK + GIN on Postgres and JSON on SQLite. Both go through `App\Support\Casts\AsStringList`. synced → specs/07 `profiles`.
- **Choice lists:** countries come from ICU (ext-intl) regions minus non-countries (EU, UN, XK, ZZ, …), which leaves 249. synced → specs/07 `profiles`. Languages are the ICU ISO-639-1 codes; timezones are `DateTimeZone::listIdentifiers()`. All three go to the form as props (the settings pages skip SSR).
- **Media reads:** `MediaReadService` is a new Media public service. Users reads avatar status and variant URLs through it by media id, never through the Media models. synced → specs/05 §2.
- **Profile creation:** every `UserFactory` user gets a profile (`withProfileData([...])` fills it). `ProfileFactory::store()` fills the user's existing profile instead of inserting a second one. The first version's `withoutProfile()` switch never reached the callback (spec review). The migration backfills existing users. `ProfileService::createFor()` is idempotent for P1-08's registration listener.
- **Upload routes:** `uploads.intent` and `uploads.complete` are back on `account.active`. `MediaPolicy` decides per collection: an avatar needs account-write standing, everything else content-write standing (P1-02 follow-up).
- **Avatar flow:** `SettingsAvatarField` uploads through `useUpload`. Once the upload is `ready` it calls `PUT /settings/profile/avatar`, so attaching normally sees `ready` media. `processing` is accepted too (10 §3), and the form then says the photo is still being prepared.
- **Header:** the avatar (48 px variant from shared `auth.user.avatarUrl`) and username link to `/settings/profile`. Signed in, the wordmark shortens to "CC" below 640 px, because Admin + avatar + Sign out did not fit beside the full wordmark at 375 px (found in the browser run). R-31: the account controls need the width more than the full name does on a phone.
- **Settings sub-nav:** `settingsNav` in `navigation.ts` has only Profile until P1-04/P1-05. `SettingsLayout` nests inside `AppLayout`.
- **Languages input:** one searchable select per slot ("Main", "Second", "Third language"), not a multi-select. Keyboard use stays simple, and empty slots are dropped on save.
- **Clearing a choice:** country, timezone and each language slot start with a "Not set" option (value `''`, stored as null). `UiSelect` renders its placeholder disabled, so without it a chosen value could not be cleared (spec review).
- **Rate limit:** `global-write` (specs/04 §4, 120/min per user, `platform.rate_limits.global_write_per_minute`) is now defined and guards the settings writes. An Inertia form gets the wait as a flash error. Other write routes pick it up as they ship (security review). synced → specs/04 §4.
- `profiles.search_vector` is deferred to P3-05 as a trigger or reindex job. synced → specs/07 `profiles`.

### Follow-ups
- P3-05: `profiles.search_vector` needs a trigger or reindex job (username lives on `users`), plus the GIN index.
- P0-09: purge CDN copies of deleted public media. Objects are served `immutable` for a year, so the edge can keep a removed avatar after storage deleted it.
- P1-04: show the socials as links built by `SocialLinks::links()` with `rel="nofollow ugc noopener"`; Discord as text.

### Verification
- `scripts/check.sh`: all green (Pest on SQLite + Postgres, Vitest, build).
- Browser, local, as the seeded moderator:
  - `/settings/profile` renders with no console errors.
  - A URL in the YouTube field gives the handle hint, with focus on the field. A handle saves.
  - `I <3 war bases` survives the round trip.
  - The searchable country select picks by typing.
  - At 375 px there is no horizontal overflow, and the header fits once the wordmark shortens.
  - Not run in the browser: the avatar upload itself (the pane cannot pick a file). Feature tests and the P0-05 dev media page cover the pipeline.
- Reviews:
  - Antislop audit-010: no findings. It ran before the header wordmark change.
  - Spec review: 6 findings, all fixed or synced: factory, clearing selects, fixed-point strip, avatar status tests, an unrelated reformat reverted, spec drift.
  - Security review: 3 low findings, all fixed: nested-tag strip, `global-write` on settings writes, released avatars deleted at once. The CDN purge is a P0-09 follow-up.

### Open questions
Resolved by the owner, 2026-09-30 (all as recommended):
1. Username change and `username_history` move to a new row, **P1-09**, which depends on P1-04 and P1-08. synced → tasks/BOARD.md, specs/25 §4.
2. `user_stats` is created in P1-04 with `privacy_settings`; P1-03 covers `profiles` only. synced → tasks/BOARD.md P1-04 row, specs/25 §4.
3. `socials` stores handles, never URLs; links are built on render from fixed `https://` hosts, and Discord shows as text. synced → specs/07 `profiles`.
