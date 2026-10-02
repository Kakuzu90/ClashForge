---
id: P2-02
title: Attach a CoC account and verify ownership with the in-game token (schema, services, policy)
phase: 2
status: done
depends_on: [P2-01, P2-07, P1-02]
---

# Attach and verify a CoC account (backend)

## Spec refs
- Core: specs/13 §1 (rules), §2 (state machine), §3 (attach + verify, §3.1 transaction), §4 (conflict path), §9 (edge cases); specs/09 §9 (verification flow, tokens never stored), §7 (degradation contract); specs/07 `coc_accounts`, `coc_account_claims`, `users`
- Plus: specs/08 §2 (cascades), §3.1 (trust edge), §5 (`verified_accounts_count`); specs/04 §1 (email verified for attaching), §2 (attach / verify own), §3 (`CocAccountPolicy`), §4 (`coc-attach`); specs/05 §2 (PlayerAccounts surface, events); specs/11 §1, §3 (claim attempts and verification failures in the security log); specs/23 §2
- FR: FR-COC-1, FR-COC-3, FR-COC-4, FR-COC-5, FR-COC-6, FR-COC-7, FR-COC-8
- Edge cases: specs/13 §9 (two valid tokens seconds apart; API down while verifying; same person, two website accounts); specs/23 §2 (200 tags to farm badges: anomaly log at 20+; tag valid here, 404 upstream)

## Scope
- **Migrations / models / factories** (`Domain/PlayerAccounts`): `coc_accounts` and `coc_account_claims` as specs/07, without `clan_id` for now (it lands with the clans stub, P2-13; Open question 3), with partial uniques, `CHECK`s and indexes. `CocAccount` / `CocAccountClaim` with factory states unverified / verified / released / disputed. `users.verified_accounts_count` added through an Auth migration; no `users.featured_coc_account_id`: `coc_accounts.is_featured` is the only featured flag (Open question 2).
- **Enums:** `CocAccountStatus`, `VerificationMethod`, `ClaimMethod`, `ClaimStatus`, `ClaimFailureReason`.
- **AttachAccountService** (13 §3 steps 1–5):
  - `preview(user, PlayerTag)` → `AttachPreviewData`, one of: player card, already attached by you, not found, verified by another user (their public username), API unavailable. Each call counts against the `coc-attach` limit.
  - `attach(...)` creates the `unverified` row from `PlayerData` (FR-COC-3 fields, `raw_payload`), or reuses this user's `released` row, plus a claim row.
  - It refuses when a verified holder exists. With the API down it attaches from a stale answer (13 §9).
- **VerifyOwnershipService** (13 §3.1, one transaction):
  - `TokenVerifier` runs outside the transaction. Then the tag is locked with `FOR UPDATE`.
  - Any other `verified` row for the tag drops to `unverified`, keeps its `user_id`, and loses its featured flag (Open question 4). This row is promoted (`api_token`), and becomes featured if it is the user's first verified account. Both users' counts are recounted and written through a new Auth `UserStatusService::syncVerifiedAccounts()` (Open question 2), the claim row is written (`succeeded`, or `failed` with `invalid_token` / `api_error` / `rate_limited`), and `audit_logs` gets `coc_account.verified` (before/after user ids).
  - After commit: `CocAccountVerified`, plus `CocAccountOwnershipTransferred` on a supersede (05 §2 events; their consumers arrive with later tasks).
  - The token never leaves the call (09 §9).
- **Policy + limits:** `CocAccountPolicy` (`attach`: verified email and `account.active`, since restricted users may still attach; `verify`: own row in `unverified` or `released`). New `coc-verify` limiter at 5 per hour per user (09 §9), beside `coc-attach` (04 §4); every attempt counts. Security log events: `coc.attach_attempt`, `coc.verification_failed`, `coc.ownership_superseded`, `coc.attach_anomaly` (20+ accounts).
- **Config:** `config/coc.php` `accounts{attach_per_hour, verify_per_hour, anomaly_accounts}`.

