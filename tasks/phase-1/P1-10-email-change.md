---
id: P1-10
title: Add email change with re-confirmation, a verification link to the new address and a notice to the old one
phase: 1
status: done
depends_on: [P1-05, P1-08]
---

# Email change

## Spec refs
- Core: specs/04 §4 (Session security: "email change invalidates all other sessions", 15-min `password.confirm`; Rate limits; Registration: the 60-min signed link by ULID + email hash, opened then confirmed by POST), §3 (settings writes open to restricted, not suspended); specs/11 "CSRF" (re-confirmation for email change), "Account enumeration" (generic messages on email change), "Data exposure via page props", §3 (security log), §5 (email visible to its owner and admins only)
- Plus: specs/07 `users`; specs/08 §6 (deleted accounts keep their email taken until anonymised); specs/16 §2 Security "Email address changed (to old + new)" E*; specs/18 §6 Settings; specs/19 §4 `/settings/*`
- FR: FR-AUTH-8; FR-AUTH-4 (settings writes open to unverified accounts, so a mistyped address can be fixed)
- Edge cases: specs/23 §1 "User changes email to one already registered" (rejected with a generic error, both addresses notified), "A verification link is opened by a mail scanner"

## Scope
- **Migration** (07 `users`): `pending_email` citext null, `pending_email_requested_at` timestamptz null (Open question 2).
- **Domain** (`Domain/Auth`): `EmailChangeService`:
  - `request(User, email)`: same address as now is a field error; a free address is stored as pending (replacing an earlier one, so its link dies) and gets the link; a taken address (soft-deleted accounts included) per Open question 1. Logs `auth.email_change_requested`.
  - `confirm(User, ulid, hash)`: one conditional update (pending still matches, address still free, else the taken result), sets `email`, `email_verified_at`, clears pending; ends every other session, cycles the remember token, regenerates this session id (04 §4); logs `auth.email_changed`; queues the old-address notice and a "your email is now …" to the new one (16 §2 E*). `cancel(User)` clears pending.
  - Notifications: `EmailChangeLinkNotification`, `EmailChangedNotification` (old address), `EmailChangeAttemptNotification` (taken address, at most one an hour, as the registration notice).
- **Policy + Form Request**: `UserPolicy::changeEmail` (own, account-write standing); `ChangeEmailRequest` (email, ≤255, the registration email rules incl. the disposable blocklist).
- **HTTP**: `PUT /settings/security/email` (current password inline, see Decision 3), `DELETE /settings/security/email` (cancel), `GET|POST /settings/email/confirm/{ulid}/{hash}` (signed, 60 min, `auth`, Open question 3); new limiter `email-change`.
- **UI**: `Settings/Security` gains an "Email address" section: masked current address (Open question 5), the form (idle, saving, errors), a pending row with the masked new address, Resend and Cancel. `Settings/EmailChangeConfirm` (pending: names the account and the new address, one button; results: changed, expired or used, taken, already changed).
- **Config keys**: `platform.auth.email_change_per_hour` (3), `platform.auth.email_change_notice_per_hour` (1); the link reuses `verification_link_minutes`.

## Out of scope
- An undo link in the old-address notice (Open question 4: not in the MVP); bounce handling and the "bouncing" banner (P1-15); admin editing of a user's email; account deletion (P1-11); 2FA.

## Acceptance criteria
- Functional: FR-AUTH-8; the new address is live only after its link is confirmed; the old one gets the notice; other sessions and the remember token die on confirm, this session survives.
- Authorization: own account only; re-confirmation within 15 min required; restricted and unverified accounts can change; suspended cannot (04 §1, §3); the link only confirms for its own signed-in account.
- Enumeration: free and taken addresses give the same response; the signal goes by email only (11, 23 §1).
- Edge cases: two accounts pending one address, first confirm wins, the second gets "taken"; a newer request kills the older link; a mail scanner opening the link changes nothing.
- States: form idle, saving, sent, errors (focus on the field), throttled with the wait; pending row; confirm page results; 375 px and desktop.

## Tests
- Feature (`assertInertia`): request (free, same, taken, pending replaced, cancel), confirm (fresh, expired, tampered, used, superseded, taken at confirm, other account signed in, signed out → sign-in → back), sessions and remember token after confirm, the three emails, Security page props.
- Security: enumeration (same status, props, flash for free and taken); mass assignment (`email_verified_at`, `pending_email`, `role`); re-confirmation enforced; `email-change` limiter; status matrix; no unmasked address in props.
- Unit: email masking, if a new helper.
- Vitest: Email section pending / form switch, if it carries logic.

## Notes

