# P1-13 Admin dashboard: test cases

Source: [tasks/phase-1/P1-13-admin-dashboard.md](../phase-1/P1-13-admin-dashboard.md) · FR-ADMIN-5, specs/12 §11, specs/04 §2–3 (`view-platform-stats`, admin+), specs/20 §5–6, specs/10 §8–9, specs/18 §6 Admin, specs/21 §3

Shared setup used by several cases below:

- **Failed jobs through Adminer** (SQL command; times are UTC, like the app):
  ```sql
  INSERT INTO failed_jobs (uuid, connection, queue, payload, exception, failed_at)
  SELECT gen_random_uuid()::text, 'database', 'default',
         '{"displayName":"App\\Jobs\\QaAlphaJob","data":"QA_SECRET_PAYLOAD"}',
         'RuntimeException: QA_SECRET_EXCEPTION in /var/www/app.php:1',
         (now() AT TIME ZONE 'UTC') - interval '10 minutes'
  FROM generate_series(1, 3);
  ```
  Change the class name, the row count (`generate_series(1, N)`) and the interval per case. Reset
  with `DELETE FROM failed_jobs;`.
- **A real failing job** (alternative): `docker compose exec app php artisan tinker --execute="dispatch(function () { throw new RuntimeException('QA'); });"`.
  The `queue` worker retries it 3 times, then it lands in `failed_jobs` under a `Closure (…)` name.
- **Media through the app:** sign in as `test_user`, Settings → Profile, upload a JPEG or PNG avatar
  (a few hundred KB) and wait for processing to finish (the `queue-media` container makes the variants).
  Check sizes in Adminer: `SELECT SUM(size_bytes), COUNT(*) FROM media WHERE status <> 'pending';`
  and the same over `media_variants`.
- **Staff state through Adminer:** as in P1-06 (`users.status` restricted / pending_deletion /
  suspended / banned); restore `active` afterwards.

## Happy path

### TC-P1-13-001: Admin opens the dashboard and sees three panels
- Priority: High · Type: Functional
- Ref: FR-ADMIN-5 / task Scope "HTTP + UI"
- Preconditions: Fresh seed. Signed in as `test_admin`.
- Steps:
  1. Click "Admin" in the top bar.
- Expected: URL `/admin`, tab title "Admin". Heading "Dashboard" and a "Back to site" button beside it. Nav Dashboard · Users · Logs with "Dashboard" current, and a second "Back to site" link (chevron icon) pinned at the bottom of the left sidebar (TC-P1-13-033). Three panels: "New sign-ups" ("Accounts created, deleted ones included."), "Failed jobs" ("Jobs that used up their retries."), "Media storage" ("Uploads and their resized copies in the bucket.", full width on desktop).

### TC-P1-13-002: Sign-ups panel after a fresh seed
- Priority: High · Type: Functional
- Ref: FR-ADMIN-5 / Decision 3
- Preconditions: Fresh seed (4 verified accounts created now, the super admin included). Signed in as `test_admin`.
- Steps:
  1. Read the "New sign-ups" panel.
- Expected: Three columns "Last 24 hours", "Last 7 days", "Last 30 days", each "4" with "4 verified" under it.

### TC-P1-13-003: Unverified sign-up counts in the total, not in verified
- Priority: High · Type: Functional
- Ref: task Scope "Domain" (verified count beside each)
- Preconditions: Fresh seed. Register `qa_new` in a private window; do not click the Mailpit link.
- Steps:
  1. As `test_admin`, reload `/admin`.
  2. Verify `qa_new` from Mailpit; reload `/admin`.
- Expected: 1: each window "5" with "4 verified". 2: each window "5" with "5 verified".

### TC-P1-13-004: Sign-up windows by account age
- Priority: High · Type: Functional
- Ref: task Acceptance "correct counts at the window edges"
- Preconditions: Fresh seed. In Adminer set `created_at` (UTC): `test_user` = now − 23 h 50 min; `test_moderator` = now − 24 h 10 min; `test_admin` = now − 6 days 23 h; `test_super_admin` = now − 29 days 23 h. (`UPDATE users SET created_at = (now() AT TIME ZONE 'UTC') - interval '24 hours 10 minutes' WHERE username = 'test_moderator';` and similar.)
- Steps:
  1. Reload `/admin` as `test_admin`.
  2. Set `test_super_admin` to now − 30 days 10 min; reload.
