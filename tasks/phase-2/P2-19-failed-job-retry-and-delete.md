---
id: P2-19
title: Retry and delete failed jobs from the System Health page, audited
phase: 2
status: done
depends_on: [P2-06, P1-06]
---

# Retry and delete failed jobs, audited

## Spec refs
- Core: specs/20 §5 (poison jobs: "an admin page lists failures grouped by class for retry or deletion"), §4 (rule 1 idempotent jobs, rule 7 at most 200 jobs per fan-out)
- Plus: specs/04 §2–3 (matrix row, write abilities need an active account); specs/12 §9 + specs/07 `audit_logs` (audit entry shape); specs/11 (admin write paths, rate limits); specs/05 module table, specs/19 §1–2 (where the service lives)
- FR: FR-ADMIN-5 (failed jobs), NFR-SEC-6 (privileged actions audited)
- Edge cases: specs/23 §9 (a retried job runs again, so it must be idempotent, 20 §4 rule 1)

## Scope
- **Domain**: a service that retries (puts back on its queue, removes from `failed_jobs`) or deletes failed jobs, one job or a whole class, at most `platform.admin.failed_jobs_bulk_max` (200) per action; in `Domain/Operations` (created by P2-06, Q1). Writes one `audit_logs` entry per job (`AuditAction` `failed_job.retried` / `failed_job.deleted`, `AuditSubject::FailedJob`, `failed_jobs.id`; context: class, queue, uuid, batch size). Rows already gone (another admin acted) are skipped and counted.
- **Policy + Form Request**: new staff ability `manage-failed-jobs`, admin+, active account required (new 04 §2 row, 04 §3). Requests: one uuid, or a class name from the current by-class list. Named limiter `admin-failed-jobs`.
- **UI**: per-class and per-job Retry / Delete on P2-06's failed-jobs panel; delete asks for confirmation in the page, no password re-confirmation (Q3); a flash with done / skipped counts; buttons hidden without the ability (`can` flags), re-checked on the server.
- Config: `failed_jobs_bulk_max`, the limiter's rate.

## Out of scope
- Editing payloads, retrying jobs not in `failed_jobs`, pruning (stays with `platform:prune-operational-tables`).

## Acceptance criteria
- Functional: retry re-queues exactly the chosen jobs; delete removes them; each one audited.
- Authorization: admin and super admin with an active account; moderators, users, restricted / suspended staff refused; 403 without the ability.
- Edge cases: job already retried or deleted, unreadable payload (delete only), class larger than the cap, concurrent actions by two admins.
- States: confirmation, success flash, error inline with request id.

## Tests
- Feature: retry and delete (one, class, capped), audit rows, skipped counts; role × status matrix; validation (unknown uuid, class not in the list).
- Security: rate limit, no payload or exception text in audit context or props, CSRF on POST/DELETE.
- Vitest: confirmation step and button visibility.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended):
1. Module home: new edge module `Domain/Operations` owning `jobs` / `failed_jobs` reads and writes; P2-06 creates it with the read models, this task adds the write service.
2. Ability: `manage-failed-jobs`, admin+, active account. Sync into specs/04 §2–3.
3. No password re-confirmation; a confirmation step in the page.

### Decisions and divergences (implement, 2026-10-06)
1. **Retry** goes through Laravel's own `queue:retry` for each job, so attempts are reset and `retryUntil` refreshed as the framework does. It runs inside a per-job transaction that locks the `failed_jobs` row first. The queue push, the row's removal and the audit entry commit together, all on the application connection (specs/20 §4).
   - A second admin finds the row gone and counts it as skipped.
   - A command that no longer unserialises rolls back and stays for deletion (`kept`).

   synced → specs/20 §5, specs/05 Operations row, specs/23 §9.
2. **Targets:** one `uuid`, a `class` (the payload's `displayName`), or `unreadable`. "Unreadable" covers no class or a blank one, matching how the page groups them. Retrying unreadable jobs is refused.
   - A target with nothing left is a validation error.
   - Class actions take the oldest `failed_jobs_bulk_max` (200), and the flash says how many are left.

   synced → specs/20 §5.
3. **Per-job actions:** the page listed only classes, so "Show jobs" loads one class's newest `platform.admin.failed_jobs_list_max` (50) jobs on demand. This is the optional `failedJobList` prop: uuid, queue and time, never the payload. Each job there has Retry and Delete. synced → specs/20 §6, specs/18 §6.
4. **Confirmation:** delete (one job or a class) confirms in a modal that says how many go. Retry runs at once. Both show the done / skipped / not-retryable / left counts in the flash. synced → specs/18 §6.
5. **Limits:** `admin-failed-jobs` allows 20 actions per minute per staff member (`platform.rate_limits.admin_failed_jobs_per_minute`), on top of `global-write`. A breach is a flash error, since the buttons have no field. synced → specs/04 §4.
6. **Audit:** `failed_job.retried` / `failed_job.deleted` on `AuditSubject::FailedJob` (`failed_jobs.id`). The context holds class, queue, uuid and batch size; no payload, no exception. synced → specs/07 `audit_logs`, specs/12 §9.
7. **Ability:** `manage-failed-jobs` (admin+). It is a write ability, so restricted, suspended and pending-deletion admins can read the page but get no buttons and a 403. The page gets `canManageFailedJobs`. synced → specs/04 §2–3.
8. Not added to `/dev/components`: no new `Ui*` variant.

### Review fixes (verify, 2026-10-06)
- antislop audit-040: no findings.
- Spec (medium): a class retry with at least as many unretryable jobs as the cap re-picked them on every run and retried nothing. The service now walks past them by id until the cap of jobs done or skipped is reached, and looks at most 5 times the cap. Tested. synced → specs/20 §5, specs/23 §9.
- Spec (medium): the two-admins case had no test. It is now tested: the other row is removed while the run is going (on `JobRetryRequested`), and the run counts it as skipped.
- Spec (low-medium): an error from the delete confirmation was hidden behind the modal. The modal now closes on error, and the error shows under the table. Tested (Vitest).
- Spec (low) + security (note): a class name with spaces around it could be shown but not acted on. The class target now compares trimmed names, like the page. Names past 200 characters (shown cut) still cannot be targeted as a class. Tested.
- Security (low): the on-demand job list had no named limiter. `GET /admin/system` now shares `admin-search`. synced → specs/04 §4.
- Spec (low): Vitest cases added for the job-list rows (no Retry for unreadable jobs, no actions without the ability), and for the inline errors (a refusal, and a failed request with its request id).
- Spec (observation), kept: authorization stays in the service, as specs/04 §3 allows, and the 403s are tested over HTTP.
- Security (note), accepted: retry is atomic only while the database queue and `failed_jobs` share the application connection. That holds by configuration (specs/20 §4).

