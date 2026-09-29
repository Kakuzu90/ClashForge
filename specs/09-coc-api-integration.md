# 09 — Clash of Clans API Integration

## 1. Principle

The application never talks to `api.clashofclans.com` directly. It talks to a `CocApiClient`
interface. Everything downstream — services, jobs, Livewire components, tests — depends on that
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
a cache hit costs no rate-limit budget.

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
| Storage | `COC_API_TOKENS` env as a comma-separated list; loaded into a `CocKeyPool` |
| Selection | Round-robin across healthy keys, so per-key limits are spread |
| Health | A key returning 403 with `accessDenied.invalidIp` is marked unhealthy and removed from rotation; an alert fires |
| Rotation | A scheduled job (weekly, plus on-demand) detects the current egress IP, and if it changed, calls the developer portal API to create a key for the new IP and revoke the stale one |
| Startup check | A console command verifies at least one healthy key before the app is considered ready; the health endpoint reports key-pool status |
| Never | Keys are never exposed to the browser, never logged, never placed in a queued job payload |

**Fallback if automated rotation is not possible** (portal credentials unavailable): a documented
manual runbook plus an alert when all keys go unhealthy. The application must degrade to snapshots,
not to errors.

## 4. Rate limiting

The official API does not publish precise limits; empirically it is on the order of tens of
requests per second per key, with throttling responses under sustained load. We therefore
**self-limit well below any observed ceiling** and treat 429 as a normal condition, not an error.

| Control | Value | Mechanism |
|---|---|---|
| Global budget | 10 req/s, 500 req/min across all keys | `RateLimiter` via `Cache` (atomic increment + lock) |
| Per-key budget | 5 req/s | same, keyed by key id |
| Interactive priority | User-triggered lookups reserve 30% of the budget | two limiter buckets: `coc-interactive`, `coc-background` |
| Background yield | Sync jobs check the interactive bucket and back off when it is under pressure | `SyncThrottle` guard |
| 429 handling | Honour `Retry-After` when present, else exponential backoff with jitter, max 5 attempts | job `backoff()` |
| Request log | Every call recorded in `coc_api_requests` (endpoint, status, duration, cached) | pruned at 7 days |

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
| Negative cache: 404 `notFound` | 10 min | `coc:404:{TAG}` | Stops retry storms on typo'd tags |
| Circuit-breaker state | — | `coc:circuit` | Shared across workers |

All caching uses the `Cache` facade with tags avoided (the database store does not support tag
flushing efficiently); invalidation is by explicit key deletion on manual refresh.

**Stale-while-error:** every successful response is also written to a long-lived
`coc:player:{TAG}:last` entry (24 h). When the API fails, the client returns that payload marked
`stale: true`, and the UI shows a "data from X ago" label. The database snapshot is the deeper
fallback below that.

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
trusted data and not worth the budget.

### Scheduler shape

- `coc:sync-accounts` runs every 5 minutes, selects up to N due rows ordered by `next_due_at`,
  and dispatches one job per account onto the `sync` queue. N is derived from the remaining
  background rate budget, so the scheduler self-throttles.
- Each `SyncCocAccountJob` is `ShouldBeUnique` on the account id (60 s) and uses
  `WithoutOverlapping`, so a slow run never doubles up.
- On success: update `coc_accounts`, write a `coc_account_snapshots` row **only if a tracked value
  changed**, reset `api_sync_failures`, compute the next tier and `next_due_at`.
- On `notFound` (404): increment failures; after 3 consecutive, set the account to a `stale` display
  state and notify the owner ("we can't find this tag any more — it may have been renamed or
  deleted"). Never auto-unverify: tag lookups fail transiently.
- On 5xx / timeout: exponential backoff, do not count toward the "not found" counter.
- Clan sync (`coc:sync-clans`) runs hourly for clans with `tracked_reason` set, same pattern.

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
| Maintenance | 503 `inMaintenance` | **Open the circuit for the stated duration**; the API returns a maintenance end time — honour it; site-wide banner |
| Server error | 500/502/504 | Retry ×3 with backoff, then circuit-breaker accounting |
| Network timeout | — | 5 s connect, 10 s total; counts as a failure |
| Malformed payload | 200 but unexpected shape | Log with the raw body, treat as failure, do not partially write |

### Circuit breaker
- Opens after 10 consecutive failures or a >50% error rate over 2 minutes (min 20 samples).
- While open: no outbound calls; everything serves from cache/snapshots; a site banner appears; a
  half-open probe runs every 60 s.
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
| `PlayerData` | tag, name, townHallLevel, expLevel, trophies, bestTrophies, warStars, attackWins, defenseWins, donations, league, clan (tag, name, role, badge), labels, heroes[], troops[], spells[], heroEquipment[], achievements[] |
| `ClanData` | tag, name, description, badges, level, points, memberCount, warFrequency, warLeague, capitalHallLevel, requiredTownHall, requiredTrophies, type, location, members[] |
| `TokenVerificationResult` | tag, status (`ok`/`invalid`), verifiedAt |
| `UnitData` | name, level, maxLevel, village, superTroopIsActive |

**Asset URLs in responses:** `clan.badgeUrls` is stored verbatim as a URL and rendered unmodified —
clan badges stay referenced, never mirrored, because there is one per clan and they change.
`league.iconUrls` is stored too, but leagues are a finite set, so the resolver prefers our
self-hosted copy in the `game/` pack and falls back to the API URL when a league id is missing from
the manifest. Either way nothing is downloaded into the media pipeline or re-encoded — that would
be a modification the fan-content policy does not permit. See [18 §2.3](18-design-system.md) and
[10 §11](10-media-storage.md).

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
5. Attempts are rate-limited (5/hour per user) and every attempt is written to
   `coc_account_claims`, successful or not.

Tokens are **never stored** — not in the database, not in logs, not in job payloads. They exist only
inside the request that verifies them. The claim record stores the outcome, not the token.

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
| Never | The test suite makes no real network calls. CI runs with no outbound access to the API |

## 11. Configuration surface (`config/coc.php`)

```
base_url, tokens[], timeouts{connect,total}, cache{player_ttl,clan_ttl,static_ttl,negative_ttl,stale_ttl},
rate{global_per_second,per_key_per_second,interactive_share}, circuit{threshold,window,probe_interval},
sync{tiers{hot,warm,cold,frozen}, batch_size, queue}, key_rotation{enabled, portal_email, portal_password}
```

Every value is environment-overridable. No magic numbers anywhere else in the codebase.