- Expected: 1: Last 24 hours "1", Last 7 days "3", Last 30 days "4". 2: Last 30 days "3"; the other two unchanged.

### TC-P1-13-005: Soft-deleted accounts still count as sign-ups
- Priority: Medium · Type: Functional
- Ref: Decision 3
- Preconditions: Fresh seed. In Adminer set `test_user` `deleted_at` = now.
- Steps:
  1. Reload `/admin` as `test_admin`.
- Expected: Counts unchanged ("4", "4 verified" in every window).

### TC-P1-13-006: Failed jobs panel with no failures is a good state
- Priority: High · Type: UI state
- Ref: task Scope "HTTP + UI" (empty states)
- Preconditions: `failed_jobs` empty. Signed in as `test_admin`.
- Steps:
  1. Read the "Failed jobs" panel.
- Expected: "Last hour" "0" and "Last 24 hours" "0", no warning pill, and the line "No failed jobs in the last 24 hours." No table.

### TC-P1-13-007: Failed jobs panel with failures lists job classes
- Priority: High · Type: Functional
- Ref: FR-ADMIN-5 / task Scope "Failed jobs"
- Preconditions: Insert 3 `QaAlphaJob` rows at −10 min and 2 `QaBetaJob` rows at −3 hours (setup SQL). Signed in as `test_admin`.
- Steps:
  1. Reload `/admin`.
- Expected: "Last hour" "3", "Last 24 hours" "5". A table "Job" / "Failures" / "Last failure": `App\Jobs\QaAlphaJob` 3 (last failure about 10 min ago), then `App\Jobs\QaBetaJob` 2 (about 3 hours ago).

### TC-P1-13-008: A real failed job shows up
- Priority: Medium · Type: Functional
- Ref: specs/20 §5
- Preconditions: `failed_jobs` empty. The `queue` container running.
- Steps:
  1. Dispatch the failing closure (setup), wait about 10 seconds.
  2. Reload `/admin` as `test_admin`.
- Expected: "Last hour" "1"; the table lists a `Closure (…)` job with 1 failure and the current time.

### TC-P1-13-009: Failed-job windows: 1 hour and 24 hours
- Priority: High · Type: Functional
- Ref: task Acceptance "window edges" / Review fixes "failed-job window edges"
- Preconditions: `failed_jobs` empty. Insert one row each at −59 min, −61 min, −23 h 59 min and −24 h 1 min (different class names `QaJ59m`, `QaJ61m`, `QaJ1439m`, `QaJ1441m`).
- Steps:
  1. Reload `/admin` as `test_admin`.
- Expected: "Last hour" "1" (only −59 min). "Last 24 hours" "3"; the table lists `QaJ59m`, `QaJ61m`, `QaJ1439m` and not `QaJ1441m`.

### TC-P1-13-010: Over-threshold pill at more than 20 an hour
- Priority: High · Type: Functional
- Ref: specs/20 §6 / `platform.admin.failed_jobs_alert_per_hour` (20)
- Preconditions: `failed_jobs` empty.
- Steps:
  1. Insert 20 rows at −5 min; reload `/admin`.
  2. Insert 1 more row at −5 min; reload.
  3. Change all 21 rows to −2 hours (`UPDATE failed_jobs SET failed_at = (now() AT TIME ZONE 'UTC') - interval '2 hours';`); reload.
- Expected: 1: "Last hour" "20", no pill. 2: "Last hour" "21" with a red pill "Over 20 an hour". 3: "Last hour" "0", no pill; "Last 24 hours" "21".

### TC-P1-13-011: Top classes are capped at 5, ordered by count then latest failure
- Priority: High · Type: Functional
- Ref: `platform.admin.failed_jobs_top_classes` (5) / Review fixes "equal-count tie-break"
- Preconditions: `failed_jobs` empty. Insert: `QaA` ×6, `QaB` ×5, `QaC` ×4, `QaD` ×3, `QaE` ×1 at −30 min, `QaF` ×1 at −10 min, all within 24 h.
- Steps:
  1. Reload `/admin`.
