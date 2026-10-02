---
id: P2-19
title: Retry and delete failed jobs from the System Health page, audited
phase: 2
status: todo
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
