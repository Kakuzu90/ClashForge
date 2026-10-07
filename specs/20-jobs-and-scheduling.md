# 20 — Background Jobs & Scheduled Tasks

## 1. Queues

Five named queues on the `database` connection. Worker counts are the MVP baseline.

| Queue | Purpose | Workers | Timeout | Tries | Backoff |
|---|---|---|---|---|---|
| `high` | User-visible, latency-sensitive: verification follow-up, notification for a direct action | 1 | 60s | 3 | 10, 30, 60 |
| `default` | Everything else user-triggered: indexing, counters, fan-out | 1 | 120s | 3 | 30, 120, 300 |
| `media` | Image and video processing (CPU-heavy) | 1 (separate container, CPU-capped) | 900s | 2 failures within a 60 min window | 60, 300 |
| `sync` | CoC API synchronisation | 1 | 60s | 3 | 60, 300, 900 |
| `low` | Email, digests, pruning, reconciliation | 1 | 300s | 3 | 60, 300, 900 |

Worker command shape (already in `docker-compose.yml`, extended):
```
php artisan queue:work database --queue=high,default --tries=3 --sleep=1 --rest=0.2 --max-time=3600
php artisan media:sweep-temp; php artisan queue:work media --queue=media --tries=2 --sleep=3 --max-time=3600 --memory=512 --timeout=900
php artisan queue:work database --queue=sync,low    --tries=3 --sleep=2 --max-time=3600
```

The media worker reads the `media` queue connection: the same `jobs` table, but its own
`retry_after` (1200 s, above the 900 s job timeout, see §5). Jobs are dispatched on the default
connection with queue `media`. `ProcessMediaJob` uses `maxExceptions` 2 inside a 60-minute
`retryUntil` window instead of `tries`: exceptions and timeouts use up the budget, but releasing
for a full temp volume does not.

`--max-time=3600` recycles workers hourly, which bounds memory leaks and picks up deploys.
`--rest` matters on a database queue: it stops idle workers from hammering Postgres with polling.

## 2. Job catalogue

### CoC integration (`sync`)

| Job | Trigger | Notes |
|---|---|---|
| `SyncCocAccountJob` | Scheduler (tiered) or manual refresh fallback | `ShouldBeUnique` 60s on account id. Writes a snapshot only on change. Updates `sync_states` tier and `next_due_at`. The manual refresh's fallback carries `source: manual` (its snapshot says so, it also takes the owner's unverified row, and out of tries it leaves the schedule alone, P2-20) |
| `RestoreFeaturedAccountJob` | After commit, when the featured fallback found every candidate row locked by another transaction (P2-14) | `ShouldBeUnique` 60s on user id. Locks the user's holding rows, then the user, and gives the earliest-verified one the flag; a no-op if the user has one |
| `SyncClanJob` | Scheduler, or on `CocAccountVerified` for a new clan | `ShouldBeUnique` on clan id |
| `VerifyAccountOwnershipJob` | Fallback when synchronous verification times out | Never stores the token; re-verification is the user's action, so this exists only for the rare timeout path |
| `RefreshStaticReferenceDataJob` | Weekly | Leagues, locations |
| `RotateCocApiKeysJob` | Weekly + on IP-change detection | Alerts on failure |

### Auth (`high`)

| Job | Trigger | Notes |
|---|---|---|
| `SendVerificationLifecycleEmailJob` | `auth:process-unverified` | P1-16: scalar account id, HMAC email binding, warning flag and dispatch ULID; re-check recipient eligibility under the account lock, render a fresh 60-minute verification link, write durable send receipt. `high`, 60s timeout, 3 attempts, 10/30/60 backoff. Security mail bypasses preferences/cap |

### Media (`media`)

