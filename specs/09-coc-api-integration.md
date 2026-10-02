# 09 — Clash of Clans API Integration

## 1. Principle

The application never talks to `api.clashofclans.com` directly. It talks to a `CocApiClient`
interface. Everything downstream — services, jobs, controllers, tests — depends on that
interface and on our own DTOs, never on the API's JSON shape.

```
Domain services ─▶ PlayerLookup / ClanLookup / TokenVerifier   (use-case services, our DTOs)
                          │
                          ▼
                    CocApiClient (interface)
                   ┌──────┴────────┬──────────────┐
         HttpCocApiClient   CachedCocApiClient  FakeCocApiClient
                   │        (decorator)          (tests, local dev)
                   ▼
          ThrottledCocApiClient (decorator: rate limit + circuit breaker + request log)
                   ▼
          Laravel HTTP client ──▶ api.clashofclans.com/v1
```

Decorator order at the container binding: `Cached( Throttled( Http ) )`. Caching sits outermost so
a cache hit costs no rate-limit budget. Only the `http` driver is wrapped; the fake stays bare. Every
player and clan call carries a `CocPriority` (`interactive` by default, `background` for sync) and a
`fresh` flag (manual refresh) that the decorators read.

## 2. Endpoints used

| Use case | Endpoint | Method | Notes |
|---|---|---|---|
| Fetch player | `/players/{tag}` | GET | Core. Tag URL-encoded (`%23ABC`). |
| **Verify ownership** | `/players/{tag}/verifytoken` | POST | Body `{"token": "..."}`. Returns `status: ok\|invalid`. The entire claiming system rests on this. |
| Fetch clan | `/clans/{tag}` | GET | Phase 4. Includes member list and roles. |
| Search clans | `/clans?name=&locationId=&minMembers=` | GET | Phase 4, for clan lookup UX. |
| Leagues / locations | `/leagues`, `/locations` | GET | Static reference data, cached 7 days, seeded. |
| War league group | `/clans/{tag}/currentwar/leaguegroup` | GET | Phase 7 only, if CWL features ship. |

Endpoints deliberately **not** used: current war, war log, capital raid seasons, ranking lists.
Each adds sync cost and staleness surface for features the MVP does not have.

## 3. Credentials and the IP-binding problem

The official API issues keys **bound to specific IP addresses**. This is the single most
operationally annoying constraint of the integration.

**Requirements this places on infrastructure:**
1. All outbound API traffic must leave from a **stable, known set of IPs**. Practically: a VPS with
   a static IP, or a dedicated NAT gateway. Serverless/auto-scaling egress is incompatible without
   a proxy.
2. Keys are created through the developer portal API (`developer.clashofclans.com/api/apikey/*`)
   using a portal email/password. This enables **automated key rotation**.
3. Support **multiple keys** (one per egress IP, plus spares), stored as a key pool.

**Key management design:**

| Element | Decision |
|---|---|
| Storage | `COC_API_TOKENS` env as a comma-separated list; loaded into a `CocKeyPool`. A key is identified by the first 8 hex characters of its token's sha256 (`coc.key_pool.id_length`), the only form that is ever logged or shown |
| Selection | Round-robin across healthy keys, so per-key limits are spread (a `Cache` counter shared by all workers) |
| Health | A key returning 403 (`accessDenied` or `accessDenied.invalidIp`) is marked unhealthy in the cache for at most `coc.key_pool.unhealthy_ttl` (1 h) and removed from rotation; the call is retried once with another key. `coc.key_unhealthy` is logged once per outage (error; critical for `invalidIp`), and `coc.keys_all_unhealthy` (critical) when the last key goes |
| Rotation | A scheduled job (weekly, plus on-demand) detects the current egress IP, and if it changed, calls the developer portal API to create a key for the new IP and revoke the stale one |
| Startup check | `coc:check-health` (also run by `platform:check-health` every 5 min) sends `GET /locations?limit=1` once per key: a success clears the key's marker, a 403 sets it. Ok when every key works, degraded when some do or the API did not answer, down when none works or none is configured; it fails only on down. `/health` reports the result as `coc`, never a required check (NFR-AVAIL-2) |
| Never | Keys are never exposed to the browser, never logged, never placed in a queued job payload (`CocApiKey` hides the token from dumps and refuses serialisation) |

