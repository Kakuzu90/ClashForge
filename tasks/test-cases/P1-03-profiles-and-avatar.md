# P1-03 Profile settings and avatar upload: test cases

Source: tasks/phase-1/P1-03-profiles-and-avatar.md; specs/07 `profiles`, specs/10 §3–§8, specs/04 §3–§4, specs/11 "Stored XSS" / "Mass assignment", FR-PROFILE-1–3, FR-PROFILE-6.

Console snippets below need the CSRF token first: in DevTools Console run
`const t = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);` on any signed-in page, then the
`fetch(...)` the case gives (headers `{'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': t}`,
written as `H` below). Profile edits made directly in Adminer are hidden from `/u/{username}` for up to 5 minutes
(cache); edit through the page unless a case says otherwise. `docker compose exec app php artisan cache:clear` resets the
limiters after TC-P1-03-037 and TC-P1-03-041.

## Happy path

### TC-P1-03-001: Profile settings page renders for the owner
- Priority: High · Type: Functional
- Ref: FR-PROFILE-1, FR-PROFILE-2; task Scope "HTTP + UI"
- Preconditions: fresh seed; signed in as `test_user`.
- Steps:
  1. Open the avatar menu in the header and choose "Settings".
  2. Note the URL, the browser tab title and the sections on the page.
  3. In Adminer run `SELECT * FROM profiles WHERE user_id = (SELECT id FROM users WHERE username = 'test_user');`
- Expected: URL is `/settings/profile`; tab title "Profile settings · Clash Commons". Heading "Settings" with the sub-nav (Profile active, then Privacy, Security, Notifications, Danger zone). Three flat cards: "Avatar" (initials avatar "T", "Upload photo", hint "JPEG, PNG or WebP, up to 2 MB. Cropped to a square."), "Username", and the profile form (Display name with hint "Shown instead of @test_user. Leave empty to use your username.", Bio with a 0/500 counter, Country, Timezone, "Main language" / "Second language" / "Third language", "Social handles" with the line "Handles only, not links. Your profile links to them for you.", YouTube and X with an `@` prefix, Twitch, Discord, "Save profile"). No console errors. Exactly one `profiles` row exists for the user, with `socials` = `{}`.

### TC-P1-03-002: Save every profile field
- Priority: High · Type: Functional
- Ref: FR-PROFILE-2; task Acceptance "Functional"
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. Display name `Clash Chief`; Bio `Farming TH14, war every weekend.`
  2. Country: type `Ger` in the searchable select and pick Germany; Timezone: pick `Europe/Berlin`.
  3. Main language English, Second language German, leave Third on "Not set".
  4. YouTube `clashchief`, Twitch `clashchief_tv`, X `@clash_chief`, Discord `Clash.Chief`.
  5. Click "Save profile", then reload the page.
  6. In Adminer view the user's `profiles` row.
- Expected: The button shows a loading state, then "Saved." appears beside it. After reload every value is still there (YouTube and X shown without the `@`, since the prefix supplies it). DB: `display_name` = `Clash Chief`, `country_code` = `DE`, `timezone` = `Europe/Berlin`, `languages` = `{en,de}`, `socials` = `{"youtube": "@clashchief", "twitch": "clashchief_tv", "x": "clash_chief", "discord": "clash.chief"}`. The avatar initials change to "CC".

### TC-P1-03-003: Clearing choices and the display name stores null
- Priority: Medium · Type: Functional
- Ref: task Decisions "Clearing a choice", "Languages input"
- Preconditions: TC-P1-03-002 done.
- Steps:
  1. Set Country, Timezone and Main language to "Not set"; keep Second language German.
  2. Empty the Display name field and click "Save profile".
  3. Reload and check the `profiles` row in Adminer.
- Expected: "Saved." DB: `country_code`, `timezone` and `display_name` are NULL; `languages` = `{de}` (the empty slot is dropped, German moves to the first position after reload). The avatar falls back to the username initial "T".

### TC-P1-03-004: The same language twice is stored once
- Priority: Low · Type: Edge case
- Ref: specs/07 `profiles.languages` (unique, ≤3)
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. Pick English in all three language slots and save.
  2. Reload; check `profiles.languages` in Adminer.
- Expected: Saved without an error; `languages` = `{en}`; after reload only "Main language" shows English, the other slots show "Not set".

