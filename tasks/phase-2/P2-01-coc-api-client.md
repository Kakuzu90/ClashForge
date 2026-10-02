---
id: P2-01
title: Build the CoC API client behind an interface, with DTO mappers, a key pool and a fake
phase: 2
status: review
depends_on: [P0-02]
---

# Build the CoC API client (interface, HTTP client, DTOs, key pool, fake)

## Spec refs
- Core: specs/09 §1 (interface, use-case services), §2 (endpoints), §3 (key pool, health, never-logged keys), §7 (failure table, degradation contract), §8 (DTOs, game-update resilience), §9 (token never stored), §10 (testing), §11 (`config/coc.php`)
- Plus: specs/05 §2 (CocIntegration public surface, edge module), §6 (local binds to the fixture fake); specs/19 §2 (edge module rule), §6 (`tests/Contract`, `tests/Fixtures`), §7 (`coc:check-health`); specs/20 §2 (`platform:check-health` gains the key pool); specs/03 NFR-MAINT-5, NFR-AVAIL-2; specs/11 §2 (secrets never logged), §3 (alert: all keys unhealthy; CoC tokens never logged)
- FR: FR-COC-2 (tag normalisation before any API call); groundwork for FR-COC-3/5 (fetch + verify, wired in P2-02)
- Edge cases: specs/23 §2 (tag valid here but 404 upstream: trust the API, log the mismatch); §5 (malformed 200, new field, removed field, TH18, egress IP change detected within 5 min)

## Scope
- **Value objects:** `PlayerTag` and `ClanTag` on `StringValueObject` (FR-COC-2: trim, uppercase, leading `#`, `O`→`0`, charset `[0289PYLQGRJCUV]`, 3–12 after `#`; `urlEncoded()` → `%23…`) in `App\Domain\CocIntegration\Data\`, with a `CocTagFieldRules` facade for Form Requests (Open question 2).
- **DTOs** (`CocIntegration/Data`, readonly, 09 §8): `PlayerData` (+ `PlayerClanData`, `LeagueData`, `UnitData`, achievements, labels, `rawPayload`), `ClanData` (+ members), `TokenVerificationResult`. Mappers are internal: unknown units kept verbatim, missing fields → null, no TH maximum; a payload without `tag`/`name` or of the wrong type is malformed.
- **Client:** `Contracts/CocApiClient` (`player`, `clan`, `verifyToken` with the token `#[\SensitiveParameter]`). `HttpCocApiClient` on Laravel `Http` (connect 5 s, total 10 s), mapping 09 §7 into internal exceptions: 403 `accessDenied`/`accessDenied.invalidIp` → mark the key unhealthy, alert, retry once with another key; 404, 429 (+ `Retry-After`), 503 `inMaintenance`, 5xx, timeout, malformed (log the raw body, capped). No retry loops or caching here (P2-07).
- **Key pool:** `CocKeyPool` reads `COC_API_TOKENS`; key id = short sha256 prefix (the only identifier ever logged); round-robin over healthy keys via an atomic `Cache` counter; unhealthy marker in `Cache` for `coc.key_pool.unhealthy_ttl` (3600 s), cleared by a successful probe (Open question 4); all unhealthy → unavailable + `coc.keys_all_unhealthy` critical log (Sentry alert, 11 §3). `status()` → `KeyPoolStatusData`.
- **Use-case services** (the public surface, 09 §1): `PlayerLookup`, `ClanLookup`, `TokenVerifier`. They return result DTOs with a `CocLookupStatus` enum (`found`/`not_found`/`unavailable`; `ok`/`invalid` for tokens) instead of throwing, because `Exceptions/` is not an allowed cross-module namespace (05 §2) and an outage must never become a 5xx (09 §7). A 404 for a well-formed tag logs `coc.tag_not_found` (23 §2).
- **Fake:** `FakeCocApiClient` (scriptable: players, clans, valid tokens, failure per call) + synthetic fixtures in `tests/Fixtures/coc/`, each marked `"_fixture": "synthetic"` (Open question 1). Binding by `coc.driver` (`COC_API_DRIVER`): `fake` in local/testing, `http` required in production (boot check fails loudly).
- **Health:** `coc:check-health` probes each key with one cheap authenticated call, updates the markers and caches `platform:health:coc`; `platform:check-health` calls it; `/health` gains a `coc` check that is never in `platform.health.required` (degraded, not down, NFR-AVAIL-2). Fake driver reports `ok`.
- **Config:** `config/coc.php` with `driver`, `base_url`, `tokens`, `timeouts{connect,total}`, `key_pool{unhealthy_ttl}`, `log{malformed_body_bytes}`; the cache/rate/circuit/sync/key_rotation groups land with the tasks that read them. `.env.example`: `COC_API_DRIVER`, `COC_API_TOKENS` (empty).

## Out of scope
- Throttle, circuit breaker, caching, stale-while-error, `coc_api_requests`, `CocApiStatus`, maintenance banner, dashboard API panel → P2-07
- Key rotation via the developer portal, `/leagues` + `/locations` reference data → P2-08
- Clan search, CWL; attach/verify UI and `coc_account_claims` (P2-02); sync and snapshots (P2-09, Open question 3)

## Acceptance criteria
- Functional: FR-COC-2 rejects invalid tags before any HTTP call; player, clan and verifytoken map to DTOs; every 09 §7 row maps to the right result.
- Authorization: no new route, policy or write path (specs/04 unchanged).
- Edge cases: 23 §2 and §5 rows above, each with a test.
- Secrets: API keys and in-game tokens never appear in logs, exceptions, Sentry events, cache values or job payloads.
- States: no UI.

