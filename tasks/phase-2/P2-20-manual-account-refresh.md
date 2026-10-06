---
id: P2-20
title: Let owners refresh a CoC account by hand, once per 10 minutes
phase: 2
status: done
depends_on: [P2-09, P2-04]
---

# Manual account refresh

## Spec refs
- Core: specs/09 §6 "Manual refresh" (1 per 10 min per account, synchronous with a 3 s timeout, job fallback), §6 tiers ("viewed in the last 24 h" is hot, joins with P2-20), §7 degradation contract (manual refresh disabled with a message while the API is down)
- Plus: specs/04 §2 (attach / verify / detach row: own account ○; restricted users keep account writes, §3), §4 (`coc-refresh` 1 / 10 min, user + account); specs/11 (named limiter on every write); specs/18 §6 account detail (sync status footer: "updated 12 minutes ago" + manual refresh button); specs/20 §2 (`SyncCocAccountJob` as the manual refresh fallback); specs/07 `coc_accounts` (new `last_viewed_at`), `coc_account_snapshots.source = manual`
- FR: FR-COC-9 (and FR-COC-10's hot tier input)
- Edge cases: specs/23 §5 (a malformed answer writes nothing, never changes `status`); a refresh racing a scheduled sync (job uniqueness, row lock)

## Scope
- **Domain** (`AccountRefreshService`, PlayerAccounts): owner only, rows `unverified`, `verified`, `disputed` (the holder). Lookup with `fresh: true`, `CocPriority::Interactive` and a 3 s deadline (Open question 1). Found → store under the row lock: data, `api_synced_at`, `api_sync_failures` reset, a snapshot with `source: manual` when a tracked value changed, then `recordSuccess` on the schedule. Unverified rows: Open question 2. Not found → counts like a sync 404. Deadline hit → dispatch `SyncCocAccountJob` (carrying the manual source), report "refreshing in the background". Circuit open / no budget → refused with the API-unavailable message.
- **Limiter**: `coc-refresh`, keyed user + account, 1 per `coc.sync.manual_cooldown` (600 s); when it is spent is Open question 3. Plus `global-write`.
- **Route**: `POST /accounts/{ulid}/refresh` (`auth`, `account.active`); a foreign or hidden ULID is a 404.
- **Sync tier input** (from P2-04): the account page records `coc_accounts.last_viewed_at`, at most once per account per hour (cache-throttled); `SyncTierRules` makes an account viewed in the last 24 h hot. Who counts and whether a view pulls the next sync forward: Open question 4. Migration + specs/07 row.
- **Props**: `AccountDetailData` gains `canRefresh` and `refreshAvailableAt` (owner only).
- **UI**: the sync footer on `Accounts/Show.vue` shows the age to everyone; the owner gets a Refresh button. States: idle, refreshing, cooling down (with the wait), unavailable (circuit open, `cocApi` prop), error. Result flash: updated / no change / refreshing in the background / tag not found.
- Config: `coc.sync.manual_timeout` (3), `coc.sync.manual_cooldown` (600).

## Out of scope
- The account page itself (P2-04); background sync and the stale display (P2-09); clan refresh (P4-01).

## Acceptance criteria
- Functional: FR-COC-9; the owner sees fresh data within 3 s or a background notice.
- Authorization: owner only (others, staff included: 404); restricted owners may refresh; suspended / banned / pending deletion blocked by `account.active`; `suspended` and `released` rows refused.
- Edge cases: a second refresh inside 10 min refused with the wait; API down never 5xx; 404; malformed answer writes nothing; a refresh during a running sync never double-writes.
- States: idle, refreshing, cooling down, unavailable, error.

## Tests
- Feature: happy path (changed / unchanged, snapshot `manual`), deadline fallback dispatches the job, cooldown, circuit open, 404, malformed, unverified row, `last_viewed_at` throttle and tier.
- Security: IDOR (another user's account, hidden account), limiter keyed per user + account, CSRF.
- Vitest: button states and the cooldown countdown.

## Notes

### Open questions
Resolved by the owner, 2026-10-06 (all as recommended); specs synced at implement → Finish.
1. **The 3 s deadline.** The HTTP client uses 5 s connect / 10 s total for every call. Recommended: pass a per-call timeout through `CocApiClient::player` (3 s connect and total for a manual refresh only). Hitting that deadline is our budget, not the API's fault, so it does not count toward the circuit breaker or the account's failures; it is logged, and the fallback job makes the real call with the normal timeouts. (Alternative: dispatch the job and wait up to 3 s for it, which ties up a web worker and needs a free queue worker.)
2. **Unverified accounts** (09 §6: synced only by hand, "not trusted data"). Recommended: a refresh updates the row's game data and `api_synced_at`, but writes no snapshot and starts no schedule; snapshots keep starting at verification (P2-09).
3. **When the cooldown is spent.** Recommended: only when the call reaches the API (found, not found, deadline fallback). A refusal because the circuit is open or the budget is out does not spend it, so our outage never locks the owner out for 10 minutes.
4. **Which views make an account hot.** The page is public and indexable, so crawlers would make every account hot and spend the background budget. Recommended: signed-in viewers only (the owner included). The hourly write also pulls the account's next sync forward to the hot interval when it sits on a slower tier, so a view takes effect within 2 h rather than at the next cold sync.

### Decisions and divergences (implement, 2026-10-06)
1. **Deadline** (Open question 1): `CocApiClient::player()` takes a `timeout` that runs through the decorators, and `PlayerLookup::find()` passes it on. The HTTP client then uses it as the total limit and `min(connect, timeout)` to connect. Running out of it is a new `CocFailureReason::Deadline`:
   - the breaker never counts it;
   - `coc_api_requests` logs it as `deadline`;
   - the API panel leaves it out of its failure count.
   synced → specs/09 §6, §7, §11.
2. **Cooldown** (Open question 3): `RefreshCooldown` is a `RateLimiter` key `coc-refresh:{user}:{account}`, held by the service (`global-write` still on the route).
   - It is counted before the call, so parallel clicks reach the API once.
   - It is given back for every answer that stores nothing: circuit open, maintenance, throttled, no healthy key, 5xx, malformed. Open question 3 named only the circuit and the budget; an API error is not the owner's doing either.
   - No security-log line on a hit, as `ClaimLimits` does; the button already shows the wait.
   synced → specs/04 §4, specs/09 §6.
3. **Messages:** a changed and an unchanged answer both say "Game data updated.", since "unchanged" only means no tracked progress changed (trophies may still have moved). Other outcomes:
   - background: a success flash;
   - not found, unavailable and cooling down (with the minutes): error flashes.
   synced → specs/18 §6.
4. **Unverified rows** (Open question 2): `AccountSyncService::apply()` with `source: manual` takes the owner's unverified row: data and `api_synced_at`, with no snapshot, no `sync_states` row and no 404 count. The fallback job:
   - carries `source: manual`, so its snapshot says so and it takes unverified rows;
   - out of tries, leaves the schedule alone.
   synced → specs/09 §6, specs/20 §2.
5. **Who may refresh:** `CocAccountPolicy::refresh` allows the owner's `unverified`, `verified` or `disputed` row, for an account allowed account writes (restricted included).
   - `suspended`: 403.
   - Released, foreign or hidden: 404.
   - Pending deletion: 403 from the policy, since `account.active` lets it through.
   synced → specs/04 §2.
6. **Views** (Open question 4): `AccountViewRecorder`, from `AccountController::show`, records signed-in views only, once per `coc.sync.view_record_seconds` per account (`Cache::add`). The write goes through the query builder, so `updated_at` stays. `SyncTierRules` makes an account hot for `coc.sync.hot_viewed_hours` (24) after a view. `SyncSchedule::promote` moves a warm or cold row to hot at once:
   - next due one hot interval after its last success, now at the earliest, never later than before;
   - frozen, stopped and unscheduled rows are left as they are.
   synced → specs/07 `coc_accounts`, specs/09 §6.
7. **Props and UI:** `AccountDetailData` gets `canRefresh` and `refreshWaitSeconds`. The sync footer shows the age to everyone; the owner also gets a secondary "Refresh" button, which:
   - counts the cooldown down in the browser;
   - is disabled and explained while cooling down or while `cocApi` is set;
   - spins while refreshing.
   R-31: a plain secondary button in the existing footer, in the same register as "Make featured"; a refresh is a utility action, not a reward. synced → specs/18 §6.
8. **Route:** `POST /accounts/{ulid}/refresh` (`auth`, `account.active`, `global-write`). synced → specs/19 §4.

### Review fixes (verify, 2026-10-06)
- antislop audit-041: no findings.
- Spec (medium): `SyncSchedule::promote` pulled forward rows that were backing off, and rows just claimed (job still queued). It now leaves rows with failures and rows due within `coc.sync.claim_seconds` alone. Tested. synced → specs/09 §6.
- Spec (medium): the 3 s limit was per attempt, so a 403 key swap could take 6 s. It now covers the whole call, and the swap gets only what is left. Tested. synced → specs/09 §6.
- Spec (medium): a `SyncCocAccountJob` queued before this change would have an uninitialised readonly `source`. It is now a plain property with a default. Tested.
- Spec (low) + security (low): every connection error under a manual limit was a `deadline`, which hid a real outage from the breaker and the API panel. Only cURL's timeout (error 28) is a `deadline` now; refused, DNS and TLS errors stay `timeout`. Tested. synced → specs/09 §6.
- Spec (low): `deadline` was missing from the `error_code` and failure-reason lists. synced → specs/07 `coc_api_requests`, specs/09 §4, §8.
- Spec (low): an exception or a `Skipped` outcome kept the cooldown although nothing was stored. The give-back now runs in `finally` unless the outcome is updated, background or not found. Tested.
- Spec (low): tests added for:
  - a refresh right after a sync (one snapshot);
  - a `Found` answer with no player (nothing stored, cooldown given back);
  - an unverified row's job fallback.
- Spec (low): the refusal message moved from the controller to `RefreshResultData::message()`.
- Security (medium): one signed-in user could make every account they can see hot and spend the background budget. Each viewer now records at most `coc.sync.views_per_viewer_per_hour` (30) accounts. Over the cap, the account's hourly marker is dropped, so the next viewer still counts. Tested, with a hidden-account case. synced → specs/09 §6.
- Security (low): refresh spend grew with the number of rows a user holds. There is now a per-user cap across accounts, `coc.sync.manual_per_hour` (20), with a new `too_many_refreshes` outcome. It is given back with the cooldown. Tested. synced → specs/04 §4, specs/09 §6.