### TC-P1-03-005: Bio counter and plain text round trip
- Priority: Medium · Type: Functional
- Ref: FR-PROFILE-6; task Decisions "Plain text"
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. Type `I <3 war bases & "friendly" 2 < 3` in Bio; watch the counter.
  2. Save, reload, and open `/u/test_user`.
- Expected: The counter tracks the length up to 500. The bio is stored and shown exactly as typed (the `<3` and `< 3` are not cut), on the settings page and on the public profile.

### TC-P1-03-006: Upload a JPEG avatar
- Priority: High · Type: Functional
- Ref: FR-PROFILE-3; specs/10 §3, §5
- Preconditions: signed in as `test_user` (verified); no avatar; a JPEG of about 1000 × 800 px and under 2 MB (`photo.jpg`); all containers running.
- Steps:
  1. In the Avatar card click "Upload photo" and pick `photo.jpg`.
  2. Watch the card until it settles.
  3. In Adminer: `SELECT id, ulid, status, collection, attachable_type, attachable_id, expires_at FROM media ORDER BY id DESC LIMIT 1;`, then `SELECT name, width, height FROM media_variants WHERE media_id = <id>;` and the user's `profiles.avatar_media_id`.
- Expected: The card shows "Uploading photo.jpg" with a progress bar, then "Preparing your photo", then the new photo replaces the initials (cropped square). The button now reads "Change photo" and a "Remove" button appears. DB: `status` = `ready`, `collection` = `avatar`, `attachable_*` point at the profile, `expires_at` NULL; three variants, 512 × 512, 128 × 128 and 48 × 48; `profiles.avatar_media_id` = that id.

### TC-P1-03-007: PNG and WebP avatars are accepted
- Priority: Medium · Type: Functional
- Ref: specs/10 §4; `media.image.mimes`
- Preconditions: as TC-P1-03-006; a PNG and a WebP, each 400 × 400 px and under 2 MB.
- Steps:
  1. Upload the PNG and wait for it to show.
  2. Upload the WebP and wait for it to show.
- Expected: Both finish and show as the avatar; each new `media` row ends `ready` with three square variants.

### TC-P1-03-008: A new avatar replaces the old one, whose objects are deleted
- Priority: High · Type: Functional
- Ref: task Acceptance "A new avatar replaces the old one"; Decisions "Releasing an avatar"; specs/10 §8
- Preconditions: `test_user` has a ready avatar (note its `media.id` and `path` in Adminer); MinIO console http://localhost:9001 open on the media bucket.
- Steps:
  1. Click "Change photo" and upload a different image.
  2. When it shows, re-query the old `media` row in Adminer.
  3. Look for the old object keys in the MinIO console.
- Expected: The new photo shows. The old row is briefly `deleting` with `attachable_*` NULL, then disappears (hard-deleted by the queue, not soft-deleted). Its original and variant objects are gone from the bucket. `profiles.avatar_media_id` points at the new row.

### TC-P1-03-009: Remove the avatar
- Priority: High · Type: Functional
- Ref: FR-PROFILE-3; task Scope `DELETE /settings/profile/avatar`
- Preconditions: `test_user` has a ready avatar.
- Steps:
  1. Click "Remove".
  2. Check the card, the header avatar and `/u/test_user`.
  3. Check `profiles.avatar_media_id` and the old `media` row in Adminer.
- Expected: The card falls back to initials, "Remove" disappears and the button reads "Upload photo". The header avatar and the public profile show initials too, straight away. `avatar_media_id` is NULL and the old media row is gone after the queue runs.

### TC-P1-03-010: Re-uploading the same file is allowed
- Priority: Medium · Type: Edge case
- Ref: specs/23 §4 "the same file is uploaded twice"
- Preconditions: `test_user` has `photo.jpg` as a ready avatar.
- Steps:
  1. Click "Change photo" and pick the same `photo.jpg` again.
  2. Check the `media` table.
- Expected: A new upload starts (progress shown) and finishes; a new `media` row is attached and the previous row is released and deleted. No error about duplicates.

