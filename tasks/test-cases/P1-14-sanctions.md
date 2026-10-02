# P1-14 Sanctions: suspend, ban, lift: test cases

Source: tasks/phase-1/P1-14-sanctions.md (Scope, Decisions 1–20, Review fixes); specs/12 §4, §6; specs/04 §1–2; specs/16 §2; specs/20 §3.

## Happy path

### TC-P1-14-001: Suspend a user for 7 days from the user detail
- Priority: High · Type: Functional
- Ref: FR-ADMIN-3, specs/12 §4, §6; task Scope "HTTP + UI"
- Preconditions: fresh seed; signed in as `test_admin`; queue containers running; Mailpit empty.
- Steps:
  1. Open `/admin/users`, open `test_user`.
  2. In the "Sanctions" section press "Suspend".
  3. Check the dialog: title "Suspend test_user", fields "Reason" (placeholder "Choose a reason"), "Length" (default 7, suffix "days", hint "1 to 90 days."), "Message to test_user" (hint "Shown to them on the notice and in the email.", character counter), "Internal note" (hint "Staff only. What happened and why.").
  4. Before typing a message, check the "What they will see" box.
  5. Choose reason "Spam", keep 7 days, message `Posting invite spam in profiles.`, internal note `Three reports on 2 Oct, screenshots in ticket 42.`
  6. Change the length to 3, then back to 7, watching the preview and the submit button.
  7. Press "Suspend for 7 days".
- Expected: step 4 the preview reads "Your account is suspended.", "Reason: Your message appears here.", "Ends: <now + 7 days>"; step 6 the "Ends" line and the button label ("Suspend for 3 days" / "Suspend for 7 days") follow the length live. After submit: the dialog closes, a success toast "test_user is suspended." shows bottom right and closes by itself after about 5 seconds (hovering it pauses the timer; the same applies to the ban and lift toasts below); the Account section shows the "Suspended" pill, "Reason shown to them: Posting invite spam in profiles." and "Ends <date>"; the history shows a "Suspension" entry with the "Active" pill, "Spam", "Told them", "Internal note", "Issued <date> by test_admin" and "Ends <date>"; the panel now shows "Lift the suspension", "Suspend" and "Ban".

### TC-P1-14-002: A suspension writes the sanction, moderation action, status, audit entry and security line
- Priority: High · Type: Functional
- Ref: specs/12 §6 "all sanctions write user_sanctions + moderation_actions + audit_logs"; FR-MOD-5; Decisions 2, 10
- Preconditions: TC-P1-14-001 done.
- Steps:
  1. In Adminer, open `users` for `test_user`.
  2. Open `user_sanctions` filtered by that `user_id`.
  3. Open `moderation_actions` filtered by `target_user_id` = that id.
  4. Open `/admin/audit` (and the "Audit trail" section on the user detail).
  5. Run `docker compose exec app sh -c 'tail -n 5 storage/logs/security-*.log'`.
- Expected: `users.status` = `suspended`, `status_reason` = the message, `status_expires_at` ≈ now + 7 days. One `user_sanctions` row: `type` = `suspension`, `reason_code` = `spam`, `public_reason`, `internal_note`, `issued_by` = test_admin's id, `starts_at` = now, `expires_at` = starts_at + 7 days, `lifted_at` null, `moderation_action_id` set. One `moderation_actions` row: `action` = `suspend`, `target_type` = `user`, `target_id` = test_user's id, `reason_code` = `spam`, `note` = the internal note, `duration_hours` = 168, `actor_id` = test_admin, `ip_hash` set. The audit log has a "Sanction applied" entry by test_admin on test_user, before `{status: active}` and after `{status: suspended, reason, until}`, context with `sanction: suspension`, `reason_code: spam`, `days: 7`. The security log has a `moderation.sanction_applied` line with actor, user, `type: suspension` and `until`.

### TC-P1-14-003: Ban a user
- Priority: High · Type: Functional
- Ref: FR-ADMIN-3, specs/12 §6; Decisions 6, 18
- Preconditions: fresh seed; signed in as `test_admin` in browser A; `test_user` signed in in browser B; Mailpit empty.
- Steps:
  1. In browser A open test_user's detail, press "Ban".
  2. Check the dialog: title "Ban test_user", no "Length" field; preview "Your account is banned and can no longer sign in.", "Reason: …", "Every device they are signed in on is signed out now."
  3. Choose reason "Scam", message `Selling fake gem codes.`, internal note `Confirmed with two victims.`, press "Ban test_user".
