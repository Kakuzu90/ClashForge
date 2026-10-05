---
id: P2-14
title: Let owners detach a CoC account and choose their featured account
phase: 2
status: done
depends_on: [P2-02]
---

# Detach and featured account

## Spec refs
- Core: specs/13 §2 (state machine: `verified → released` on detach), §6 (detach, release, reuse on re-attach); specs/07 `coc_accounts` (`is_featured` partial unique, `user_id` set null on release)
- Plus: specs/04 §2 (attach / verify / detach own, ○), §3 (`CocAccountPolicy`); specs/08 §3.1 (featured flag, released row reused), §3.2 (base credit set null on release), §5 (`verified_accounts_count` recounted in the detach transaction); specs/11 (sensitive actions re-confirm the password, `password-confirm` limiter); specs/16 §2 (Tag released: I); specs/18 §6 (account detail, attach success step 3 "set as featured" prompt)
- FR: FR-COC-12, FR-COC-13
- Edge cases: specs/23 §2 (base credit chip disappears on detach: no bases yet, see Out of scope); specs/13 §9 (two website accounts, one tag: a detached row is reused)

## Scope
- **Domain** (`Domain/PlayerAccounts`): new `AccountOwnershipService`:
  - `detach(user, ulid, currentPassword)` (13 §6), in one transaction. It locks the row, then the owner's `users` row (`UserStatusService::lockAccounts`). It sets `user_id = null`, `status = released` and clears `is_featured`. It closes the user's `pending` claim rows for the row (Open question 4), recounts through `syncVerifiedAccounts()` and writes `audit_logs` `coc_account.released` (new `AuditAction`, `context.reason = detach`). Snapshots stay.
  - After commit: `CocAccountReleased` (new event, carrying the user id, tag and reason) and the in-app "Tag released" notice (16 §2).
  - `feature(user, ulid)`: moves `is_featured` to this row in one transaction; the partial unique holds.
  - A shared release helper that P2-24 reuses for deletion and ban.
- **Featured fallback** (Open question 2): when the featured row is detached or superseded, the user's earliest-verified remaining `verified` / `disputed` row becomes featured. This changes `VerifyOwnershipService`'s supersede step.
- **Policy + Form Request**: `CocAccountPolicy::detach` (own row in `unverified` or `verified`, with `allowsAccountWrites()`; Open question 3); `::feature` (own `verified` or `disputed` row, same standing). Another user's ulid is a 404 (owner-scoped lookup, as `verify`). `DetachAccountRequest` takes `current_password` inline (Open question 1). A wrong password logs `auth.password_confirm_failed`.
- **UI**:
  - Account page `/accounts/{ulid}`, owner only: a "Make featured" button and a "Remove account" action that opens a `UiDialog`. The dialog explains what happens (the tag can be claimed by anyone with a token; game history stays) and takes the password.
  - After a detach, a flash and a redirect to the own profile.
  - Attach success step 3 (18 §6, from P2-11): when the new account did not become featured, prompt "Make this your featured account".
  - Ability flags come from the controller (`can.detach`, `can.feature`).
- **Routes**: `DELETE /accounts/{ulid}` (`throttle:password-confirm`), `PUT /accounts/{ulid}/featured`.
- Config: none.

## Out of scope
- Release on account deletion, release 30 days after a ban, and holding a deletion while a dispute involves the user → P2-24
- Base credit set null on detach (08 §3.2, 23 §3): no `base_layouts` yet → P3-01 listens to `CocAccountReleased`
- Detaching a `disputed` row (release goes through the dispute, P2-16); staff-side release of a `suspended` row (P2-17)
- PlayerCards on profiles, the featured hero card (P2-22)

## Acceptance criteria
- Functional: FR-COC-12 (one featured per user, switchable), FR-COC-13 (detach → `released`, claimable, audit trail kept); re-attaching the tag reuses the released row (13 §6, already in `AttachAccountService`).
- Authorization: owner only; suspended, banned and pending-deletion accounts are refused; another user's ulid 404s; `disputed` and `suspended` rows cannot be detached.
- Edge cases: detaching the featured account moves the flag; detaching the last verified account drops the count to 0 (the badge goes); a wrong password changes nothing.
- States: dialog idle / submitting / wrong password / throttled; featured button pending.

## Tests
- Feature (`assertInertia`): detach happy path (row, count, featured, audit, claims, event after commit, notice); feature switch; fallback on detach and on supersede; re-attach reuses the row; validation.
- Security: IDOR on detach and feature; wrong password logged and throttled; refused standings and statuses.
- Vitest: the detach dialog's states.

## Notes