### TC-P1-03-011: Header avatar uses the 48 px variant
- Priority: Medium · Type: Functional
- Ref: task Scope "Shared `auth.user.avatarUrl`"; Decisions "Header"
- Preconditions: `test_user` has a ready avatar.
- Steps:
  1. Reload any page; inspect the header avatar image in DevTools (Elements, then the `img` `src` and its natural size).
  2. Open the avatar menu.
- Expected: The header image is the 48 × 48 variant (natural size 48 × 48, a different URL from the 128 px image in the Avatar card). The menu lists "Your profile" (`/u/test_user`), "Settings" (`/settings/profile`) and "Sign out".

## Validation

### TC-P1-03-012: Display name over 50 characters
- Priority: Medium · Type: Validation
- Ref: FR-PROFILE-2; `platform.profile.display_name_max` 50
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. Try to type 60 characters in Display name; note where input stops.
  2. In DevTools Elements remove the `maxlength` attribute from the Display name input, type 51 characters, click "Save profile".
- Expected: Step 1 stops at 50 characters. Step 2 shows "The display name field must not be greater than 50 characters." under the field, focus moves to it, and nothing is saved.

### TC-P1-03-013: Bio over 500 characters
- Priority: Medium · Type: Validation
- Ref: FR-PROFILE-2; `platform.profile.bio_max` 500
- Preconditions: as TC-P1-03-012.
- Steps:
  1. Paste 600 characters into Bio; note the counter and the stored length.
  2. Remove the textarea's `maxlength` in DevTools, enter 501 characters, save.
- Expected: Step 1 is cut at 500 (counter at the limit). Step 2 shows "The bio field must not be greater than 500 characters." with focus on Bio; nothing saved.

### TC-P1-03-014: YouTube accepts a handle only
- Priority: High · Type: Validation
- Ref: task Scope `SocialLinks`; Open question 3
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. YouTube `https://www.youtube.com/@clashchief`, save.
  2. YouTube `ab` (too short), save.
  3. YouTube `clash.chief-01`, save.
- Expected: Steps 1 and 2 show "Use your YouTube handle, like @clashchief." under YouTube with focus on it; nothing saved. Step 3 saves as `@clash.chief-01`.

### TC-P1-03-015: Twitch handle rules
- Priority: Medium · Type: Validation
- Ref: `SocialLinks` (Twitch 4–25 letters, numbers, underscores)
- Preconditions: as TC-P1-03-014.
- Steps:
  1. Twitch `abc`, save. 2. Twitch `clash-chief`, save. 3. Twitch `clash_chief_tv`, save.
- Expected: Steps 1 and 2: "Use your Twitch username, 4 to 25 letters, numbers or underscores." Step 3 saves.

### TC-P1-03-016: X handle rules
- Priority: Medium · Type: Validation
- Ref: `SocialLinks` (X up to 15, `@` dropped)
- Preconditions: as TC-P1-03-014.
- Steps:
  1. X `@clash_chief`, save; check `socials` in Adminer.
  2. X `clash_chief_12345` (17 characters), save.
- Expected: Step 1 saves as `clash_chief` (no `@`). Step 2: "Use your X handle without the @, up to 15 letters, numbers or underscores."

### TC-P1-03-017: Discord username rules
- Priority: Medium · Type: Validation
- Ref: `SocialLinks` (Discord 2–32 lowercase, digits, dots, underscores)
- Preconditions: as TC-P1-03-014.
- Steps:
  1. Discord `Clash.Chief`, save; check Adminer.
  2. Discord `a`, save. 3. Discord `chief#1234`, save.
- Expected: Step 1 saves as `clash.chief`. Steps 2 and 3: "Use your Discord username, 2 to 32 lowercase letters, numbers, dots or underscores."

### TC-P1-03-018: Country, timezone and languages outside the lists
- Priority: Medium · Type: Validation
- Ref: task Scope value objects `CountryCode`, `Timezone`, `LanguageCodes`
- Preconditions: signed in as `test_user`; CSRF token `t` set in the Console.
- Steps:
  1. Run `fetch('/settings/profile', {method: 'PATCH', headers: H, body: JSON.stringify({display_name: '', bio: '', country_code: 'XX', languages: ['en', 'zz'], timezone: 'Mars/Base', socials: {}})}).then(async r => console.log(r.status, await r.text()))`.
  2. Run it again with `country_code: 'EU'`, `timezone: ''` and `languages: ['en', 'de', 'fr', 'es']`.