- Expected: toast "test_user is banned."; the Account section shows the "Banned" pill, "Reason shown to them: Selling fake gem codes." and "No end date"; "Signed in now" reads "0 browsers"; the history shows a "Ban" entry, "Active", "Ends: No end date"; the panel shows only "Lift the ban". Browser A (the admin) stays signed in.

### TC-P1-14-004: A ban writes its records and ends the account's sessions
- Priority: High · Type: Functional
- Ref: specs/04 §1; Decision 6
- Preconditions: note test_user's `remember_token` in Adminer before TC-P1-14-003, then run it.
- Steps:
  1. In Adminer check `users`, `user_sanctions`, `moderation_actions` and `sessions` for test_user.
  2. Check `/admin/audit`.
- Expected: `users.status` = `banned`, `status_reason` = the message, `status_expires_at` null, `remember_token` differs from the noted value. `user_sanctions` row `type` = `ban`, `expires_at` null. `moderation_actions` row `action` = `ban`, `target_type` = `user`, `duration_hours` null. No `sessions` row has test_user's `user_id`; test_admin's session row is still there. Audit entry "Sanction applied", context `sanction: ban`, no `days`.

### TC-P1-14-005: Lift a suspension
- Priority: High · Type: Functional
- Ref: specs/12 §6 "lifting needs a reason"; Decisions 1, 2
- Preconditions: test_user suspended (TC-P1-14-001); signed in as `test_admin`; Mailpit cleared.
- Steps:
  1. On test_user's detail press "Lift the suspension".
  2. Check the dialog: title "Lift the suspension", field "Why lift it" (hint "Staff only."), text "test_user gets an email saying their suspension has been lifted."
  3. Enter `Reports were a misunderstanding.` and press "Lift the suspension".
  4. Check Adminer `user_sanctions`, `moderation_actions`, `users`, and `/admin/audit`.
- Expected: toast "The suspension is lifted."; status pill "Active"; the history entry shows the "Lifted" pill and "Lifted <date> by test_admin: Reports were a misunderstanding."; the panel shows "Suspend" and "Ban" only. The sanction row has `lifted_by` = test_admin and `lifted_at` set; a new `moderation_actions` row has `action` = `lift`, `target_type` = `user_sanction`, `target_id` = the sanction id, `note` = the lift reason. `users.status` = `active`, reason and end cleared. Audit "Sanction lifted" with context `sanction: suspension` and the note; security line `moderation.sanction_lifted`.

### TC-P1-14-006: Lift a ban, then the user signs in again
- Priority: High · Type: Functional
- Ref: specs/12 §6; Decision 3 (`unban` stays for bans)
- Preconditions: test_user banned (TC-P1-14-003).
- Steps:
  1. As test_admin press "Lift the ban", enter `Appeal accepted by email.`, press "Lift the ban".
  2. In Adminer check the new `moderation_actions` row.
  3. In browser B sign in as test@example.com / `password`.
- Expected: toast "The ban is lifted."; history "Ban" entry "Lifted … by test_admin: Appeal accepted by email."; the new action has `action` = `unban`, `target_type` = `user_sanction`. Sign-in succeeds and the home page loads normally.

### TC-P1-14-007: A super admin suspends an admin
- Priority: High · Type: Authorization
- Ref: specs/04 §2 rule 1 (strictly outrank)
- Preconditions: signed in as `test_super_admin`.
- Steps:
  1. Open `/admin/users`, open `test_admin`.
  2. Suspend for 1 day with any reason, message and note.
- Expected: the panel shows "Suspend" and "Ban"; the suspension succeeds ("test_admin is suspended."). Lift it afterwards so the next cases have an active admin.

## Validation

### TC-P1-14-008: Required fields on the suspend dialog
- Priority: High · Type: Validation
- Ref: specs/04 rule 2; task Scope "Form Requests per action"
- Preconditions: signed in as `test_admin`, on test_user's detail (active account).
- Steps:
  1. Press "Suspend", clear the length, leave everything else empty, press the submit button ("Suspend for … days").
  2. Enter only spaces in the message and the note, choose a reason, set length 7, submit.
