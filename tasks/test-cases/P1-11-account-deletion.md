# P1-11 Account deletion and the Danger zone: test cases

Source: tasks/phase-1/P1-11-account-deletion.md · specs/04 §1, §3–§4, specs/08 §6, specs/11 "CSRF" / §5, specs/23 §1, FR-AUTH-9, FR-AUTH-4, NFR-PRIV-2

Notes for the tester:
- Grace window `platform.auth.deletion_grace_days` = 30. An account is due when
  `users.deletion_requested_at` ≤ now − 30 days. "Move time" by editing `deletion_requested_at` in
  Adminer, e.g. SQL command
  `UPDATE users SET deletion_requested_at = now() - interval '31 days' WHERE email = 'test@example.com';`
- Run the nightly job by hand: `docker compose exec app php artisan platform:anonymize-deleted`
  (add `--dry-run` to count only). It is scheduled daily at 04:00.
- Current-password guesses share the `password-confirm` limit (5 per minute, 20 per hour per account)
  with the Security, username and email-change forms.
  Reset the limiters between cases with `docker compose exec app php artisan cache:clear`.
- After a full anonymisation, reseed (`migrate:fresh --seed`) before cases that need `test_user`.

## Happy path

### TC-P1-11-001: Danger zone page in settings
- Priority: High · Type: Functional
- Ref: specs/18 §6; task Scope "UI"
- Preconditions: signed in as `test_user`
- Steps:
  1. Avatar menu → "Settings" → "Danger zone" (last item of the settings nav).
- Expected: URL `/settings/danger-zone`, title "Danger zone". A red-bordered section "Delete your account" with "Your profile will be hidden and every device signed out. Sign in again within 30 days to cancel deletion." and "After 30 days, your profile, avatar and notifications will be removed and your account anonymised. Your username stays reserved. Moderation and audit records are kept. Registering again will not restore your data.". Form: "Current password", checkbox "I understand what account deletion removes.", red button "Request account deletion".

### TC-P1-11-002: Request deletion
- Priority: High · Type: Functional
- Ref: FR-AUTH-9; AccountDeletionService::request
- Preconditions: signed in as `test_user` in browser A
- Steps:
  1. Enter `password`, tick the checkbox, click "Request account deletion".
  2. In Adminer open `test_user`'s `users` row.
- Expected: the browser lands on `/login` (signed out) with the notice "Deletion requested. Your profile is hidden and every device was signed out. Sign in within 30 days to cancel.". Adminer: `status` = `pending_deletion`, `deletion_requested_at` = now, `deletion_previous_status` = `active`, `status_reason` and `status_expires_at` NULL, `deleted_at` NULL, `email` and `username` unchanged. Security log `auth.deletion_requested`.

### TC-P1-11-003: Request ends every session and remember-me
- Priority: High · Type: Security
- Ref: Owner decision 1; specs/04 §4
- Preconditions: `test_user` signed in on browser A and on browser B with "Keep me signed in" ticked, B on `/settings/profile`
- Steps:
  1. In A request deletion (TC-P1-11-002).
  2. In B reload.
  3. In B delete the `clash-commons-session` cookie (keep `remember_web_*`) and reload.
  4. In Adminer filter `sessions` by `test_user`'s `user_id`.
- Expected: B lands on `/login` at step 2 and stays signed out at step 3. No `sessions` rows remain for the account. In A the `remember_since` cookie is removed.

### TC-P1-11-004: Pending deletion hides the public profile
- Priority: High · Type: Functional
- Ref: FR-AUTH-9; specs/04 §1
- Preconditions: TC-P1-11-002 done
- Steps:
  1. As a guest (or as `test_moderator`), open `/u/test_user`.
- Expected: the not-found page "We couldn't find that profile" / "Check the username in the link, or head back to the home page." with "Go to the home page", identical to an unknown username (e.g. `/u/nobody_xyz`).

### TC-P1-11-005: Pending deletion keeps the email reserved
- Priority: High · Type: Functional
- Ref: FR-AUTH-9; specs/23 §1
- Preconditions: TC-P1-11-002 done; signed out
- Steps:
  1. Open `/register`, enter username `test_user_two`, email test@example.com, a valid new password twice, wait a few seconds, submit.
  2. Check Mailpit and Adminer `users`.
- Expected: the "Check your email" page shows (no error revealing the address). Mailpit: "Someone tried to sign up with your email" to test@example.com, greeting "Hi test_user,". No new `users` row; `test_user_two` does not exist.