| Job | Trigger | Notes |
|---|---|---|
| `ProcessMediaJob` | `/uploads/{ulid}/complete` | Validate → re-encode → variants → `ready`. Pre-flight disk-space check. Cleans its temp dir in `failed()` |
| `GenerateVideoPosterJob` | Part of `ProcessMediaJob` (not separate — one job, one temp file) | — |
| `PurgeQuarantineObjectJob` (`low`) | Delayed, after processing ends | Deletes the quarantine key again once its presigned PUT has expired, so a late re-upload cannot linger ([10 §3](10-media-storage.md)) |
| `DeleteMediaObjectsJob` (`low`) | Sweep, purge, entity deletion | Deletes originals + variants + the row for a batch of ≤50 claimed (`deleting`) ids; re-checks each row under a lock; one refused row does not stop the batch (the job throws at the end and retries 3× at 60/300/900 s); 75 s timeout, below the `database` connection's `retry_after`; idempotent |
| `media:sweep-orphans` (command, runs inline) | Hourly schedule | Claims expired unattached media, queues deletion in batches, re-queues stalled deletions ([10 §9](10-media-storage.md)) |
| `media:retry-failed` (command, runs inline) | Every 6 h | Re-dispatches `ProcessMediaJob` for young `processing_error` failures with runs left |
| `media:purge-deleted` (command, runs inline) | Daily schedule | Claims media soft-deleted > 7 days ago |
| `media:reconcile-storage` (command, runs inline) | Weekly schedule | Two-pass: log, then delete on the next consecutive detection ≥ 24 h later. **Scans `public/`, `quarantine/`, `private/` only — the `game/` prefix is allowlisted out, because game assets have no `media` row by design** ([10 §9](10-media-storage.md)) |
| `media:sweep-temp` (command) | Media worker start | Removes per-job temp dirs a killed worker left ([10 §10](10-media-storage.md)) |
| `assets:verify-pack` (command, runs inline) | Weekly schedule | Checks every manifest entry still exists in `game/{version}/` with a matching SHA-256 and the bucket manifest is byte-identical; alerts on missing, extra or altered objects |

### Bases (`default`)

| Job | Trigger | Notes |
|---|---|---|
| `PublishBaseWhenMediaReadyJob` | `MediaReady` listener | Flips `processing → published` when all attached media are ready |
| `AggregateBaseViewsJob` | Hourly schedule | Rolls `base_view_events` into `base_metrics.views_count`, then prunes >30d |
| `AggregateBaseCopiesJob` | Hourly schedule | Same for copy events |
| `bases:recompute-trending` (command, runs inline) | Every 15 min; `--all` nightly | Bases published in the last `bases.trending.active_days` (7), every published base with `--all`; chunked, one upsert per chunk; bumps the feed cache version (P3-03). "Activity in the last 7 days" replaces the publish window with P3-04 |
| `DetectDuplicateLayoutJob` | `BasePublished` | Cross-author hash match → Low-priority moderation case |
| `IndexSearchDocumentJob` | `BasePublished`, profile/account updates, moderation actions | Not built on the Postgres driver: triggers keep the vectors in the same transaction (P3-05); arrives with a search engine ([17 §7](17-search-and-discovery.md)) |

### Auth (`high`)

| Job | Trigger | Notes |
|---|---|---|
| `SendPasswordResetLinkJob` | `POST /forgot-password`, for every well-formed email | Broker lookup + token + reset email off the request path, so the answer's timing says nothing ([11](11-security.md)); the broker's 1-per-minute throttle applies inside it |

### Notifications (`high` / `low`)

| Job | Trigger | Notes |
|---|---|---|
| `SendNotificationJob` | Domain events | Writes the row, handles grouping with a row lock. Built in P1-07 as the queued `WriteInAppNotice` listener (`high`) plus the `InAppChannel` job of a Laravel notification; grouping joins with FR-NOTIF-5 |
| `SendEmailNotificationJob` | Same, `low` queue | P1-15: current email preferences, UTC daily cap, deleted-recipient check and durable event receipts; timeout 60s, 3 attempts, backoff 60/300/900. Bounce state joins with the mail-provider webhooks (P0-09). Security emails are sent by their own module on `high` |
| `FanOutToFollowersJob` | `BasePublished` (P2) | Chunked at 200; switches to pull-based above 1000 followers |
| `SendDigestJob` | Daily/weekly schedule (P5) | Batched per user |
| `PruneNotificationsJob` | Nightly | Read >90d, unread >180d, cap 500/user. Built as the `notifications:prune` command, run inline (chunked deletes) |

