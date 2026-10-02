---
id: P2-09
title: Sync verified accounts on a tiered schedule and write snapshots on change
phase: 2
status: done
depends_on: [P2-02, P2-07]
---

# Tiered account sync and snapshots

## Spec refs
- Core: specs/09 §6 (tiers, scheduler shape, failure rules), §7 (failure table, degradation contract); specs/20 §1 (`sync` queue), §2 (`SyncCocAccountJob`), §3 (`coc:sync-accounts` every 5 min), §4 (job rules), §6 (sync success rate < 90 % over 30 min)
- Plus: specs/07 `coc_accounts` (`api_synced_at`, `api_sync_failures`), `coc_account_snapshots`, `sync_states`; specs/08 (snapshot cascade); specs/13 §9 row "tag returns 404"; specs/16 §2 "Account not found for 3 syncs" (I, batched); specs/21 §3 (`player_sync_ttl`); specs/05 module table (Q2)
- FR: FR-COC-10; FR-ADMIN-5 / NFR-OBS-6 (sync success rate)
- Edge cases: specs/23 §5 (malformed body writes nothing; intermittent 404: three failures before any visible change; maintenance: everything reads from what is stored; sync never changes verification status)

## Scope
- **Migrations / models / factories**: `sync_states` (07, CocIntegration, behind a public `SyncSchedule` service: due rows, record success / failure; Q2) and `coc_account_snapshots` (07, PlayerAccounts) with factories.
- **Domain**: tier rules (09 §6: hot / warm / cold / frozen; inputs: owner activity = the later of `users.last_login_at` and the newest session `last_activity`, through a new Auth read service; `is_featured`; no "viewed" input until P2-04, Q3) with intervals in `coc.sync.tiers`; a sync service that fetches with `CocPriority::Background`, applies `AccountGameData`, writes a snapshot only when a progression value changed (TH / BH level, XP level, best trophies, war stars, unit levels, clan tag, league id; volatile counters are stored but do not trigger, Q4; `source` scheduled / verification), resets failures and schedules `next_due_at`. Failure rules: a 404 counts toward `api_sync_failures` (3 → `stale` display + the owner's in-app notice, once); a 5xx, timeout or malformed answer backs off exponentially without touching the 404 count; an open circuit reschedules without counting. Never changes `status` (09 §7). Verification writes the first snapshot (`source: verification`) and the account's first `sync_states` row.
- **Jobs / schedule**: `SyncCocAccountJob` on `sync` (`ShouldBeUnique` 60 s on the account, `WithoutOverlapping`, 20 §4); `coc:sync-accounts` every 5 min (`withoutOverlapping`, `onOneServer`), picking due rows by `next_due_at`, at most min(`coc.sync.batch_size`, background budget left). Verified and disputed accounts only (Q5). Frozen (5 failures in a row, or stale) retries every 7 days and stops after `coc.sync.frozen_max_attempts` (4), counted on System Health as "stopped syncing"; a later success revives it.
- **Notification**: "Account not found for 3 syncs" (16 §2, in-app), names the tag.
- **Admin**: sync success rate over `coc.sync.success_window_minutes` (30) against `coc.sync.success_alert` (0.9) on the dashboard's API panel and the System Health API panel (P2-10, P2-06 notes).
- Config: `coc.sync.{tiers, batch_size, queue, backoff_base, not_found_stale, frozen_*, success_window_minutes, success_alert}` (09 §11).
- Policy / form request / user UI: none (background only).

## Out of scope
- Manual refresh (FR-COC-9, 09 §6 "Manual refresh") → P2-20 (split from this task).
- Rendering from stored data with its age (FR-COC-14) → P2-04, which owns the account UI; this task keeps `api_synced_at` and the snapshots it reads.
- Clan sync (`coc:sync-clans`) → P4-01; snapshot compaction (07 retention) → P2-21 (Q6).

## Acceptance criteria
- Functional: FR-COC-10: due verified accounts sync on their tier; a snapshot is written only on a tracked change.
- Authorization: no HTTP surface; the job re-reads the account and no-ops if it is no longer eligible.
- Edge cases: 23 §5 rows above; account released, suspended or deleted between dispatch and run; duplicate dispatch; budget exhausted (scheduler picks fewer).
- States: dashboard and System Health panels show the rate, "no syncs yet", and over-threshold.

## Tests
- Feature: scheduler selection and budget cap; job success / 404 / 5xx / malformed / circuit open; tier transitions; snapshot on change only; notice sent once at the third 404; verification baseline; panels' new field; schedule entry.
- Unit: the new enums against the specs/07 value lists. Tier rules, the tracked-value diff and the backoff read config and the clock, so they are tested as Feature tests (`AccountSyncTest`, `SyncScheduleTest`).
- Vitest: panel shows the rate and the over-threshold flag.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended):
1. Manual refresh split to P2-20 (after this task and P2-04); the data-age UI (FR-COC-14) is P2-04's.
2. `sync_states` belongs to CocIntegration behind a public `SyncSchedule` service; PlayerAccounts (and later Clans) call it. Sync into specs/05 module table.
3. Tier inputs: owner activity from `users.last_login_at` and the newest session `last_activity` via a new Auth read service; `is_featured`; "viewed in the last 24 h" waits for P2-04. Sync into specs/09 §6.
4. Snapshots trigger on progression changes only; trophies, attack / defense wins and donations are stored but do not trigger. Sync into specs/07 `coc_account_snapshots`, specs/09 §6.
5. Frozen retries weekly, stops after 4 tries ("stopped syncing" on System Health), revives on a later success; disputed accounts sync, unverified / suspended / released do not. Sync into specs/09 §6.
6. Snapshot compaction is a later task: P2-21 on the board.