### TC-P1-11-006: Pending deletion keeps the username taken
- Priority: Medium · Type: Functional
- Ref: specs/08 §6
- Preconditions: TC-P1-11-002 done; signed out
- Steps:
  1. On `/register` enter username `test_user` with a new email `fresh1@example.com`, submit.
- Expected: "That username is taken. Pick another." under the username field; no account created.

### TC-P1-11-007: A fresh password sign-in cancels deletion
- Priority: High · Type: Functional
- Ref: FR-AUTH-9; Owner decision 1
- Preconditions: TC-P1-11-002 done
- Steps:
  1. Sign in as test@example.com / `password`.
  2. In Adminer open the `users` row.
  3. As a guest open `/u/test_user`.
- Expected: sign-in succeeds normally. Adminer: `status` = `active`, `deletion_requested_at` and `deletion_previous_status` NULL. The profile is visible again. Security log `auth.deletion_cancelled`.

### TC-P1-11-008: Nightly command anonymises a due account
- Priority: High · Type: Functional
- Ref: FR-AUTH-9; specs/08 §6; specs/19 §7
- Preconditions: fresh seed; `test_user` has a display name, bio, an avatar and at least one notification; note its `ulid`, `id` and avatar `media` row; request deletion (TC-P1-11-002); then set `deletion_requested_at` to now − 31 days in Adminer
- Steps:
  1. Run `docker compose exec app php artisan platform:anonymize-deleted`.
  2. Inspect in Adminer: `users`, `profiles`, `privacy_settings`, `user_stats`, `notifications`, `sessions`, `password_reset_tokens`, `media`, `username_history` for that user id.
- Expected: output "Anonymised 1 accounts.". `users`: `username` = `deleted_user_<ulid in lowercase>`, `email` = a 64-character hex hash, `password`, `remember_token`, `email_verified_at`, `pending_email`, `last_login_at`, `last_login_ip_hash` NULL, `status` = `banned`, `status_reason` NULL, `deleted_at` set. `profiles`: display name, bio, country, timezone, avatar NULL; languages and socials empty. `privacy_settings` back to defaults; `user_stats` counts zero. No `notifications`, `sessions` or `password_reset_tokens` rows for the account. The avatar `media` rows go to `deleting` and are removed once the queue runs. `username_history` has a row `username` = `test_user`, `reserved_forever` = true.

### TC-P1-11-009: Anonymisation audit entry, without personal data
- Priority: High · Type: Functional
- Ref: specs/07 `audit_logs`; Implementation decisions "user.anonymised"
- Preconditions: TC-P1-11-008 done; signed in as `test_admin`
- Steps:
  1. Open `/admin/audit` and find the newest entry.
  2. Open its details / diff.
- Expected: action "Account anonymised", actor "Console", subject the user. Before `status: pending_deletion`, after `status: banned`, context `command: platform:anonymize-deleted`. No email, username, IP or profile data appears in the entry.

### TC-P1-11-010: Anonymised account cannot sign in and its profile is gone
- Priority: High · Type: Functional
- Ref: specs/08 §6
- Preconditions: TC-P1-11-008 done
- Steps:
  1. Sign in as test@example.com / `password`.
  2. Open `/u/test_user` and `/u/deleted_user_<ulid>`.
- Expected: "That email and password do not match. Check both and try again." (no ban message). Both profile URLs show "We couldn't find that profile"; `/u/test_user` does not redirect anywhere.

### TC-P1-11-011: The email is free again after anonymisation
- Priority: High · Type: Functional
- Ref: specs/23 §1 "same-email registration after 30 days"
- Preconditions: TC-P1-11-008 done; signed out
- Steps:
  1. Register with username `returning_player`, email test@example.com, a valid password.
  2. Check Mailpit and Adminer.
- Expected: "Check your email" page; Mailpit has a verification email to test@example.com addressed to `returning_player`. A new `users` row exists with a new `ulid`; the anonymised row is unchanged. After verifying and signing in, `returning_player` has an empty profile: nothing from the old account is restored.

### TC-P1-11-012: The original username stays reserved forever
- Priority: High · Type: Functional
- Ref: Owner decision 3; specs/07 `username_history`
- Preconditions: TC-P1-11-008 done
- Steps:
  1. On `/register` try username `test_user` (and `TEST_USER`) with a new email.
  2. Signed in as another account with a verified email and no recent username change, try to change the username to `test_user` on `/settings/profile`.
- Expected: both show "That username is taken. Pick another.".

## Validation

