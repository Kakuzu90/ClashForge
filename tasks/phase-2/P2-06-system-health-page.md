---
id: P2-06
title: Build the admin System Health page (queues, failed jobs by class, scheduler, CoC key pool), read-only
phase: 2
status: done
depends_on: [P2-01, P2-07, P1-13]
---

# Build the admin System Health page

## Spec refs
- Core: specs/20 §6 (metrics and alert lines, the System Health page), §5 (poison jobs listed by class), §1 (the five queues)
- Plus: specs/05 module table + specs/19 §1–2 (new `Operations` read-model module); specs/18 §6 Admin (layout, deferred panels, skeleton / empty / inline error), §4 (`AdminPanel`); specs/09 §3 (key pool); specs/04 §2–3 (`view-platform-stats`); specs/21 §3 (no caching); tasks/phase-1/P1-13 (dashboard panels, `FailedJobsSummary`)
- FR: FR-ADMIN-5 (failed jobs, API sync health), NFR-OBS-6
- Edge cases: specs/23 §9 (worker killed mid-job: reserved jobs return after `retry_after`); no rule in 23 for an empty queue or an empty key pool (states below)

## Scope
- **Module**: new read-model module `Domain/Operations` owning reads of the framework queue tables (`jobs`, `failed_jobs`), added to the specs/05 module table and deptrac (P2-19 Q1). P1-13's `FailedJobsSummary` (+ its Data) moves in from `App\Support\Health`; the dashboard keeps working unchanged.
- **Read models** (`Domain/Operations`): `QueueStatsQuery` (per queue in `platform.health.queues`: pending now, delayed, reserved, oldest pending age, over the `platform.health.queue_max_wait` line; other queue names found in `jobs` listed too); `FailedJobsSummary` gains `byClass()` (every retained failure, `platform.prune.failed_jobs_days`, grouped by `displayName` with queue, count, first and last failure, last-hour count and the alert flag); scheduler heartbeat age from `HealthChecker` (`platform.health.heartbeat_max_age`).
- **CoC**: `CocKeyPool::status()` exposed as `#[TypeScript]` Data (`id` = token-hash prefix, healthy, reason, unhealthy since), beside `CocApiHealthReport::summary()` (breaker, calls, failures). No token ever leaves the module.
- **UI**: `GET /admin/system` → `Admin\SystemHealthController`, `Admin/System` page, `PageMeta` noindex. Gate `access-admin` + `view-platform-stats`. One deferred group per panel (Queues, Failed jobs, Scheduler, Clash of Clans API), each with skeleton, empty and inline error (`useVisitError`) per 18 §6, built from `AdminPanel`. Over-threshold values flagged with text, not colour alone. The dashboard's failed-jobs panel links here; admin nav gains "System" between Users and Logs (`viewPlatformStats`, Q3). Failures show job class and queue only (Q4).
- Config: `platform.health.queues` (the five §1 queues). Thresholds reuse `platform.health.queue_max_wait`, `heartbeat_max_age`, `platform.admin.failed_jobs_alert_per_hour`.
- Migrations / jobs / policy / form request: none (GET only; ability already exists).

## Out of scope
- Retry and delete of failed jobs (audited) → P2-19 (split from this task).
- CoC sync success rate → P2-09 (no sync data yet; 20 §6).
- Alert delivery (Sentry, paging) → P0-09.
- Media processing p95 (needs a processing-start time, follow-up with P3-02) and worker liveness (container monitoring, P0-09) (Q2).

## Acceptance criteria
- Functional: FR-ADMIN-5 / NFR-OBS-6: an admin sees queue depth and oldest pending age per queue against the 20 §6 lines, every retained failure grouped by class, the scheduler heartbeat age, and the key pool with each key's state.
- Authorization: admin and super admin (restricted and pending-deletion included, a read ability); users and moderators get 403; suspended staff go to the suspended notice and banned staff are signed out (`account.active`); the deferred props are never sent to them.
- Edge cases: no jobs, no failures, no keys (fake driver), heartbeat never written ("unknown", not "down"), a delayed job not counted as waiting, a malformed payload grouped as "Unreadable job payload".
- States: skeleton, empty ("No failed jobs in the last 30 days"), inline error with request id, over-threshold.

