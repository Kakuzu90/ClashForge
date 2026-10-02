# P1-10 Email change: test cases

Source: tasks/phase-1/P1-10-email-change.md; specs/04 §4 "Email change", "Session security", specs/11 "Account enumeration", "CSRF", specs/16 §2, specs/23 §1.

The "Email address" section sits between "Password" and "Where you're signed in" on
`/settings/security`. Accepted requests and resends are limited to 3 per account and hour, so run
`docker compose exec app php artisan cache:clear` between cases (it resets every limiter) unless
the case tests a limit. Success messages ("Check … for a link …", "Email change cancelled. …")
show as toasts bottom right (above the bottom tabs below 768 px) and close after about 5 s.

## Happy path

### TC-P1-10-001: Email section shows the masked current address
- Priority: Medium · Type: UI state
- Ref: Open question 5, specs/11 "Data exposure via page props"
- Preconditions: signed in as `test_user`; nothing pending.
- Steps:
  1. Open `/settings/security`.
  2. In DevTools > Network, open the page response and search the Inertia props for `test@example.com`.
- Expected: the section "Email address" says "Your email is t***@example.com. A new address works once you confirm it from that inbox, and every other device is signed out." with "New email", "Current password" and "Send confirmation link". No prop contains the full address.

### TC-P1-10-002: Request a change to a free address
- Priority: High · Type: Functional
- Ref: FR-AUTH-8, Decision 2, owner decision 2026-10-02 (flash toasts)
- Preconditions: signed in as `test_user`; Mailpit empty; queue containers running.
- Steps:
  1. Enter `qa-new@example.com` in "New email" and `password` in "Current password"; press "Send confirmation link".
  2. Check Adminer `users` for `test_user`.
- Expected: the form clears and a pending row shows "Waiting for you to confirm q***@example.com." with "The link expires 60 minutes after it was sent. Until then your email stays the same. To send it again, enter your current password below.", and buttons "Send the link again" and "Cancel the change". The field label becomes "Use a different new email". Adminer: `email` is still test@example.com; `pending_email` is qa-new@example.com and `pending_email_requested_at` is now. A success toast reads "Check q***@example.com for a link to confirm the change. It expires in 60 minutes." and closes by itself after about 5 s.

### TC-P1-10-003: Link email to the new address
- Priority: High · Type: Email
- Ref: specs/16 §2, Decision 5
- Preconditions: TC-P1-10-002 done.
- Steps:
  1. Open Mailpit.
- Expected: one email to qa-new@example.com, subject "Confirm your new Clash Commons email", greeting "Hi test_user,", button "Confirm your new email", with "The link expires in 60 minutes and only works while you are signed in to that account." and "If you did not ask for this, ignore this email. Nothing changes until the link is used." No email to test@example.com yet. The link has the form `/settings/email/confirm/{ulid}/{40-hex hash}?expires=…&signature=…`.

### TC-P1-10-004: Confirm the change
- Priority: High · Type: Functional
- Ref: FR-AUTH-8, Decision 4
- Preconditions: TC-P1-10-003 done; this browser signed in as `test_user`.
- Steps:
  1. Open the link in this browser.
  2. Press "Use this email".
- Expected: step 1 shows "Confirm your new email" with "This changes the email for test_user to q***@example.com." and "Every other device will be signed out. Did not ask for this? Close this page and nothing changes."; Adminer is unchanged. After step 2 the page `/settings/email/confirmed` shows "Email changed" with "Your email is changed. Every other device was signed out." and a "Security settings" button. Adminer: `email` qa-new@example.com, `email_verified_at` now, `pending_email` and `pending_email_requested_at` NULL. This browser stays signed in.

### TC-P1-10-005: Emails after the change, to the old and new address
- Priority: High · Type: Email
- Ref: specs/16 §2 "Email address changed (to old + new)", Open question 4
- Preconditions: TC-P1-10-004 done.
- Steps:
  1. Open Mailpit.