### Open questions
Resolved by the owner, 2026-10-05 (all as recommended); specs synced at implement → Finish.
1. **How is the password re-confirmed?** 13 §6 asks for password re-confirmation. specs/11 offers either the 15-minute `password.confirm` page or the password typed inline, which the account-deletion, password, email and username forms use. Recommended: inline in the dialog, sharing the `password-confirm` limiter; specs/11 would list detach among the inline forms.
2. **Which account becomes featured once the featured one is gone?** The specs only say a user's first verified account becomes featured. Recommended: the earliest-verified remaining `verified` / `disputed` row, both after a detach and after a supersede, so a profile keeps its card. The alternative is no featured account until the user picks one.
3. **Which rows can be detached?** The state machine only draws `verified → released`. Recommended: `unverified` and `verified`. A `disputed` row is refused with a pointer to the dispute (release there is the voluntary transfer, 13 §5 3c), and a `suspended` row stays with staff.
4. **What happens to the user's `pending` claim rows for a detached account?** ClaimStatus has `rejected` and `superseded`. Recommended: `superseded`, the same as a supersede.

Split from the board row on 2026-10-05: the deletion and ban releases and the deletion hold → P2-24.

### Decisions and divergences (implement, 2026-10-05)
1. `AccountOwnershipService` (`detach`, `feature`, and a public `release()` for P2-24) owns the writes. Detach locks every row of the tag and then the user, the order verification uses. It re-checks the policy on the locked row and account, then checks the password. Pending claims for the row become `superseded`. `release()` also clears `verified_at` and `verification_method`, as the dispute release does. Detaching an `unverified` row writes the same audit entry and sends the same notice. The audit entry `coc_account.released` records `before {status, user_id}`, `after {status: released, user_id: null}` and `context {tag, reason}`. synced → specs/05 §2 (PlayerAccounts surface, `CocAccountReleased` event), specs/13 §6.
2. The password is checked by a new Auth `PasswordConfirmationService::confirm()` (same message and `auth.password_confirm_failed` log as the settings forms). The existing settings forms keep their own copies for now. synced → specs/05 §2 (Auth surface), specs/11 (detach among the inline-password forms).
3. Featured fallback: `Support\FeaturedAccount::fallback()` gives the flag to the earliest-verified remaining `verified` / `disputed` row. It runs after a detach, a token supersede, a holder's dispute release, an admin transfer and an admin suspend. It locks with `SKIP LOCKED`, so a row another transaction is changing is passed over rather than waited for while holding the user lock. If that leaves the user without a flag while they still hold an account, `RestoreFeaturedAccountJob` (after commit, unique per user) locks their holding rows and then the user, and sets it (spec review finding 1). `HOLDING` moved to `CocAccountStatus::HOLDING`; `AttachAccountService::HOLDING` points at it. synced → specs/13 §3.1 step 2 and §6, specs/08 §3.1, specs/20 §2 (job list).
4. Feature locks the target row and the current featured row in id order, then the user. The one exception is a row that became featured after that select: it is locked after the user. A deadlock there is retried (3 attempts). No spec change.
5. "Tag released" is an in-app notice written by `SendOwnershipNotice::handleReleased` (queued, after commit) with `tag` and `name` and no link. New `NotificationType::CocAccountReleased`, category Ownership. synced → specs/16 §2.
6. `ReleaseReason` has only `detach`; P2-24 adds `deletion` and `ban`. synced → specs/05 §2 (events).
7. Routes: `DELETE /accounts/{ulid}` (`throttle:global-write` + `throttle:password-confirm`), `PUT /accounts/{ulid}/featured`. After a detach the user lands on their profile with a success flash; feature goes back with one. synced → specs/19 §4.
8. Props: `AccountDetailData.canDetach` / `canFeature` and `OwnCocAccountData.canFeature`. `canFeature` is false for the row that is already featured. The owner actions sit in the account page's footer next to the sync status. The Remove dialog is a `UiModal` with the password inline. synced → specs/18 §6 (account detail, attach success step 3).
9. Out-of-scope fix: `AccountPageTest` still expected the small league icon after fae1e19 moved the code to the large one. The test was updated; the code is unchanged. No spec change.

### Review fixes (verify, 2026-10-05)
- antislop audit-035: no findings.
- Spec (medium): the `SKIP LOCKED` fallback could leave a user without a featured account when another transaction held the only candidate row. `RestoreFeaturedAccountJob` now repairs it after commit. The job is tested (restores, leaves an existing flag, no holding rows). The skip-locked branch that dispatches the job is not exercised: it needs a lock held from a second Postgres connection on committed rows.
- Spec (low): the Verified prompt said the featured account "is the first one people see", but the profile cards come with P2-22. It now says it "is listed first on your profile", which is true of the own list today.
- Spec (low): Vitest now covers the dialog's submitting and throttled states and the featured button's pending state.
- Spec (low): `HOLDING` moved to `CocAccountStatus` so Support no longer imports a Service.
- Security (low): `feature()` could deadlock in a three-way race, because a row a fallback featured after its select is updated while it holds the user lock. The transaction now retries up to 3 times.
- Security (low): the fallback could leave a user with no featured account when a sync held the row. The same `RestoreFeaturedAccountJob` fix covers it.