### TC-P1-11-013: Missing confirmation checkbox
- Priority: High · Type: Validation
- Ref: RequestAccountDeletionRequest
- Preconditions: signed in as `test_user` on `/settings/danger-zone`
- Steps:
  1. Enter `password`, leave the checkbox unticked, submit.
- Expected: "Confirm that you understand what account deletion removes." on the checkbox; focus moves to the first field in error; the password field is cleared; account still `active`.

### TC-P1-11-014: Missing current password
- Priority: High · Type: Validation
- Ref: RequestAccountDeletionRequest
- Preconditions: on `/settings/danger-zone`
- Steps:
  1. Leave the password empty, tick the checkbox, submit.
- Expected: "Enter your current password." under "Current password"; focus moves there; account still `active`.

### TC-P1-11-015: Wrong current password
- Priority: High · Type: Validation
- Ref: AccountDeletionService::request
- Preconditions: on `/settings/danger-zone`
- Steps:
  1. Enter `wrong-password`, tick the checkbox, submit.
- Expected: "That is not your current password." under "Current password"; field cleared; still signed in and `active`. Security log `auth.password_confirm_failed`.

### TC-P1-11-016: Both fields missing
- Priority: Low · Type: Validation
- Ref: RequestAccountDeletionRequest
- Preconditions: on `/settings/danger-zone`
- Steps:
  1. Submit the empty form.
- Expected: both messages from TC-P1-11-013 and TC-P1-11-014; focus on "Current password".

### TC-P1-11-017: Password guesses are throttled (5 per minute)
- Priority: High · Type: Security
- Ref: task Scope "password-confirm throttles"; `password_confirm_per_minute` = 5
- Preconditions: signed in as `test_user`; no password attempts in the last hour
- Steps:
  1. Submit 5 wrong passwords (checkbox ticked) within a minute.
  2. Submit a 6th time with `password`.
  3. Wait the stated time, submit with `password`.
- Expected: attempt 6 shows "Too many attempts. Try again in N seconds." under "Current password" and the account stays `active`. Step 3 succeeds (deletion requested). Security log `auth.rate_limited` with `limiter: password-confirm`.

## Authorization / account status

### TC-P1-11-018: Guest cannot open the Danger zone
- Priority: Medium · Type: Authorization
- Ref: routes/web/settings.php
- Preconditions: signed out
- Steps:
  1. Open `/settings/danger-zone`.
- Expected: redirect to `/login`.

### TC-P1-11-019: Password is required server-side, whatever the UI sends
- Priority: High · Type: Security
- Ref: Acceptance "password confirmation enforced independently of UI"
- Preconditions: signed in as `test_user` on `/settings/danger-zone`
- Steps:
  1. Submit once with a wrong password and the checkbox ticked (to capture the request). In DevTools → Network right-click `DELETE /settings/danger-zone` → Copy → Copy as fetch.
  2. Paste in the Console, remove `current_password` from the JSON body, run.
  3. Run again with `"current_password":""`.
- Expected: neither request changes the account (Adminer `status` = `active`); the session stays signed in. Each attempt counts toward the `password-confirm` limit.

### TC-P1-11-020: Only your own account can be deleted
- Priority: Medium · Type: Authorization
- Ref: UserPolicy::requestDeletion; specs/04 §3
- Preconditions: signed in as `test_user`
- Steps:
  1. Capture `DELETE /settings/danger-zone` as in TC-P1-11-019 and add `"user_id": <test_moderator id>`, `"username":"test_moderator"` to the body with `test_user`'s correct password.
- Expected: the extra fields are ignored: `test_user` is the account set to `pending_deletion`; `test_moderator` is untouched. There is no route that takes another account's id.

### TC-P1-11-021: Unverified account may request deletion
- Priority: High · Type: Authorization
- Ref: FR-AUTH-4; Acceptance "unverified/restricted accounts may request"
- Preconditions: in Adminer set `test_user.email_verified_at` = NULL; sign in as `test_user`
- Steps:
  1. Open `/settings/danger-zone` (the verify-email banner shows) and request deletion.
- Expected: the form is available and the request succeeds as in TC-P1-11-002.

### TC-P1-11-022: Restricted account may request; cancelling restores the restriction
- Priority: High · Type: Authorization
- Ref: Acceptance "cancellation preserves effective sanctions"; Implementation decisions `deletion_previous_status`
- Preconditions: in Adminer set `test_user.status` = `restricted`, `status_reason` = "Low-effort posts", `status_expires_at` = now + 5 days; signed in as `test_user`
- Steps:
  1. Request deletion.
  2. Check Adminer.
  3. Sign in again; check Adminer.