- Expected: two emails with subject "Your Clash Commons email was changed". To test@example.com: "The email for your Clash Commons account was changed to q***@example.com on <date> at <time> UTC. Every other device was signed out." and "If you did not make this change, sign in and change your password, then change the email back." (no undo link). To qa-new@example.com: "This is now the email for your Clash Commons account, since <date> at <time> UTC. …". Addresses appear masked. No in-app notification is created.

### TC-P1-10-006: Sign in with the new address only
- Priority: High · Type: Functional
- Ref: FR-AUTH-8
- Preconditions: TC-P1-10-004 done; signed out.
- Steps:
  1. Sign in with `test@example.com` / `password`.
  2. Sign in with `qa-new@example.com` / `password`.
  3. Request a password reset for test@example.com.
- Expected: step 1 shows "That email and password do not match. Check both and try again."; step 2 signs in; step 3 shows the generic status but Mailpit gets no reset email for test@example.com.

### TC-P1-10-007: Cancel a pending change
- Priority: High · Type: Functional
- Ref: task Scope `cancel(User)`, Decision 6, owner decision 2026-10-02 (flash toasts)
- Preconditions: signed in as `test_user` with a pending change to `qa-new@example.com` and its link at hand.
- Steps:
  1. Press "Cancel the change".
  2. Open the link from Mailpit.
- Expected: the pending row disappears and the label is back to "New email"; a success toast reads "Email change cancelled. Your email has not changed."; Adminer `pending_email` is NULL. Step 2 shows "Email not changed" with "This link has already been used or has expired. Ask for a new one in your security settings."

### TC-P1-10-008: Send the link again
- Priority: Medium · Type: Functional
- Ref: Decision 2, Decision 3, Review fixes, owner decision 2026-10-02 (flash toasts)
- Preconditions: signed in as `test_user` with a pending change to `qa-new@example.com` (one accepted request this hour); Mailpit cleared.
- Steps:
  1. Leave "New email" empty, enter `password` in "Current password" and press "Send the link again".
  2. Once the toast has closed, repeat step 1.
- Expected: step 1 shows a success toast "Check q***@example.com for a link to confirm the change. It expires in 60 minutes."; no "Sent." text appears next to the buttons; the password field clears; Mailpit gets a new "Confirm your new Clash Commons email" to the pending address. Step 2 shows the same toast again and sends a third link. Every link opens the confirm page (same pending address).

### TC-P1-10-009: Unverified account can change and is verified by the new address
- Priority: High · Type: Functional
- Ref: FR-AUTH-4, Decision 4
- Preconditions: a registered, unverified account (P1-08 flow, e.g. `qa_typo` with `qa-typo@exmaple.com`), signed in; its original verification link at hand.
- Steps:
  1. On `/settings/security` change the email to `qa-fixed@example.com` with the current password.
  2. Confirm the link from Mailpit.
  3. Open the bell; open the original verification link.
- Expected: the request and confirm work. `email_verified_at` is set; the "Confirm your email" banner is gone; the bell has "Your email is confirmed". The original verification link shows "This link has already been used or has expired. Ask for a new one."

## Validation

### TC-P1-10-010: Empty fields
- Priority: Medium · Type: Validation
- Ref: task Acceptance "errors (focus on the field)"
- Preconditions: signed in as `test_user`.
- Steps:
  1. Press "Send confirmation link" with both fields empty.
- Expected: "The new email field is required." under "New email" and "The current password field is required." under "Current password"; focus on "New email". Nothing in Mailpit.

### TC-P1-10-011: Invalid and disposable addresses
- Priority: Medium · Type: Validation
- Ref: Decision 7 (`EmailFieldRules`), FR-AUTH-11
- Preconditions: signed in as `test_user`.
- Steps:
  1. Submit `qa-new@` with the right password.
  2. Submit `qa-new@mailinator.com`.
  3. Submit `qa-new@inbox.mailinator.com`.