## Out of scope
- Attach page, controllers, Form Requests, profile CTA → P2-11
- Verified and superseded notifications (in-app + email) → P2-12
- Clans stub and `clan_id` (Open question 3) → P2-13
- Detach / release / featured switching (FR-COC-12/13, 13 §6) → P2-14
- Disputes and auto-resolving a claimant's dispute (P2-03); snapshots and sync (P2-09); `EnsureHasVerifiedCocAccount` (with P3-01)

## Acceptance criteria
- Functional: the FR ids above; only one verified owner per tag (DB-enforced); the token beats an unverified claim and supersedes a verified holder.
- Authorization: guests, unverified emails, and suspended / banned / pending-deletion accounts are refused; nobody can verify another user's row (IDOR).
- Edge cases: the rows above; nothing is half-written when the API fails mid-flow.
- States: no UI (P2-11).

## Tests
- Feature: preview outcomes; attach (new, reuse released, already attached, conflict, stale); verify ok / invalid / not found / unavailable; supersede; first account featured; claim rows and audit entries; events after commit only.
- Security: IDOR on verify; both limiters; the token absent from DB, logs, events and job payloads; concurrent verification serialised (two holders never both `verified`).
- Unit: the enum value lists match the specs/07 CHECK lists. There are no transition methods: transitions live in the services and are tested there (review).
- Config: limits read from config.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended). Q1 and Q3 synced → tasks/BOARD.md (P2-11, P2-12, P2-13, P2-14).
1. **Split:** the board row is three pages of work. Recommended: this file is the backend. New rows: P2-11 "Attach flow UI (`/accounts/attach` three steps, error states, success, the own profile's Accounts empty-state CTA)", P2-12 "Ownership notifications (verified: I + E; superseded: I + E*)", P2-14 "Detach, release and featured account (FR-COC-12/13, 13 §6)". All depend on P2-02, and P2-11 also on P2-12.
2. **Who writes `users`?** Auth owns `users`, but 07/08 put `verified_accounts_count` and `featured_coc_account_id` there, written by the verification service. Recommended: a new Auth method (e.g. `UserStatusService::syncVerifiedAccounts(userId, count)`) called inside the verify transaction with the count recounted from `coc_accounts`. Drop `users.featured_coc_account_id` and keep `coc_accounts.is_featured` (partial unique) as the only featured flag, so there is no circular FK.
3. **`clan_id` without a `clans` table:** 07 lists `clans` as "read-only stub in M", but no task creates it. Recommended: store `clan_tag` and `clan_role` now; add P2-13 "Clans stub: `clans` table, ensure-clan listener on `CocAccountVerified`, `coc_accounts.clan_id`" before P2-04, which needs the clan name and badge.
4. **What happens to a superseded holder's row?** 13 §3.1 says demote it and move `user_id` to a `previous_user_id` field (no such column in 07), while 08 §3.1 says a transfer keeps one row and changes `user_id`. Rows are per user (07: `UNIQUE (user_id, tag_normalized)`). Recommended: the holder's own row drops to `unverified` and keeps its `user_id`, so they see it and can re-verify. Their featured flag clears, the count drops, and the audit entry records both user ids. Snapshot history is read by tag across rows. 08 §3.1 and 13 §3.1 would be synced to say so.

### Decisions and divergences (implement, 2026-10-02)
1. The one-owner partial unique covers `verified` and `disputed`: a disputed row still holds the tag (13 §2), so a verification while a dispute is open must supersede it rather than sit beside it. Attach refuses, and verification supersedes, a holder in either status. synced → specs/07, specs/13 §3.1.
2. `coc_accounts` columns:
   - The game stats (`th_level` included) are nullable, since a field the API drops reads as null (23 §5).
   - No `deleted_at`: ownership history is never deleted (13 §1).
   - `clan_id` comes with P2-13.
   - `coc_account_claims.user_id` restricts deletes, as forensic history.
   - Enum `CHECK`s exist on Postgres only.
   - The GIN index on `ign` uses the `simple` configuration.
   synced → specs/07.