- Expected: step 1 shows inline errors "The reason field is required.", "The length field is required.", "The message to the account holder field is required.", "The internal note field is required."; the dialog stays open, nothing is saved (history unchanged). Step 2 shows the message and note "required" errors again (whitespace is not a reason).

### TC-P1-14-009: Suspension length bounds
- Priority: High · Type: Validation
- Ref: specs/12 §6 (1–90 days); config `moderation.sanctions.suspension_max_days` = 90
- Preconditions: as TC-P1-14-008, all other fields filled.
- Steps:
  1. Length `0`, submit.
  2. Length `91`, submit.
  3. Length `90`, submit; then lift it.
  4. Suspend again with length `1`; then lift it.
- Expected: step 1 "The length field must be at least 1."; step 2 "The length field must not be greater than 90."; steps 3–4 succeed, the "Ends" date is now + 90 days and now + 1 day.

### TC-P1-14-010: Over-long message and note are refused by the server
- Priority: Medium · Type: Validation
- Ref: config `public_reason_max` 255, `note_max` 2000
- Preconditions: signed in as `test_admin` on test_user's detail; note test_user's ULID (shown as "Account id"). In the browser console define this helper (used by later cases too):
  ```js
  const send = (url, body, method = 'POST') => fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]) }, body: JSON.stringify(body) }).then(async (r) => console.log(r.status, await r.text()));
  ```
- Steps:
  1. In the dialog type past 255 characters in the message and check the counter.
  2. Run `send('/admin/users/<ulid>/suspension', { reason_code: 'spam', days: 7, public_reason: 'x'.repeat(256), internal_note: 'y'.repeat(2001) })`.
  3. Run `send('/admin/users/<ulid>/suspension', { reason_code: 'not_a_reason', days: 7, public_reason: 'm', internal_note: 'n' })`.
- Expected: step 1 the field stops at 255 characters. Step 2 logs 422 with "The message to the account holder field must not be greater than 255 characters." and "The internal note field must not be greater than 2000 characters.". Step 3 logs 422 with "The selected reason is invalid.". No sanction is created.

### TC-P1-14-011: Lifting needs a reason
- Priority: High · Type: Validation
- Ref: specs/12 §6
- Preconditions: test_user suspended; signed in as `test_admin`.
- Steps:
  1. Press "Lift the suspension", leave "Why lift it" empty, submit.
  2. Run `send('/admin/users/<ulid>/sanction', { note: '   ' }, 'DELETE')` (helper from TC-P1-14-010).
- Expected: step 1 shows "The reason for lifting field is required." and the suspension stays active; step 2 logs 422 with the same message.

## Authorization (role hierarchy: who may sanction whom)

### TC-P1-14-012: A user and a guest cannot reach the sanction routes
- Priority: High · Type: Authorization
- Ref: specs/04 §2 (suspend / ban / lift: admin+)
- Preconditions: test_user's ULID known; test_moderator's ULID known.
- Steps:
  1. Signed in as `test_user`, open `/admin/users/<test_moderator ulid>`.
  2. As test_user, run `send('/admin/users/<test_moderator ulid>/ban', { reason_code: 'spam', public_reason: 'x', internal_note: 'y' })` from any app page.
  3. Sign out, open `/admin/users/<ulid>`.
- Expected: steps 1–2 are 403; step 3 redirects to `/login`. No row is written.

### TC-P1-14-013: A moderator cannot suspend, ban or lift
- Priority: High · Type: Authorization
- Ref: specs/04 §2–3; StaffAbility `access-admin`, `suspend-user`, `ban-user`, `lift-sanction` are admin+; owner decision 2026-10-02 (moderators never enter `/admin`)
- Preconditions: signed in as `test_moderator`; test_user suspended by an admin (for the lift).
- Steps:
  1. Open `/admin/users/<test_user ulid>`.
  2. Click "Reports" in the top bar (`/moderation/reports`), define the `send` helper from TC-P1-14-010 in the console there, and run `send('/admin/users/<test_user ulid>/suspension', { reason_code: 'spam', days: 3, public_reason: 'x', internal_note: 'y' })`, then the same to `/ban`, then `send('/admin/users/<test_user ulid>/sanction', { note: 'x' }, 'DELETE')`.
- Expected: step 1 is 403 (the whole `/admin` area is admin+). Each call in step 2 logs 403, checked before validation. The suspension is unchanged.

