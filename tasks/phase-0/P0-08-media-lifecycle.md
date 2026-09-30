---
id: P0-08
title: Build the media lifecycle jobs: orphan sweeper, purge-deleted, retry-failed, object deletion, temp sweep, storage reconcile
phase: 0
status: done
depends_on: [P0-05]
---

# Build the media lifecycle jobs

## Spec refs
- Core: specs/10 §9 (cleanup jobs, cascade rules), §10 (worker dies mid-transcode); specs/20 §2 "Media" (`DeleteMediaObjectsJob`, `SweepOrphanMediaJob`, `ReconcileStorageJob`), §3 (schedule), §4 (job rules), §5
- Plus: specs/07 "Media" (sweeper index, `deleting` status, soft deletes); specs/10 §2 (`game/` prefix), §11.3 ("no `media` rows", allowlist); specs/25 Phase 0 "Media pipeline"; tasks/phase-0/P0-05 Follow-ups, P0-06 open question 1
- FR: FR-MEDIA-8, FR-MEDIA-9 (purge half; entity cascades land with each parent)
- Edge cases: specs/23 §4 "`complete` never called", "reconcile finds an object with no database row", "quarantined media … account deleted"; §9 "queue worker killed mid-media-job"

## Scope
- **Migrations / models**: `media_storage_orphans (path text PK, first_seen_at, last_seen_at)` + internal model; `media.processing_attempts smallint default 0`, incremented per `ProcessMediaJob` run (Open questions 1–2; add both to specs/07).
- **Domain** (`Domain/Media`):
  - `DeleteMediaObjectsJob` (queue `low`, id payload, 20 §2): delete original + every variant key, then force-delete the row; missing objects are not an error; no-op when the row is gone (idempotent, 20 §4.1).
  - `MediaLifecycleService`:
    - `sweepOrphans()`: unattached rows past `expires_at` in `pending`/`uploaded`/`ready`/`failed` via the partial index (Open question 3), marked `deleting` then queued for deletion in chunks of ≤200 (10 §9, 20 §4.7).
    - `purgeDeleted()`: soft-deleted > 7 days → `deleting` → deletion job (10 §9, FR-MEDIA-9).
    - `retryFailed()`: `failed` + `processing_error`, younger than 24 h, < 3 attempts → `uploaded`, re-dispatch `ProcessMediaJob`; when the last attempt fails, dispatch `MediaRetriesExhausted` (10 §9; listener in P1-07).
  - `StorageReconciler`: scan `public/`, `quarantine/`, `private/` **only**, from a constant allowlist in code (never "everything except `game/`", 10 §9). Keys with no `media.path`/`media_variants.path` are logged on the first pass and deleted on the second consecutive detection. A key that has gained a row, or vanished, drops out. Rows whose object is missing are logged at `error` (Sentry alert, P0-07). Listing is lazy (Flysystem `listContents`), not `allFiles`.
  - Quarantined rows are never touched by any job here (10 §9 cascade rules).
- **Commands** (`Console/Commands/Media`, thin, call the service): `media:sweep-orphans`, `media:purge-deleted`, `media:retry-failed`, `media:reconcile-storage` (with `--dry-run`), `media:sweep-temp`.
- **Schedule** (`routes/console.php`, 20 §3): sweep hourly :20, purge daily 02:30, reconcile Sun 05:00, retry every 6 h at :45 (missing from 20 §3; add on sync). Each gets `withoutOverlapping`, `onOneServer`, `runInBackground`, `onFailure` log.
- **Worker boot temp sweep** (10 §10, 23 §9): `media:sweep-temp` deletes per-job dirs under `media.processing.temp_dir` older than the media connection's `retry_after`. The media worker's compose command runs it before `queue:work`.
- **Config** (`config/media.php` `lifecycle`): `purge_after_days` 7, `retry_max_attempts` 3, `retry_window_hours` 24, `reconcile_prefixes`, `delete_chunk` 200.
- **Observability** (20 §4.10): every command logs a summary line (`media.swept`, `media.purged`, `media.retried`, `media.reconciled`) with counts and duration.

## Out of scope
- Quarantine 30-day purge with its `audit_logs` entry → P3-06 (Open question 4; add to its board row)
- Entity cascades (base/user delete → media soft delete) → each parent task (P1-05 user deletion, P3-01 bases)
- Per-user storage recompute into `user_stats` → P1-03 or later
- Owner notification on retries exhausted → P1-07 listener on `MediaRetriesExhausted`
- R2 lifecycle rule and staging verification → P0-09

## Acceptance criteria
- Functional: FR-MEDIA-8 (unattached media past 24 h is removed from storage and the database), FR-MEDIA-9 purge half; all five commands scheduled per 20 §3.
- Authorization: no HTTP surface; artisan and scheduler only. No job can delete an attached, `processing` or `quarantined` row, or any key outside the allowlisted prefixes.
- Edge cases: expired `pending` with bytes PUT → object and row gone; orphan key deleted only on the second run; `game/` objects survive any number of reconcile runs; a killed worker's temp dir is gone after the next boot; retry stops at 3 attempts.
- States: n/a (no UI).