- Expected: Five rows: `QaA` 6, `QaB` 5, `QaC` 4, `QaD` 3, `QaF` 1 (`QaF` beats `QaE` on the more recent failure). `QaE` is not listed. "Last 24 hours" is "20" (all classes, not only the top five).

### TC-P1-13-012: Malformed payload and over-long names
- Priority: Medium · Type: Edge case
- Ref: Decision 4 / task Tests "Unit: malformed payload tolerated"
- Preconditions: `failed_jobs` empty. Insert one row with payload `not json at all`, one with payload `[1,2,3]` (JSON but not an object), and one whose `displayName` is 250 characters long (e.g. `App\\Jobs\\` followed by 240 `X`).
- Steps:
  1. Reload `/admin`.
- Expected: The panel loads (no error). The two bad payloads are one row in muted text "Unreadable job payload" with 2 failures. The long name is cut to 200 characters ending in "...". The counts include all three rows.

### TC-P1-13-013: Media storage panel with no uploads
- Priority: High · Type: UI state
- Ref: Decision 5 / task Scope "Media"
- Preconditions: Fresh seed (no media rows). Signed in as `test_admin`.
- Steps:
  1. Read the "Media storage" panel.
- Expected: "Stored" shows a zero size and "0 files"; "Deleted, awaiting purge" a zero size and "0 files, removed after 7 days"; "Held for review" "0" "quarantined uploads". The table "Collection" / "Size" / "Files" lists all six collections, each zero: Avatar, Account image, Base screenshot, Base video, Report evidence, Portfolio image.

### TC-P1-13-014: Uploaded avatar counts original plus variants
- Priority: High · Type: Functional
- Ref: specs/10 §8 / task Tests "variants summed"
- Preconditions: One processed avatar (setup). Note the Adminer sums for `media` and `media_variants`.
- Steps:
  1. Reload `/admin` as `test_admin`.
- Expected: "Stored" equals the two byte sums added together, shown in decimal units (1 MB = 1,000,000 bytes, e.g. 1,500,000 bytes reads "1.5 MB"); files = 1 original + the number of variant rows. The Avatar row shows the same size and file count; the other collections stay at zero.

### TC-P1-13-015: Pending uploads are not counted
- Priority: Medium · Type: Edge case
- Ref: Open question 3 / Decision 5
- Preconditions: One processed avatar. In Adminer set its `media.status` to `pending`.
- Steps:
  1. Reload `/admin`.
  2. Set `status` back to `ready`; reload.
- Expected: 1: Stored drops to zero and Avatar to zero (the original and its variants leave the count). 2: the earlier figures return.

### TC-P1-13-016: Soft-deleted media moves to "awaiting purge"
- Priority: High · Type: Functional
- Ref: specs/10 §9 / Decision 5 / task Tests "soft-deleted media"
- Preconditions: One processed avatar. Note the Stored figures.
- Steps:
  1. In Adminer set the avatar's `media.deleted_at` to now (a soft delete, as when its parent record is deleted; removing the avatar in Settings deletes the objects right away instead, so it does not exercise this window).
  2. Reload `/admin` as `test_admin`.
- Expected: "Stored" is unchanged (the objects stay in the bucket). "Deleted, awaiting purge" shows the avatar's original plus variants, "N files, removed after 7 days". The Avatar row drops to zero (the table caption reads "Storage by collection, deleted media left out").

### TC-P1-13-017: Quarantined media is held for review, never awaiting purge
- Priority: High · Type: Functional
- Ref: specs/12 §11 / Review fixes "soft-deleted quarantined media"
- Preconditions: One processed avatar, not deleted.
- Steps:
  1. In Adminer set its `media.status` to `quarantined`; reload `/admin`.
  2. Also set `media.deleted_at` = now; reload.
- Expected: 1: "Held for review" "1"; the avatar's bytes still count in Stored and in the Avatar row. 2: "Held for review" still "1"; the bytes stay in the Avatar row and are not added to "Deleted, awaiting purge".