- Expected: step 1 "Enter a valid email address."; steps 2 and 3 "Use an email address you will keep. Throwaway inboxes are not accepted." Each under "New email"; the password field is cleared; nothing pending.

### TC-P1-10-012: Wrong current password
- Priority: High · Type: Validation
- Ref: Decision 3, specs/11 "CSRF"
- Preconditions: signed in as `test_user`.
- Steps:
  1. Enter `qa-new@example.com` and `wrongpassword`; submit.
- Expected: "That is not your current password." under "Current password", which is cleared and focused; nothing pending; no email. The security log has `auth.password_confirm_failed`.

### TC-P1-10-013: Same address as now
- Priority: Medium · Type: Validation
- Ref: task Scope `request` ("same address as now is a field error")
- Preconditions: signed in as `test_user` (email test@example.com).
- Steps:
  1. Submit `TEST@example.com` with the right password.
- Expected: "That is already your email address." under "New email"; nothing pending; it does not count toward the hourly limit.

### TC-P1-10-014: Send again without the password
- Priority: Medium · Type: Validation
- Ref: Decision 3, Review fixes, owner decision 2026-10-02 (flash toasts)
- Preconditions: signed in with a pending change.
- Steps:
  1. Press "Send the link again" with "Current password" empty.
  2. Repeat with a wrong password.
- Expected: step 1 "The current password field is required."; step 2 "That is not your current password."; both under "Current password"; no email; no success toast.

## Authorization / account status

### TC-P1-10-015: Guests are sent to sign in
- Priority: Medium · Type: Authorization
- Ref: specs/19 §4 `/settings/*`
- Preconditions: signed out.
- Steps:
  1. Open `/settings/security`.
- Expected: redirected to `/login`.

### TC-P1-10-016: Restricted account can change its email
- Priority: Medium · Type: Authorization
- Ref: task Acceptance "restricted and unverified accounts can change", specs/04 §3
- Preconditions: `test_user` restricted (admin sanction panel as `test_admin`, or `users.status = 'restricted'` in Adminer); signed in as `test_user`.
- Steps:
  1. Request a change to `qa-new@example.com` and confirm the link.
- Expected: both steps work as in TC-P1-10-002/004.

### TC-P1-10-017: Suspended account cannot change its email
- Priority: High · Type: Authorization
- Ref: task Acceptance "suspended cannot", specs/04 §1, §3
- Preconditions: `test_user` suspended (admin sanction panel or `users.status = 'suspended'`); signed in as `test_user`.
- Steps:
  1. Open `/settings/security`.
  2. Submit a new email with the right password.
- Expected: step 1 loads. Step 2 answers 403 with the page "Your account is suspended" / "Changes are off until the suspension ends."; nothing pending; no email.

### TC-P1-10-018: Suspended after requesting: the link cannot confirm
- Priority: Medium · Type: Authorization
- Ref: specs/04 §3
- Preconditions: `test_user` has a pending change with a link, then is suspended.
- Steps:
  1. Open the link and press "Use this email".
- Expected: the confirm is refused with the "Your account is suspended" page; `email` is unchanged.

### TC-P1-10-019: Link opened while signed out
- Priority: High · Type: Authorization
- Ref: Open question 3
- Preconditions: `test_user` has a pending change with a link; this browser signed out.
- Steps:
  1. Open the link.
  2. Sign in as `test_user`.
- Expected: step 1 redirects to `/login`; after signing in the browser returns to the "Confirm your new email" page for that link, and "Use this email" confirms.

### TC-P1-10-020: Link opened by another signed-in account
- Priority: High · Type: Authorization
- Ref: task Acceptance "the link only confirms for its own signed-in account", Decision 4
- Preconditions: `test_user` has a pending change with a link; this browser signed in as `test_moderator`.
- Steps:
  1. Open test_user's link.
- Expected: "Email not changed" with "This link is for a different account. Sign in to that account and open the link again." The page names neither test_user nor the new address; nothing changes for either account.

