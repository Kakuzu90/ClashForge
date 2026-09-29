# 20 — Background Jobs & Scheduled Tasks

## 1. Queues

Five named queues on the `database` connection. Worker counts are the MVP baseline.

| Queue | Purpose | Workers | Timeout | Tries | Backoff |
|---|---|---|---|---|---|
| `high` | User-visible, latency-sensitive: verification follow-up, notification for a direct action | 1 | 60s | 3 | 10, 30, 60 |
| `default` | Everything else user-triggered: indexing, counters, fan-out | 1 | 120s | 3 | 30, 120, 300 |
| `media` | Image and video processing (CPU-heavy) | 1 (separate container, CPU-capped) | 900s | 2 | 60, 300 |
| `sync` | CoC API synchronisation | 1 | 60s | 3 | 60, 300, 900 |
| `low` | Email, digests, pruning, reconciliation | 1 | 300s | 3 | 60, 300, 900 |

Worker command shape (already in `docker-compose.yml`, extended):
```
php artisan queue:work database --queue=high,default --tries=3 --sleep=1 --rest=0.2 --max-time=3600
php artisan queue:work database --queue=media       --tries=2 --sleep=3 --max-time=3600 --memory=512
php artisan queue:work database --queue=sync,low    --tries=3 --sleep=2 --max-time=3600
```

`--max-time=3600` recycles workers hourly, which bounds memory leaks and picks up deploys.
`--rest` matters on a database queue: it stops idle workers from hammering Postgres with polling.

## 2. Job catalogue

### CoC integration (`sync`)

| Job | Trigger | Notes |
|---|---|---|
| `SyncCocAccountJob` | Scheduler (tiered) or manual refresh fallback | `ShouldBeUnique` 60s on account id. Writes a snapshot only on change. Updates `sync_states` tier and `next_due_at` |
| `SyncClanJob` | Scheduler, or on `CocAccountVerified` for a new clan | `ShouldBeUnique` on clan id |
| `VerifyAccountOwnershipJob` | Fallback when synchronous verification times out | Never stores the token; re-verification is the user's action, so this exists only for the rare timeout path |
| `RefreshStaticReferenceDataJob` | Weekly | Leagues, locations |
| `RotateCocApiKeysJob` | Weekly + on IP-change detection | Alerts on failure |

### Media (`media`)

| Job | Trigger | Notes |
|---|---|---|
| `ProcessMediaJob` | `/uploads/{ulid}/complete` | Validate → re-encode → variants → `ready`. Pre-flight disk-space check. Cleans its temp dir in `failed()` |
| `GenerateVideoPosterJob` | Part of `ProcessMediaJob` (not separate — one job, one temp file) | — |
| `DeleteMediaObjectsJob` | Media purge, entity deletion | Deletes originals + variants from R2; idempotent |
| `SweepOrphanMediaJob` | Hourly schedule | — |
| `ReconcileStorageJob` | Weekly schedule | Two-pass: log, then delete on second detection. **Scans `public/`, `quarantine/`, `private/` only — the `game/` prefix is allowlisted out, because game assets have no `media` row by design** ([10 §9](10-media-storage.md)) |
| `VerifyGameAssetPackJob` | Weekly schedule | Checks every manifest entry still exists in `game/{version}/` with a matching SHA-256; alerts on missing, extra or altered objects |

### Bases (`default`)

| Job | Trigger | Notes |
|---|---|---|
| `PublishBaseWhenMediaReadyJob` | `MediaReady` listener | Flips `processing → published` when all attached media are ready |
| `AggregateBaseViewsJob` | Hourly schedule | Rolls `base_view_events` into `base_metrics.views_count`, then prunes >30d |
| `AggregateBaseCopiesJob` | Hourly schedule | Same for copy events |
| `RecomputeTrendingJob` | Every 15 min | Only bases with activity in the last 7 days + a full pass nightly |
| `DetectDuplicateLayoutJob` | `BasePublished` | Cross-author hash match → Low-priority moderation case |
| `IndexSearchDocumentJob` | `BasePublished`, profile/account updates, moderation actions | No-op on the Postgres driver (generated columns handle it); real work once a search engine exists |

