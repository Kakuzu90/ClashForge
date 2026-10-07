# 21 — Caching Strategy

## 1. Rules that make the Redis migration a config change

1. Only `Cache::`, `cache()`, `Cache::lock()`, `RateLimiter::` and `Queue::`/`dispatch()`.
   **No `Redis::` facade, no predis/phpredis calls, no Lua, no driver-specific commands, anywhere.**
2. **No cache tags.** The database store technically supports them, but tag flushing is a scan.
   Use structured key prefixes and explicit invalidation instead.
3. Every cached value must be reconstructible. The cache is never the source of truth, and a cold
   cache must never produce a wrong answer — only a slower one.
4. TTLs are always explicit. `Cache::forever()` is banned outside of reference data with a manual
   invalidation command.
5. Keys are built by a small set of key-builder helpers, never inline string concatenation — that
   is what makes invalidation auditable.
6. Tests run on the `array` store; a CI job runs the suite on the `database` store to catch
   serialisation assumptions.

## 2. Cache layers

| Layer | What | Where |
|---|---|---|
| **L0 — CDN** | Static assets, public media | Cloudflare, 1 year, immutable keys |
| **L1 — HTTP** | Anonymous full-page HTML for hot public pages | Cloudflare page rules + `Cache-Control`, 60s, bypassed for authenticated cookies |
| **L2 — Application** | Query results, computed values, rendered fragments | `Cache` facade (database → Redis later) |
| **L3 — Request memory** | Per-request memoisation (privacy settings, permissions) | In-process static/`once()`, or a `scoped` container binding (`PrivacyPolicyResolver`) |
| **L4 — Database** | Denormalised counters and precomputed scores | Columns, not a cache |

L4 is the most important one and the one people forget: `base_metrics.trending_score` and the
counter columns mean the hot feed query needs no cache at all to be fast. Cache is the second line
of defence, not the first.

## 3. Cache inventory

| Key pattern | Contents | TTL | Invalidated by |
|---|---|---|---|
| `coc:player:{TAG}` | Raw player payload | 5 min | Manual refresh, sync write |
| `coc:player:{TAG}:last`, `coc:clan:{TAG}:last` | Last good payload (stale fallback for interactive views) | 24 h | Next success |
| `coc:clan:{TAG}` | Clan payload | 15 min | Clan sync |
| `coc:leagues`, `coc:locations` | Reference data | 7 days | Weekly refresh command |
| `coc:404:player:{TAG}`, `coc:404:clan:{TAG}` | Negative lookup, per kind | 10 min | Next successful fetch of that tag |
| `coc:circuit` | Circuit-breaker state (reason, until) | open period + 1 day | State transitions |
| `coc:circuit:consecutive`, `coc:circuit:bucket:{n}:{ok\|fail}` | Breaker counters (run, 10 s buckets) | window + 1 bucket, renewed on write | Cleared when the breaker closes |
| `coc:circuit:probe` | Half-open probe slot | `circuit.probe_interval` | Probe outcome |
| `coc-rate:global:*`, `coc-rate:background:*`, `coc-rate:key:{id}` | Rate budgets (`RateLimiter`) | 1 s / 60 s | Automatic |
| `coc:key:{id}:unhealthy` | Refused key marker (reason, since) | 1 h (`coc.key_pool.unhealthy_ttl`) | A successful `coc:check-health` probe |
| `coc:key:cursor` | Round-robin counter | 24 h (`coc.key_pool.cursor_ttl`) | — (losing it restarts the rotation) |
| `feed:v{n}:{sort}:{th}:{category}:…:p{page}[:{position}]` | One feed page (`FeedPageData`), trending or new orders filtered by TH range and category only, pages 1–`bases.feed.cache_max_page` (5); tag, likes and video filters and deeper pages run live (P3-03) | 5 min (`bases.feed.cache_ttl_trending`), New 60 s (`cache_ttl_new`) | The `feed:version` bump (§4 mechanism 3) by `Bases\Services\CacheInvalidator::baseFeeds()`: base publish, sanction applied or lifted, a credited account released or transferred, a privacy change, a deletion request, every trending run; base hide/remove/visibility change from P3-06/P3-09 |
| `feed:version` | The feed key version | 30 days | Incremented by the invalidators above |
| `base:{ulid}:view` | Rendered detail view-model (not HTML) | 5 min | Base update, like, comment, moderation |
| `base:{ulid}:related` | Related base ids | 15 min | Trending recompute |
| `profile:{username}` | Public profile view-model (viewer-independent; `user_stats` values up to 5 min old). Holds no CoC account data: the profile's cards are read per request, since syncs change them often (P2-22). Key lowercased (usernames are case-insensitive); stored with the version in `profile:{username}:version`, so a reader that started before a change cannot serve its stale build | 5 min (`platform.profile.cache_ttl`) | Profile update, username change (old and new name), avatar set/remove/ready, privacy update, base publish, `user_stats` writes (P3-04) |
| `account:{ulid}:card` | PlayerCard DTO. Not used yet: the account page reads one row and one snapshot, and the card varies by viewer (`clanHidden`), so only a viewer-independent part could be cached (P2-04) | 5 min | Account sync, verification |
| `user:{id}:privacy` | Privacy settings row, as scalars. A missing row reads as `private` (fail closed) | 1 h (`platform.profile.privacy_cache_ttl`) | Settings update writes the new row through (readers only add on a miss) |
| `user:{id}:permissions` | Derived capability flags (can publish, can recruit) | 10 min | Role/status change, verification, sanction |
| `notif:unread:{id}` | Unread count (the shared `unreadCount`) | 60 s (`platform.notifications.unread_cache_ttl`) | Notification create, mark read, mark all read. The nightly prune does not clear it; the TTL catches up |
| `search:{signature}` | Result ids + facet counts (anonymous only) | 60 s | — (short TTL is the invalidation) |
| `tags:popular` | Top tags | 1 h | Nightly tag reconcile |
| `stats:homepage` | Site-wide counters for the landing page | 15 min | — |
| `sitemap:chunk:{n}` | Sitemap XML | 24 h | Nightly regeneration |
| `ratelimit:*` | Limiter counters | per limiter | Automatic. `/health` uses its own `file` store, so a database outage cannot break it |
| `platform:health:storage` | Last storage probe result | 15 min | Next `platform:check-health` |
| `platform:health:coc` | Last CoC key-pool probe result | 15 min | Next `platform:check-health` / `coc:check-health` |
| `platform:health:heartbeat` | Last scheduler tick | 7 days (expired → `unknown`) | Next `platform:heartbeat` |
| `media:signed-get:{sha1}` | Signed private media URL | signed TTL − 30 s | — (short TTL is the invalidation) |
| `assets:publish:{version}` | Publish lock (`Cache::lock`) | 30 min | Release at the end of the run |
| `lock:*` | `Cache::lock` atomic locks | per lock | Automatic |