## Security

### TC-P1-10-021: Free and taken addresses answer the same
- Priority: High · Type: Security
- Ref: specs/11 "Account enumeration", Open question 1, Decision 1
- Preconditions: signed in as `test_user`; Mailpit empty; DevTools Network open.
- Steps:
  1. Request a change to `moderator@example.com` (taken) with the right password; note the redirect, props and pending row.
  2. Cancel, then request `qa-free@example.com` (free); note the same.
- Expected: both show the pending row with a masked address ("Waiting for you to confirm m***@example.com." / "q***@example.com"), the same redirect and the same success toast ("Check m***@example.com for a link to confirm the change. It expires in 60 minutes." / "Check q***@example.com …"), also in the `flash.success` prop. Adminer stores both as `pending_email`. `test_moderator`'s `email` is untouched.

### TC-P1-10-022: Emails for a taken address
- Priority: High · Type: Email
- Ref: specs/23 §1 "User changes email to one already registered", Decision 5
- Preconditions: TC-P1-10-021 step 1 done.
- Steps:
  1. Open Mailpit.
- Expected: no link email. moderator@example.com gets "Someone tried to use your email on another account" (greeting "Hi test_moderator,", "There is nothing to do. Your account has not changed."). test@example.com gets "Your Clash Commons email was not changed" with "You asked to change your email to m***@example.com. That address cannot be used for your account, so no link was sent and your email has not changed." and a "Security settings" button.

### TC-P1-10-023: Taken-address notices at most once an hour per inbox
- Priority: Medium · Type: Security
- Ref: `email_change_notice_per_hour` 1
- Preconditions: TC-P1-10-022 done less than an hour ago (do not clear the cache).
- Steps:
  1. As `test_user`, request `moderator@example.com` again with the right password.
- Expected: the same pending row and the same "Check m***@example.com for a link …" toast; Mailpit gets neither a second attempt notice to moderator@example.com nor a second rejection to test@example.com.

### TC-P1-10-024: A deleted account's address counts as taken
- Priority: Medium · Type: Security
- Ref: specs/08 §6, task Scope `request`
- Preconditions: an account with email `qa-gone@example.com` soft-deleted in Adminer (`deleted_at` set); signed in as `test_user`.
- Steps:
  1. Request a change to `qa-gone@example.com`.
- Expected: the pending row shows as usual; no link email; test@example.com gets the "was not changed" email.

### TC-P1-10-025: Mail scanner opening the link changes nothing
- Priority: High · Type: Security
- Ref: specs/23 §1 "A verification link is opened by a mail scanner"
- Preconditions: `test_user` has a pending change with a link.
- Steps:
  1. Fetch the link with `curl` (no cookies), then open it in the signed-in browser without pressing the button.
  2. Check Adminer.
- Expected: curl gets a redirect to `/login`. The browser shows the confirm page. `email` and `pending_email` are unchanged; no "was changed" emails.

### TC-P1-10-026: Confirming ends other sessions and cycles the remember token
- Priority: High · Type: Security
- Ref: FR-AUTH-8, specs/04 §4 "email change invalidates all other sessions", Decision 4
- Preconditions: browser A and browser B (private window) both signed in as `test_user`, B with "Keep me signed in". In Adminer note `users.remember_token` and A's `sessions.id`. A pending change with its link.
- Steps:
  1. In browser A confirm the link.
  2. Reload a page in browser B.
  3. Check Adminer.