- Expected: Both answer 422. Step 1 errors: `country_code` "Choose a country from the list.", `timezone` "Choose a timezone from the list.", `languages` "Choose languages from the list.". Step 2: `country_code` "Choose a country from the list." (EU is not a country), `languages` "Choose up to 3 languages.". The profile is unchanged.

### TC-P1-03-019: Unsupported file type is refused before upload
- Priority: High · Type: Validation
- Ref: specs/10 §3 "Intent endpoint rules"; `media.image.types_label`
- Preconditions: signed in as `test_user`; a GIF file `anim.gif` and a PDF.
- Steps:
  1. Click "Upload photo"; in the file picker switch to "All files" and pick `anim.gif`.
  2. Repeat with the PDF.
  3. Check the `media` table.
- Expected: Each time the card shows "This file type is not supported. Use a JPEG, PNG or WebP image." with a "Try again" link; the avatar is unchanged and no new `media` row exists.

### TC-P1-03-020: File over 2 MB is refused
- Priority: High · Type: Validation
- Ref: specs/10 §8 (1 avatar ≤ 2 MB); `media.collections.avatar.max_bytes`
- Preconditions: a JPEG of about 3 MB.
- Steps:
  1. Upload the 3 MB JPEG as the avatar.
- Expected: "This file is larger than 2 MB." with "Try again"; no new `media` row; avatar unchanged.

### TC-P1-03-021: Image smaller than 200 × 200 px
- Priority: High · Type: Validation
- Ref: specs/10 §10; `media.image.min_width` / `min_height` 200
- Preconditions: a PNG of 199 × 199 px and one of 150 × 400 px.
- Steps:
  1. Upload the 199 × 199 PNG; wait for processing.
  2. Repeat with the 150 × 400 PNG.
  3. Check the newest `media` rows.
- Expected: Each ends with "This image is too small. It needs to be at least 200 × 200 pixels." and "Try again". Rows: `status` = `failed`, `failure_reason` = `dimensions_too_small`, not attached. The current avatar is unchanged.

### TC-P1-03-022: Image at exactly 200 × 200 px
- Priority: Low · Type: Edge case
- Ref: `media.image.min_width` / `min_height`
- Preconditions: a PNG of exactly 200 × 200 px.
- Steps:
  1. Upload it.
- Expected: Accepted and shown as the avatar; the `media` row ends `ready` with three variants.

### TC-P1-03-023: Image larger than 6000 × 6000 px
- Priority: Medium · Type: Validation
- Ref: `media.image.max_width` / `max_height` 6000
- Preconditions: a single-colour PNG of 6001 × 6001 px saved under 2 MB.
- Steps:
  1. Upload it and wait.
- Expected: "This image is too large. The limit is 6000 × 6000 pixels."; row `failed` with `failure_reason` = `dimensions_too_large`; avatar unchanged.

### TC-P1-03-024: Animated image is refused
- Priority: Medium · Type: Validation
- Ref: `MediaFailureReason::Animated`
- Preconditions: an animated WebP (or APNG saved as `.png`) of at least 200 × 200 px, under 2 MB.
- Steps:
  1. Upload it and wait.
- Expected: "Animated images are not supported here. Upload a still image."; row `failed`, `failure_reason` = `animated`.

### TC-P1-03-025: Non-image renamed to .png is held for review
- Priority: High · Type: Security
- Ref: specs/10 §9–§10 (real MIME check, quarantine)
- Preconditions: a text file containing `hello` renamed to `fake.png`.
- Steps:
  1. Upload `fake.png` and wait.
  2. Check the newest `media` row.
- Expected: "This file is being held for review and was not published." Row `status` = `quarantined`, `failure_reason` = `suspicious_content`, not attached, no variants. The avatar is unchanged.

### TC-P1-03-026: Damaged image cannot be read
- Priority: Low · Type: Validation
- Ref: `MediaFailureReason::Undecodable`
- Preconditions: a JPEG cut to its first 300 bytes (e.g. `head -c 300 photo.jpg > broken.jpg`).
- Steps:
  1. Upload `broken.jpg` and wait.
- Expected: "This image could not be read. It may be damaged; try exporting it again."; row `failed`, `failure_reason` = `undecodable`.

## Authorization / account status