**Fallback if automated rotation is not possible** (portal credentials unavailable): a documented
manual runbook plus an alert when all keys go unhealthy. The application must degrade to snapshots,
not to errors.

## 4. Rate limiting

The official API does not publish precise limits; empirically it is on the order of tens of
requests per second per key, with throttling responses under sustained load. We therefore
**self-limit well below any observed ceiling** and treat 429 as a normal condition, not an error.

| Control | Value | Mechanism |
|---|---|---|
| Global budget | 10 req/s, 500 req/min across all keys, taken once per logical call | `RateLimiter` via `Cache`: `coc-rate:global:{second,minute}` |
| Per-key budget | 5 req/s | `coc-rate:key:{id}`, checked when `CocKeyPool` picks a key; the second request of a 403 key swap spends it but not the global budget, so a key outage can exceed the global numbers by one request per call |
| Interactive priority | User-triggered lookups reserve 30% of the budget | background calls also count against `coc-rate:background:{second,minute}`, capped at `floor(global × 0.7)` (7/s, 350/min) |
| Background yield | The sync scheduler sizes its batch from the background budget left this minute | `CocApiStatus::backgroundBudgetRemaining()` |
| Over budget | No call and no sleep: the call fails as `throttled` with the wait as `retryAfter` | background jobs `release()` |
| 429 handling | Same `throttled` failure with the API's `Retry-After`; never counted by the circuit breaker | job `backoff()` |
| Request log | One `coc_api_requests` row per outbound request (both requests of a key swap, health probes as `locations`) and per cache hit (`was_cached`; 404 for a cached miss). `error_code` is the API's `reason` or `timeout`; a failed insert is logged and never fails the call | pruned at 7 days |

Background sync jobs use `Job::release()` rather than blocking sleeps, so a throttled worker frees
the process for other queues.

## 5. Caching

| Data | TTL | Key | Rationale |
|---|---|---|---|
| Player (`/players/{tag}`) | 5 min | `coc:player:{TAG}` | Short — users refresh after attacks |
| Player, background sync | 30 min effective | same key, written by sync | Sync writes the cache so an interactive view right after a sync is free |
| Clan (`/clans/{tag}`) | 15 min | `coc:clan:{TAG}` | Members change slowly |
| Clan search results | 5 min | `coc:clansearch:{hash}` | Bounded by query hash |
| Leagues / locations | 7 days | `coc:leagues`, `coc:locations` | Static |
| Negative cache: 404 `notFound` | 10 min | `coc:404:player:{TAG}`, `coc:404:clan:{TAG}` | Stops retry storms on typo'd tags; per kind, since a player and a clan may share a tag |
| Circuit-breaker state | — | `coc:circuit` | Shared across workers |

All caching uses the `Cache` facade with tags avoided (the database store does not support tag
flushing efficiently). Entries are plain arrays (`{payload, fetched_at}`, the tag without `#` in the
key), mapped again on read; an entry that no longer maps is dropped and fetched again. Interactive
calls read the entry and the negative cache; background and `fresh` (manual refresh) calls skip both
reads but write, which is how a manual refresh invalidates. Token verification is never cached.

**Stale-while-error:** every successful response is also written to a long-lived
`coc:player:{TAG}:last` / `coc:clan:{TAG}:last` entry (24 h). When the API fails, an interactive,
non-`fresh` call gets that payload as a `found` result with `stale: true` and its original
`fetchedAt`, and the UI shows a "data from X ago" label. Background and `fresh` calls get the failure
instead, so sync backs off and a manual refresh says the API is unavailable. The database snapshot
is the deeper fallback below that.

## 6. Synchronisation strategy

### Tiered freshness

Syncing every account every hour is wasteful; most accounts are inactive. `sync_states` assigns a
tier, and the scheduler picks due rows.

| Tier | Criteria | Interval |
|---|---|---|
| `hot` | Owner active in the last 7 days, or account viewed in the last 24 h, or featured | 2 hours |
| `warm` | Verified, owner active in the last 30 days | 12 hours |
| `cold` | Everything else verified | 72 hours |
| `frozen` | 5+ consecutive failures, or account not found | 7 days, then stop and flag |