## Tests
- Feature (`assertInertia`): component `Admin/System`, deferred groups and prop shapes; role × status matrix; query count ≤ 25.
- Security: props never carry payloads, exception text, tokens or emails; key ids only.
- Unit: queue stats (pending vs delayed vs reserved, oldest age, unknown queue), by-class grouping incl. malformed payloads, heartbeat age.
- Vitest: panel states (skeleton, empty, error, over-threshold).

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended):
1. Retry / delete split to P2-19; this task is read-only.
2. Media p95 and worker liveness stay off the page; media p95 follows a processing-start timestamp (P3-02), worker liveness stays with container monitoring (P0-09). Sync into specs/20 §6.
3. Admin nav: "System" between Users and Logs, with `view-platform-stats`. Sync into specs/18 §6.
4. Failed jobs show job class and queue only; payloads and exception text never reach the page (the exception is in Sentry).
5. (From P2-19 Q1) `Domain/Operations` is created here, so the read models are not built in `App\Support` and moved a task later. Sync into specs/05, specs/19 §1.

### Decisions and divergences (implement, 2026-10-02)
1. `Domain/Operations` holds `FailedJobsQuery` (P1-13's `FailedJobsSummary`, renamed to the `*Query` convention, plus `byClass()`), `QueueStatsQuery` and `SchedulerStatusQuery`; the Data moved with it (`App.Domain.Operations.Data.*` in TS). It is a read-model module, not an edge module: it depends on no other module today, but P2-19 adds Audit, so it stays out of the arch test's isolated list and specs/19 §2 gives it no isolation line; only the cross-module internals rule applies. synced → specs/05 module table, specs/19 §1.
2. Queue rows: `waiting` = not reserved and `available_at` passed, `delayed` = not reserved and not yet available, `reserved` = held by a worker ("Running" on the page). The oldest wait counts from `available_at`, so a released or delayed job does not look stuck. Depth alert as of page load against `platform.health.queue_max_depth` (500); the "for 10 min" part is alerting's (P0-09). New config `platform.health.queues`, `platform.health.queue_max_depth`. synced → specs/20 §6.
3. Failures by class read every kept row (≤ `platform.prune.failed_jobs_days`, 30), grouped by class and queue in SQL and merged per class in PHP; no `failed_at` index needed at this volume (P1-13 note 4). synced → specs/20 §5–6.
4. `HealthChecker::lastHeartbeat()` is the one public read of the heartbeat; `/health` and the page agree by construction. `SchedulerState` (running / stopped / unknown) is a new enum.
5. CoC keys: `CocApiHealthReport::keys()` returns `CocKeyData` (TS) by key id (sha256 prefix) with the API's reason code. The id is shown here on purpose; the dashboard still shows counts only. synced → specs/05 CocIntegration row.
6. `auth.can.viewPlatformStats` joins the shared props for the nav item (shared-props change). Nav: Dashboard, Users, System, Logs. synced → specs/18 §6, specs/04 §2 row text.
7. NFR-OBS-6 says moderators; the page is admin+ because moderators never enter `/admin` (owner decision, 2026-10-02, specs/04 §3). specs/03 is planning context and was left as is. synced → specs/20 §6.
8. New helper `formatDuration` (two largest units). No new components; the page reuses `AdminPanel`, `AdminTable`, `UiPill`, `UiSkeleton`, `UiAlert` in the dashboard's pattern, so nothing joins `/dev/components`. R-31: over-threshold values are danger pills with the line in words ("Waiting over 5 min"), never colour alone. synced → specs/18 §6.
9. The dashboard's failed-jobs panel gets an "Open System health" button.

### Verification
- Checks: full `scripts/check.sh`, all 13 pass (Pest sqlite 1686 passed, 10 skipped; Postgres 1696 passed; Vitest, build, generated files up to date).
- Reviews: spec review 3 low findings (task wording on "edge module" and on suspended / banned staff, the specs/18 `formatDuration` sentence), all fixed; security review no findings; antislop audit-032 no findings.