## Tests
- Unit: `PlayerTag`/`ClanTag` table (case, `O`→`0`, missing `#`, whitespace, bad charset, length 2/3/12/13, lookalike Unicode); key pool round-robin, skip unhealthy, all unhealthy.
- Contract (`tests/Contract`): mappers against every fixture (full player, no clan, unknown troop, TH beyond today's max, missing field, clan, token ok/invalid).
- Integration: `Http::fake` sequences 200 / 403 / 403 `invalidIp` + retry on the next key / 404 / 429 / 503 / 500 / timeout / malformed; `Http::preventStrayRequests()` suite-wide.
- Security: key and token absent from log records, exception messages and cache; production refuses the fake driver.
- Feature: `coc:check-health` + `/health` `coc` check (ok / degraded / unknown); config-driven values read from config.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended). Q3 synced → tasks/BOARD.md (P2-09).
1. **Fixtures:** 09 §10 wants recorded real responses, but there is no developer key yet (P0-09 blocked). Recommended: hand-built fixtures from the documented shape, each marked `"_fixture": "synthetic"`, swapped for recorded ones once a key exists; the contract test runs on both.
2. **Where do `PlayerTag`/`ClanTag` live?** PlayerAccounts, Clans and Http form rules need them, and `Support/` value objects are not a cross-module target for module-specific types (P0-02). Recommended: `App\Domain\CocIntegration\Data\` (public namespace) with a `CocTagFieldRules` facade for Form Requests (19 §1). Alternative: `App\Support\ValueObjects\`.
3. **Board gap:** specs/25 §4 lists "Tiered sync + snapshots" but the board has no row. Recommended: add P2-09 "Tiered sync, snapshots, manual refresh" (09 §6, FR-COC-9/10/14), depending on P2-02 and P2-07.
4. **How long is a key out of rotation?** 09 §3 says "removed from rotation" without a duration. Recommended: the marker lasts `coc.key_pool.unhealthy_ttl` (3600 s); `coc:check-health` (every 5 min) clears it as soon as a probe succeeds, so a fixed key returns within 5 min and a dead one costs at most one failed call per hour.

### Decisions and divergences (implement, 2026-10-02)
1. The lookups return result DTOs (`PlayerLookupResult`, `ClanLookupResult`, `TokenVerificationResult`) with a status enum and never throw; `CocApiFailure` and `TagNotFound` stay in the module's internal `Exceptions/`. `TokenVerificationStatus` adds `not_found` and `unavailable` to the API's `ok`/`invalid`, so an outage never reads as a wrong token. Results carry a `CocFailureReason` (throttled, maintenance, server_error, timeout, no_healthy_key, malformed) and `retryAfter`, so sync jobs (P2-09) can `release()` correctly. synced → specs/09 §8, specs/05 §2.
2. Driver: `coc.driver` (`COC_API_DRIVER`), `fake` by default. Resolving the fake in production throws, and `coc:check-health` reports it as down. That replaces the boot check, so `/health` and artisan still run on a misconfigured host. synced → specs/09 §11, specs/05 §6.
3. Key pool: key id = first `coc.key_pool.id_length` (8) hex characters of the token's sha256, the only identifier logged. Duplicate tokens are loaded once. The round-robin cursor is a `Cache` counter. The unhealthy marker lasts `unhealthy_ttl` (3600 s), and `coc.key_unhealthy` is logged once per outage, not once per call: error for `accessDenied`, critical for `accessDenied.invalidIp`. `coc.keys_all_unhealthy` (critical) is logged when the last key goes. A reason the API sends that we do not know is stored as `accessDenied`. `CocApiKey` hides its token from `var_dump` and refuses serialisation. synced → specs/09 §3, specs/21 §3.
4. Health: `GET /locations?limit=1` once per key. Ok when every key works, degraded when some work or the API did not answer (markers untouched), down when every key is refused or none is configured. The result goes to cache key `platform:health:coc`, which `HealthChecker` reads by key (Support cannot depend on Domain), and `/health` shows it as `coc`, never required. `coc:check-health` and `platform:check-health` both fail only on down. synced → specs/09 §3, specs/20 §2, specs/21 §3.
5. The verifytoken response echoes the token. Only `status` is read, and on a malformed verifytoken body the body is not logged (security test covers it). Other malformed 200s log the body cut to `coc.log.malformed_body_bytes` (2048). synced → specs/09 §9.
6. Mapping: only `tag` and `name` are required; a missing field is null or an empty list, a present field of the wrong type refuses the whole response. The API names mapped to ours: player top-level `role` → `clan.role`; clan `members` → `memberCount`, `memberList` → `members`, `requiredTownhallLevel` → `requiredTownHall`, `clanCapital.capitalHallLevel` → `capitalHallLevel`. Hero equipment nested under heroes is left in `rawPayload`. synced → specs/09 §8.
7. No retries in the HTTP client except the key swap after a 403. The 5xx retry ×3 with backoff from 09 §7 belongs to the throttle decorator (P2-07). A 400 counts as `server_error`. No spec change (09 §7 stands; the retry lands with P2-07).
8. `Http::preventStrayRequests()` now runs in every test (`tests/TestCase.php`). It caught `HomePageTest` making a real request to the SSR renderer; that test now fakes the renderer as down. synced → specs/09 §10.
9. Fixtures are synthetic (Open question 1), documented in `tests/Fixtures/coc/README.md`. The contract test maps every file under `players/` and `clans/`, so recorded files dropped in later are covered automatically. synced → specs/09 §10.
10. The three CoC enums appear in `generated.d.ts` (the transformer collects domain enums). No spec change.

### Review (verify, 2026-10-02)
- scripts/check.sh: all green (sqlite + postgres).
- Spec review: skipped (single module, no policy/schema/shared-props change).
- Security: no findings. antislop audit-025: no findings.