### Open questions
Resolved by the owner, 2026-10-01: 1, 2, 3 and 5 as recommended; 4 declined (MVP).
1. **Taken address.** The form always answers "Check <new address> for a link"; for a taken address nothing changes and no link is sent, the taken address gets the attempt notice (one an hour) and the current address gets "that address can't be used for your account". The existence signal goes only to inboxes, as registration does. synced → specs/23 §1, specs/11 "Account enumeration".
2. **Pending address.** `users.pending_email` + `pending_email_requested_at`; the link is addressed by ULID + a hash of the pending address (P1-08 shape), so the page can show and cancel a pending change and a new request kills the old link. synced → specs/07 `users`, specs/08 §6.
3. **Who can confirm.** The link needs the account signed in on that browser (a guest goes to sign-in and back); confirming ends every session but this one. synced → specs/04 §4 "Email change", specs/02 FR-AUTH-8.
4. **Undo link / recovery.** Declined for the MVP: no undo link, no new board row. The old-address notice goes out as specs/16 §2 says, whether or not the old address was verified.
5. **Placement and display.** A section on `Settings/Security` (no new nav item), the current address masked like the verify page (`C***@example.com`). synced → specs/18 §6, specs/11 "Data exposure via page props".

### Decisions and divergences (implement, 2026-10-01)
1. A taken address is stored as pending too, so the Security page, the flash and the redirect match a free one (Open question 1); "nothing changes" means the email itself and that no link is sent. Confirming re-checks and answers `taken`. synced → specs/11 "Account enumeration", specs/23 §1.
2. Routes: `PUT /settings/security/email` and `POST /settings/security/email/resend` (the Scope's Resend needed its own route), both `throttle:password-confirm`, `DELETE /settings/security/email`, `GET|POST /settings/email/confirm/{ulid}/{hash}` (`auth`, `account.active` from the settings group; `global-write` on the POST), `GET /settings/email/confirmed` (result, flashed). synced → specs/19 §4.
3. Re-confirmation (owner, 2026-10-01: no redirect to `/confirm-password`): the email form has a Current password field, as the password form does, and "Send the link again" sends it too; `EmailChangeService` checks it (`auth.password_confirm_failed` on a miss) and the controller marks the session confirmed. Both routes share the `password-confirm` limiter; `emailNeedsPassword` is gone. synced → specs/04 §4, specs/11 "CSRF", specs/18 §6.
4. Confirm: one conditional update keyed on `pending_email` (two presses change once); a unique-index race answers `taken` and clears the pending address; ends every other session, cycles the remember token, migrates this session id. An account that never confirmed its first address is verified by it and gets `EmailVerified` (in-app "email confirmed"); the old verification link dies with the address hash. Opening another account's link says only `wrong_account`. synced → specs/04 §4 "Email change", "Session security".
5. Emails: the link is built when the mail is sent (no signed URL in the queue) and goes on demand to the pending address; "Email address changed" to the old address (on demand) and the new one; for a taken address `EmailChangeAttemptNotification` to its owner and `EmailChangeRejectedNotification` to the requester, each at most `email_change_notice_per_hour` per inbox. Addresses in emails are masked. No in-app copy (specs/16 §2 lists E* only). synced → specs/16 §2.
6. Security log: `auth.email_change_requested` (with `taken`), `auth.email_changed`, `auth.email_change_cancelled`, `auth.email_change_taken`; a limiter breach is `auth.rate_limited` (`email-change`). synced → specs/11 §3.
7. `EmailFieldRules` (Domain/Auth/Data) now holds the email rules for registration and email change. `pending_email` is in `$hidden` and defaults to null on the model. synced → specs/05 §2.
8. UI: `SettingsEmailSection` as its own flat card between Password and Sessions on `Settings/Security`; `Settings/EmailChangeConfirm` in `AppLayout`. R-31: the same flat card and dense form register as the password section, and the confirm page copies `Auth/VerificationResult`'s card; no new variant, so `/dev/components` is unchanged. synced → specs/18 §6.
9. `email-change` (spec review): counted in `EmailChangeLimit` after validation, not as route middleware, so typos and the current address are free, as at registration; a breach is an `email` field error with the wait. synced → specs/04 §4 Rate limits, specs/05 §2.

### Review fixes (verify, 2026-10-01)
- Security (low): "Send the link again" now takes the current password, so a copied cookie cannot finish a stale pending change days later. synced → specs/04 §4 "Email change".
- Security (low): change links to one address are capped across all accounts (`email_change_links_per_address_per_hour`, 3). synced → specs/11 "Account enumeration".
- Security (low), accepted: the requester's inbox learns that an address is taken (owner decision, Open question 1); recorded as an accepted risk. synced → specs/11 "Account enumeration".
- Spec (low, plausible): the `email-change` limit counted typos; now counted after validation (Decision 9).
- antislop audit-019: no findings.