### Decisions and divergences (implement, 2026-10-02)
1. `sync_states` gains `frozen_attempts` (failed weekly retries since freezing) and timestamps, `tier` allows `frozen`, `next_due_at` null means not scheduled, plus an index on `last_attempt_at` for the success rate. synced → specs/07 `sync_states`.
2. `coc_account_snapshots` gains `builder_hall_level` (a Builder Hall change is progression, Q4); `captured_at` is whole seconds and a second write in the same second keeps the first, so two verifications in one second cannot fail on the unique key. synced → specs/07.
3. Counters: `coc_accounts.api_sync_failures` counts consecutive 404s only (stale at 3, the notice once, exactly at the third); `sync_states.consecutive_failures` counts every failure (frozen at 5). The stale state is derived (`api_sync_failures >= 3`), no new column. synced → specs/07 `coc_accounts`, specs/09 §6.
4. Failure split: 5xx, timeout and malformed count against the account and back off (`backoff_base` 300 s, doubled, capped at the tier interval); circuit open, maintenance, throttled and no healthy key postpone by the API's wait (else `backoff_base`) without counting. The scheduler queues nothing while the circuit is open. synced → specs/09 §6.
5. Re-reading under a row lock before writing: an account that changed status or owner while the API answered is dropped and its schedule stopped (specs/23 §5: a sync never changes `status`).
6. The verification snapshot and the first schedule come from a queued listener on `CocAccountVerified` (`StartAccountSync`), which also covers the admin dispute transfer (it dispatches the same event). synced → specs/09 §6.
7. `coc:sync-accounts` runs at `2-59/5` (specs/20 §3: nothing at :00). synced → specs/20 §3, specs/09 §6.
8. Sync success rate = `sync_states` rows whose latest attempt is in the window and succeeded; one attempt per row, close to the per-attempt rate since tiers are hours long. Shown on both API panels with an "Under 90%" pill and the stopped count. synced → specs/20 §6, specs/18 §6.
9. The "Account not found" notice is in-app only, immediate (one per account per stale streak), instead of "batched": there is nothing to batch with. synced → specs/16 §2.
10. New config `coc.sync.*` (09 §11 list extended); new Auth service `UserActivityReader`; new CocIntegration service `SyncSchedule`; new PlayerAccounts `AccountSyncService`, `AccountSyncScheduler`, `SyncCocAccountJob`. synced → specs/05, specs/09 §11.
11. (Spec review) The scheduler claims the rows it queues (`SyncSchedule::claimDue`, `coc.sync.claim_seconds` 1800, above the job's tries and backoff), so a backlog never queues an account twice and the job stays idempotent (specs/20 §4 rules 1, 8). synced → specs/09 §6, §11.
12. (Spec review) An account no longer synced loses its `sync_states` row (`stop()` deletes), so "stopped syncing" counts only accounts that ran out of frozen retries. synced → specs/07 `sync_states`.
13. (Spec review) The "not found" notice is written in the same transaction as the third 404: if it fails, the count rolls back and the retry sends it.
14. (Spec review) `SyncCocAccountJob::failed()` logs `coc.account_sync_failed` and records a failure for the account, so a poison account backs off and freezes (specs/20 §4 rule 4).
15. (Spec review) specs/23 §2's year-inactive row now says the cold tier; frozen is for failures only. synced → specs/23 §2. The `CocAccountVerified` consumer list names `StartAccountSync`. synced → specs/05 events table, specs/09 §6.
16. Accounts verified before this change have no `sync_states` row and are not synced until they are verified again. Nothing is live yet (P0-09), so no backfill.

### Verification
- Checks: full `scripts/check.sh`, all 13 pass (Pest sqlite 1752 passed, 10 skipped; Postgres 1762 passed; Vitest, build, generated files up to date).
- Reviews: spec review 7 findings (duplicate dispatch, stopped over-count, notice loss, missing `failed()`, specs/05 and 23 drift, Unit line), all fixed as notes 11–15 with tests; security review no findings; antislop audit-033 no findings.
