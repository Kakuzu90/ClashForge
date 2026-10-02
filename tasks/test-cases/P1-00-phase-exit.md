# P1-00 Phase 1 exit: test cases

Source: specs/25 Phase 1 "Exit"; tasks/BOARD.md Phase 1. Run on a fresh `migrate:fresh --seed`, at
375 px and on desktop.

## End-to-end flow

### TC-P1-00-001: Register a new account
- Priority: High · Type: Functional
- Ref: FR-AUTH-1, FR-AUTH-11, P1-08
- Preconditions: signed out; Mailpit empty.
- Steps:
  1. Open `/register`.
  2. Enter email `qa1@example.com`, username `qa_one`, a 12+ character password that is not breached, and the same password again.
  3. Wait a few seconds, then submit.
- Expected: the "Check your email" page shows; nobody is signed in; Mailpit has one verification email to qa1@example.com.

### TC-P1-00-002: Verify the email
- Priority: High · Type: Functional
- Ref: FR-AUTH-3, P1-08
- Preconditions: TC-P1-00-001 done.
- Steps:
  1. Open the link in the Mailpit email.
  2. Check the page names `qa_one`, then press the confirm button.
- Expected: the page says the email is confirmed; in Adminer `users.email_verified_at` is set for `qa_one`. Opening the link alone (before pressing the button) changed nothing.

### TC-P1-00-003: Sign in and complete the profile
- Priority: High · Type: Functional
- Ref: FR-AUTH-2, FR-PROFILE-2, P1-01, P1-03
- Preconditions: TC-P1-00-002 done.
- Steps:
  1. Sign in at `/login` as qa1@example.com.
  2. Open `/settings/profile`; set a display name, bio, country, a language, a timezone and a YouTube handle.
  3. Save.
- Expected: the success message shows; reloading keeps every value.

### TC-P1-00-004: Upload an avatar
- Priority: High · Type: Functional
- Ref: FR-PROFILE-3, P1-03, P0-05
- Preconditions: signed in as `qa_one`; queue containers running.
- Steps:
  1. On `/settings/profile`, choose a JPEG or PNG avatar under the size limit.
  2. Wait for processing to finish.
- Expected: the processing state shows, then the new avatar; the header avatar updates.

### TC-P1-00-005: View the public profile
- Priority: High · Type: Functional
- Ref: FR-PROFILE-5, P1-04
- Preconditions: TC-P1-00-004 done; privacy left at its default.
- Steps:
  1. Open `/u/qa_one` signed in, then in a private window as a guest.
- Expected: avatar, display name, `@qa_one`, bio, country, languages, the YouTube link and member-since show; the owner sees "Edit profile" and "Privacy", the guest does not.

### TC-P1-00-006: An admin suspends the user
- Priority: High · Type: Functional
- Ref: FR-ADMIN-3, P1-12, P1-14
- Preconditions: TC-P1-00-005 done; `qa_one` still signed in in another browser.
- Steps:
  1. Sign in as `test_admin`; open `/admin/users`, search `qa_one`, open the detail.
  2. Suspend for 7 days with a reason.
- Expected: the detail shows the suspension, its reason and end; the audit trail lists it; Mailpit has the suspension email to qa1@example.com.

### TC-P1-00-007: The suspension takes effect
- Priority: High · Type: Authorization
- Ref: specs/04 §3, P1-02, P1-14
- Preconditions: TC-P1-00-006 done.
- Steps:
  1. In `qa_one`'s browser, reload any page, then try to save the profile form.
  2. Open `/u/qa_one` as a guest.
- Expected: writes are refused with the suspended page showing the reason and end date; reads still work for the account; the public profile still shows (suspended accounts stay listed).

### TC-P1-00-008: Lifting restores the account
- Priority: Medium · Type: Functional
- Ref: P1-14
- Preconditions: TC-P1-00-007 done.
- Steps:
  1. As `test_admin`, lift the suspension on the user detail.
  2. As `qa_one`, save the profile form again.
- Expected: the save succeeds; Mailpit has the "lifted" email; the audit log lists the lift.

## Phase-wide checks

### TC-P1-00-009: Automated security suite is green
- Priority: High · Type: Security
- Ref: specs/25 Phase 1 exit (enumeration, mass assignment, IDOR)
- Preconditions: containers running.
- Steps:
  1. Run `scripts/check.sh`.
- Expected: every line passes, including `pest (sqlite)` and `pest (postgres)`; `src/tests/Security/` has no failures.

### TC-P1-00-010: No account enumeration across the auth forms
- Priority: High · Type: Security
- Ref: specs/11 "Account enumeration"
- Preconditions: `test_user` exists; `nobody@example.com` does not.
- Steps:
  1. Request a password reset for test@example.com, then for nobody@example.com.
  2. Register with test@example.com, then with a new address.
  3. Sign in with a wrong password for test@example.com, then for nobody@example.com.
- Expected: each pair gets the same page, message and status; only Mailpit shows a difference (reset link / "someone tried to register" to the existing address).

### TC-P1-00-011: Settings and admin pages work at 375 px
- Priority: Medium · Type: UI state
- Ref: specs/18 §5, §8
- Preconditions: signed in as `test_admin`.
- Steps:
  1. At 375 px, open every settings page and every admin page; tab through each with the keyboard.
- Expected: no horizontal scroll; the bottom nav shows; every control is reachable with a visible focus ring; no console errors.