**Never cached:** anything containing another user's private data, moderation queues, admin views,
unread notification contents (only the count), draft content, signed URLs beyond their own TTL,
anything on an authenticated write path.

## 4. Invalidation

Three mechanisms, in order of preference:

1. **Short TTLs** for anything where 60 seconds of staleness is invisible (feeds, counts, search).
   This is the default; it needs no code and cannot leak.
2. **Explicit deletion** on the write that changes the data, listed in a single
   `CacheInvalidator` class per module so that "what busts this key" has one answer.
   Example: `PublishBaseService` → `CacheInvalidator::baseFeeds($thLevel, $category)` +
   `CacheInvalidator::profile($username)`.
3. **Versioned key prefixes** for wide invalidations that would otherwise need tags:
   `feed:v{n}:...` where `{n}` comes from a cached integer. Bumping the version orphans the old keys
   (they expire on their own TTL) and is O(1). Used when the trending algorithm changes or a
   deploy changes a DTO shape, and for every feed invalidation (P3-03): a feed page depends on
   too many filter combinations to delete key by key.

**Rule:** a cached view-model that includes user-generated content must be invalidated by the
moderation action that hides it, with no TTL reliance. Hidden content appearing for five more
minutes is a moderation failure, not a caching trade-off.

## 5. What is deliberately not cached

- **The authenticated base feed, per user.** It is personalised (TH preference, bookmarks, likes)
  and the underlying query is already index-covered and sub-100 ms. Caching it per user would
  multiply the cache size by the user count for no gain. Its viewer-independent card list shares
  the anonymous feed keys (§3); only the viewer's own state is added per request (P3-03).
- **Counters.** They live in `base_metrics`/`user_stats` columns. A cached counter has two sources
  of truth and will drift.
- **Authorization decisions.** Policies run per request. Only the coarse capability flags
  (`user:{id}:permissions`) are cached, and any sanction or role change deletes that key
  immediately.

## 6. Database-store specifics

Running cache on Postgres has three failure modes worth naming:

| Issue | Mitigation |
|---|---|
| Table bloat from high write churn | Keep TTLs short but not tiny (60s minimum), avoid caching per-user objects, schedule an expired-row delete nightly, and let autovacuum do the rest. Monitor `pg_total_relation_size('cache')` |
| Lock contention on hot keys | `Cache::lock` with short leases and `block()` timeouts; never hold a cache lock across an HTTP call |
| Serialisation cost | Cache DTOs and arrays, never Eloquent models or collections of models |

Monitoring: cache table size, cache hit ratio (instrumented in a small cache decorator that counts
hits/misses per key prefix), and slow queries against the `cache` table.

## 7. Redis migration triggers

Restating [06 §4](06-tech-stack.md) as an operational checklist. Migrate when **any one** of:

| Signal | Threshold | Move |
|---|---|---|
| `cache` table size | > 500 MB, or bloat requiring manual vacuum | `CACHE_STORE=redis` |
| Cache write rate | > 200/s sustained | `CACHE_STORE=redis` |
| `jobs` table row count | > 50k sustained, or visible lock waits | `QUEUE_CONNECTION=redis` + Horizon |
| Job throughput | > 100/min sustained | same |
| `sessions` writes competing with reads | session queries in the top 5 by total time | `SESSION_DRIVER=redis` |
| Rate limiter writes | visible in slow-query logs | follows the cache store |
| Any real-time feature | broadcasting required | Redis + Reverb |
| App tier scaling | ≥2 app servers and latency matters | Redis for all three |

**Migration procedure** (should take under an hour):
1. Provision Redis (managed, or a container on the same host with persistence off for cache —
   cache loss must be survivable by design).
2. Deploy with `CACHE_STORE=redis` first, in isolation. Watch the error rate and the cache hit
   ratio for 24 hours.
3. Then `SESSION_DRIVER=redis` (users get logged out once — schedule it, announce it, or migrate
   sessions with a bridging deploy that reads both).
4. Then `QUEUE_CONNECTION=redis`: drain the database queue first (`queue:work --stop-when-empty` on
   the old connection), then flip, then start Horizon.
5. Keep the database tables and migrations in place for one release as a rollback path.
6. Run the full test suite against Redis in CI from this point on.

If any application code needs changing during this migration, rule 1 of this document was violated
and the fix belongs in the code, not in the migration.