- Expected: browser A stays signed in. Browser B is signed out and its remember cookie does not sign it back in. `sessions` has one row for `test_user`, with a new `id` (A's session id was regenerated). `remember_token` has changed. The security log has `auth.email_changed`.

### TC-P1-10-027: Expired or tampered link
- Priority: High · Type: Security
- Ref: task Tests "confirm (expired, tampered)"
- Preconditions: `test_user` has a pending change with a link; signed in as `test_user`.
- Steps:
  1. Change one character of the `signature` value; open it.
  2. Change the hash segment of the path; open it.
  3. Open an untouched link more than 60 minutes after it was sent.
- Expected: each shows "Email not changed" with "This link has already been used or has expired. Ask for a new one in your security settings."; `email` unchanged.

### TC-P1-10-028: Mass assignment is ignored
- Priority: Medium · Type: Security
- Ref: task Tests "mass assignment"
- Preconditions: signed in as `test_user`.
- Steps:
  1. Copy a `PUT /settings/security/email` request as fetch; add `email_verified_at: null`, `pending_email: 'qa-x@example.com'`, `role: 'admin'` to the body with email `qa-y@example.com` and the right password; run it.
- Expected: `pending_email` is qa-y@example.com (not qa-x), `role` is still user, `email_verified_at` unchanged.

### TC-P1-10-029: Accepted requests limit, 3 per hour per account
- Priority: High · Type: Security
- Ref: specs/04 §4 `email-change`, Decision 9 (`email_change_per_hour` 3)
- Preconditions: signed in as `test_user`; no accepted requests in the last hour. Pace the submits so no more than 5 fall in any one minute: the `password-confirm` route limit counts every submit of this form.
- Steps:
  1. Request a change to `qa-a@example.com`, then press "Send the link again" (with the password), then request `qa-b@example.com`: 3 accepted.
  2. Submit `qa-c@` (invalid) and the current address, then a valid `qa-c@example.com`.
- Expected: the invalid and same-address submits get their own field errors. The valid 4th request shows "Too many attempts. Try again in N minutes." under "New email"; `pending_email` stays qa-b@example.com and no email goes to qa-c@example.com.

### TC-P1-10-030: Current-password guesses share the password-confirm limit
- Priority: Medium · Type: Security
- Ref: Decision 3 (`password_confirm_per_minute` 5, `password_confirm_per_hour` 20)
- Preconditions: signed in as `test_user`.
- Steps:
  1. Within one minute submit the email form 5 times with a wrong current password.
  2. Submit a 6th time with the right password.
- Expected: step 2 shows "Too many attempts. Try again in N seconds." under "Current password" and nothing is pending. The password form on the same page is blocked too until the wait ends.

### TC-P1-10-031: Change links to one address capped across accounts
- Priority: Low · Type: Security
- Ref: Review fixes (`email_change_links_per_address_per_hour` 3)
- Preconditions: Mailpit empty; four seeded accounts.
- Steps:
  1. Signed in as `test_user`, `test_moderator`, `test_admin` and then `test_super_admin` in turn, each requests a change to `qa-shared@example.com`.
- Expected: all four see the pending row; Mailpit has exactly 3 link emails to qa-shared@example.com.

## Edge cases

### TC-P1-10-032: Two accounts pending one address, first confirm wins
- Priority: High · Type: Edge case
- Ref: task Acceptance "first confirm wins, the second gets taken", Decision 4
- Preconditions: `test_user` and `test_moderator` both requested `qa-race@example.com` (two link emails, told apart by the greeting).
- Steps:
  1. As `test_user`, confirm their link.
  2. As `test_moderator`, open their link and press "Use this email".
- Expected: step 1 changes test_user's email. Step 2 shows the confirm page, then "Email not changed" with "That address belongs to another account, so your email has not changed." test_moderator's `email` is unchanged and `pending_email` is now NULL.

### TC-P1-10-033: A newer request kills the older link
- Priority: High · Type: Edge case
- Ref: task Acceptance "a newer request kills the older link", Open question 2
- Preconditions: signed in as `test_user`.
- Steps:
  1. Request `qa-first@example.com`; keep the link.
  2. Request `qa-second@example.com` in "Use a different new email".
  3. Open the first link.
- Expected: the pending row shows "q***@example.com" for the second address; step 3 shows "Email not changed" with "This link has already been used or has expired. Ask for a new one in your security settings."

### TC-P1-10-034: Link used twice and double press
- Priority: Medium · Type: Edge case
- Ref: Decision 4 ("two presses change once")
- Preconditions: a pending change with its link; Mailpit cleared.
- Steps:
  1. Open the link and double-click "Use this email".
  2. Open the same link again.
- Expected: one change, one pair of "was changed" emails. Step 2 shows "Email changed" with "This email is already on your account."

### TC-P1-10-035: Result page without a result
- Priority: Low · Type: Edge case
- Ref: Decision 2 (`/settings/email/confirmed` is flashed)
- Preconditions: signed in.
- Steps:
  1. Open `/settings/email/confirmed` directly, or reload it after a result.
- Expected: redirected to `/settings/security`.

## UI states

### TC-P1-10-036: Saving and throttled states
- Priority: Low · Type: UI state
- Ref: task Acceptance "States"
- Preconditions: signed in; DevTools Network throttled to "Slow 3G".
- Steps:
  1. Submit a valid change.
  2. Press "Cancel the change", then on the next pending row press "Send the link again".
- Expected: "Send confirmation link" shows its loading state while saving; "Cancel the change" shows loading while cancelling; "Send the link again" is disabled while the form is busy.

### TC-P1-10-037: Section and confirm page at 375 px
- Priority: Medium · Type: UI state
- Ref: task Acceptance "375 px and desktop", Decision 8
- Preconditions: DevTools device toolbar at 375 px; signed in with a pending change to a long address (e.g. `quality-assurance-team-member@example.com`).
- Steps:
  1. Open `/settings/security`; trigger a field error.
  2. Open the confirm link and a result page.
- Expected: no horizontal scroll; masked addresses and the username wrap inside the card; the pending row's buttons wrap onto a new line rather than overflowing; errors sit under their fields.

### TC-P1-10-038: Show and hide the current password
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: signed in as `test_user`; nothing pending; Mailpit empty.
- Steps:
  1. On `/settings/security`, in "Email address", enter `qa-new@example.com` in "New email" and `password` in "Current password".
  2. Press the eye button at the right edge of "Current password"; press it again; press it a third time.
  3. With the password showing as text, press "Send confirmation link".
- Expected: "New email" has no toggle. Before step 2 the button reads "Show password" (`aria-pressed="false"`); each press switches between plain text with "Hide password" (`aria-pressed="true"`) and dots with "Show password". `password` is kept through every press. Step 3 works as in TC-P1-10-002: the pending row, the success toast and one link email.

### TC-P1-10-039: Current-password toggle with the keyboard
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: signed in as `test_user`; nothing pending.
- Steps:
  1. Click into "New email", type `qa-new@example.com`, press Tab and type `password`.
  2. Press Tab, then Space, then Enter.
  3. Press Tab.
- Expected: step 2 puts focus on the toggle with a visible focus ring; Space shows `password` as text ("Hide password"), Enter masks it again ("Show password"). Neither key submits the form: nothing pending, no toast, no email, both values kept. Step 3 moves focus to "Send confirmation link".

### TC-P1-10-040: Email-change toasts close, pause and repeat
- Priority: Low · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (flash toasts)
- Preconditions: signed in as `test_user`; nothing pending.
- Steps:
  1. Request a change to `qa-new@example.com`; leave the pointer away from the toast.
  2. Press "Cancel the change" and hover the toast for 10 s, then move the pointer away.
  3. Request `qa-new@example.com` again and press "Cancel the change" once more.
  4. Repeat steps 1 and 2 at 375 px.
- Expected: step 1's "Check q***@example.com …" toast closes by itself after about 5 s. Step 2's "Email change cancelled. Your email has not changed." stays while hovered and closes once the pointer leaves; "Dismiss notification" closes a toast at once. Step 3 shows both toasts again (the same message after a second identical action). At 375 px the toasts sit above the bottom tabs and wrap inside the screen width.
