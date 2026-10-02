---
id: P2-20
title: Let owners refresh a CoC account by hand, once per 10 minutes
phase: 2
status: todo
depends_on: [P2-09, P2-04]
---

# Manual account refresh

## Spec refs
- Core: specs/09 §6 "Manual refresh" (1 per 10 min per account, synchronous with a 3 s timeout, job fallback), §7 degradation contract (manual refresh disabled with a message while the API is down)
- Plus: specs/04 §2 (own account, ○), §3 (account writes open to restricted users); specs/11 (rate limits); specs/18 §6 account detail; specs/20 §2 (`SyncCocAccountJob` as the fallback)
- FR: FR-COC-9
- Edge cases: specs/23 §5 (malformed answer writes nothing); a refresh racing a scheduled sync (job uniqueness)

## Scope
- **Domain**: refresh through P2-09's sync service with `fresh: true` and `CocPriority::Interactive`, 3 s budget; on timeout dispatch `SyncCocAccountJob` and report "refreshing in the background"; snapshot `source: manual`. Unverified accounts may refresh (09 §6: only by hand).
- **Policy + Form Request**: `CocAccountPolicy::refresh` (owner only); named limiter per account (`coc-refresh`, 10 min).
- **UI**: a Refresh button with the last-updated age on the account detail page (P2-04); disabled with the API-unavailable message while the circuit is open; result flash (updated / unchanged / background / not found).
- Config: `coc.sync.manual_timeout`, `coc.sync.manual_cooldown`.

## Out of scope
- The account page itself (P2-04); background sync (P2-09).

## Acceptance criteria
- Functional: FR-COC-9; the owner sees fresh data within 3 s or a background notice.
- Authorization: owner only; other users 404; suspended / banned blocked by `account.active`.
- Edge cases: second refresh inside 10 min refused with the wait; API down; 404.
- States: idle, refreshing, cooling down, unavailable, error.

## Tests
- Feature: happy path, timeout fallback, cooldown, policy, API down, 404.
- Security: IDOR (another user's account), rate limit.
- Vitest: button states.

## Notes

### Open questions
None yet; scope follows the specs. Split from P2-09.
