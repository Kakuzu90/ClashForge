---
id: P2-24
title: Release CoC tags when an account is deleted or stays banned, and hold deletion during a dispute
phase: 2
status: todo
depends_on: [P2-14, P1-11, P1-14, P2-03]
---

# Tag release on deletion and ban

## Spec refs
- Core: specs/13 §6 (on ban: released after 30 days; on deletion: released at the end of the deletion window), §9 (tag verified by a user later banned for fraud; verified user deletes their website account); specs/08 §6 (`coc_accounts` row of the anonymisation table, dispute holds); specs/20 §2–3 (`ReleaseBannedUserTagsJob`, `coc:release-banned-tags` daily 04:15)
- Plus: specs/02 FR-AUTH-9; specs/04 §1 (`banned`: tags released after 30 days); specs/05 §2 (module surfaces; Auth must not depend on PlayerAccounts); specs/07 `user_sanctions`, `coc_accounts`; specs/16 §2 (Tag released: I); specs/18 §6 (Danger zone)
- FR: FR-AUTH-9, FR-COC-13
- Edge cases: specs/23 §1 (deletion requested while a dispute involves the user: queued but held, the user told why and can cancel), §2 (verified owner banned: hidden with the owner, released after 30 days)

## Scope
- **Deletion seam** (Open question 1): new Auth contracts, implemented by PlayerAccounts and bound in its provider:
  - `DeletionHold::holdsFor(userId)` returns a reason or null.
  - `DeletionStep::run(userId)` runs inside the anonymisation transaction.
  - `AccountDeletionService::anonymise()` skips a held account and leaves it `pending_deletion`.
- **Release on deletion**: inside the anonymisation transaction, P2-14's release helper releases every row the user still holds (`audit_logs` `coc_account.released`, `reason = deletion`). There is no notice: the account's notifications are deleted in the same transaction.
- **Deletion hold** (23 §1): an `open` / `awaiting_*` dispute with the user as claimant or holder holds the anonymisation. The Danger zone shows a pending-deletion user why (the dispute is still open) and keeps Cancel. The anonymisation runs on the first nightly pass after the dispute closes.
- **Release after a ban**: `coc:release-banned-tags` (daily 04:15) with `ReleaseBannedUserTagsJob`. It picks users still banned whose active ban started at least `coc.accounts.ban_release_days` (30) ago, and releases their rows (`reason = ban`, console actor). It also sends the in-app Tag released notice, which the user reads if the ban is lifted. It is idempotent per user, chunked, and serialised on the user lock.
  - Reading the ban start crosses into Moderation: `SanctionReadModel::bannedSince(cutoff)` (Open question 2).
- **Running disputes of a banned holder** are untouched: 13 §9 says the dispute continues.
- Config: `coc.accounts.ban_release_days`.

## Out of scope
- Notifying earlier disputants that a released tag is claimable (13 §9) (Open question 3)
- Account deletion's other holds (open marketplace orders, P6); the user-facing detach (P2-14)

## Acceptance criteria
- Functional: after the window, every `coc_accounts` row of the user is `released` with `user_id` null and the count is 0. A user banned for 30+ days loses their tags. A ban lifted before day 30 keeps them.
- Authorization: console and job only; no new route.
- Edge cases: a running dispute holds the deletion, and the user can still cancel; a re-banned user's 30 days count from the current ban; reruns are no-ops.
- States: the Danger zone's "held" message.

## Tests
- Feature: deletion releases tags and audits them; the dispute hold skips then resumes; the ban release at 29 and 31 days, lifted ban, idempotent rerun; the Danger zone shows the hold.
- Architecture: Auth does not reference `App\Domain\PlayerAccounts`.

## Notes

### Open questions
Resolved by the owner, 2026-10-05 (all as recommended); specs synced at implement → Finish.
1. **How does Auth's deletion pipeline reach PlayerAccounts?** PlayerAccounts depends on Auth (05 §2), so a direct call is a cycle, and an after-commit event would release the tags outside the anonymisation transaction. Recommended: Auth defines the two small contracts above (hold + step). PlayerAccounts implements them, and later modules (orders, P6) add theirs. 05 §2 and 08 §6 would be synced.
2. **Where is "banned for 30 days" read from?** `users.status` has no start date. `user_sanctions.starts_at` is Moderation's. Recommended: a Moderation read model returning user ids whose active ban started before the cutoff, which the PlayerAccounts command calls.
3. **Notify earlier disputants of a fraud ban's released tag (13 §9)?** No such notification type is in specs/16. Recommended: leave it out for now, and add a row to P2-18 (dispute notifications) if wanted.

Split from P2-14 on 2026-10-05.