### Moderation (`default` / `low`)

| Job | Trigger | Notes |
|---|---|---|
| `EvaluateAutoModerationJob` | `ReportFiled`, content created | Runs the rule set, may auto-hide and raise priority |
| `moderation:expire-sanctions` (command, runs inline) | Every 15 min | Ends expired restrictions/suspensions, notifies once; only picks accounts whose status is still set |
| `EscalateAgingCasesJob` | Hourly | Raises priority on SLA-breaching cases, alerts staff |
| `DetectAnomaliesJob` | Nightly | Mass-reporting rings, review rings, interaction spikes, ban-evasion candidates |
| `coc:compact-snapshots` (command, runs inline, `--dry-run`) | Daily 02:45 | Thins `coc_account_snapshots` per the [07](07-database-schema.md) retention (last row per UTC day after `coc.snapshots.keep_all_days`, per ISO week after `daily_until_days`; verification rows and each account's newest stay), `coc.snapshots.batch_size` accounts per delete, so a stopped run is safe to repeat and a second run deletes nothing (P2-21) |
| `coc:release-banned-tags` (command, runs inline) | Daily 04:15 | Releases tags 30 days after the active ban started (`coc.accounts.ban_release_days`), one transaction per user with the ban re-checked under the lock (P2-24, [13 §6](13-claiming-workflow.md)) |

### Platform (`low`)

| Job | Trigger | Notes |
|---|---|---|
| `ReconcileCountersJob` | Nightly | Repairs every denormalised counter listed in [08 §5](08-entity-relationships.md) |
| `auth:process-unverified` (command, runs inline) | Daily, 04:10 | P1-16: reminder at registration age 3 days, final warning at 27, anonymisation at 30; ordinary users only, excludes staff/pending deletion. Chunked by `platform.auth.unverified_batch_size` (100), policy-checked account locks, `--dry-run`, summary counts. Requires successful warning send plus three days from enqueue and send; overdue accounts get only a warning first |
| `platform:anonymize-deleted` (command, runs inline) | Nightly, 04:00 | Executes the 30-day deletion pipeline through `AccountDeletionService`, chunked by id (`platform.auth.deletion_batch_size`, 100); locks and re-checks each account, audits once. `--dry-run` counts due accounts without changes. Media deletion remains queued after commit |
| `GenerateSitemapJob` | Nightly | Public bases + profiles, chunked sitemap index |
| `ExportUserDataJob` | On request | Builds a ZIP, uploads privately, emails a 7-day signed link |
| `platform:prune-operational-tables` (command, runs inline, `--dry-run`) | Nightly, 02:00 | `coc_api_requests` > `coc.request_log.retention_days` (7), `failed_jobs` > `platform.prune.failed_jobs_days` (30), expired `cache` and `cache_locks` rows, `sessions` idle past `session.lifetime` or older than the 30-day absolute cap; `base_view_events` > 30 d joins with P3-04 |
| `platform:check-health` (command, runs inline) | Every 5 min | R2 reachability (one HEAD, no writes) and the CoC key pool (`coc:check-health`, one `/locations` call per key) → cached health state read by `/health`; fails when storage is not ok or no CoC key works |
| `platform:heartbeat` (command) | Every minute | Scheduler liveness marker for `/health` (§6) |

## 3. Schedule

```
* / 1 min    platform:heartbeat            (the one task allowed at :00; withoutOverlapping(5))
2-59/5      coc:sync-accounts            (withoutOverlapping, onOneServer; offset off :00)
* / 5 min    platform:check-health
11-59/15     bases:recompute-trending      (offset off :00; `--all` daily at 03:15)
* / 15 min   moderation:expire-sanctions   (at :07, :22, :37, :52, so never at :00)
hourly :05   coc:sync-clans               (only tracked clans)
hourly :10   bases:aggregate-metrics       (views + copies)
hourly :20   media:sweep-orphans
hourly :25   coc:process-disputes         (escalate, withdraw, remind holders on day 3 and 6; P2-03, P2-18)
6 h at :45   media:retry-failed           (00:45, 06:45, 12:45, 18:45)
hourly :30   moderation:escalate-aging-cases
daily  02:00 platform:prune-operational-tables
daily  02:15 notifications:prune
daily  02:30 media:purge-deleted
daily  02:45 coc:compact-snapshots
daily  03:00 stats:reconcile
daily  03:15 bases:recompute-trending --all
daily  03:30 moderation:detect-anomalies
daily  04:00 platform:anonymize-deleted
daily  04:10 auth:process-unverified       (platform.auth.unverified_schedule_time)
daily  04:15 coc:release-banned-tags
daily  04:30 platform:generate-sitemap
daily  08:00 notifications:send-digests    (P5, per-user timezone aware)
weekly Sun 05:00  media:reconcile-storage
weekly Sun 05:15  assets:verify-pack
weekly Sun 05:30  coc:rotate-keys
weekly Mon 06:00  coc:refresh-reference-data
monthly 1st 06:30 platform:rotate-ip-salt
monthly 3rd 04:20 auth:refresh-disposable-domains
```

Every scheduled task uses `withoutOverlapping()`, `onOneServer()`, `runInBackground()` where it is
not latency-sensitive, and `->onFailure()` to alert. Heavy jobs are spread across the hour on
purpose — nothing starts at `:00`.

The scheduler container runs `schedule:run` every 60 seconds (already in `docker-compose.yml`).
In production, prefer `php artisan schedule:work` under a supervisor, or a real cron entry.

## 4. Job design rules

1. **Idempotent.** Every job can run twice without harm. Check state before acting; use natural
   keys, not "has this job run" flags.
2. **Small payloads.** Pass ids, never models or DTOs with loaded relations. A serialised model in
   a database queue payload is both a bloat and a staleness bug.
3. **Uniqueness where it matters.** `ShouldBeUnique` on syncs and per-entity processing;
   `WithoutOverlapping` middleware on anything that mutates shared aggregates.
4. **Explicit failure.** `failed()` cleans up temp files, marks the entity's state
   (`media.status = failed`), and notifies the owner when a user is waiting.
5. **Bounded retries.** `tries` + `retryUntil` where a late retry would be wrong (a stale sync 6
   hours later is worse than no sync).
6. **No chained user-visible latency.** Anything a user is waiting for happens in the request or on
   `high`; everything else can be seconds late.
7. **Chunked fan-out.** No job may enqueue more than 200 jobs; larger work splits into batches.
8. **Rate-budget aware.** Jobs calling the CoC API check the background budget and
   `release()` rather than blocking a worker.
9. **Time-boxed.** Every job has a `timeout` below the worker's, and long work (ffmpeg) also has an
   OS-level limit.
10. **Observable.** Every job logs start/end with a duration and the entity id; slow jobs (>10s on
    non-media queues) log a warning.

P1-16 inserts each `database` queue job and its enqueue timestamp in the same application database
transaction, explicitly using `beforeCommit()` so both become visible together. The queue database
connection must be the application connection. This is a durable dispatch boundary, not an event;
domain events still dispatch after commit. Send receipts and eligibility are re-checked under the
account lock. A notice skipped for temporary ineligibility clears its unsent enqueue marker only
when its dispatch key still matches, so cancellation of self-deletion or return from a staff role
can queue a fresh warning; old jobs cannot clear or deliver that newer notice. An exhausted mail job remains in `failed_jobs` for retry; an unsent final warning
prevents purge. SMTP acceptance followed by a crash before its receipt commits can duplicate a
notice on retry (the same limitation as P1-15); provider idempotency remains a P0-09 follow-up.

## 5. Failure handling

| Failure | Response |
|---|---|
| Job exception | Retry per policy, then `failed_jobs` + Sentry + alert if the rate exceeds 20/h |
| Job timeout | Same as exception; media jobs additionally mark the media `failed` |
| Worker crash / OOM | Workers restart via the container policy; `--max-time` recycling bounds leaks; reserved jobs return to the queue after `retry_after` |
| Queue backlog | Alert at depth >500 for 10 minutes. Runbook: scale the relevant worker, then investigate |
| Poison job (fails every time) | After `tries`, it lands in `failed_jobs`; the System Health page lists failures grouped by class (§6). Admins with `manage-failed-jobs` retry (Laravel's `queue:retry`: attempts reset, `retryUntil` refreshed) or delete one job or a class, at most `platform.admin.failed_jobs_bulk_max` (200) per action, oldest first; each job in its own transaction with its row locked, its push and its audit entry, so a second admin skips it. Unreadable payloads can only be deleted, and a command that no longer loads is stepped over and kept for deletion (P2-19) |
| Deploy during a long job | `queue:restart` after deploy; workers finish the current job then exit |
| Scheduler missed runs | Tasks are catch-up-safe by design (they select due work, not "work since last run"); a missed window self-heals on the next tick |
| Database queue contention | The symptom that triggers the Redis migration ([21](21-caching-strategy.md)) |

`retry_after` in `config/queue.php` must exceed the longest job timeout on that connection —
otherwise a long media job is re-reserved and runs twice. This is the single most common
database-queue bug; set it to 1200 for the media worker's connection or give `media` its own
connection entry with its own `retry_after`.

## 6. Monitoring

| Metric | Alert |
|---|---|
| Queue depth per queue | >500 for 10 min |
| Oldest pending job age | >5 min on `high`, >30 min on `default` |
| Failed jobs per hour | >20 |
| Media processing p95 | >180s |
| CoC sync success rate | <90% over 30 min |
| Scheduler heartbeat | No `schedule:run` in 5 min |
| Worker liveness | Any worker container restarting more than twice in 10 min |

The admin "System Health" page (`/admin/system`, P2-06, `view-platform-stats`: admins, since
moderators never enter `/admin`) shows the above plus the CoC key-pool status, so staff can tell
"the user is lying" from "sync has been broken since Tuesday". It lists every queue of §1 (and
any other found in `jobs`) with jobs waiting (runnable now), delayed and running, the oldest wait
against `platform.health.queue_max_wait`, and depth against `platform.health.queue_max_depth`
(500) as of page load; the "for 10 min" part of the depth alert belongs to alerting (P0-09).
Failures are every row kept in `failed_jobs` (`platform.prune.failed_jobs_days`), grouped by the
payload's `displayName` with the queues each failed on, job class and queue only. The scheduler
row reads the `platform:heartbeat` beat (`platform.health.heartbeat_max_age`); no beat is
"unknown", not stopped. Media processing p95 (P3-02) sits under the queue table in the same
deferred group: the latest run of each upload processed in the last
`media.health.processing_window_hours` (24), from `media.processing_started_at` to `processed_at`,
nearest rank, flagged over `media.health.processing_p95_alert_seconds` (180). Worker liveness is not
on the page (owner decision, 2026-10-02): it is a container restart count the app cannot see
(container monitoring, P0-09).
The CoC sync success rate (P2-09) is on the API panel. Retry and delete of failed jobs (P2-19,
§5) sit on the failures panel, with each class's newest `platform.admin.failed_jobs_list_max` (50)
jobs listed on demand (uuid, queue and time only). The
read models live in `Domain/Operations` ([05](05-architecture.md)). The admin dashboard's
failed-jobs panel (P1-13) shows failures in the last hour and 24 h, flags the hour above the
alert line (`platform.admin.failed_jobs_alert_per_hour`) and lists the most failed job classes by
the payload's `displayName`; payloads and exception text never reach the page. Its Clash of Clans
API panel (P2-10) shows the breaker state (and the next try while open), healthy keys of the
total, and the last `coc.health.window_hours` (24) of `coc_api_requests`: calls, cache hits,
failures as the breaker counts them (timeout or 5xx; 403, 429 and 404 are not) and the most
common error code. Since P2-09 it also shows the account sync success rate: `sync_states` rows
whose latest attempt falls in the last `coc.sync.success_window_minutes` (30), succeeded or not,
flagged under `coc.sync.success_alert` (90 %), plus the accounts that stopped syncing (frozen and
out of retries). Each row holds its latest attempt only, so this is the rate per account, close
to the per-attempt rate since a tier is hours long.
