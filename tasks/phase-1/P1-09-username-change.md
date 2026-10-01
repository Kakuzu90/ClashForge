---
id: P1-09
title: Add username change with a 30-day limit, 90-day reservation of the old name and /u/{old} redirects
phase: 1
status: done
depends_on: [P1-04, P1-08]
---

# Username change

## Spec refs
- Core: specs/07 `users` (`username`, `username_changed_at`), `username_history` (90-day rule, `reserved_forever`); specs/04 §4 (Registration username rules, Session security re-confirmation, Rate limits), §3 (settings writes open to restricted, not suspended), "IDOR prevention" (users addressed by username)
- Plus: specs/11 "Account enumeration" (one `Profile/NotFound` for every miss on `/u/{username}`), §3 (security log); specs/07 `audit_logs`; specs/08 §6 (deletion reserves the current name forever); specs/21 §3 `profile:{username}`; specs/17 §6 (canonical profile URL); specs/18 §6 Settings; specs/19 §4 `/settings/*`, `/u/{username}`
- FR: FR-PROFILE-7; FR-AUTH-1 (username rules)
- Edge cases: specs/23 §1 "Username released and immediately re-registered by someone else", "A deleted account's original username is requested again"

## Scope
- **Migration**: none. `users.username_changed_at` and `username_history` already exist (P1-01, P1-11).
- **Domain** (`Domain/Auth`):
  - `UsernameFieldRules`: the shared rules gain "held in `username_history`" (forever, or released within 90 days by another account, Open question 2); used by registration and the change.
  - `UsernameChangeService::change(User, username, password)`: locks the account row, checks the password (Open question 1), the 30-day rule from `username_changed_at` (none yet = allowed) and the rules; updates `username` + `username_changed_at` and inserts the old name into `username_history` (`released_at = now`) in one transaction; a unique-index race answers "taken". After commit: `CacheInvalidator::profile()` for both names. Logs `auth.username_changed`; writes `audit_logs` `user.username_changed` (Open question 4).
  - `RegistrationService`'s after-insert check covers 90-day reservations as well as permanent ones (specs/08 §6 race).
  - `UserLookupService::redirectTarget(old)`: the current listed account that released `old` within 90 days (latest row wins, never `reserved_forever`).
- **Policy + Form Request**: `UserPolicy::changeUsername` (own account, settings-write standing, Open question 6); `ChangeUsernameRequest` (username normalised lowercase + trimmed, current password).
- **HTTP**: `PUT /settings/profile/username` (`throttle:password-confirm`, `global-write`). `ProfileController@show`: when `/u/{name}` finds no listed account, a reservation redirects to `/u/{current}`, only if that profile would render for this viewer, otherwise the same 404 (Open question 3).
- **UI**: `Settings/Profile` gains a "Username" flat card: current `@name`, the new-name field with the rule hint, current password, what happens to the old name; locked state with the date the next change opens; idle, saving, errors (focus on field), throttled.
- **Config keys**: `platform.auth.username_change_days` (30), `platform.auth.username_reservation_days` (90).

## Out of scope
- A live availability check (specs/11 limits one; none exists yet, Open question 5); admin renaming a user; showing username history on the admin user detail (the audit entry appears in its audit trail); search reindex of the name (P3-05).

## Acceptance criteria
- Functional: FR-PROFILE-7; one change per 30 days; the old name redirects for 90 days, then 404s (or shows whoever took it after the hold).
- Authorization: own account only; restricted can change, suspended / banned / pending deletion cannot (04 §1, §3); the server re-checks the 30-day rule.
- Enumeration: a redirect never reveals a hidden, banned or pending-deletion account (11).
- Edge cases: 23 §1 rows; two accounts racing for one name, one wins; a registration racing a release cannot take the held name; a chain (a → b → c) sends `/u/a` and `/u/b` to `/u/c`.
- States: card idle, saving, success, errors, locked, throttled; 375 px and desktop.

## Tests
- Feature (`assertInertia`): change (happy, same name, taken, reserved list, held by another, held forever, own old name, 30-day lock, wrong password); redirect (visible, hidden → 404, members-only for guest → 404, expired, chain, banned target); registration blocked by a 90-day hold; Settings/Profile props.
- Security: mass assignment (`username_changed_at`, `role`); status matrix; `password-confirm` limiter; same 404 body for hidden-target redirect and unknown name; both cache keys forgotten.
- Unit: hold window maths in `UsernameFieldRules`.
- Vitest: Username card locked / form switch, if it carries logic.

