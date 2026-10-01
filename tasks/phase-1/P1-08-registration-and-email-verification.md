---
id: P1-08
title: Add registration and email verification
phase: 1
status: done
depends_on: [P1-01]
---

# Registration and email verification

## Spec refs
- Core: specs/04 §4 (Registration, Password policy, `register` 3/h and `verify-email-resend` 3/h limiters), §3 (write gating #1); specs/11 "Authentication attacks", "Account enumeration", "API abuse" (Turnstile on register and `/forgot-password`), "Spam and fake accounts" (disposable blocklist, honeypot, minimum fill time), §3 (security log); specs/07 `users`
- Plus: specs/16 §2 (verification link E*; FR-NOTIF-2 "email verified"); specs/18 §6 (Home hero register CTA), §8; specs/05 §3 (`UserRegistered` → Users, Notifications; `EmailVerified` → Notifications, Users); specs/19 §5 (`platform.php` reserved usernames)
- FR: FR-AUTH-1, -2, -3, -4, -11; FR-NOTIF-2 (email verified)
- Edge cases: specs/23 §1 "registers with an email already in use" (generic success, notice to the existing address), "never verifies" (Open question 4)

## Scope
- **Domain (`Domain/Auth`):** `RegistrationService` (validated input → in every path hash the password inside a timebox; new email: create the user and queue the verification email; taken email: queue `RegistrationAttemptNotice` to that address; one generic result). `UsernameRules` (3–20, `[a-z0-9_]`, lowercase, reserved list in `platform.auth.reserved_usernames`, unique ignoring case). `DisposableEmailDomains` (Open question 2). `TurnstileVerifier` (Open question 1). `RegistrationGuard`: honeypot field empty and an encrypted form-start time older than `platform.auth.register_min_seconds`; a failure answers the same generic success and logs `auth.registration_blocked`. Events `UserRegistered`, `EmailVerified` (after commit).
- **Listeners:** Users creates profile, privacy settings and stats on `UserRegistered`; Notifications writes an in-app "Email confirmed" on `EmailVerified` (new `NotificationType`).
- **Verification:** Laravel's signed 60-minute link (`verification.verify`, user id + email hash), opened signed in or not (Open question 5); resend `POST /email/verification-notification` behind `verify-email-resend`; `/email/verify` notice page for signed-in unverified accounts.
- **Form Requests:** `RegisterRequest` (email, username, password + confirmation through the existing password rule with HIBP, Turnstile token, honeypot, form start). `/forgot-password` gains the Turnstile token.
- **UI:** `Auth/Register` (links to and from sign-in), `Auth/RegisterSent` ("Check your email", the same page for every outcome), `Auth/VerifyEmail` (resend with status); a one-line "Confirm your email" banner in `AppLayout` for unverified accounts; Home hero register CTA for guests; Turnstile widget component (script from `challenges.cloudflare.com`, CSP updated).
- **Config keys:** `platform.auth.{register_per_hour, verify_resend_per_hour, register_min_seconds, register_max_form_age_minutes, reserved_usernames}`, `services.turnstile.{site_key, secret, timeout}`.

## Out of scope
- Unverified reminder and purge (Open question 4); username availability live check (Open question 3); email change (P1-10); username change and `username_history` (P1-09, so released names are not yet blocked); 2FA

## Acceptance criteria
- Functional: FR-AUTH-1/2/3/11; verifying sets `email_verified_at` once, and a used, expired or tampered link shows one message.
- Authorization: guests only on register; verification link checks the signature and the email hash; resend only for the signed-in unverified account; writes gated per Open question 3.
- Enumeration: taken and new email give the same status, page, cookies and timing; the taken address gets one notice email.
- States: field errors with focus on the first; throttled as a field error with the wait; Turnstile unavailable per Open question 1; 375 px and desktop.

## Tests
- Feature: register happy path (user, profile, privacy, stats, verification email queued), each validation rule, reserved and taken usernames, disposable domain, verify (fresh, used, expired, wrong hash, other account signed in), resend + limiter, in-app "Email confirmed".
- Security: enumeration (status, body, cookies, timebox), honeypot and fast submit silently dropped and logged, Turnstile failure, `register` limiter, mass assignment (`role`, `status`, `email_verified_at` ignored).
- Unit: `UsernameRules`, `DisposableEmailDomains` (subdomains, case).
- Vitest: register form (fill-time field, error focus), Turnstile component (token emitted, reset on error).

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended). Q4 synced → tasks/BOARD.md (P1-16); Q3 and Q5 are synced into specs/04 and the P1-08 board row at implement.
1. **Turnstile keys and outages.** No Cloudflare account yet (P0-09). Recommended: `TurnstileVerifier` reads `services.turnstile.*` from env; local and tests use Cloudflare's published test keys (always pass / always fail); if siteverify cannot be reached within 3 s, fail open and log `auth.turnstile_unavailable` (as HIBP does), since the honeypot, fill time and `register` limiter still stand. Alternative: fail closed.
2. **Where does the disposable-domain list come from?** Specs/11 says "refreshed monthly". Recommended: commit the CC0 `disposable-email-domains` list as a file in the repo, matched on the domain and its parents; a monthly scheduled `auth:refresh-disposable-domains` downloads the latest into storage and falls back to the committed file if the download fails.
3. **Which writes need a verified email?** Specs/04 §3–4 say "all writes"; FR-AUTH-4 lists publishing, commenting, attaching CoC accounts and uploads. Recommended: FR-AUTH-4: a `email.verified` middleware for content routes as they land (uploads already check in `MediaPolicy`), while settings, notifications and resend stay open so a mistyped address can be fixed later (P1-10). Specs/04 wording synced. Also: no live username availability check now; a taken name is a field error on submit (usernames are public anyway).
4. **Never-verified accounts** (23 §1: reminder at day 3, purge at 30 days with a final warning). Recommended: new board row P1-16 "Unverified accounts: day-3 reminder, final warning, 30-day purge" depending on P1-08.
5. **Sign-in after registering, and `known_devices`.** Signing the new account in would differ from the taken-email path, so registration never signs in: both show `RegisterSent`. Recommended: the verification link signs the browser in only if it is already signed in to that account; otherwise it verifies and sends to sign-in with "Email confirmed, sign in". The board's "`known_devices` at registration" would set a cookie only for new emails (an enumeration signal), so it is set when the link is opened in a signed-in browser instead.