### TC-P1-03-027: Restricted account edits the profile and uploads an avatar
- Priority: High · Type: Authorization
- Ref: specs/04 §3 (profile writes open to restricted); task Acceptance "Authorization"
- Preconditions: in Adminer `UPDATE users SET status = 'restricted', status_expires_at = NULL WHERE username = 'test_user';`; signed in as `test_user`.
- Steps:
  1. Change the bio and save.
  2. Upload a valid JPEG avatar.
  3. Reset: `UPDATE users SET status = 'active' WHERE username = 'test_user';`
- Expected: Both succeed ("Saved."; the new avatar shows).

### TC-P1-03-028: Unverified account edits fields but cannot upload
- Priority: High · Type: Authorization
- Ref: task Acceptance "Unverified accounts can edit fields but not upload"
- Preconditions: in Adminer `UPDATE users SET email_verified_at = NULL WHERE username = 'test_user';`; signed in as `test_user`.
- Steps:
  1. Change the display name and save.
  2. Try to upload a valid JPEG avatar.
  3. Reset: `UPDATE users SET email_verified_at = now() WHERE username = 'test_user';`
- Expected: Step 1 saves ("Saved."). Step 2 fails with "Your email address is not verified." under the avatar and no `media` row is created. The verify-email banner shows at the top of the page.

### TC-P1-03-029: Suspended account cannot save or upload
- Priority: High · Type: Authorization
- Ref: specs/04 §1, §3
- Preconditions: signed in as `test_user` on `/settings/profile`; then in Adminer `UPDATE users SET status = 'suspended', status_expires_at = NULL WHERE username = 'test_user';`
- Steps:
  1. Without reloading, change the bio and click "Save profile".
  2. Go back to `/settings/profile` and try "Upload photo" with a valid JPEG.
  3. Reset the status to `active`.
- Expected: Step 1 lands on a 403 page "Your account is suspended" / "Changes are off until the suspension ends."; the bio is unchanged. The settings page itself still opens (read access). Step 2 fails with "This account is suspended." and no `media` row.

### TC-P1-03-030: Pending-deletion account cannot save or upload
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1, §3
- Preconditions: signed in as `test_user` on `/settings/profile`; then in Adminer `UPDATE users SET status = 'pending_deletion', deletion_requested_at = now(), deletion_previous_status = 'active' WHERE username = 'test_user';`
- Steps:
  1. Without reloading, change the bio and save.
  2. Try to upload a valid JPEG.
  3. Reset: `UPDATE users SET status = 'active', deletion_requested_at = NULL, deletion_previous_status = NULL WHERE username = 'test_user';`
- Expected: Step 1: 403 page "Your account is scheduled for deletion" / "Changes are off while the deletion is pending."; nothing saved. Step 2: "Your account status does not allow this right now."

### TC-P1-03-031: Guests cannot open profile settings
- Priority: Medium · Type: Authorization
- Ref: task Scope routes (`auth`)
- Preconditions: signed out.
- Steps:
  1. Open `/settings/profile`.
- Expected: Redirect to `/login`; after signing in as `test_user` the browser returns to `/settings/profile`.

## Security

### TC-P1-03-032: Another user's media ULID gets a 404
- Priority: High · Type: Security
- Ref: task Acceptance "Another user's media ULID gets a 404"; specs/11 IDOR
- Preconditions: `test_moderator` has uploaded an avatar; note its `media.ulid` in Adminer. Signed in as `test_user`, CSRF token `t` set.
- Steps:
  1. Run `fetch('/settings/profile/avatar', {method: 'PUT', headers: H, body: JSON.stringify({media: '<moderator ulid>'})}).then(r => console.log(r.status))`.
  2. Check both users' `profiles.avatar_media_id` and the media row's `attachable_id`.
- Expected: 404. Nothing changes: `test_user` keeps their avatar (or none), the moderator's media stays attached to the moderator's profile.

### TC-P1-03-033: Wrong collection or unfinished upload cannot become the avatar
- Priority: Medium · Type: Security
- Ref: specs/10 §3 "Attachment"
- Preconditions: signed in as `test_user` (active, verified), CSRF token `t` set.
- Steps:
  1. Run `fetch('/uploads/intent', {method: 'POST', headers: H, body: JSON.stringify({collection: 'base_screenshot', filename: 'a.png', size: 1000, mime: 'image/png'})}).then(r => r.json()).then(console.log)`; note `mediaUlid`.
  2. PUT that ULID to `/settings/profile/avatar` as in TC-P1-03-032, logging `await r.text()`.
  3. Repeat steps 1–2 with `collection: 'avatar'` (an intent never uploaded).
