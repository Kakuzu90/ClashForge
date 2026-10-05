---
id: P2-24
title: Release CoC tags when an account is deleted or stays banned, and hold deletion during a dispute
phase: 2
status: done
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

### Decisions and divergences (implement, 2026-10-05)
1. Seam: `Auth\Contracts\DeletionHold` (`reasonFor`, tag `HOLD_TAG`) and `DeletionStep` (`lock`, `run`, tag `STEP_TAG`). PlayerAccounts tags `AccountDeletionHooks` with both in its provider; Auth resolves them with `app()->tagged()`. An arch test keeps Auth off `App\Domain\PlayerAccounts`. synced → specs/05 §2, specs/08 §6.
2. `DeletionStep::lock()` runs first in the anonymisation transaction, before the account row is locked, because other modules lock their rows first and the account after. Both `anonymise` and the never-verified `purgeUnverified` call it, and both run the steps. Holds apply to self-deletion only: an unconfirmed email cannot open a dispute. synced → specs/08 §6.
3. A `suspended` row is not released on deletion or ban. It stays as staff left it, so deleting the account is not a way out of a suspension. On deletion, `unverified`, `verified` and `disputed` rows are released (`disputed` cannot be there while the hold works). After a ban, only `unverified` and `verified` rows are released. A `disputed` row waits for its dispute (13 §9) and is released on a later pass. synced → specs/13 §6, specs/08 §6.
4. The deletion notice is skipped by the listener (`reason = deletion`); the account's notifications go with it. The audit actor is `console` for deletion and `scheduler` for the ban release. synced → specs/16 §2, specs/13 §6.
5. Danger zone (23 §1): signing in cancels a deletion, so a pending user never sees the Danger zone. The hold is shown before the request instead: `DangerZonePageData.holds` lists the reasons in a "Deletion would wait" notice, and the form stays. The confirmation on the sign-in page says the dispute holds it. synced → specs/23 §1, specs/18 §6 (Danger zone).
6. `coc:release-banned-tags` runs inline (`BannedTagRelease`), like the other daily sweeps; there is no `ReleaseBannedUserTagsJob`. Users come from Moderation's new `Services\BanLookup` (`bannedSince(cutoff)`, `isBannedSince(user, cutoff)`; active ban, `starts_at` ≤ cutoff), streamed by cursor. Each user runs in one transaction: their rows, then the user, then the ban and the status are re-checked. synced → specs/20 §2–3, specs/05 §2 (Moderation surface).
7. `ReleaseReason` gains `deletion` and `ban`. Config: `coc.accounts.ban_release_days` (30). synced → specs/05 §2, specs/13 §6.
8. A dispute cannot be opened against a holder whose deletion is pending (refused as `not_held`), so nobody can push a deletion back (security review finding 1). synced → specs/13 §5, §6, specs/23 §1.

### Review fixes (verify, 2026-10-05)
- antislop audit-036: no findings.
- Spec (low): the hold alert's "You can still request it now." showed for accounts that may not request. It now shows under `canRequestDeletion` only, with a Vitest.
- Spec (low): the Danger zone now says connected Clash of Clans accounts are released and claimable.
- Spec (low): tests added for the never-verified purge releasing tags, a hold while a dispute is `awaiting_admin`, and a held user cancelling by signing in.
- Spec (trivial): the two release-status lists are named `ON_DELETION` / `ON_BAN`, each pointing at the other. The Vitest spacing is fixed by Prettier.
- Security (low): a dispute opened after the deletion request could postpone erasure indefinitely. `DisputeService::open` now refuses it; tested.
- Security (low, existing code, not fixed): `SanctionService` refuses to ban a `pending_deletion` account, so someone expecting a ban can ask for deletion first and leave no ban or evasion record. This needs an owner decision (specs/12 §6) → follow-up task "Decide how bans and pending deletion interact".