### TC-P1-14-014: An admin cannot sanction another admin
- Priority: High · Type: Authorization
- Ref: specs/04 §2 rule 1; Decision 8
- Preconditions: a second admin: register `qa_admin2` (or use any extra account) and set `users.role` = `admin` in Adminer. Signed in as `test_admin`.
- Steps:
  1. Open qa_admin2's detail.
  2. Run `send('/admin/users/<qa_admin2 ulid>/suspension', { reason_code: 'spam', days: 3, public_reason: 'x', internal_note: 'y' })` and the same to `/ban`.
- Expected: the "Sanctions" section reads "You can't change this account's standing." with no buttons; both calls log 403; no `user_sanctions` row; the denial appears in the security log.

### TC-P1-14-015: Nobody sanctions themselves, and super admins are out of reach
- Priority: High · Type: Authorization
- Ref: specs/04 §2 rule 1; Decision 8 (the controller 404s accounts the viewer cannot see)
- Preconditions: signed in as `test_admin`; ULIDs of test_admin and test_super_admin (from Adminer).
- Steps:
  1. Open `/admin/users/<own ulid>` and `/admin/users/<test_super_admin ulid>`.
  2. Run `send('/admin/users/<own ulid>/ban', {...valid fields})` and the same for the super admin's ULID.
  3. Sign in as `test_super_admin` and repeat step 1 with its own ULID.
- Expected: every page and call is 404; nothing is written.

### TC-P1-14-016: A restricted admin cannot act but can still look
- Priority: Medium · Type: Authorization
- Ref: Decision 8 (staff actions need an active account)
- Preconditions: in Adminer set test_admin `status` = `restricted`, `status_expires_at` null.
- Steps:
  1. Signed in as `test_admin`, open test_user's detail.
  2. Run `send('/admin/users/<test_user ulid>/suspension', {...valid fields, days: 3})`.
  3. Reset test_admin's `status` to `active`.
- Expected: the detail loads; the "Sanctions" section reads "You can't change this account's standing."; step 2 logs 403.

### TC-P1-14-017: A pending-deletion or suspended admin cannot act
- Priority: Medium · Type: Authorization
- Ref: Decision 8; `account.active` on the admin group
- Preconditions: signed in as `test_admin`.
- Steps:
  1. In Adminer set test_admin `status` = `pending_deletion`. Reload test_user's detail.
  2. Run `send('/admin/users/<test_user ulid>/suspension', {...valid fields, days: 3})` (helper from TC-P1-14-010).
  3. Set test_admin `status` = `suspended`, `status_expires_at` = tomorrow. Open `/admin/users`.
  4. Reset test_admin to `active`, `status_expires_at` null.
- Expected: step 1 the detail still loads and reads "You can't change this account's standing."; step 2 logs 403 with "Your account status does not allow this right now." and nothing is written. Step 3 redirects to `/account/suspended`.

## Effects on the sanctioned account

### TC-P1-14-018: A suspension applies on the user's next request
- Priority: High · Type: Functional
- Ref: Phase 1 exit; specs/23 §7 (per-request check)
- Preconditions: `test_user` signed in in browser B on `/settings/profile`; test_admin in browser A.
- Steps:
  1. In browser A suspend test_user for 7 days, message `Posting invite spam in profiles.`
  2. In browser B click any link to a public page (for example the wordmark to `/`), without signing out.
- Expected: browser B lands on `/account/suspended`: heading "Your account is suspended", text "While it is suspended, you can't post or browse other players' content. You can still sign out, and your settings and notifications stay open.", a card with "Reason" = the message and "Ends" = the end date and time. Browser B is still signed in (the suspension does not end sessions).

### TC-P1-14-019: What a suspended user can and cannot reach
- Priority: High · Type: Functional
- Ref: specs/04 §1; EnforceAccountStatus allow-list (`account.suspended`, `logout`, `settings.*`, `notifications.*`)
- Preconditions: test_user suspended and signed in.
- Steps:
  1. Open `/`, `/u/test_moderator` and `/admin`.
  2. Open `/settings/profile`, `/settings/security`, `/notifications`.
  3. On `/settings/profile` change the bio and save.
  4. Sign out from the account menu.
- Expected: step 1 each redirects to `/account/suspended`. Step 2 each page opens. Step 3 shows the 403 page "Your account is suspended", "Changes are off until the suspension ends.", the reason and end, and a "Back to home" button; the bio is unchanged in Adminer. Step 4 signs out.