- Expected: Step 2: 422 with `media` "This upload was made for something else. Upload the file again." Step 3: 422 with `media` "This upload is not ready to use. Upload the file again." The avatar is unchanged.

### TC-P1-03-034: Mass assignment on the profile update does nothing
- Priority: High · Type: Security
- Ref: specs/11 "Mass assignment"; task Tests "Security"
- Preconditions: signed in as `test_user`, CSRF token `t` set; note `test_user`'s `role`, `status`, `profiles.user_id` and `avatar_media_id`.
- Steps:
  1. Run `fetch('/settings/profile', {method: 'PATCH', headers: H, body: JSON.stringify({display_name: 'Mass', bio: '', country_code: '', languages: [], timezone: '', socials: {}, user_id: 1, avatar_media_id: 1, role: 'super_admin', status: 'active'})}).then(r => console.log(r.status))`.
  2. Re-check the same columns in Adminer.
- Expected: The request succeeds (display name becomes `Mass`), but `role`, `status`, `profiles.user_id` and `avatar_media_id` are unchanged.

### TC-P1-03-035: Script payloads in display name and bio are stripped
- Priority: High · Type: Security
- Ref: FR-PROFILE-6; specs/11 "Stored XSS"; task Decisions "Plain text"
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. Display name `<script>alert(1)</script>Chief`; Bio `<img src=x onerror=alert(1)>Hi <<b>script>alert(2)<</b>/script> <!-- hidden -->end`. Save.
  2. Reload settings, open `/u/test_user`, and check `profiles` in Adminer.
- Expected: No alert fires anywhere. Stored and shown display name: `alert(1)Chief`; bio: `Hi alert(2) end` (tags, the rebuilt nested `<script>` and the comment all removed). Nothing renders as HTML.

### TC-P1-03-036: URLs and javascript: values in social fields are rejected
- Priority: High · Type: Security
- Ref: FR-PROFILE-6; task Tests "A URL or `javascript:` value in a social field is rejected"
- Preconditions: signed in as `test_user` on `/settings/profile`.
- Steps:
  1. Enter `javascript:alert(1)` in YouTube, Twitch, X and Discord; save.
  2. Enter `https://evil.example/x` in each; save.
- Expected: Each field shows its handle hint (YouTube "Use your YouTube handle, like @clashchief.", Twitch, X and Discord hints as in TC-P1-03-015 to 017); focus goes to the first; nothing is saved.

### TC-P1-03-037: Settings writes are capped by global-write
- Priority: Low · Type: Security
- Ref: specs/04 §4; `platform.rate_limits.global_write_per_minute` 120; task Decisions "Rate limit"
- Preconditions: signed in as `test_user`, CSRF token `t` set.
- Steps:
  1. Run `for (let i = 0; i < 125; i++) { const r = await fetch('/settings/profile', {method: 'PATCH', headers: {...H, Accept: 'text/html'}, redirect: 'manual', body: JSON.stringify({display_name: 'n' + i, bio: '', country_code: '', languages: [], timezone: '', socials: {}})}); }` and then check `profiles.display_name` in Adminer.
  2. Check `storage/logs/security-<date>.log` (`docker compose exec app tail -n 5 storage/logs/security-$(date +%F).log`).
- Expected: The display name stops at `n119`: writes past 120 in the minute are not applied (redirected back with the flash error "Too many changes. Wait a minute and try again."). The security log has `auth.rate_limited` with `limiter` `global-write`. After a minute saving works again. Note: the settings page does not render that flash as built; record whether any message shows.

## Edge cases

