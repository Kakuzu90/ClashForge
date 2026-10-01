---
id: P1-13
title: Fill the admin dashboard with sign-ups, failed jobs and media storage panels
phase: 1
status: done
depends_on: [P1-12]
---

# Fill the admin dashboard (v1)

## Spec refs
- Core: specs/02 FR-ADMIN-5; specs/12 §11 (dashboard surfaces, incl. quarantined media count); specs/18 §6 Admin (states, deferred rows, `useVisitError`), §4 admin components
- Plus: specs/20 §5 (failed jobs), §6 (failed jobs > 20/h alert); specs/10 §8–9 (storage, purge window); specs/07 `media`, `media_variants`, `users`; specs/21 §3 "Never cached: admin views"; specs/04 §2–3 (staff abilities, read abilities open to restricted staff); specs/11 "Data exposure via page props"; specs/05 §1 (Admin is Http-only over modules' public surfaces)
- FR: FR-ADMIN-5 (new signups, failed jobs, media storage usage; open reports, pending disputes and API sync health join with P3-06, P2-03 and P2-01)
- Edge cases: specs/23 has no dashboard rows. Covered here: a panel whose query fails must not blank the page (18 §6 inline error)

## Scope
- **Domain (read only, each module's public surface):**
  - `Domain/Auth`: `Queries/SignupStatsQuery` → `SignupStatsData` (accounts created in the last 24 h, 7 days and 30 days, UTC; verified count beside each).
  - `Domain/Media`: `Queries/MediaStorageQuery` → `MediaStorageData` (bytes and object count of originals + variants, per collection; quarantined count; bytes soft-deleted and awaiting purge). Definition: Open question 3.
  - Failed jobs: `failed_jobs` is framework-owned, so `App\Support\Health\FailedJobsSummary` → `FailedJobsSummaryData` (count in the last hour and 24 h, `over_threshold` at > 20/h, top 5 job classes by 24 h count with last failure time). Never the payload or exception text.
- **Policy:** panel visibility per Open question 1, checked in the controller; the page stays `access-admin`.
- **HTTP + UI:** `DashboardController` returns one deferred prop per panel, each in its own defer group, so a slow or failing panel shows its own skeleton or inline error (request id) while the others render. `Admin/Dashboard.vue`: a panel grid of stat tiles (plain admin register, 18 §4), failed-job classes in a compact `AdminTable`; empty states per panel ("No failed jobs in the last 24 hours" is a good state, shown as such). No caching (21 §3), no polling.
- **Config keys:** `platform.admin.failed_jobs_alert_per_hour` (20), `platform.admin.failed_jobs_top_classes` (5).
- **Board:** P2-01, P2-03 and P3-06 rows gain "+ dashboard panel, from P1-13".

## Out of scope
- Open reports, pending disputes, CoC API sync health panels (their modules add them)
- Failed-job list with retry / delete, queue depth, oldest-job age, key pool (specs/20 §5–6 System Health; Open question 2)
- Per-user quota display, bucket listing, charts or time series

## Acceptance criteria
- Functional: FR-ADMIN-5 (sign-ups, failed jobs, media storage) with correct counts at the window edges.
- Authorization: per Open question 1; users 403; suspended staff to the notice; panels the viewer may not see are absent from props, not just hidden.
- Data: aggregates only; no emails, usernames, job payloads or exception messages in props.
- States: per-panel skeleton / empty / inline error; moderators without panels get an empty state that says what arrives; 375 px and desktop.

## Tests
- Feature (`assertInertia`): each deferred prop's shape and counts (window boundaries, soft-deleted media, variants summed, quarantined count); threshold flag at 20 / 21 per hour; top-classes limit; query budget ≤ 15.
- Security: role × status matrix on the page and on each deferred prop; props exposure (no payload, exception, email).
- Unit: failed-job class extraction from the payload (`displayName`, malformed payload tolerated).
- Vitest: dashboard panel states (skeleton, empty, error, over-threshold).

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended). Q1 is synced into specs/04 §2–3 at implement; Q2 synced → tasks/BOARD.md (P2-06).
1. **Who sees which panel?** Specs/04 has no dashboard row. Recommended: the page stays `access-admin` (moderator+); a new read ability `view-platform-stats` (admin+, open to restricted and pending-deletion admins) gates all three panels, added to the 04 §2 matrix and its test. Moderators see an empty state until the report queue lands (P3-06). Alternative: sign-ups under `view-users`, the other two under the new ability.
2. **Where does the failed-job page go?** Specs/20 §5 wants a page listing failures by class with retry / delete; §6 and NFR-OBS-6 want a System Health page with the CoC key pool. Recommended: new board row P2-06 "System health page: queue depth, oldest job, failed jobs by class with retry / delete (audited), CoC key pool and sync rate", depending on P2-01 and P1-13; the dashboard panel links to it once it exists.
3. **What counts as storage used?** Recommended: `media` + `media_variants` `size_bytes` for every row except `pending` (declared size, no object yet); soft-deleted rows included, since their objects stay in the bucket until `media:purge-deleted` (10 §9), and shown separately as "awaiting purge". The `game/` pack has no rows and is not counted.

### Decisions and divergences (implement, 2026-10-01)
1. `view-platform-stats` is `StaffAbility::ViewPlatformStats`, admin+, a read ability (open to restricted and pending-deletion admins); matrix test row added. synced → specs/04 §2 (new row), §3 (read abilities list).
2. Panels the viewer may not see are not in the response at all: no deferred group is announced, and a hand-made partial reload for one gets Inertia's `null`. The page prop `platformStats` tells the page which layout to draw.
3. Sign-ups count every account created in the window, soft-deleted ones included (the sign-up happened), with the email-verified count beside it. One query with conditional counts over `users (created_at)`.
4. Failed jobs live in `App\Support\Health\FailedJobsSummary`: `failed_jobs` is framework-owned, so no module holds it. Classes come from the payload's `displayName`, read in SQL behind a JSON check (`IS JSON OBJECT` on Postgres 16, `json_valid` on SQLite), so one malformed row reads as "Unreadable job payload" instead of failing the query. Names are capped at 200 characters. `failed_jobs` has no `failed_at` index; fine at MVP volume, P2-06 adds one if its page needs it. synced → specs/20 §6.
5. Storage: `media` + `media_variants` `size_bytes` for every row except `pending`, soft-deleted rows counted and also given apart as "awaiting purge" with `media.lifecycle.purge_after_days`. Quarantined media is never in "awaiting purge" (the purge never selects it) and counts as held for review even when its parent was deleted. Every collection is listed, zero ones included, so the table shape is stable. Grouped by position (`GROUP BY 1, 2`): Postgres does not match a bound select expression to the same bound group expression. synced → specs/10 §8, specs/12 §11.
6. One deferred group per panel. A failed group shows its inline error (request id from `useVisitError`) in every panel still waiting; loaded panels keep their data. The error is one per page and the next visit clears it everywhere, so "Try again" reloads every panel still missing. Sizes in decimal units (1 GB = 1000 MB), as the bucket bills. synced → specs/18 §6.
7. New UI: `AdminPanel` (titled plain box, `--radius-sm`, body font; R-31: the work register of the other admin pages) and `useNumberFormat` (`formatCount`, `formatBytes`). Panel numbers use `text-h3` in the body font, not `UiStatBlock`, which is the display-font celebrate register. synced → specs/18 §4.
8. No caching (specs/21 §3) and no polling; the numbers are as of page load.

### Review fixes (verify, 2026-10-01)
- Spec (medium): "Try again" on one failed panel cleared the shared error and left the other failed panels on a skeleton; it now reloads every missing panel (Decision 6), with a Vitest case.
- Spec (low): soft-deleted quarantined media was counted as "awaiting purge", which the 7-day purge never removes; now kept with the held media (Decision 5), covered in the storage test.
- Spec (low): quarantined count kept deleted parents in on purpose and says so in the DTO docblock (Decision 5).
- Spec (low): failed-job window edges (exactly 1 h and 24 h, a second past each) and the equal-count tie-break are tested.
- Spec (low): the Postgres branch runs in `scripts/check.sh` (`pest (postgres)`); that run caught the grouping error fixed in Decision 5.
- Security: no findings. antislop audit-016: no findings.