### TC-P1-14-020: A suspended user can sign in and lands on the notice
- Priority: High · Type: Functional
- Ref: specs/04 §1 (`canLogIn` is false for bans only)
- Preconditions: test_user suspended; signed out.
- Steps:
  1. Sign in as test@example.com / `password`.
- Expected: sign-in succeeds and the browser ends on `/account/suspended` with the reason and end date.

### TC-P1-14-021: A ban signs the user out on every browser, remember-me included
- Priority: High · Type: Security
- Ref: specs/04 §1; Decision 6
- Preconditions: test_user signed in in browser B with "Remember me" ticked and in a private window C without it; test_admin in browser A.
- Steps:
  1. In browser A ban test_user.
  2. Reload browser B and C; in B also fully quit and reopen the browser, then open `/settings/profile`.
- Expected: B and C show the signed-out header after reload; `/settings/profile` redirects to `/login` (the remember cookie no longer signs in). Browser A is still signed in as test_admin.

### TC-P1-14-022: A banned user cannot sign in, and is told why only after the right password
- Priority: High · Type: Security
- Ref: specs/04 §1; FR-MOD-8; specs/11 "Account enumeration"
- Preconditions: test_user banned with message `Selling fake gem codes.`
- Steps:
  1. Sign in as test@example.com with a wrong password.
  2. Sign in with `password`.
- Expected: step 1 shows "That email and password do not match. Check both and try again." Step 2 shows "This account is banned, so it cannot sign in. Reason: Selling fake gem codes." and nobody is signed in; the security log has `auth.login_blocked`.

### TC-P1-14-023: A banned user's public profile disappears at once and returns after the lift
- Priority: Medium · Type: Functional
- Ref: specs/23 §7 "A banned user's content is still cached"; task Scope (`CacheInvalidator::profile`)
- Preconditions: open `/u/test_user` signed out (so it is cached) and see it render.
- Steps:
  1. As test_admin ban test_user.
  2. Reload `/u/test_user` signed out.
  3. Lift the ban; reload `/u/test_user`.
- Expected: step 2 shows the "Profile not found" page with HTTP 404, the same as an unknown name; step 3 shows the profile again.

### TC-P1-14-024: After a lift the user's next request works normally
- Priority: High · Type: Functional
- Ref: specs/12 §6
- Preconditions: test_user suspended and on `/account/suspended` in browser B.
- Steps:
  1. As test_admin lift the suspension.
  2. In browser B reload `/account/suspended`, then open `/u/test_moderator` and save a bio change on `/settings/profile`.
- Expected: `/account/suspended` redirects to `/`; the profile opens; the bio saves.

## Expiry / scheduler

### TC-P1-14-025: The expiry command is scheduled every 15 minutes, off the hour
- Priority: Medium · Type: Functional
- Ref: specs/20 §3; Decision 5
- Preconditions: none.
- Steps:
  1. Run `docker compose exec app php artisan schedule:list`.
- Expected: `moderation:expire-sanctions` is listed with cron `7-59/15 * * * *` (runs at :07, :22, :37, :52, never at :00).

### TC-P1-14-026: A suspension past its end stops applying before the job runs
- Priority: High · Type: Edge case
- Ref: specs/23 §7 "A sanction expires while the user is mid-session"; Decision 4
- Preconditions: stop the scheduler so it cannot run first: `docker compose stop scheduler`. test_user suspended for 7 days and signed in on `/account/suspended` in browser B.
- Steps:
  1. In Adminer run:
     `UPDATE users SET status_expires_at = now() - interval '1 minute' WHERE username = 'test_user';`
     `UPDATE user_sanctions SET expires_at = now() - interval '1 minute' WHERE user_id = (SELECT id FROM users WHERE username = 'test_user') AND lifted_at IS NULL;`
  2. In browser B reload.
  3. As test_admin open test_user's detail.
- Expected: browser B is redirected to `/` and can browse normally, although `users.status` is still `suspended`. The detail shows the "Active" pill, the history entry shows "Ended", and the panel offers "Suspend" and "Ban" but no lift button.

### TC-P1-14-027: The command ends due suspensions, resets the status and records it
- Priority: High · Type: Functional
- Ref: Decisions 4, 10, 16; specs/20 §2
- Preconditions: TC-P1-14-026 done (scheduler stopped); Mailpit cleared.
- Steps:
  1. Run `docker compose exec app php artisan moderation:expire-sanctions`.
  2. Check Adminer `users`, `user_sanctions`, `moderation_actions`; `/admin/audit`; the security log.
