# 22 — Scaling Considerations

## 1. Growth stages

| Stage | MAU | Bases | Shape | Monthly infra |
|---|---|---|---|---|
| **0 — Launch** | < 1k | < 2k | 1 VPS (app+web+workers), managed Postgres small, R2, Cloudflare free | ~$40–60 |
| **1 — Traction** | 1k–10k | 2k–20k | Same, larger VPS (4 vCPU / 8 GB), Postgres 2 vCPU / 4 GB, separate media worker container | ~$100–150 |
| **2 — Growth** | 10k–50k | 20k–100k | App VPS + separate worker VPS, Postgres 4 vCPU / 8 GB + read replica, Redis, Meilisearch | ~$250–400 |
| **3 — Scale** | 50k–250k | 100k–500k | 2–3 app nodes behind a load balancer, dedicated worker pool, Postgres with replicas and partitioning, transcoding offloaded | ~$800–1500 |
| **4 — Beyond** | 250k+ | 500k+ | Extract media service, consider read-path caching layer, regional CDN tuning, possible database sharding by entity | — |

The architecture is designed so Stages 0–2 need **no code changes**, only configuration and
infrastructure. Stage 3 needs the changes listed in §4.

## 2. Bottlenecks in the order they will actually appear

### 1st — Media processing (Stage 1)
One ffmpeg transcode saturates a core for 30–90 seconds. Twenty video uploads in an hour is a
15-minute queue backlog on a single worker.
**Signals:** media queue depth > 50, p95 processing > 180s, sustained worker CPU > 80%.
**Response, in order:** (a) separate the media worker onto its own container with a CPU cap;
(b) move it to its own VPS; (c) offload to Cloudflare Stream or a transcoding service — the
`MediaProcessor` interface already exists for this.

### 2nd — Database queue contention (Stage 1→2)
The `jobs` table becomes a hot row-lock target. Symptoms: rising job wait time with idle CPU,
lock waits in `pg_stat_activity`.
**Response:** Redis + Horizon ([21 §7](21-caching-strategy.md)). This is the single highest-value
infrastructure upgrade on the path.

### 3rd — CoC API rate budget (Stage 2)
50k users × ~2 accounts each = 100k accounts. Even on the cold tier (72h), that is ~1,400 syncs an
hour, comfortably within budget — but the hot tier is what bites during peak hours.
**Response:** widen the tier intervals, add more API keys across more egress IPs, prioritise
accounts that are actually viewed (view-driven `hot` promotion is already in the design), and
consider not syncing accounts whose owners have not logged in for 60 days at all.

### 4th — Feed and search query cost (Stage 2)
`base_layouts` at 100k+ rows with faceted filtering.
**Response:** the partial indexes are already specified; then keyset pagination everywhere (already
required); then a read replica for feed and search queries; then Meilisearch ([17 §7](17-search-and-discovery.md)).

### 5th — Event table growth (Stage 2→3)
`base_view_events` at 10M+ rows and `coc_account_snapshots` at 5M+.
**Response:** they are append-only and time-ordered, so **declarative partitioning by month** with
automated partition creation and a drop-old-partitions job. `DROP PARTITION` is instant; a
`DELETE FROM ... WHERE viewed_at < ...` on 10M rows is an outage. Plan the partition migration
*before* the tables get big — it is far cheaper at 1M rows than at 20M.

### 6th — Session and cache table churn (Stage 2)
Already covered by the Redis move.

### 7th — Single app server (Stage 3)
**Response:** the app is stateless by requirement (NFR-SCALE-2), so this is: put a load balancer in
front, run 2–3 app containers, move sessions to Redis, ensure no local filesystem writes outside
the framework cache, and make deploys roll node by node.

## 3. Database scaling plan