Unverified accounts are **not** background-synced at all — only on manual refresh. They are not
trusted data and not worth the budget. Disputed accounts keep syncing (the holder still holds the
tag, [13 §2](13-claiming-workflow.md)); suspended and released ones stop.

Since P2-09 (owner decisions, 2026-10-02): "owner active" is the later of the last password
sign-in and the newest session's last request (`UserActivityReader`); "viewed in the last 24 h"
joins with P2-20, a throttled `last_viewed_at` write on the account page (P2-04 kept the page read-only). Intervals are `coc.sync.tiers`. Frozen
retries every 7 days and, after `coc.sync.frozen_max_attempts` (4) failed retries, stops
(`next_due_at` null); System Health counts it as "stopped syncing" and a later success revives
it. `sync_states` belongs to CocIntegration behind `SyncSchedule`; the account module picks the
tier.

### Scheduler shape

- `coc:sync-accounts` runs every 5 minutes (from :02, so not on the hour), claims up to N due rows
  (each moves `coc.sync.claim_seconds` ahead, so a job waiting in a backlog is never queued twice) ordered by `next_due_at`,
  and dispatches one job per account onto the `sync` queue. N is derived from the remaining
  background rate budget, so the scheduler self-throttles.
- Each `SyncCocAccountJob` is `ShouldBeUnique` on the account id (60 s) and uses
  `WithoutOverlapping`, so a slow run never doubles up.
- On success: update `coc_accounts`, write a `coc_account_snapshots` row **only if a tracked value
  changed** (progression only: TH / BH level, XP level, best trophies, war stars, unit levels,
  clan tag, league id; [07](07-database-schema.md)), link `clan_id` to the clan's row (P2-13), reset `api_sync_failures`, compute the next
  tier and `next_due_at`. Verification writes the first snapshot and starts the schedule (a
  listener on `CocAccountVerified`, which an admin dispute transfer dispatches too).