- Expected: output "1 sanction ended.". `users.status` = `active`, `status_reason` and `status_expires_at` null. The sanction row keeps `lifted_at` and `lifted_by` null (ended, not lifted). No new `moderation_actions` row. Audit entry "Sanction ended" with actor "System" (`via` scheduler), before `{status: suspended, …}`, after `{status: active}`, context `sanction: suspension`. Security line `moderation.sanction_expired`. Mailpit receives "Your Clash Commons suspension is over" (TC-P1-14-033).

### TC-P1-14-028: The command is idempotent and leaves bans and running suspensions alone
- Priority: High · Type: Functional
- Ref: Decision 4 ("a rerun does nothing")
- Preconditions: TC-P1-14-027 done; test_moderator banned; a third account (for example `qa_admin2` back to role `user`) suspended for 7 days (not moved).
- Steps:
  1. Run `moderation:expire-sanctions` twice.
  2. Check Mailpit, Adminer and the audit log.
  3. Start the scheduler again: `docker compose start scheduler`.
- Expected: both runs print "0 sanctions ended."; no new email, audit entry or status change; test_moderator stays `banned`, the third account stays `suspended`.

## Emails

### TC-P1-14-029: "Your account is suspended" email
- Priority: High · Type: Email
- Ref: specs/16 §2 (E*); FR-MOD-8; Decision 12
- Preconditions: Mailpit cleared; queue running.
- Steps:
  1. As test_admin suspend test_user for 7 days, message `Posting invite spam in profiles.`
  2. Open the email in Mailpit.
- Expected: one email to test@example.com, subject "Your Clash Commons account is suspended": "Hi test_user,", "Your account is suspended until <d Month YYYY> at <HH:MM> UTC." (matching `user_sanctions.expires_at` in UTC), "Reason: Posting invite spam in profiles.", "Until then you can sign in to read your settings and notifications, but you cannot post or browse other players' content.", signed "Clash Commons". The internal note does not appear. No appeal link (P5-02).

### TC-P1-14-030: "Your account is banned" email
- Priority: High · Type: Email
- Ref: specs/16 §2; Decision 12
- Preconditions: Mailpit cleared.
- Steps:
  1. Ban test_user with message `Selling fake gem codes.`
- Expected: subject "Your Clash Commons account is banned": "Hi test_user,", "Your account is banned and can no longer sign in. Every device it was signed in on has been signed out.", "Reason: Selling fake gem codes.", "Clash Commons". No internal note, no appeal link.

### TC-P1-14-031: Lift emails for a suspension and a ban
- Priority: High · Type: Email
- Ref: specs/16 §2 "Sanction lifted / expired"
- Preconditions: Mailpit cleared.
- Steps:
  1. Suspend test_user, clear Mailpit, lift the suspension.
  2. Ban test_user, clear Mailpit, lift the ban.
- Expected: step 1 "Your Clash Commons suspension is over" with "Your suspension has been lifted. Your account works as normal again." and an "Open Clash Commons" button to the home page. Step 2 "Your Clash Commons ban is over" with "Your ban has been lifted. Your account works as normal again." The lift note does not appear in either.

### TC-P1-14-032: Emails go out on the `high` queue
- Priority: Low · Type: Email
- Ref: task Scope "Emails (E*, queued on high)"
- Preconditions: stop the `high` / `default` worker: `docker compose stop queue`.
- Steps:
  1. Suspend test_user.
  2. In Adminer open the `jobs` table.
  3. `docker compose start queue`.
- Expected: step 2 shows the queued work on `queue` = `high`; nothing in Mailpit yet. After step 3 the suspension email arrives.

### TC-P1-14-033: "Suspension is over" email after expiry
- Priority: High · Type: Email
- Ref: Decision 4, 12
- Preconditions: TC-P1-14-027 run.
- Steps:
  1. Open the email in Mailpit.
- Expected: one email, subject "Your Clash Commons suspension is over", "Your suspension has ended. Your account works as normal again.", "Open Clash Commons" button. Running the command again sends nothing more.

### TC-P1-14-034: A replaced suspension sends only the new notice
- Priority: Medium · Type: Email
- Ref: Open question 1; Decision 17
- Preconditions: test_user suspended for 7 days; Mailpit cleared.
- Steps:
  1. Suspend test_user again for 3 days.
  2. Ban test_user.