| Stage | Action |
|---|---|
| 0–1 | Correct indexes, `EXPLAIN` on every list query, connection pooling via PgBouncer from day one (transaction mode, which is free and prevents the classic connection exhaustion at Stage 2) |
| 2 | Read replica; route feed, search, public profiles and admin reports to it. Laravel's read/write connections make this a config change — but **every read-after-write path must be audited** for replica lag (publish → redirect to the base page is the obvious one; use `sticky => true`) |
| 2 | Partition `base_view_events`, `base_copy_events`, `coc_account_snapshots`, `audit_logs` by month |
| 2 | Snapshot compaction: keep every snapshot for 90 days, then daily, then weekly after a year |
| 3 | Additional replicas; move analytics/reporting queries off the primary entirely |
| 3 | Consider moving `base_view_events` out of Postgres into an append-only analytics store if view tracking becomes the dominant write load |
| 4 | Only if genuinely needed: split the media and audit domains into their own database. Sharding user data is a last resort and would signal a product far beyond this plan |

**Connection limits:** the most common self-inflicted outage at Stage 2 is app containers ×
workers × pool size exceeding Postgres `max_connections`. PgBouncer from Stage 0 makes this a
non-event.

## 4. Code changes required at Stage 3 (and only then)

1. Read/write connection splitting with explicit `sticky` handling and a documented list of
   read-after-write paths.
2. Pull-based follower feeds (already specified as the >1000-follower path in
   [16 §7](16-notifications.md)) become the default.
3. Trending recomputation becomes incremental (only bases with recent activity) rather than a full
   pass — the design already splits these; Stage 3 drops the nightly full pass.
4. Media module extraction if it is still in-process: it already has no synchronous callers, so
   this is replacing job dispatch with an HTTP/queue boundary.
5. CDN-level caching of anonymous HTML for the top discovery pages, with a purge hook on publish
   and moderation.

## 5. What we will not do

| Not doing | Why |
|---|---|
| Microservices | No independent scaling pressure; the domains are chatty; the team is small |
| Kubernetes | Two to four containers do not need an orchestrator's operational surface |
| Multi-region active-active | The audience is global but latency-tolerant; the CDN handles the parts that matter. Multi-region Postgres is a full-time job |
| Sharding | Postgres on modern hardware handles this data volume comfortably for years |
| GraphQL | No client demanding it; it would add query-cost and authorization complexity |
| Event sourcing | Audit logs give us the forensics we need without the read-model machinery |
| A separate analytics warehouse | Until product questions cannot be answered by SQL on a replica |

## 6. Capacity reference points

Rough, but enough to know when something is wrong:

| Operation | Expected cost |
|---|---|
| Base feed page (24 cards, indexed, counters denormalised) | 3–6 queries, < 40 ms |
| Base detail page | 6–10 queries, < 60 ms |
| Profile page | 5–8 queries, < 50 ms |
| Livewire filter update | 2–4 queries, < 30 ms |
| Image processing (3 variants from a 5 MB source) | 2–5 s |
| Video transcode (60 s, 1080p → 720p) | 30–90 s |
| CoC account sync (cache miss) | 200–600 ms, 1 API call |
| Trending recompute (10k active bases) | 2–5 s |
| Nightly counter reconcile (100k bases) | 30–90 s |

A page issuing more than 25 queries fails the build (NFR-PERF-7). A page taking more than 400 ms at
p95 is a bug with an owner, not a capacity problem to be solved with a bigger server.

## 7. Cost control levers, in the order to pull them

1. **CDN everything static.** Already the design; verify the hit ratio is > 95%.
2. **R2 over S3.** Zero egress is the difference between a $20 and a $200 media bill.
3. **Sync less.** The tiered sync schedule is the biggest single lever on API and database load;
   widening cold-tier intervals costs nothing a user will notice.
4. **Prune aggressively.** View events, API request logs, read notifications, expired sessions and
   cache rows. Storage is cheap; bloated hot tables are not.
5. **Transcode less.** Lower the video cap, or make video a verified-users-only feature if abuse or
   cost gets out of hand.
6. **Defer Redis and Meilisearch until the triggers fire.** Each is ~$15–30/month plus operational
   attention; neither buys anything before its trigger.
7. **Right-size the database before the app server.** Postgres is almost always the first thing
   that needs more memory, and almost never needs more cores at this scale.