- Expected: step 2: `status` = `pending_deletion`, `deletion_previous_status` = `restricted`, `status_reason` and `status_expires_at` kept. Step 3: `status` = `restricted` with the same reason and end date; deletion columns NULL.

### TC-P1-11-023: A restriction that ended during the grace window is not restored
- Priority: Medium · Type: Edge case
- Ref: AuthenticationService::cancelDeletion (effective status)
- Preconditions: TC-P1-11-022 step 2 state (pending deletion over a restriction)
- Steps:
  1. In Adminer set `status_expires_at` to yesterday.
  2. Sign in as `test_user`; check Adminer.
- Expected: `status` = `active`, `status_reason` and `status_expires_at` NULL.

### TC-P1-11-024: Suspended account sees deletion as unavailable
- Priority: High · Type: Authorization
- Ref: Owner decision 1 "suspended self-deletion blocked"
- Preconditions: suspend `test_user` from the admin panel (TC-P1-02-016) and sign in as `test_user`
- Steps:
  1. Open `/settings/danger-zone`.
- Expected: the page loads (no redirect to the notice); the explanation is shown, but instead of the form: "Account deletion is unavailable while your account is suspended."

### TC-P1-11-025: Suspension applied while the form is open blocks the submit
- Priority: High · Type: Authorization
- Ref: specs/04 §3 #2; EnsureAccountIsActive
- Preconditions: `test_user` signed in on `/settings/danger-zone` with the form filled (password + checkbox)
- Steps:
  1. In Adminer set `status` = `suspended`, `status_reason` = "Test", `status_expires_at` = now + 1 day.
  2. Click "Request account deletion".
- Expected: WriteBlocked page "Your account is suspended" / "Changes are off until the suspension ends." (HTTP 403). Adminer: still `suspended`, `deletion_requested_at` NULL.

### TC-P1-11-026: Ban applied while the form is open signs the session out
- Priority: Medium · Type: Authorization
- Ref: specs/04 §1
- Preconditions: as TC-P1-11-025, but set `status` = `banned`
- Steps:
  1. Click "Request account deletion".
- Expected: redirect to `/login` with "This account is banned, so it cannot sign in."; no deletion requested.

### TC-P1-11-027: Signing in during the window does not undo a ban
- Priority: Medium · Type: Edge case
- Ref: Acceptance "cancellation preserves effective sanctions"
- Preconditions: TC-P1-11-002 done; in Adminer set `status` = `banned` (simulating staff action during the window)
- Steps:
  1. Sign in as test@example.com / `password`.
- Expected: refused with the banned message; `status` stays `banned`.

## Edge cases

### TC-P1-11-028: 30-day boundary
- Priority: High · Type: Edge case
- Ref: `deletion_grace_days` = 30; Tests "30-day boundary"
- Preconditions: TC-P1-11-002 done
- Steps:
  1. Set `deletion_requested_at` = `now() - interval '29 days 23 hours'`; run `platform:anonymize-deleted --dry-run`, then without `--dry-run`.
  2. Set `deletion_requested_at` = `now() - interval '30 days 1 minute'`; run `--dry-run`, then without.
- Expected: step 1: "Would anonymise 0 accounts." then "Anonymised 0 accounts."; the account is still `pending_deletion`. Step 2: "Would anonymise 1 accounts." then "Anonymised 1 accounts.".

### TC-P1-11-029: Dry run changes nothing
- Priority: High · Type: Functional
- Ref: task Scope "`--dry-run`"
- Preconditions: TC-P1-11-002 done, `deletion_requested_at` moved to now − 31 days
- Steps:
  1. Run `docker compose exec app php artisan platform:anonymize-deleted --dry-run`.
  2. Check Adminer and `/admin/audit`.
- Expected: "Would anonymise 1 accounts."; the `users` row, profile, media and notifications are unchanged; no audit entry.

### TC-P1-11-030: Rerunning the command is idempotent
- Priority: High · Type: Edge case
- Ref: Acceptance "reruns create no duplicate audit entry"
- Preconditions: TC-P1-11-008 done
- Steps:
  1. Run `platform:anonymize-deleted` again.
  2. Check `/admin/audit` and `username_history`.
- Expected: "Anonymised 0 accounts."; still exactly one "Account anonymised" entry and one `reserved_forever` row for `test_user`.

