---
id: P2-07
title: Wrap the CoC HTTP client in throttle, circuit-breaker and cache decorators, with a request log
phase: 2
status: done
depends_on: [P2-01]
---

# CoC client decorators: throttle, circuit breaker, cache, request log

## Spec refs
- Core: specs/09 §1 (decorator order `Cached(Throttled(Http))`), §4 (rate budgets, interactive share, 429), §5 (TTLs, negative cache, stale-while-error, no tags), §7 (failure table, circuit breaker, degradation contract), §11 (config)
- Plus: specs/21 §1 (driver-agnostic rules, key builders, explicit TTLs), §3 (`coc:*` keys); specs/20 §2 (`PruneOperationalTablesJob`), §3 (02:00 prune), §4 rule 8 (rate-budget aware jobs); specs/07 `coc_api_requests`; specs/03 NFR-AVAIL-2, NFR-OBS-3
- FR: FR-COC-14 groundwork (stale payload with its age); NFR-AVAIL-2
- Edge cases: specs/23 §5 (maintenance at peak: circuit opens, everything serves stale; quota exhausted by a bug: budget + breaker degrade instead of hammering)

## Scope
- **Migration + model + factory:** `coc_api_requests` exactly as specs/07 (one divergence: `status_code` nullable, since a timeout has no status). Internal `CocRequestLog` writes one row per outbound call (inside the HTTP client, so a 403 key swap logs two) and per cache hit (`was_cached`). A failed insert logs a warning and never fails the call.
- **Priority:** `CocPriority` enum (`interactive`, `background`) added to the `CocApiClient` methods and the three lookups, interactive by default; `PlayerLookup::find(..., fresh: true)` skips the cache read (manual refresh, P2-09).
- **Throttle decorator** (`ThrottledCocApiClient`, 09 §4): `RateLimiter` buckets: global 10/s and 500/min; background capped at 70% of both, so 30% stays for interactive calls; per key 5/s, checked in `CocKeyPool::next()`. Over budget → `Throttled` with `retryAfter`, no HTTP call, no sleep. A real 429 counts the same way.
- **Circuit breaker** (in the throttle decorator, state in `coc:circuit`): opens after `circuit.consecutive_failures` (10) or an error rate above 50% over 120 s with at least 20 samples, counted in 10 s cache buckets. Failures are 5xx, timeout, malformed and maintenance; not 404, 429 or 403. While open there are no calls (`Unavailable`). After `probe_interval` (60 s) a single half-open probe goes through (an atomic `Cache::add` on `coc:circuit:probe`): success closes the breaker, failure reopens it. Maintenance opens it for `Retry-After` when sent, else until the next successful probe (Open question 2). No in-request retries: every failed attempt counts toward the breaker, and background jobs retry through their queue backoff (Open question 1). Transitions are logged (`coc.circuit_opened` error, `coc.circuit_closed` info).
- **Cache decorator** (`CachedCocApiClient`, 09 §5, 21 §3): caches the raw payload (arrays, not objects), re-mapped on read. Player TTL 300 s for interactive calls. Background calls skip the read and write with a TTL of 1800 s. Clan TTL 900 s. 404s are negative-cached for 600 s under `coc:404:player:{TAG}` / `coc:404:clan:{TAG}`. Every success also writes `…:last` for 24 h. On `Unavailable` that entry is served with `stale: true` and its `fetchedAt` (new fields on `PlayerData` / `ClanData`). `verifyToken` is never cached and never served stale. Keys come from `CocCacheKeys`.
- **Public status:** `CocApiStatus` (`state()` → `CocApiStateData`: closed / open / half_open, reason `failures` | `maintenance`, `openUntil`; `isAvailable()`; `backgroundBudgetRemaining()` for the sync scheduler, P2-09).
- **Binding:** `Cached(Throttled(Http))` for the `http` driver; the fake stays bare (its failures are scripted).
- **Prune** (Open question 3): `platform:prune-operational-tables`, daily at 02:00 (`withoutOverlapping`, `onOneServer`), with `--dry-run` and a summary line: `coc_api_requests` older than `coc.request_log.retention_days` (7), `failed_jobs` older than `platform.prune.failed_jobs_days` (30), expired `cache` and `sessions` rows. `base_view_events` joins with P3-04.
- **Config** (`config/coc.php`): `rate{global_per_second, global_per_minute, per_key_per_second, interactive_share}`, `circuit{consecutive_failures, error_rate, window, min_samples, bucket_seconds, probe_interval}`, `cache{player_ttl, player_sync_ttl, clan_ttl, negative_ttl, stale_ttl}`, `request_log{retention_days}`; `config/platform.php`: `prune{failed_jobs_days}`.

## Out of scope
- Site-wide maintenance banner and the dashboard's API sync health panel → P2-10 (split here)
- Reference data and its 7-day cache, key rotation (P2-08); sync jobs, `sync_states`, the manual-refresh endpoint (P2-09); System Health page (P2-06)

## Acceptance criteria
- Functional: decorator order holds (a cache hit spends no budget); budgets, reservation, breaker transitions and TTLs follow config; NFR-AVAIL-2: an open breaker returns stale or unavailable, never an exception.
- Authorization: no new route or write path (specs/04 unchanged).
- Edge cases: specs/23 §5 rows above.
- Secrets: request log rows hold endpoint, tag, status, timing and error code only.
- States: no UI.