### TC-P1-13-018: Numbers are live on each load (no caching, no polling)
- Priority: Medium · Type: Functional
- Ref: specs/21 §3 / Decision 8
- Preconditions: Signed in as `test_admin` on `/admin`, DevTools Network open.
- Steps:
  1. Leave the page open for 2 minutes without touching it.
  2. Insert a failed job in Adminer, register a new account; reload `/admin`.
- Expected: 1: no new network requests and no number changes. 2: the new failure and sign-up show immediately after the reload.

## Authorization

### TC-P1-13-019: Super admin sees all three panels
- Priority: High · Type: Authorization
- Ref: specs/04 §2 `view-platform-stats`
- Preconditions: Signed in as `test_super_admin`.
- Steps:
  1. Open `/admin`.
- Expected: Same three panels and figures as for `test_admin`.

### TC-P1-13-020: Moderator is refused the dashboard and has Reports instead
- Priority: High · Type: Authorization
- Ref: specs/04 §2–3 (`access-admin` admin+) / specs/18 §6 Moderation / owner decision 2026-10-02 (replaces the moderator empty state, Open question 1)
- Preconditions: Signed in as `test_moderator`.
- Steps:
  1. Look at the top bar.
  2. Type `/admin` in the address bar.
  3. Click "Reports" in the top bar.
- Expected: Step 1: "Reports" before the bell; no "Admin" link. Step 2: "403 | This action is unauthorized."; no "Dashboard" heading, no "Nothing to review yet" empty state, no panels or skeletons. An `auth.permission_denied` line is logged. Step 3: `/moderation/reports` in the member layout (tab title "Reports"), heading "Reports" and the empty state "No open reports" / "Reports from members land here for review. Nothing is waiting right now."

### TC-P1-13-021: The deferred panels are 403 for a moderator
- Priority: High · Type: Security
- Ref: Decision 2 / specs/04 §2 `view-platform-stats` admin+ / owner decision 2026-10-02
- Preconditions: Signed in as `test_moderator` on `/moderation/reports`, DevTools open.
- Steps:
  1. In the console run:
     `const p = JSON.parse(document.querySelector('[data-page]').dataset.page); await fetch('/admin', {headers: {'X-Inertia': 'true', 'X-Inertia-Version': p.version, 'X-Inertia-Partial-Component': 'Admin/Dashboard', 'X-Inertia-Partial-Data': 'signups,failedJobs,storage'}}).then(async r => [r.status, await r.text()])`
  2. Repeat with `'X-Inertia-Partial-Data'` set to `'signups'`, then `'failedJobs'`, then `'storage'` alone.
- Expected: Every request returns `403`; no response body contains `signups`, `failedJobs`, `storage`, a count or a job name. One `auth.permission_denied` line per request in the security log.

### TC-P1-13-022: Regular user and guest are refused
- Priority: High · Type: Authorization
- Ref: specs/04 §2 `access-admin`
- Preconditions: none.
- Steps:
  1. Signed in as `test_user`, open `/admin`.
  2. Signed out, open `/admin`.
- Expected: 1: "403 | This action is unauthorized." and no "Admin" or "Reports" link in the top bar. 2: redirect to `/login`.

### TC-P1-13-023: Restricted and pending-deletion admins still see the panels
- Priority: High · Type: Authorization
- Ref: Decision 1 / specs/04 §3 (read abilities)
- Preconditions: Signed in as `test_admin`.
- Steps:
  1. In Adminer set `test_admin` `status` `restricted`; reload `/admin`.
  2. Set it to `pending_deletion` (`deletion_requested_at` now, `deletion_previous_status` `active`); reload.
- Expected: All three panels load in both states.

### TC-P1-13-024: Restricted moderator is refused too
- Priority: Medium · Type: Authorization
- Ref: specs/04 §3 / owner decision 2026-10-02
- Preconditions: `test_moderator` `status` `restricted`. Signed in as `test_moderator`.
- Steps:
  1. Open `/admin`.
  2. Run the partial request from TC-P1-13-021 step 1 from `/moderation/reports`.
  3. Click "Reports" in the top bar.