### TC-P1-11-031: Sign-in before the nightly run wins over anonymisation
- Priority: High · Type: Edge case
- Ref: Acceptance "cancellation racing the nightly pipeline cannot anonymise a restored account"
- Preconditions: TC-P1-11-002 done; `deletion_requested_at` moved to now − 31 days
- Steps:
  1. Sign in as `test_user` (cancels).
  2. Run `platform:anonymize-deleted`.
- Expected: "Anonymised 0 accounts."; the account is `active` with its username, email and profile intact.

### TC-P1-11-032: Only due accounts are processed in a mixed batch
- Priority: Medium · Type: Edge case
- Ref: AccountDeletionService::due
- Preconditions: `test_user` pending deletion 31 days ago; `test_moderator` pending deletion 5 days ago (set both in Adminer: `status` = `pending_deletion`, `deletion_previous_status` = `active`, `deletion_requested_at`)
- Steps:
  1. Run `platform:anonymize-deleted`.
- Expected: "Anonymised 1 accounts."; only `test_user` is anonymised; `test_moderator` stays `pending_deletion` with its data.

### TC-P1-11-033: Moderation and audit records survive anonymisation
- Priority: High · Type: Edge case
- Ref: Owner decision 2; specs/08 §6
- Preconditions: fresh seed; as `test_admin` suspend `test_user` for 1 day, then "Lift the suspension"; note the `user_sanctions` and `moderation_actions` rows and the audit entries for `test_user`; then sign in as `test_user` and request deletion; move `deletion_requested_at` to now − 31 days
- Steps:
  1. Run `platform:anonymize-deleted`.
  2. Check `user_sanctions`, `moderation_actions`, `audit_logs` in Adminer; open the user's detail page in `/admin/users` (search `deleted_user_`).
- Expected: every earlier sanction, moderation action and audit row is still there and unchanged; the admin detail still shows the sanction history and audit trail, plus the new "Account anonymised" entry.

### TC-P1-11-034: Quarantined media is kept
- Priority: Low · Type: Edge case
- Ref: task Scope "preserve quarantine"; specs/10 §9
- Preconditions: `test_user` owns two `media` rows; in Adminer set one row's `status` = `quarantined`; request deletion and move `deletion_requested_at` to now − 31 days
- Steps:
  1. Run `platform:anonymize-deleted`; let the queue run.
- Expected: the non-quarantined row is removed; the quarantined row remains with `status` = `quarantined`.

### TC-P1-11-035: The command is scheduled daily at 04:00
- Priority: Medium · Type: Functional
- Ref: specs/20 §3; routes/console.php
- Preconditions: none
- Steps:
  1. Run `docker compose exec app php artisan schedule:list`.
- Expected: a row `0 4 * * *  php artisan platform:anonymize-deleted`.

## UI states

### TC-P1-11-036: Submitting state
- Priority: Medium · Type: UI state
- Ref: Acceptance States "submitting"
- Preconditions: on `/settings/danger-zone`; DevTools throttling "Slow 4G"
- Steps:
  1. Enter `password`, tick the box, submit.
- Expected: while the request runs the button shows its loading state and the password field and checkbox are disabled; a second click does not send a second request.

### TC-P1-11-037: Keyboard only
- Priority: Medium · Type: UI state
- Ref: specs/18 §8
- Preconditions: on `/settings/danger-zone`
- Steps:
  1. Using only Tab, Space and Enter: move to "Current password", type a wrong password, Tab to the checkbox, press Space, Tab to the button, press Enter.
- Expected: every control has a visible focus ring in that order; Space toggles the checkbox; after the error, focus lands on "Current password".

### TC-P1-11-038: Layout at 375 px and desktop
- Priority: Medium · Type: UI state
- Ref: Acceptance States "phone/desktop layout"
- Preconditions: on `/settings/danger-zone`
- Steps:
  1. View at 375 px and at ≥1280 px, with and without validation errors; also view the suspended variant (TC-P1-11-024).
- Expected: the red border wraps the whole section; text wraps, no horizontal scroll; the button and checkbox are fully tappable (not under the bottom tab bar). No console errors.

### TC-P1-11-039: Danger zone props expose nothing extra
- Priority: Medium · Type: Security
- Ref: specs/11 "Data exposure via page props"
- Preconditions: signed in as `test_user`
- Steps:
  1. Navigate to Danger zone from another settings page and inspect the Inertia response `props`.
- Expected: page props are only `graceDays` (30) and `canRequestDeletion` (true), plus the shared props; no email, status, deletion dates or password data.