## Notes

### Open questions
Resolved by the owner, 2026-10-01: all six as recommended.
1. **Re-confirmation.** Specs do not say. Recommend the current password inline (as email and deletion), sharing `password-confirm`: a stolen session could otherwise rename the account and hold its real name away from it for 90 days.
2. **Own old name.** Recommend the 90-day hold blocks other accounts only; the owner may switch back (still one change per 30 days).
3. **Redirect shape.** Recommend 301 with `Cache-Control: no-store`, so the 90-day end and privacy changes take effect at once; target hidden from this viewer → the same `Profile/NotFound` 404. Specs/23 "afterwards return 404 rather than the new person's profile" read as: the redirect stops after 90 days; a name someone else then takes shows them.
4. **Record.** Recommend `audit_logs` `user.username_changed` (before/after username, actor = the user) plus `auth.username_changed` in the security log, so moderators can trace handles; no email or in-app notice (specs/16 lists none).
5. **Availability check.** specs/11 mentions a rate-limited boolean check, but none exists. Recommend not building one now (submit-time validation only) and leaving that line as the rule for when one is added.
6. **Unverified accounts.** FR-AUTH-4 keeps settings writes open to unverified emails, but each change holds a name for 90 days. Recommend requiring a verified email for this one write, to stop throwaway accounts squatting names.

### Decisions and divergences (implement, 2026-10-01)
1. Migration after all: `users.username_changed_at` was in specs/07 but never created; `2026_10_01_000009` adds it (null default on the model). specs/07 already matched.
2. `UsernameFieldRules::isHeld(name, exceptUserId)` replaces `isPermanentlyReserved`: forever rows hold for everyone, 90-day rows for every account but the releaser. `forChange(userId)` ignores the account's own row; registration's before and after-insert checks use `isHeld`. Messages share `UsernameFieldRules::TAKEN`. synced → specs/04 §4 Registration, specs/07 `username_history`, specs/08 §6.
3. `UsernameChangeService::change` locks the row, authorizes, checks the password, the same name, the wait and the hold again under the lock; writes `username`, `username_changed_at`, the history row and the audit entry in one transaction; forgets both cache keys after commit. `settingsFor()` builds the card props. synced → specs/04 §4 "Username change", specs/05 §2, specs/21 §3.
4. Redirect: `UserLookupService::renamedFrom()` (latest unexpired release, nobody holding the name, listed account) and `PublicProfileReadModel::redirectFor()` (privacy check); `/u/{old}` answers 301 with `Cache-Control: no-store`. synced → specs/11 "Account enumeration", specs/23 §1, specs/17 §6, specs/02 FR-PROFILE-7.
5. `PUT /settings/profile/username` with `throttle:password-confirm` (a breach is a `current_password` field error, as for the other forms); a correct password marks the session confirmed. synced → specs/04 §4 Session security + Rate limits, specs/11 "CSRF", specs/19 §4.
6. Records: `audit_logs` `user.username_changed` (actor = the account, before/after username, `state-info`); security log `auth.username_changed` with `from` / `to`. synced → specs/07 `audit_logs`, specs/11 §3.
7. UI: `SettingsUsernameSection` as a flat card between the avatar and the profile form on `Settings/Profile`; states: form, locked (date in the viewer's locale), unverified (link to `/email/verify`). R-31: the same flat card and dense form as `SettingsEmailSection`; no new variant, so `/dev/components` is unchanged. Limits (`minLength`, `maxLength`, both windows) come from the server. synced → specs/18 §6.

### Review fixes (verify, 2026-10-01)
- Spec (medium): a change could take a name another account released in a racing transaction; `change()` now re-checks the hold after its update, as registration does after its insert. Tests for that race, the unique-index branch and registration's after-insert check.
- Spec (low, plausible): a suspended account saw the card with no reason; it now says changes are unavailable while suspended. Tests for a suspended redirect target and a plain active account in the status matrix.
- Security (low): authorization now reads the locked row, so a sanction committed after the request started counts; the username rules `bail`, so bytes Postgres cannot store get a field error; a swap deadlock (40P01) answers "taken". Tests for the first two. `AccountDeletionService::request` looks similar but is unaffected: `requestDeletion` reads status from the locked target row.
- Security (low): spec drift (inline-password list, `password-confirm` row, security log) closed by the sync above.
- Open question 6 (verified email) synced → specs/04 §3 write gating, specs/02 FR-AUTH-4; Open question 5 → specs/11 "Account enumeration".
- antislop audit-022: no findings.