- Expected: Steps 1–2: 403, as for an unrestricted moderator; no panel data. Step 3: `/moderation/reports` opens (reading stays open to restricted staff).

### TC-P1-13-025: Suspended staff go to the notice; banned staff are signed out
- Priority: High · Type: Authorization
- Ref: specs/04 §1 / owner decision 2026-10-02
- Preconditions: Signed in as `test_admin` (and separately `test_moderator`).
- Steps:
  1. In Adminer set the account's `status` `suspended`, `status_expires_at` next week; open `/admin`.
  2. Set `status` `banned`; reload `/admin`.
- Expected: 1: redirect to `/account/suspended` ("Your account is suspended"), for the moderator too (the suspension notice comes before the moderator's 403). 2: redirect to `/login` with "This account is banned, so it cannot sign in."

## Security

### TC-P1-13-026: Props carry aggregates only: no payload, exception, email or username
- Priority: High · Type: Security
- Ref: task Acceptance "Data" / specs/11 "Data exposure via page props"
- Preconditions: Failed-job rows from the setup SQL (payload has `QA_SECRET_PAYLOAD`, exception has `QA_SECRET_EXCEPTION`). Signed in as `test_admin`, DevTools Network open.
- Steps:
  1. Reload `/admin`. Open the three deferred responses (`X-Inertia-Partial-Data` `signups`, `failedJobs`, `storage`).
  2. Search each for `QA_SECRET`, `RuntimeException`, `/var/www`, `@example.com`, `test_user`, `payload`, `exception`.
- Expected: No match. `signups` holds counts only; `failedJobs` holds `lastHour`, `last24Hours`, `alertPerHour`, `overThreshold` and `topClasses` (`name`, `count`, `lastFailedAt`); `storage` holds byte and file totals, `collections`, `purgeAfterDays`, `quarantinedCount`.

### TC-P1-13-027: HTML in a job name renders as text
- Priority: Medium · Type: Security
- Ref: specs/11 XSS
- Preconditions: Insert a failed job with payload `{"displayName":"<img src=x onerror=alert(1)>"}`.
- Steps:
  1. Reload `/admin` as `test_admin`.
- Expected: No alert; the Job cell shows the literal text `<img src=x onerror=alert(1)>`.

### TC-P1-13-028: Each panel loads in its own deferred request
- Priority: Medium · Type: Functional
- Ref: task Scope "HTTP + UI" (one defer group per panel) / Decision 6
- Preconditions: Signed in as `test_admin`, DevTools Network open.
- Steps:
  1. Reload `/admin` and look at the requests to `/admin`.
- Expected: The first response carries `platformStats: true` and lists three deferred groups; three separate partial requests follow, one each for `signups`, `failedJobs` and `storage`.

## UI states

### TC-P1-13-029: Per-panel skeletons while loading
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 / task Acceptance "States"
- Preconditions: Signed in as `test_admin`. DevTools throttling "Slow 3G".
- Steps:
  1. Reload `/admin`.
- Expected: Each panel shows its own skeleton (screen reader labels "Loading sign-ups", "Loading failed jobs", "Loading media storage") and fills in independently as its request returns.

### TC-P1-13-030: One failing panel shows its inline error while the others render
- Priority: High · Type: UI state
- Ref: Decision 6 / task "Edge cases" (a failing panel must not blank the page)
- Preconditions: Signed in as `test_admin`. In Adminer run `ALTER TABLE media_variants RENAME TO media_variants_qa;`
- Steps:
  1. Reload `/admin`.
  2. In Adminer run `ALTER TABLE media_variants_qa RENAME TO media_variants;`, then click "Try again" in the storage panel.
- Expected: 1: "New sign-ups" and "Failed jobs" show their numbers; "Media storage" shows a red alert "Media storage didn't load" with "If it keeps failing, quote request id <id>." and "Try again". No error modal. 2: the storage panel loads; the other panels keep their data.

### TC-P1-13-031: "Try again" reloads every panel still missing
- Priority: Medium · Type: UI state
- Ref: Review fixes "Try again on one failed panel" / Decision 6
- Preconditions: Signed in as `test_admin`. In Adminer rename both `media_variants` → `media_variants_qa` and `failed_jobs` → `failed_jobs_qa`.
- Steps:
  1. Reload `/admin`.
  2. Rename both tables back, then click "Try again" in the "Failed jobs" panel only.
- Expected: 1: "Failed jobs didn't load" and "Media storage didn't load" alerts (each with a request id); sign-ups render. 2: both failed panels load (neither stays on a skeleton); sign-ups keep their figures.

### TC-P1-13-032: 375 px and desktop layout
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States"
- Preconditions: Signed in as `test_admin`, with failed jobs and an avatar present.
- Steps:
  1. View `/admin` at desktop width.
  2. In DevTools device mode set 375 px and reload.
- Expected: 1: "New sign-ups" and "Failed jobs" side by side; "Media storage" spans both columns. 2: panels stack in one column; the nav is behind "Menu"; the three sign-up figures stay on one row and readable; the failed-jobs and storage tables scroll inside their own area; long job names wrap; no horizontal page scroll.

### TC-P1-13-033: "Back to site" is pinned at the bottom of the sidebar and the frame stays fixed
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 Admin / owner decision 2026-10-02
- Preconditions: Signed in as `test_admin`, with failed jobs and an avatar present (so the page is taller than a short window). Desktop width.
- Steps:
  1. Open `/admin` and look at the left sidebar.
  2. Shrink the browser window height to about 500 px (or zoom to 200%) and scroll the panels to the end with the mouse wheel.
  3. Click "Back to site" at the bottom of the sidebar.
- Expected: Step 1: the sidebar runs the full height under the top bar; Dashboard · Users · Logs sit at the top and "Back to site", with a left chevron and a thin line above it, sits at the very bottom, apart from the nav items. Step 2: only the content column scrolls; the top bar and the sidebar (with "Back to site" still visible at its bottom) do not move; the footer is the last thing in the content column. Step 3: the member home page `/` opens in the member layout.

### TC-P1-13-034: Keyboard reaches the sidebar, "Back to site" and the scrolling content
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 Admin (keyboard-first), §8 / owner decision 2026-10-02
- Preconditions: Signed in as `test_admin` on `/admin` at desktop width, window short enough that the content column scrolls (TC-P1-13-033 step 2).
- Steps:
  1. Reload and press Tab repeatedly from the top of the page.
  2. Shift+Tab back to "Back to site" and press Enter.
  3. Go back to `/admin`, press Tab once and Enter on "Skip to content", then press Page Down and End.
- Expected: Step 1: the order is "Skip to content", the wordmark ("Clash Commons home"), Dashboard, Users, Logs, "Back to site", then the dashboard's own "Back to site" button and anything focusable in the panels (the scrollable table regions); each shows a visible focus ring; nothing focused is hidden behind the fixed top bar. Step 2: `/` opens. Step 3: focus moves to the main content and Page Down / End scroll the content column to the footer while the top bar and sidebar stay in place.

### TC-P1-13-035: Below 768 px the nav folds behind "Menu" with "Back to site" last
- Priority: Medium · Type: UI state
- Ref: specs/18 §6 Admin / owner decision 2026-10-02
- Preconditions: Signed in as `test_admin`. DevTools device mode.
- Steps:
  1. Set 375 px wide, open `/admin`.
  2. Tap "Menu".
  3. Tap "Users".
  4. Tap "Menu", then "Back to site".
  5. Back on `/admin`, set the width to 767 px, then to 768 px.
- Expected: Step 1: the top bar shows the wordmark, "Admin" and a "Menu" button (`aria-expanded="false"`); no sidebar; the whole page scrolls normally with the top bar staying at the top. Step 2: `aria-expanded="true"`; the menu opens under the top bar listing Dashboard, Users, Logs and, last, "Back to site" with its chevron; every item is at least 44 px tall. Step 3: `/admin/users` opens and the menu closes. Step 4: `/` opens. Step 5: at 767 px the "Menu" button shows; at 768 px it is gone and the fixed left sidebar with "Back to site" at its bottom takes its place.