### TC-P1-03-038: An upload that is never completed expires and is swept
- Priority: Medium · Type: Edge case
- Ref: specs/23 §4 "upload completes but `complete` is never called"; `media.pending_expiry_hours` 24
- Preconditions: signed in as `test_user`; DevTools Network open.
- Steps:
  1. In Network, block the request URL pattern `*/complete` (right-click, "Block request URL").
  2. Upload a valid JPEG.
  3. Check the newest `media` row (`status`, `expires_at`).
  4. Run `UPDATE media SET expires_at = now() - interval '1 minute' WHERE ulid = '<ulid>';` then `docker compose exec app php artisan media:sweep-orphans`.
  5. Re-query the row after a few seconds; unblock the URL.
- Expected: Step 2 ends with "The upload did not finish. Check your connection and try again." Step 3: row `pending` or `uploaded`, not attached, `expires_at` about 24 h ahead. After the sweep the row turns `deleting` and is then removed; the avatar never changed.

### TC-P1-03-039: Slow processing, then "Check again"
- Priority: Medium · Type: UI state
- Ref: task Scope states "processing"; specs/10 §10
- Preconditions: `docker compose stop queue-media`; signed in as `test_user`.
- Steps:
  1. Upload a valid JPEG; leave the page open for about 3–4 minutes.
  2. `docker compose start queue-media`, wait 10 seconds, click "Check again".
- Expected: The card shows "Preparing your photo" while waiting, then "This is taking longer than usual. Check again". After "Check again" the photo is processed and set as the avatar.

### TC-P1-03-040: Storage unreachable during upload, then "Try again"
- Priority: Medium · Type: UI state
- Ref: specs/10 §10 (PUT retried twice)
- Preconditions: `docker compose stop minio`; signed in as `test_user`.
- Steps:
  1. Upload a valid JPEG.
  2. `docker compose start minio`, wait until it is up, click "Try again".
- Expected: After the retries fail: "The upload did not finish. Check your connection and try again." with "Try again". The retry runs the whole upload again and the avatar is set.

### TC-P1-03-041: Upload intents are limited to 30 an hour
- Priority: Low · Type: Edge case
- Ref: `media.rate_limits.intents_per_hour` 30
- Preconditions: signed in as `test_user`, CSRF token `t` set.
- Steps:
  1. Run `for (let i = 0; i < 31; i++) { const r = await fetch('/uploads/intent', {method: 'POST', headers: H, body: JSON.stringify({collection: 'avatar', filename: 'a.png', size: 1000, mime: 'image/png'})}); console.log(i + 1, r.status); }`
  2. Try a normal avatar upload in the page.
- Expected: Requests 1–30 answer 201, the 31st 429. The page upload fails with "Too Many Attempts." until the hour window resets.

## UI states

### TC-P1-03-042: Stored avatar still processing or refused
- Priority: Medium · Type: UI state
- Ref: task Decisions "Avatar flow" (`processing` accepted)
- Preconditions: `test_user` has an attached avatar; note its `media.id`.
- Steps:
  1. `UPDATE media SET status = 'processing' WHERE id = <id>;` and reload `/settings/profile`.
  2. `UPDATE media SET status = 'failed' WHERE id = <id>;` and reload.
  3. Set it back to `ready`.
- Expected: Step 1: "Your new photo is still being prepared. It shows here once it is ready." and initials instead of the photo. Step 2: "That photo could not be used. Upload a different one." Step 3: the photo is back.

### TC-P1-03-043: Saving and field-error states
- Priority: Medium · Type: UI state
- Ref: task Scope states "saved, field errors"
- Preconditions: signed in as `test_user`; DevTools Network throttling "Slow 3G".
- Steps:
  1. Change the bio and save; watch the button.
  2. Enter an invalid Twitch handle and an invalid Discord name; save.
- Expected: Step 1: the button shows a loading state and cannot be pressed twice; "Saved." appears and fades. Step 2: both fields show their hints and focus lands on the first invalid field (Twitch).

### TC-P1-03-044: Layout at 375 px and desktop
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States"; Decisions "Header", R-31
- Preconditions: signed in as `test_user` with a display name and an avatar.
- Steps:
  1. In DevTools device mode set 375 × 812 and load `/settings/profile`; scroll the whole page.
  2. Switch to 1280 px wide.
- Expected: At 375 px: no horizontal scroll; the header shows the short "CC" wordmark beside the avatar menu; the settings sub-nav scrolls sideways; country / timezone and the language slots stack in one column; the avatar card wraps without overlap. At 1280 px: country and timezone sit side by side, the three language slots in one row, the sub-nav is a left column.
