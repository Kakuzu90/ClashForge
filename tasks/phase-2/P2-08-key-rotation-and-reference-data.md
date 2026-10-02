---
id: P2-08
title: Detect a CoC egress IP change with an alert and runbook, and serve leagues and locations reference data
phase: 2
status: blocked
depends_on: [P2-01, P0-09]
---

# Key rotation and reference data

## Spec refs
- Core: specs/09 §2 (`/leagues`, `/locations`: static, cached 7 days, seeded), §3 (rotation row; "Fallback if automated rotation is not possible"), §11 (`cache.static_ttl`, `key_rotation{…}`)
- Plus: specs/21 §1 rule 4, §3 (`coc:leagues`, `coc:locations`, 7 days, weekly refresh); specs/20 §2 (`RotateCocApiKeysJob`, `RefreshStaticReferenceDataJob`), §3 (Sun 05:30 `coc:rotate-keys`, Mon 06:00 `coc:refresh-reference-data`); specs/23 §5 (egress IP changes without warning); specs/24 R5; specs/11 (key rotation runbook; secrets never logged); specs/10 §11.1 (league emblems keyed by league id)
- FR: none directly (NFR-AVAIL-2, specs/24 R5)
- Edge cases: specs/23 §5 "Our egress IP changes without warning"

## Scope
- **Reference data:**
  - `CocApiClient::leagues()` / `locations()` on the HTTP client and the fake. The fake reads new synthetic fixtures under `tests/Fixtures/coc/reference/`.
  - Both go through the throttle as `background` calls. They are never cached by the decorator; the refresh writes the cache.
  - The public `ReferenceData` service (`leagues()`, `locations()`, `league(id)`) reads `coc:leagues` / `coc:locations`, falls back to the seed (Open question 3), and never calls the API in a request.
- **`coc:refresh-reference-data`** (Mon 06:00, `withoutOverlapping`, `onOneServer`; on demand too) fetches both and writes the cache for `coc.cache.static_ttl`. A failed fetch keeps the old entry and logs a warning.
- **IP change detection** (Open questions 1–2):
  - `coc:rotate-keys` (Sun 05:30, on demand too) and any `accessDenied.invalidIp` answer compare the egress IP with `coc.key_rotation.egress_ips`.
  - A change logs `coc.egress_ip_changed` (critical, once per new IP) with the new IP and the key ids, never tokens.
  - With rotation off, the log line points to the runbook.
- **Runbook:** `docs/runbooks/coc-api-keys.md`: create a key for the new IP in the portal, update `COC_API_TOKENS` and `COC_EGRESS_IPS`, deploy, check `coc:check-health`, revoke the old key.
- **Config:** `cache.static_ttl`, `key_rotation{enabled (false), egress_ips[]}`. The portal credentials wait for Open question 1.

## Out of scope
- Using the reference data: league emblems (P2-04/P2-05) and location filters (P4-01).
- Automated key creation through the developer portal, if split off (Open question 1).
- The System Health page (P2-06).

## Acceptance criteria
- **Functional:**
  - Leagues and locations read from the cache, else the seed, with no API call in a request.
  - The weekly refresh replaces them.
  - An IP change is detected by the weekly command and by an `invalidIp` 403, and alerted once.
- **Degradation:** a failed refresh or an API outage leaves the old data in place. Detection never blocks a call.
- **Security:** no token in logs, cache values, alerts or the runbook's example output; key ids only.
- **States:** no UI.

## Tests
- **Feature (time frozen, `Http::fake`):**
  - refresh writes both keys with the TTL;
  - a failure keeps the old data;
  - the seed is used when the cache is empty;
  - the fake's fixtures;
  - IP change from the command and from an `invalidIp` 403, alerted once per IP;
  - nothing when unchanged;
  - the schedule entries.
- **Security:** no token in any log line or cache value the commands write.
- **Unit:** parsing the IP out of the 403 message (Open question 2).
- **Config:** TTL, `egress_ips` and the rotation flag read from config.

## Notes

### Deferred (owner decision, 2026-10-02)
The owner plans a VPS with a static IP for all Clash of Clans API calls (specs/09 §3 requirement 1), so the egress IP should not change. The task waits for that host (P0-09). When it resumes, re-check the open questions below against the VPS setup: with a fixed IP, a manual runbook may be enough for rotation, and the reference data part can be taken on its own if a consumer (P2-04 league emblems) needs it first.

### Open questions
1. **Automated rotation through the developer portal:** specs/09 §3 wants a job that creates a key for the new IP and revokes the old one. That runs into three problems:
   - The portal API is undocumented (`/api/login`, `/api/apikey/list|create|revoke`) and needs a portal email and password in the environment.
   - Keys it creates can't be written back into `COC_API_TOKENS` at runtime, so they would need encrypted storage in a new table.
   - We have no portal account yet (P0-09 is blocked).

   Recommended: this task ships what the spec calls the fallback (detection, an urgent alert, the runbook) plus the reference data. Automated rotation becomes a new board row, P2-15, blocked on portal credentials, and decides key storage there. Alternative: build it now against a faked portal, with created keys encrypted in a `coc_api_keys` table.
2. **How the egress IP is known:** the options are:
   - (a) the 403 `accessDenied.invalidIp` message, which names the calling IP, so it's known exactly when it breaks, with no third party;
   - (b) a public "what is my IP" service, called weekly, which sends a request outside our stack;
   - (c) only the configured list.

   Recommended: (a) plus the configured `coc.key_rotation.egress_ips` as the expected set. The weekly command then reports keys whose last health probe hit `invalidIp`, and no third-party service is used.
3. **"Seeded" reference data** (09 §2): Recommended: checked-in seed files `database/data/coc/leagues.json` and `locations.json`, synthetic and marked like the fixtures until a key exists. They are read only when the cache is empty, and the weekly refresh replaces them in the cache. The cache TTL is 8 days for a weekly refresh, so a late run never leaves a gap (21 says 7). Alternative: `coc_leagues` / `coc_locations` tables.