- Expected: step 1 sends one "Your Clash Commons account is suspended" email with the new (3-day) end and no "suspension is over" email. Step 2 sends one "Your Clash Commons account is banned" email and no "suspension is over" email.

### TC-P1-14-035: A sanction lifted before its email went out sends only the "over" email
- Priority: Medium · Type: Email
- Ref: Decision 17 (stale-notice guard)
- Preconditions: Mailpit cleared; `docker compose stop queue`.
- Steps:
  1. Suspend test_user, then lift the suspension straight away.
  2. `docker compose start queue`; wait a few seconds.
- Expected: Mailpit has only "Your Clash Commons suspension is over"; no "account is suspended" email is sent for a suspension that is no longer active.

## Security

### TC-P1-14-036: Markup in the message and note is shown as text everywhere
- Priority: High · Type: Security
- Ref: specs/11 "XSS"; Decision 19
- Preconditions: Mailpit cleared.
- Steps:
  1. Suspend test_user with message `<img src=x onerror=alert(1)> [click](https://evil.example) ![i](https://evil.example/x.png)` and note `<script>alert(2)</script>`.
  2. View the admin detail (Account section and history), test_user's `/account/suspended`, test_user's `/notifications`, and the email in Mailpit (HTML and text views).
- Expected: no alert runs anywhere; the characters show literally; in the email the Markdown is not turned into a link or an image (no request to evil.example in the HTML source).

### TC-P1-14-037: Extra fields cannot set status, role, issuer or a ban length
- Priority: High · Type: Security
- Ref: task Tests "mass assignment"; Decision 18
- Preconditions: signed in as `test_admin`; helper from TC-P1-14-010; test_user active.
- Steps:
  1. `send('/admin/users/<ulid>/suspension', { reason_code: 'spam', days: 3, public_reason: 'm', internal_note: 'n', status: 'active', role: 'admin', issued_by: 1, expires_at: '2030-01-01', starts_at: '2020-01-01' })`.
  2. Lift it, then `send('/admin/users/<ulid>/ban', { reason_code: 'spam', public_reason: 'm', internal_note: 'n', days: 30 })`.
- Expected: step 1 creates a 3-day suspension issued by test_admin, starting now; test_user's `role` stays `user` and `status` is `suspended`. Step 2 creates a ban with `expires_at` null and `duration_hours` null.

### TC-P1-14-038: Malformed or unknown account ids, and nothing to lift
- Priority: Medium · Type: Security
- Ref: specs/04 §3 (no autoincrement id in URLs); Decision 1
- Preconditions: signed in as `test_admin`.
- Steps:
  1. `send('/admin/users/123/suspension', {...valid})` and `send('/admin/users/01ARZ3NDEKTSV4RRFFQ69G5FAV/ban', {...valid})` (well-formed but unknown).
  2. With test_user active: `send('/admin/users/<test_user ulid>/sanction', { note: 'x' }, 'DELETE')`.
- Expected: step 1 both 404. Step 2 logs 422 with "This account has no active suspension or ban to lift."

### TC-P1-14-039: Moderation and audit records cannot be edited
- Priority: Medium · Type: Security
- Ref: Decision 2 (append-only by trigger)
- Preconditions: at least one `moderation_actions` row.
- Steps:
  1. In Adminer edit the `note` of a `moderation_actions` row and save; then try to delete the row.
  2. Try the same on an `audit_logs` row.
- Expected: each change is rejected with "moderation_actions is append-only" / "audit_logs is append-only"; the rows are unchanged.

### TC-P1-14-046: Too many writes in a minute: the wait shows as an error toast
- Priority: Medium · Type: Security
- Ref: specs/04 §4 (global write backstop); `platform.rate_limits.global_write_per_minute` = 120; specs/18 §4 Toast; owner decision 2026-10-02 (flash messages as toasts)
- Preconditions: signed in as `test_admin` on test_user's detail (active account); helper from TC-P1-14-010 defined; Adminer open on `user_sanctions`.
- Steps:
  1. In the console run `for (let i = 0; i < 120; i++) await send('/admin/users/<test_user ulid>/sanction', { note: '' }, 'DELETE');` (each call fails validation but uses one of the 120 writes allowed per minute).
  2. Within the same minute press "Suspend", fill a valid form and submit.
  3. Wait 10 seconds without touching the page.
  4. Close the toast with its "Dismiss notification" button.
  5. Wait a full minute, then suspend test_user again from the dialog.