3. `users.verified_accounts_count` is written only by `UserStatusService::syncVerifiedAccounts()` and counts `verified` and `disputed` rows. There is no `users.featured_coc_account_id`. synced → specs/07, specs/08 §3.1 and §5, specs/05 §2.
4. `ClaimFailureReason` adds `not_found`. Claim rows: synced → specs/07 (claims lifecycle).
   - `preview` writes none.
   - A refused attach writes a `failed` row with no account.
   - An attach writes `pending`, and each verification attempt writes its own `succeeded` or `failed` row.
   - On success the account's `pending` rows become `succeeded`; a superseded holder's `pending` rows become `superseded`.
5. `coc-attach` counts distinct tags per user and hour: the first preview or attach of a tag spends an attempt, and returning to it is free. `coc-verify` is a new limiter, 5 per hour per user, counting every attempt; a throttled try writes a `rate_limited` claim row. synced → specs/04 §4, specs/13 §3, specs/09 §9.
6. Attach reuses the latest `released` row for the tag, whoever held it before (13 §6, continuous history). synced → specs/13 §6, specs/08 §3.1.
7. On supersede the holder's row keeps its `user_id` and drops to `unverified` without its featured flag. One audit entry, `coc_account.verified` (new `AuditAction`, new `AuditSubject::CocAccount`), records `verified_user_ids` before and after. `CocAccountVerified` and `CocAccountOwnershipTransferred` (method as `VerificationMethod`) implement `ShouldDispatchAfterCommit`. synced → specs/13 §3.1, specs/08 §3.1.
8. Policy: No spec change: specs/04 §3 already limits the restricted block to content writes.
   - Restricted accounts may attach and verify; this is not one of the content writes they lose.
   - Verify needs the user's own row in `unverified`; another user's ulid is a 404 (owner-scoped lookup).
   - A second verify of a verified row is refused.
9. `PlayerData` gains `donationsReceived`, `builderHallLevel` and `builderBaseTrophies`, which specs/07 stores. synced → specs/09 §8.
10. A `suspended` row gets no special handling yet; nothing creates one before P2-03. Follow-up for P2-03.
11. The new Data DTOs and enums are in `generated.d.ts` for P2-11. No spec change.

### Review fixes (verify, 2026-10-02)
- Security (high): a real owner who had never attached a held tag had no token path, since attach is refused and verify needed an existing row, so a holder could keep a tag hostage. New `VerifyOwnershipService::verifyTag()` checks the token first, then creates the user's row and promotes it in one transaction (13 §4 A); P2-11 uses it on the conflict card. Tested: takeover, failed token writes no row, shared limit, refused standings.
- Spec (medium): two verifications by one user for different tags raced on the featured flag and the count, and two crossing supersedes could deadlock. The verifier's and holders' `users` rows are now locked in id order (`UserStatusService::lockAccounts`) before either is read. Tested: lock order and SQL, one user with two tags.
- Security (low): limiters counted after checking; they now count first and compare (a refused tag stays unspent). Throttled attempts wrote a claim row and a log line each; now only the first refusal of a window does.
- Security (low): closing `pending` claims was not scoped to the user, so a reused row could rewrite an earlier user's history; scoped and tested.
- Security (low): `tag`, `tag_normalized` and all claim columns were fillable; now set with forceFill / forceCreate only. Tested.
- Spec + security (low): the anomaly flag fired only on an exact count; now at or above the threshold, once per `coc.accounts.anomaly_reflag_hours` (24).
- Spec (low): tests added for preview while the API is down and a throttled attach's claim row.
- Security (low), not fixed here: a hard-deleted user's holding row stays `verified` with no owner (08 §2 wants it released). No hard-delete path exists, and claim rows block one. Releasing tags at the end of the deletion window and 30 days after a ban → P2-14 (board row updated). `verifyTag` can supersede such a row meanwhile.
- antislop audit-027, finding 1 (low): the migration header comment bundled several decisions; split into comments beside each column or index (owner-approved, 2026-10-02).
- scripts/check.sh: all green (sqlite + postgres).