### Notifications (`high` / `low`)

| Job | Trigger | Notes |
|---|---|---|
| `SendNotificationJob` | Domain events | Writes the row, handles grouping with a row lock |
| `SendEmailNotificationJob` | Same, `low` queue | Respects preferences, bounce state and the daily cap |
| `FanOutToFollowersJob` | `BasePublished` (P2) | Chunked at 200; switches to pull-based above 1000 followers |
| `SendDigestJob` | Daily/weekly schedule (P5) | Batched per user |
| `PruneNotificationsJob` | Nightly | Read >90d, unread >180d, cap 500/user |

### Moderation (`default` / `low`)

| Job | Trigger | Notes |
|---|---|---|
| `EvaluateAutoModerationJob` | `ReportFiled`, content created | Runs the rule set, may auto-hide and raise priority |
| `ExpireSanctionsJob` | Every 15 min | Lifts expired restrictions/suspensions, notifies |
| `EscalateAgingCasesJob` | Hourly | Raises priority on SLA-breaching cases, alerts staff |
| `DetectAnomaliesJob` | Nightly | Mass-reporting rings, review rings, interaction spikes, ban-evasion candidates |
| `ReleaseBannedUserTagsJob` | Nightly | Releases tags 30 days after a ban |

### Platform (`low`)

| Job | Trigger | Notes |
|---|---|---|
| `ReconcileCountersJob` | Nightly | Repairs every denormalised counter listed in [08 §5](08-entity-relationships.md) |
| `AnonymizeDeletedUsersJob` | Nightly | Executes the 30-day deletion pipeline |
| `GenerateSitemapJob` | Nightly | Public bases + profiles, chunked sitemap index |
| `ExportUserDataJob` | On request | Builds a ZIP, uploads privately, emails a 7-day signed link |
| `PruneOperationalTablesJob` | Nightly | `sessions`, `cache` expired rows, `coc_api_requests` >7d, `failed_jobs` >30d, `base_view_events` >30d |
| `CheckExternalHealthJob` | Every 5 min | CoC API + R2 reachability → health endpoint state |

## 3. Schedule

```
* / 5 min    coc:sync-accounts            (withoutOverlapping, onOneServer)
* / 5 min    platform:check-health
* / 15 min   bases:recompute-trending
* / 15 min   moderation:expire-sanctions
hourly :05   coc:sync-clans               (only tracked clans)
hourly :10   bases:aggregate-metrics       (views + copies)
hourly :20   media:sweep-orphans
hourly :30   moderation:escalate-aging-cases
daily  02:00 platform:prune-operational-tables
daily  02:15 notifications:prune
daily  02:30 media:purge-deleted
daily  03:00 stats:reconcile
daily  03:30 moderation:detect-anomalies
daily  04:00 platform:anonymize-deleted
daily  04:15 coc:release-banned-tags
daily  04:30 platform:generate-sitemap
daily  08:00 notifications:send-digests    (P5, per-user timezone aware)
weekly Sun 05:00  media:reconcile-storage
weekly Sun 05:15  assets:verify-pack
weekly Sun 05:30  coc:rotate-keys
weekly Mon 06:00  coc:refresh-reference-data
monthly 1st 06:30 platform:rotate-ip-salt
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

## 5. Failure handling

| Failure | Response |
|---|---|
| Job exception | Retry per policy, then `failed_jobs` + Sentry + alert if the rate exceeds 20/h |
| Job timeout | Same as exception; media jobs additionally mark the media `failed` |
| Worker crash / OOM | Workers restart via the container policy; `--max-time` recycling bounds leaks; reserved jobs return to the queue after `retry_after` |
| Queue backlog | Alert at depth >500 for 10 minutes. Runbook: scale the relevant worker, then investigate |
| Poison job (fails every time) | After `tries`, it lands in `failed_jobs`; an admin page lists failures grouped by class for retry or deletion |
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

An admin "System Health" page shows all of the above plus the CoC key-pool status, so a moderator
can tell the difference between "the user is lying" and "sync has been broken since Tuesday".
