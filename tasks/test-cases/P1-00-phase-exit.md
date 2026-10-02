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
- Ref: FR-AUTH-2, FR-PROFILE-2, P1-01, P1-03, owner decision 2026-10-02 (flash toasts)
- Preconditions: TC-P1-00-002 done.
- Steps:
  1. Sign in at `/login` as qa1@example.com.
  2. Open `/settings/profile`; set a display name, bio, country, a language, a timezone and a YouTube handle.
  3. Save.
- Expected: a success toast "Profile saved." shows bottom right (above the bottom tabs at 375 px) and closes by itself after about 5 s; reloading keeps every value.

### TC-P1-00-004: Upload an avatar
- Priority: High · Type: Functional
- Ref: FR-PROFILE-3, P1-03, P0-05, owner decision 2026-10-02 (crop before upload)
- Preconditions: signed in as `qa_one`; queue containers running.
- Steps:
  1. On `/settings/profile`, press "Upload photo" and choose a JPEG or PNG avatar under the size limit.
  2. In the "Crop your photo" dialog ("Your photo shows as a circle across the site."), drag the photo and zoom in a little, then press "Save photo".
  3. Wait for processing to finish.
- Expected: nothing uploads until "Save photo" is pressed. The dialog closes, "Uploading …" and then "Preparing your photo" show, then the new avatar framed as cropped; a success toast "Avatar updated." shows; the avatar in the profile menu (top bar, far right) updates. Repeating step 1 and pressing "Cancel" in the dialog uploads nothing and keeps the current avatar.

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
- Expected: the save succeeds with the success toast "Profile saved."; Mailpit has the "lifted" email; the audit log lists the lift.

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
- Ref: specs/18 §5, §8, owner decision 2026-10-02 (member layout)
- Preconditions: signed in as `test_admin`.
- Steps:
  1. At 375 px, open every settings page and every admin page; tab through each with the keyboard.
- Expected: no horizontal scroll; no sidebar on the settings pages: the bottom tabs show at the bottom of the screen and the top bar shows only the "CC" wordmark, "Admin", the bell and the profile menu. Admin pages fold their nav behind a "Menu" button. Every control is reachable with a visible focus ring; toasts sit above the bottom tabs, never over them; no console errors.

### TC-P1-00-012: Member top bar on desktop
- Priority: Medium · Type: UI state
- Ref: specs/18 §5, owner decision 2026-10-02 (member layout)
- Preconditions: signed in as `test_admin`; browser window 1280 px wide.
- Steps:
  1. Open `/`, then `/settings/profile`.
  2. Read the top bar from left to right.
  3. Narrow the window to 768 px, then to 767 px.
- Expected: no sidebar and no second nav row. Left to right: the "Clash Commons" wordmark, the primary nav as plain text links (only "Home" while the other sections have no page), then on the right "Admin", the bell and the profile menu (avatar). On `/` the "Home" link is gold with a gold bar on the header's bottom edge and has `aria-current="page"`; on `/settings/profile` it is grey with no bar. At 768 px the links are still in the top bar; at 767 px they leave the top bar and the bottom tabs appear.

### TC-P1-00-013: Staff link per role in the top bar
- Priority: Medium · Type: Authorization
- Ref: specs/04, owner decision 2026-10-02 (staff link before the bell)
- Preconditions: desktop width; the seeded accounts.
- Steps:
  1. Sign in as `test_admin`, then `test_moderator`, then `test_user`, and look at the right side of the top bar each time.
  2. As `test_moderator`, press the staff link.
- Expected: `test_admin` sees "Admin" (to `/admin`), `test_moderator` sees "Reports", `test_user` sees no staff link; never both. The staff link always sits before the bell, and the profile menu is always last. Step 2 opens the "Reports" page ("No open reports").

### TC-P1-00-014: Every password field has a show/hide toggle
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (password toggle)
- Preconditions: none; sign in where a page needs it.
- Steps:
  1. Visit each password field: `/login`; `/register` (both); a reset link (both); `/confirm-password`; `/settings/security` (password change and "Current password" under "Email address"); the username change and the danger zone.
  2. On each, type `Ember-Lantern-4417`, press the eye button inside the field, then press it again.
- Expected: every password field has the button at its right edge, named "Show password" with `aria-pressed="false"`. The first press shows `Ember-Lantern-4417` as plain text and renames the button "Hide password" (`aria-pressed="true"`); the second press masks it again. The typed value never changes. Read-only and text fields have no toggle.

### TC-P1-00-015: Server messages show as toasts across the phase
- Priority: Medium · Type: UI state
- Ref: specs/18 §8, owner decision 2026-10-02 (flash toasts)
- Preconditions: signed in as `test_user`.
- Steps:
  1. On `/settings/privacy` press "Save privacy settings" without changes; keep the pointer away from the toast.
  2. Press it again right after the toast closes.
  3. Press it once more and hover the toast for 10 s.
- Expected: step 1 shows a success toast "Privacy settings saved." bottom right that closes by itself after about 5 s. Step 2 shows the same toast again. In step 3 the toast stays while hovered and closes once the pointer leaves (after the rest of its 5 s); "Dismiss notification" closes it at once. Error toasts (for example TC-P1-08-042's resend limit) stay until dismissed.