- On `notFound` (404): increment failures; after 3 consecutive, set the account to a `stale` display
  state and notify the owner ("we can't find this tag any more — it may have been renamed or
  deleted"). Never auto-unverify: tag lookups fail transiently.
- On 5xx / timeout / malformed: exponential backoff (`coc.sync.backoff_base` doubled per failure,
  capped at the tier interval), do not count toward the "not found" counter. Trouble on our side
  (circuit open, throttled, no healthy key) postpones the account by the API's wait without
  counting a failure, and `coc:sync-accounts` queues nothing while the circuit is open.
- Clan sync (`coc:sync-clans`) runs hourly for clans with `tracked_reason` set, same pattern.

### Display
The account page (P2-04) renders from stored data only. It shows an account as stale after
`coc.sync.not_found_stale` 404s in a row, when `api_synced_at` is older than
`coc.display.stale_hours` (168), or when it has never synced. Stat deltas compare with the newest
snapshot at least `coc.display.delta_days` (7) old.

### Manual refresh
Rate-limited to 1 per 10 minutes per account (FR-COC-9), executed **synchronously** with a 3-second
timeout so the user sees the result; on timeout it falls back to dispatching a job and showing
"refreshing in the background".

## 7. Failure handling

| Failure | HTTP | Behaviour |
|---|---|---|
| Invalid/expired key | 403 `accessDenied` | Mark key unhealthy, rotate, alert, retry once with another key |
| IP not whitelisted | 403 `accessDenied.invalidIp` | Same as above + urgent alert (this breaks everything) |
| Tag not found | 404 `notFound` | Negative-cache 10 min; user-facing "no player with that tag" |
| Throttled | 429 | Backoff with jitter; background jobs release to the queue |
| Maintenance | 503 `inMaintenance` | **Open the circuit at once**, for `Retry-After` when sent (capped at `circuit.max_open_seconds`, 1 h), else until the next successful probe; site-wide banner |
| Server error | 500/502/504 | No in-request retry: the call fails at once and counts toward the circuit breaker; background jobs retry through their queue backoff (60/300/900 s, [20 §1](20-jobs-and-scheduling.md)) |
| Network timeout | — | 5 s connect, 10 s total; counts as a failure |
| Malformed payload | 200 but unexpected shape | Log with the raw body, treat as failure, do not partially write |

### Circuit breaker
- Opens after 10 consecutive failures or a >50% error rate over 2 minutes (min 20 samples, counted
  in 10 s cache buckets). Failures are 5xx, timeout, malformed and maintenance; a 404 is an answer,
  and 429 and 403 belong to the budget and the key pool, so none of them count.
- While open: no outbound calls; calls fail as `circuit_open` (or `maintenance`) with the wait;
  everything serves from cache/snapshots; a site banner appears. Once the open period (60 s) ends,
  the first real call to win `coc:circuit:probe` (an atomic `Cache::add`) is the half-open probe:
  success closes the breaker and clears the window, a counted failure reopens it for 60 s, and any
  other outcome hands the probe to the next call. Only the probe changes an open breaker; late
  answers from calls admitted earlier are ignored.
- `CocApiStatus` exposes the state (closed / open / half_open, reason, `openUntil`) to other modules;
  half-open counts as available.
- The site banner (P2-10) is the shared Inertia prop `cocApi`: `{ reason }` while open, else null,
  for every visitor, one cache read per request and never an API call. It names the reason only:
  `openUntil` is the next probe unless Supercell announced an end, and the two look the same, so
  the banner shows no time; admins see it on the dashboard's API panel. While it is set, token
  verification is paused in the UI (the server refuses it anyway, as `unavailable`); lookup and
  attach stay on.
- During maintenance windows the breaker is opened explicitly for the announced duration —
  Supercell's maintenance is frequent and scheduled; the platform must be boring about it.

### Degradation contract (the rule that must never break)
> **A CoC API outage never produces a 5xx, never blocks login, never blocks base browsing, and never
> changes an account's verification status.** It disables: attaching new accounts, ownership
> verification and manual refresh — each with an explicit "the game API is unavailable" message and
> a retry affordance.

## 8. Data mapping

Raw API JSON is mapped into readonly DTOs at the client boundary. Nothing downstream sees an array
key from Supercell.

| DTO | Contents |
|---|---|
| `PlayerData` | tag, name, townHallLevel, expLevel, trophies, bestTrophies, warStars, attackWins, defenseWins, donations, donationsReceived, builderHallLevel, builderBaseTrophies, league, clan (tag, name, role, badge), labels, heroes[], troops[], spells[], heroEquipment[], achievements[] |
| `ClanData` | tag, name, description, badges, level, points, memberCount, warFrequency, warLeague, capitalHallLevel, requiredTownHall, requiredTrophies, type, location, members[] |
| `TokenVerificationResult` | tag, status (`ok`/`invalid` from the API; `not_found`/`unavailable` from us), verifiedAt (only on `ok`) |
| `UnitData` | name, level, maxLevel, village, superTroopIsActive |

Only `tag` and `name` are required. A field the API stops sending maps to null (or an empty list);
a field present with the wrong type refuses the whole response as malformed. API names that differ
from ours: the player's top-level `role` is `clan.role`; a clan's `members` is `memberCount` and
`memberList` is `members`; `requiredTownhallLevel` is `requiredTownHall`;
`clanCapital.capitalHallLevel` is `capitalHallLevel`.

**Use-case results.** `PlayerLookup`, `ClanLookup` and `TokenVerifier` never throw for an API
problem: they return `PlayerLookupResult` / `ClanLookupResult` (`found` / `not_found` /
`unavailable`) or a `TokenVerificationResult`, with a `CocFailureReason` (`throttled`,
`maintenance`, `server_error`, `timeout`, `no_healthy_key`, `malformed`) and `retryAfter` when
unavailable. The client's exceptions stay inside the module.

**Asset URLs in responses:** `clan.badgeUrls` is stored verbatim as a URL and rendered unmodified —
clan badges stay referenced, never mirrored, because there is one per clan and they change.
`league.iconUrls` is stored too, but leagues are a finite set, so the resolver prefers our
self-hosted copy in the `game/` pack and falls back to the API URL when a league id is missing from
the manifest. Either way nothing is downloaded into the media pipeline or re-encoded — that would
be a modification the fan-content policy does not permit. See [18 §2.3](18-design-system.md) and
[10 §11](10-media-storage.md). A player's clan block (`PlayerClanData`) is also the source of the clan stub: `ClanDirectory::ensure` stores its tag, name, level and badges in `clans` and returns the id for `coc_accounts.clan_id` (P2-13).

**Game-update resilience:** new troops, heroes, equipment and TH levels appear without warning.
Rules: (1) unit lists are stored as `jsonb`, never as columns; (2) unknown unit names are stored
verbatim and rendered with a generic icon rather than dropped; (3) `raw_payload` keeps the last
full response so new fields can be backfilled without a re-sync; (4) TH level has no hardcoded
maximum in validation beyond a sanity `CHECK (th_level BETWEEN 1 AND 30)`.

## 9. Player verification (the core flow)

The player obtains an API token in-game: **Settings → More Settings → API Token**. It is
short-lived and single-use-ish, so the flow must be immediate.

1. User enters a tag → we normalise it and call `/players/{tag}` to confirm it exists and to show
   "Is this you? *IGN*, TH15, 4,200 trophies" — this catches typos before token entry.
2. User enters the in-game token.
3. `TokenVerifier` calls `POST /players/{tag}/verifytoken`.
4. `status: ok` → verification succeeds; `status: invalid` → a precise error explaining that tokens
   expire in a few minutes and must be re-copied.
5. Attempts are rate-limited (`coc-verify`, 5/hour per user) and every attempt is written to
   `coc_account_claims`, successful or not.

The form field is `api_token`. It is in the exception handler's `dontFlash`, so a failed
validation never returns it as old input; the pages clear it after every submit; it is never a
prop or a flash value; and Sentry drops request bodies and frame variables (P2-11).

Tokens are **never stored** — not in the database, not in logs, not in job payloads. They exist only
inside the request that verifies them. The claim record stores the outcome, not the token. The
verifytoken response echoes the token: only its `status` is read, and a malformed verifytoken body
is never logged.

Full state machine, conflict and dispute handling: [13-claiming-workflow.md](13-claiming-workflow.md).

## 10. Testing strategy

| Layer | Approach |
|---|---|
| Unit | `FakeCocApiClient` returning scripted DTOs, including failure modes |
| Contract | Recorded real responses as fixtures; a test asserts the mapper still produces valid DTOs. Fixtures are refreshed manually after game updates |
| Integration | `Http::fake()` with sequences covering 200 / 403 / 404 / 429 / 503 / timeout / malformed |
| Rate limiter | Time-frozen tests asserting the budget is respected and background yields to interactive |
| Circuit breaker | Tests asserting open/half-open/closed transitions and that pages still render while open |
| Scheduler | Tests asserting tier assignment, `next_due_at` computation and snapshot-only-on-change |
| Never | The test suite makes no real network calls (`Http::preventStrayRequests()` in every test). CI runs with no outbound access to the API |
| Fixtures | `tests/Fixtures/coc/`: synthetic until a developer key exists (each file marked `"_fixture": "synthetic"`), then replaced by recorded responses under the same names; the contract test maps every file |

## 11. Configuration surface (`config/coc.php`)

```
driver (fake|http), base_url, tokens[], timeouts{connect,total}, key_pool{unhealthy_ttl,cursor_ttl,id_length},
log{malformed_body_bytes}, fake{fixtures_path,valid_token}, request_log{retention_days}, health{window_hours},
cache{player_ttl,player_sync_ttl,clan_ttl,static_ttl,negative_ttl,stale_ttl},
rate{global_per_second,global_per_minute,per_key_per_second,interactive_share},
circuit{consecutive_failures,error_rate,window,min_samples,bucket_seconds,probe_interval,max_open_seconds},
sync{tiers{hot,warm,cold,frozen}, hot_active_days, warm_active_days, batch_size, queue, claim_seconds, backoff_base, not_found_stale, frozen_after, frozen_max_attempts, success_window_minutes, success_alert}, display{stale_hours, delta_days}, key_rotation{enabled, portal_email, portal_password}
```

Every value is environment-overridable. No magic numbers anywhere else in the codebase.
`driver` defaults to `fake` (the fixture client); production must run `http`: resolving the fake
there throws, and `coc:check-health` reports it as down.
