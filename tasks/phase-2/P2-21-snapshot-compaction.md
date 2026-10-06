---
id: P2-21
title: Compact CoC account snapshots nightly to one per day after 90 days and one per week after a year
phase: 2
status: done
depends_on: [P2-09]
---

# Snapshot compaction

## Spec refs
- Core: specs/07 `coc_account_snapshots` (retention: all for 90 days, then one row per account per day, then one per week after a year, "compaction job"; unique `(coc_account_id, captured_at)`; index `(coc_account_id, captured_at DESC)`)
- Plus: specs/20 §2 Platform jobs and §3 schedule (inline commands with `--dry-run`, chunked, summary counts, like `platform:prune-operational-tables`), §4 rules (idempotent, safe to re-run); specs/09 §6 (snapshot-on-change, "Display": deltas compare with the newest snapshot at least `coc.display.delta_days` old); specs/13 / P2-17 (the dispute review reads a tag's snapshots across rows, specs/08 §3.1); specs/05 §2 (PlayerAccounts owns the table)
- FR: FR-COC-10 (snapshots), FR-COC-14 (pages render from the last snapshot)
- Edge cases: specs/23 §5 (the API down: the newest snapshot is the fallback, so compaction never touches recent rows)

## Scope
- **Domain** (`SnapshotCompaction` service, PlayerAccounts): per account, older than `coc.snapshots.keep_all_days` (90) keeps one row per UTC day, and older than `coc.snapshots.daily_until_days` (365) one per ISO week (Monday, UTC). Which row survives and which are always kept: Open question 1.
  - It runs account by account, in id order, in chunks of `coc.snapshots.batch_size`.
  - Each account's delete is one statement, so a run that stops halfway is safe to repeat.
  - Day and week buckets use window functions, with a driver check (Postgres `date_trunc`, SQLite `strftime`).
  - The newest row of every account is never deleted, whatever its age (FR-COC-14).
- **Command:** `coc:compact-snapshots` (inline, `--dry-run`, logs counts of accounts scanned and rows deleted), daily at 02:45 (a free slot between `media:purge-deleted` and `stats:reconcile`), `withoutOverlapping`, `onOneServer`.
- **Config keys:** `coc.snapshots.keep_all_days`, `daily_until_days`, `batch_size`.

## Out of scope
- Monthly partitioning (specs/07: "once past ~5M rows"); snapshot charts or history UI; clan snapshots (none exist); `coc_api_requests` pruning (already nightly).

## Acceptance criteria
- Functional: after a run, every account has all its rows from the last 90 days, at most one per day between 90 and 365 days, and at most one per week beyond that.
- Idempotent: a second run deletes nothing. `--dry-run` deletes nothing and reports the same counts.
- Edge cases: an account whose only snapshots are old keeps its newest. The 7-day delta baseline is unaffected (it is inside the 90 days). A row on a bucket boundary is in exactly one bucket.
- No UI, no authorization surface (a scheduled command).

## Tests
- Feature: bucket rules at the 90-day and 365-day boundaries; the survivor rule; rows kept by Open question 1; the newest row is kept; idempotence; dry run; chunking across batches; the schedule entry; config keys read from config. Run on SQLite and Postgres (the driver-specific SQL).

## Notes

### Open questions
Resolved by the owner, 2026-10-06 (all as recommended); specs synced at implement → Finish.
1. **Which row survives a bucket, and what is always kept.** The specs say "one per day / week" but not which.
   - Recommended: keep the **last** row of each day or week, the state the account ended that period in, which is what a progression line would plot.
   - Also always keep `source = verification` rows, whatever their age. They mark when ownership was proven, and the dispute review reads a tag's history. There is at most one per verification, so they cost nothing.
2. **Week and day boundaries.** Recommended: UTC days and ISO weeks (Monday to Sunday, UTC), matching how `captured_at` is stored (timestamptz, whole seconds). The site has no per-user timezone for this data.

### Decisions and divergences (implement, 2026-10-06)
1. **Buckets** (Open questions 1 and 2): one `ROW_NUMBER()` window per batch, partitioned by account and a bucket key, newest first (`captured_at`, then `id`). Rows past place 1 go, except `source = verification`. The key is:
   - the row itself while younger than 90 days;
   - then its UTC day;
   - then the Monday of its ISO week.

   The key is driver-specific SQL: Postgres `to_char` / `date_trunc('week')` on `AT TIME ZONE 'UTC'`, SQLite `date()` / `weekday 0, -6 days`. The newest row of an account is always the last of its bucket, so it stays without a rule of its own. synced → specs/07.
2. **The lines:** a day or week cut by the 90-day or one-year line is split there. The rows after the line follow the newer rule, and the part before keeps its own last row, so every row falls in exactly one bucket. synced → specs/07.
3. **Command:** `coc:compact-snapshots` runs inline with `--dry-run`, daily at 02:45, with `withoutOverlapping` and `onOneServer`, and logs `coc.compact_snapshots` with its counts. Each batch of `coc.snapshots.batch_size` (500) accounts is one delete. synced → specs/20 §2, §3; specs/05 §2.
4. **Config:** `coc.snapshots.keep_all_days` (90), `daily_until_days` (365) and `batch_size`. synced → specs/09 §11.
5. **Not done:** each run scans every account that has rows older than 90 days; there is no high-water mark. That keeps it simple and idempotent at one window query per 500 accounts. Revisit with the monthly partitioning (specs/07, past ~5M rows).