### Decisions and divergences (implement, 2026-10-01)
1. Registration is ours, not Fortify's (`RegisterController` → `RegistrationService::submit`): Fortify's `CreateNewUser` signs the account in, which the taken-email path cannot copy. Names in the code: `UsernameFieldRules` (Scope's `UsernameRules`), `RegistrationAttemptNotification` (Scope's `RegistrationAttemptNotice`). synced → specs/04 §4 "Mechanism", specs/05 §2.
2. Enumeration: every path (new email, taken email with soft-deleted accounts included, and a trapped bot) hashes the password inside `Timebox` (`auth.timebox_duration`); none signs in; all redirect to `/register/sent`. A taken email's owner gets at most one `RegistrationAttemptNotification` an hour. Losing the email race takes the taken-email path; losing the username race gets the username error. synced → specs/11 "Account enumeration", specs/23 §1.
3. Limits: `register` counts **accepted** submissions (3 / h per IP, `RegistrationLimit`, after validation), so typos never lock a person out; every attempt has a looser route cap (`register_attempts_per_hour` 20). `verify-email-resend` 3 / h per account, a flash error on the notice page. synced → specs/04 §4.
4. Bot traps (`RegistrationGuard`): a hidden `website` field and an encrypted form token (time shown + a nonce kept in the session, up to 5 open tabs), single-use and at least 3 s old; a trapped submission answers the same and logs `auth.registration_blocked` (`honeypot`, `no_form_time`, `bad_form_time`, `replayed_form`, `too_fast`). A form older than 120 min is not a bot signal: it gets "This form was open for a long time. Reload the page and try again." Turnstile runs last and only when every other field passed, so a typo keeps the single-use token; the widget resets after a failed submit and says when it could not load. synced → specs/11 "Spam and fake accounts".
5. Turnstile (Open question 1): `TurnstileVerifier` contract, `CloudflareTurnstileVerifier` (3 s, fail open with `auth.turnstile_unavailable`, a missing secret fails closed with `auth.turnstile_misconfigured`, tokens ≤ `services.turnstile.max_token_length`). Config defaults to Cloudflare's always-pass test keys only for `local` / `testing`; production needs `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` (`.env*` is protected: the owner adds them). Tests bind `FakeTurnstile` in `TestCase`. The architecture test that forbids naming a storage provider allowlists the four Turnstile files. No app CSP exists yet; when one lands it must allow `challenges.cloudflare.com` in `script-src`, `frame-src` and `connect-src`. synced → specs/11 "API abuse", "Headers".
6. Disposable domains (Open question 2): the CC0 list (≈9,200 domains) at `resources/blocklists/disposable-email-domains.txt`; `auth:refresh-disposable-domains` (monthly, 3rd at 04:20) writes a fresh copy to storage (temp file + rename) only if it parses to ≥ `disposable_domains_min` (1,000); the storage copy wins. A domain matches itself and its subdomains. synced → specs/11, specs/20 §3, specs/19 §7.
7. Username: input lowercased and trimmed before the rules (non-strings left for the validator); 3 to 20 of `[a-z0-9_]` (anchored with `/D`, so a trailing newline fails); 43 reserved names in `platform.auth.reserved_usernames`; unique against every row, deleted accounts included. synced → specs/04 §4, specs/07 `users`.
8. Verification link (security review): `/email/verify/{ulid}/{hash}` (ULID, not the id; sha1 of the lowercased email), signed for 60 minutes. **Opening it changes nothing**: it shows the username it would confirm and a "Confirm my email" button that POSTs the signed URL, so link-scanning mail gateways confirm nothing. The result is shown on `/email/verified` (flashed). Confirming is one conditional update (two presses verify once), dispatches `EmailVerified`, and ends every session of the account except the confirming browser's own, with the remember token cycled, so whoever registered someone else's address loses it once the owner confirms. A stale or tampered link (open or confirm) says "already used or expired"; a confirmed account's link says "already confirmed". synced → specs/02 FR-AUTH-3, specs/04 §4, specs/23 §1.
9. `known_devices` (Open question 5): a browser already signed in is known from its sign-in, so the link sets nothing. An account's **first** sign-in (`last_login_at` null) gets a "First sign-in to your Clash Commons account" email instead of the new-device one, and no in-app copy (`TrackSignIn` now runs before `RecordLogin`). synced → specs/11 "Authentication attacks", specs/16 §2.
10. Verified-email gate (Open question 3): content writes only (FR-AUTH-4): uploads already check in `MediaPolicy`; later content routes add Laravel's `verified` middleware. Account, settings and notification writes stay open. synced → specs/02 FR-AUTH-4, specs/04 §3–4, specs/11 "Spam and fake accounts".
11. Events: `UserRegistered` → Users `CreateProfileForNewAccount` (queued, `high`); `EmailVerified` → Notifications in-app "Your email is confirmed" (`NotificationType::EmailVerified`, Security). Both after commit. No Notifications consumer for `UserRegistered`, no Users consumer for `EmailVerified` (nothing to unlock: gates read `email_verified_at`). synced → specs/05 §3, specs/16 §2.
12. UI: `Auth/Register`, `Auth/RegisterSent`, `Auth/VerifyEmail` (the address masked, `C***@example.com`, security review), `Auth/VerificationResult` (pending / result); `TurnstileWidget`; `VerifyEmailBanner` (a titled info alert at the top of every app page for unverified accounts, not the one-liner the Scope named: the title carries the ask, the line the consequence); sign-in ↔ register links; the Home hero "Create your account" for guests; Turnstile on `/forgot-password`. A failed register submit clears the password fields, as sign-in does. R-31: the same auth card register as sign-in and reset. synced → specs/18 §6, specs/11 "Data exposure via page props".
13. Routes: `/register`, `/register/sent`, `/email/verify`, `/email/verify/{ulid}/{hash}` (GET shows, POST confirms, `throttle:global-write`), `/email/verified`, `/email/verification-notification`. synced → specs/19 §4.
14. `RegistrationData` is a plain `final readonly` class, so the password never reaches the generated TypeScript (spec review).

### Review fixes (verify, 2026-10-01)
- Security (medium): GET verified the account, so mail gateways confirmed accounts nobody opened; now a confirm page and a POST (Decision 8), tested.
- Spec + security (low/medium): confirming now ends the account's other sessions (pre-hijack), and the first sign-in sends its own email instead of nothing (Decisions 8, 9).
- Security (low): one taken-email notice an hour per account; single-use form token; trapped submissions inside the timebox; masked address on the notice page (Decisions 2, 4, 12).
- Spec (medium): array fields gave a 500; now field errors, tested. Tests added: register error focus + Turnstile reset (Vitest), username rules (`UsernameFieldRulesTest`, a Feature test since the rules read config and the database), both race paths, the banner, the confirm page; security cases moved to `RegistrationSecurityTest`.
- Spec (low): atomic verify; the flow moved out of the controller into `RegistrationService::submit`; `RegistrationData` out of the generated types; config keys for the download floor, its timeout and the Turnstile token length; atomic blocklist write; stale forms get a field error; Turnstile load failure shown; Fortify comment updated (Decisions 4–8, 14).
- Found while testing: the username regex accepted a trailing newline; anchored (Decision 7).
- antislop audit-018: no findings.