## Tests
- Feature (`Storage::fake`, `travelTo`): each command's happy path and its exclusions (attached, fresh, quarantined, `processing`, attempts exhausted, wrong reason); deletion job idempotent on a second run and on missing objects; schedule entries and times asserted.
- Security: **reconcile never lists or deletes under `game/`** (seeded `game/1/units/x.png` + `game/1/manifest.json` survive two runs); keys under unknown prefixes are ignored; sweeper never deletes attached or quarantined media.
- Unit: reconcile two-pass state (first seen → logged, second → deleted, reappeared row → cleared); prefix allowlist is a closed constant; temp sweep age cutoff.
- Architecture: `Domain/Media` does not depend on `Domain/GameAssets`.

## Notes

### Decisions
- Sweep and reconcile are scheduled **commands** calling `MediaLifecycleService` / `StorageReconciler`; no separate `SweepOrphanMediaJob` / `ReconcileStorageJob` classes (specs/19 §7 and 20 §3 schedule commands). synced → specs/20 §2.
- Claiming = flipping rows to `deleting` in a locked batch, then one `DeleteMediaObjectsJob` per batch of ≤ `batch_size` ids. Scope's `delete_chunk` became `batch_size`, set to **50** so a batch (about 4 keys a row) fits the job's 75 s timeout, below the `database` connection's 90 s `retry_after`. The job has 3 tries with backoff 60/300/900 (20 §1 `low`). `deleteOne` re-reads each row under `lockForUpdate` and skips it unless it is still `deleting`. Failures are caught per row, so one refused row does not hold back its batch, and the job throws once at the end. A `deleting` row idle for `stale_deleting_minutes` (60) is re-queued by the hourly sweep and logs `media.deletion_stalled` at `error`. synced → specs/10 §9, specs/20 §2.
- `process()` moves a row to `processing` with a conditional update (only from `uploaded`/`processing`), so a row claimed as `deleting` in the meantime stays claimed. (Security review.)
- One attempt = one **run**: `processing_attempts` goes up on every `process()` run, including the job's own retry and a resumed `processing` row. The cap of 3 therefore bounds worker time per upload (security review; the dispatch reading allowed up to 6 × 900 s). synced → specs/07, specs/10 §9.
- The reconcile allowlist is the class constant `StorageReconciler::PREFIXES`, **not** a config key as Scope said: a config value could be widened per environment, which is what the allowlist exists to prevent. A unit test pins it to the three prefixes and checks that it covers every prefix `MediaPaths` writes. synced → specs/10 §9.
- The second detection must come ≥ `reconcile_confirm_after_hours` (24) after the first, so a manual rerun cannot delete variants of a job still in flight. Soft-deleted rows count as owners until purge. synced → specs/10 §9, specs/23 §4.
- Missing-object check: one HEAD per expected object (variants of `ready` rows, kept originals of `quarantined` and `processing_error` rows). It logs one warning per object plus one `error` summary (Sentry), and the command exits non-zero so `onFailure` fires. synced → specs/10 §9.
- The purge skips quarantined rows too, not just the sweeper (10 §9 cascade rules).
- `media:sweep-temp` runs on every media-worker start. That includes the hourly `--max-time` recycle, since the container restarts. The age guard is the media connection's `retry_after`. synced → specs/20 §1, specs/10 §10.
- `media:retry-failed` is scheduled `45 */6 * * *`. synced → specs/20 §3.
- Spec-review fix: `media:retry-failed` and `media:sweep-temp` added to the command list. synced → specs/19 §7.
- No new arch test: `tests/Architecture/ModuleBoundariesTest.php` already isolates `Media` from every other module.

### Follow-ups
- specs/20 §1 gives the `low` queue a 300 s timeout, but the `database` connection's `retry_after` is 90 s and `queue-sync` passes no `--timeout`. Whichever task first puts a job over 60 s on `low` must raise `retry_after` or give `low` its own connection (the pattern `media` uses).
- Accepted: if the scheduler is down long enough for a `processing_error` row to age out of the 24 h window before its last attempt, no `MediaRetriesExhausted` fires. The owner already sees the failure and a re-upload affordance in the upload status (spec review 4).
- The missing-object HEAD pass is O(media rows) each week. At Stage 2 volume, replace it with a diff against the listing (e.g. a temp table of listed keys).
- P0-09: R2 needs the 31-day `quarantine/` lifecycle rule. Rerun the MinIO probe below against R2.

### Verification
- `scripts/check.sh`: all green (pint, phpstan ×2, deptrac, typecheck, lint, pest sqlite + postgres, generated files, vitest, build).
- Mutation: adding `game/` to `PREFIXES` makes the game-pack security test fail.
- MinIO (real S3 listing): `public/` listing excludes `publicity/…` and `game/…` probe keys. `media:reconcile-storage --dry-run` on the dev bucket: 6 scanned, 0 flagged, 0 missing.

### Open questions
Resolved by the owner, 2026-09-30:
1. Reconcile first-pass state lives in a new table `media_storage_orphans (path PK, first_seen_at, last_seen_at)`. synced → specs/07, specs/10 §9.
2. Attempts are counted in `media.processing_attempts smallint default 0`, incremented per `ProcessMediaJob` run. synced → specs/07.
3. The sweeper also removes unattached `failed` rows past `expires_at`; `processing` and `quarantined` stay excluded. synced → specs/10 §9.
4. The quarantine 30-day purge moves to P3-06 (moderation owns quarantine review; needs P1-06 `audit_logs`). synced → tasks/BOARD.md P3-06 row.
5. On the last failed retry, dispatch `MediaRetriesExhausted`; P1-07 adds the notification listener. synced → specs/10 §9, specs/05 §2.