## Tests
- Unit: error-rate window maths; `CocCacheKeys`.
- Feature (time frozen): background yields at 70% while interactive still passes; per-key cap moves to the next key; 429 and local throttling; breaker closed → open (consecutive and rate), open → half-open → closed or reopened, maintenance; cache hit/miss/negative/stale with `fetchedAt`, background write TTL, `fresh`; a cache hit makes no HTTP call and logs `was_cached`; request log row per call including both rows of a key swap; prune.
- Security: no key or token in `coc_api_requests` or any cache value.
- Config: every limit read from config.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended).
1. **5xx retries:** 09 §7 says "retry ×3 with backoff, then circuit-breaker accounting". Recommended: no in-request retries. An interactive call fails fast to stale or unavailable, and background jobs retry through their queue backoff (60/300/900 s, specs/20 §1). Every failed attempt counts toward the breaker. Alternative: up to 3 jittered in-request retries for background calls only.
2. **Maintenance length:** 09 §7 assumes the 503 states when maintenance ends; the documented body carries only `reason` and `message`. Recommended: honour `Retry-After` when present; otherwise keep the breaker open and let the 60 s half-open probe close it when the API answers again.
3. **Prune scope:** `platform:prune-operational-tables` (specs/20 §2–3) was deferred from P0-07 to "its own task", which never got a row. Recommended: build it here for the tables that exist now (`coc_api_requests` > 7 d, `failed_jobs` > 30 d, expired `cache` and `sessions` rows), daily at 02:00, with `--dry-run`; `base_view_events` joins with P3-04. Alternative: prune `coc_api_requests` only and give the command its own row.

### Decisions and divergences (implement, 2026-10-02)
1. `coc_api_requests`: `status_code` nullable (a timeout has no response), `tag` varchar(16), `error_code` = the API's `reason` on a non-2xx or `timeout`. Rows come from the HTTP client (every request, including both requests of a key swap and the health probes as `locations`) and from the cache decorator (hits: 200, or 404 for a cached miss, `was_cached`, 0 ms). A malformed 200 is logged as 200 with no error code, since it is found only after mapping. synced → specs/07, specs/09 §4.
2. `CocPriority` and `fresh` are parameters of `CocApiClient::player()/clan()` and of the lookups. `verifyToken` is always interactive and never cached. synced → specs/09 §1.
3. Budgets: the background bucket is `floor(global × (1 − interactive_share))` per second and per minute (7/s, 350/min). The per-key cap is enforced in `CocKeyPool::next()`. When every healthy key is at its cap, the call fails as `throttled` with the wait. Over budget never sleeps and never calls. The second request of a 403 key swap spends the per-key budget but not the global one (review). synced → specs/09 §4, specs/21 §3.
4. Breaker: there is no scheduled prober. Once `openUntil` passes, the first real call to win the `coc:circuit:probe` lock is the probe. A refused call while open fails as the new `CocFailureReason::CircuitOpen`, or `Maintenance`, with `retryAfter` = seconds to the probe. A 404 counts as a success (the API answered). The consecutive-failure counter and the 10 s buckets live in the cache. Opening and closing are logged once per transition. synced → specs/09 §7, specs/21 §3.
5. Cache: entries are `{payload, fetched_at}` arrays, mapped again on read; an entry that no longer maps is dropped and fetched again. Background and `fresh` calls skip both reads (entry and negative cache) but write. The negative cache is per kind: `coc:404:player:{TAG}` and `coc:404:clan:{TAG}` instead of `coc:404:{TAG}`, since a player and a clan may share a tag. Clans get a `…:last` stale entry too. A stale answer is a `found` result with `stale: true` and the original `fetchedAt` (new fields on `PlayerData` / `ClanData`). Cache keys use the tag without `#`. Only interactive, non-`fresh` calls get the stale answer (review fix). synced → specs/09 §5, specs/21 §3.
6. `CocApiStatus`: `state()`, `isAvailable()` (false only while open; half-open counts as available), `backgroundBudgetRemaining()`. synced → specs/09 §7, specs/05 §2.
7. The decorators wrap only the `http` driver; the fake stays bare. synced → specs/09 §1.
8. Prune runs as a command inline (like the other `platform:*` jobs), not a queued `PruneOperationalTablesJob`. The CoC part is the public `CocRequestLogRetention` service; the framework tables are `App\Support\Maintenance\OperationalTablePruner`. Sessions go when idle past `session.lifetime` or older than `platform.auth.absolute_session_days`; `cache_locks` are pruned with `cache`. synced → specs/20 §2, specs/05 §2.
9. The new enums appear in `generated.d.ts`. No spec change.
10. Test note: a second `Http::fake()` does not replace the first stub (the first match wins), so tests that change answers use `Http::fakeSequence`. No spec change.

### Review fixes (verify, 2026-10-02)
- Spec (medium): closing the breaker kept the old 10 s buckets, so one error after recovery could reopen it; closing now clears the window. Tested.
- Spec (medium): background and `fresh` calls were served the stale entry; they now get the failure (sync must back off, manual refresh must say the API is unavailable). Tested for both.
- Spec (low): the consecutive-failure counter kept its first TTL and lost slow runs; it is rewritten with a fresh TTL on each failure. Tested at one failure per 20 s.
- Spec (low): a probe that ended without a verdict (throttled, 403, 429) held the probe slot for 60 s; it is handed back at once. Tested.
- Spec (low): late answers from calls admitted before the breaker opened could close or extend it; only the probe changes an open breaker now. Tested.
- Spec + security (low): an unbounded maintenance `Retry-After` could hold the breaker open for days; capped at `coc.circuit.max_open_seconds` (3600). Tested.
- Spec (low): unit test for the error-rate maths (`CircuitBreaker::exceeds`) and a test of the `bucket_seconds` / `window` knobs added; Notes and specs describe `Cache::add` (not a lock), the `coc-rate:*` keys and the key-swap budget gap.
- scripts/check.sh: all green (sqlite + postgres). antislop audit-026: no findings.