- Expected: step 1 logs 422 each time. Step 2: a red error toast "Too many changes. Wait a minute and try again." appears bottom right; no "test_user is suspended." success toast; no new `user_sanctions` row; the status pill stays "Active". The security log has `auth.rate_limited` with `limiter` `global-write`. Step 3: the error toast is still there (error toasts never close by themselves). Step 4: it goes away. Step 5: the suspension goes through with the success toast "test_user is suspended.".

## Edge cases

### TC-P1-14-040: A new suspension replaces the active one
- Priority: High · Type: Edge case
- Ref: Open question 1; Decision 2
- Preconditions: test_user suspended for 7 days.
- Steps:
  1. Press "Suspend", choose 3 days, fill the form, submit.
  2. Check the history and Adminer.
- Expected: the history shows the new "Suspension" as "Active" (ends in 3 days) and the old one as "Lifted … by test_admin: Replaced by a new suspension."; the old row has `lifted_at` set; a `lift` action on it with that note; the new `suspend` action has `metadata.replaced_sanction_id` = the old sanction id; the audit context has `replaced: suspension`.

### TC-P1-14-041: A ban replaces an active suspension; a banned account cannot be sanctioned again
- Priority: High · Type: Edge case
- Ref: Open question 1
- Preconditions: test_user suspended; the detail open in two tabs (T1, T2).
- Steps:
  1. In T1 ban test_user.
  2. Check the history in T1 ("Replaced by a new ban.") and the panel.
  3. In T2 (stale, still showing "Suspend") press "Suspend", fill the form, submit; then press "Ban" and submit.
- Expected: step 2 the old suspension reads "Lifted … : Replaced by a new ban.", the ban is "Active", only "Lift the ban" is shown. Step 3 each dialog shows the alert "This account is already banned. Lift the ban first." and nothing is written.

### TC-P1-14-042: Lifting twice from a stale tab
- Priority: Medium · Type: Edge case
- Ref: Decision 9
- Preconditions: test_user suspended; the detail open in tabs T1 and T2.
- Steps:
  1. Lift in T1.
  2. In T2 press "Lift the suspension", enter a reason, submit.
- Expected: T2's dialog shows the alert "This account has no active suspension or ban to lift."; only one `lift` action exists.

### TC-P1-14-043: Pending-deletion and deleted accounts cannot be sanctioned
- Priority: Medium · Type: Edge case
- Ref: Decision 7
- Preconditions: in Adminer set test_user `status` = `pending_deletion`.
- Steps:
  1. As test_admin open test_user's detail.
  2. `send('/admin/users/<ulid>/suspension', {...valid, days: 3})`.
  3. Set `status` = `active` and `deleted_at` = now(); repeat steps 1–2; then clear `deleted_at`.
- Expected: step 1 "You can't change this account's standing."; step 2 logs 422 "This account is waiting for deletion."; step 3 the detail shows the deleted alert, the same panel text, and the call logs 422 "This account is deleted."

### TC-P1-14-044: A decimal length is rounded down
- Priority: Low · Type: Edge case
- Ref: AdminActionPanel (length parsed as a whole number)
- Preconditions: signed in as `test_admin`; test_user active.
- Steps:
  1. Suspend with length `2.5`.
- Expected: the button reads "Suspend for 2 days"; the saved suspension ends in 2 days (`duration_hours` = 48).

## UI states

### TC-P1-14-045: Dialog saving state, cancel, history empty, and 375 px
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States: modal idle / saving / errors; history empty; 375 px and desktop"
- Preconditions: signed in as `test_admin`; a fresh account with no sanctions (for example test_moderator).
- Steps:
  1. Open the detail: check the history text.
  2. Open "Suspend", trigger a validation error, press "Cancel", reopen "Suspend".
  3. With DevTools network throttling on "Slow 3G", submit a valid suspension.
  4. Set the viewport to 375 px; open the dialog and the history.
- Expected: step 1 "No sanctions on this account."; step 2 the reopened dialog shows no old errors; step 3 the submit button shows a loading state and "Cancel" is disabled until the toast; step 4 the buttons wrap, the dialog fits the screen, the history entries stack label over value, with no horizontal scroll.
