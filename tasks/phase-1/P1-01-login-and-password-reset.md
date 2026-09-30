---
id: P1-01
title: Build login, logout, remember-me and password reset on Fortify, with the users table in its specs/07 shape
phase: 1
status: done
depends_on: [P0-01, P0-02, P0-03, P0-04, P0-05, P0-06, P0-07, P0-08]
---

# Build login, logout, remember-me and password reset

## Spec refs
- Core: specs/04 §4 (mechanism, password policy, session security, `login` + `password-reset` limiters, account recovery); specs/11 "Authentication attacks", "Account enumeration", "Session security", §4 auth suite; specs/07 "Auth & identity" (`users`, `sessions`, `password_reset_tokens`)
- Plus: specs/06 "Auth scaffolding" (Fortify backend only, our views); specs/16 §1 Security rows (password reset email: E*, immediate); specs/20 §1 (`high` queue for user-visible mail); specs/18 §4 primitives, §5 layout, §8 accessibility; specs/05 §2 (Auth module); specs/19 §4 (`routes/web/auth.php`)
- FR: FR-AUTH-2 (password policy on reset), FR-AUTH-5, FR-AUTH-6
- Edge cases: specs/23 §1 "reset requested for a non-existent account", "reset link used twice"

## Scope
- **Migration / model / factory** (07 `users`): reshape the scaffold table to specs/07 minus P1-02/P1-03/P2 columns. Add `ulid`, `username` (citext on Postgres, `COLLATE NOCASE` on SQLite), `email` citext, `last_login_at`, `last_login_ip_hash`, `deleted_at`, and the partial `(created_at)` index. Keep `remember_token` (04 §4 remember-me). Drop `name`, which lives in `profiles` from P1-03. `UserFactory` gains `username`/`ulid` and an `unverified()` state; the P0-05 dev sign-in and the seeders are updated.
- **Domain** (`Domain/Auth`):
  - `AuthenticationService`: an email lookup that runs a dummy hash check when the user is unknown, so unknown email and wrong password match in response and timing (11). On login: regenerate the session, set `last_login_at` and the salted-hash IP, rehash when the cost changed (04 §4).
  - `PasswordResetService`, implementing Fortify's `ResetsUserPasswords`: validates ≥10 chars + `uncompromised()` (FR-AUTH-2). On success it deletes the user's other `sessions` rows, cycles the remember token and consumes the token (FR-AUTH-6).
  - Event `PasswordReset`.
- **Fortify wiring** (`FortifyServiceProvider`): features `resetPasswords` only (registration and verification land in P1-08). Views are `Inertia::render`. A custom `FailedPasswordResetLinkRequestResponse` returns the same generic status as success (11 enumeration). A used or expired token shows "This link has already been used or has expired" (23 §1).
- **Limiters** (central, `Cache`-backed, 04 §4): `login` 5/min then 20/hour on ip + email; `password-reset` 3/hour on ip + email.
- **Mail**: password reset notification on queue `high` (20 §1), plain-text-first, our copy.
- **HTTP + UI** (`routes/web/auth.php`, `guest`/`auth` middleware):
  - Routes: `GET/POST /login`, `POST /logout`, `GET/POST /forgot-password`, `GET /reset-password/{token}`, `POST /reset-password`.
  - Pages `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword` on `PublicLayout`, built from `UiInput`/`UiButton`/`UiCard`. Each has field errors, a generic error, a throttled message with retry time, a submitting state and a sent state.
  - Header: "Sign in" link for guests, account menu with "Sign out" for users; nav items via `navigation.ts`.
  - After login, redirect to the intended URL or `/`.
- **Session config** (04 §4, 11): `encrypt` true, `secure` from env (true outside local), `same_site` lax, idle lifetime 14 days. Logout invalidates the session and cycles the remember token.
- **Config keys**: `auth.login_limits`, `auth.password_reset_limit`, `auth.passwords.users.expire` 60, `hashing.bcrypt.rounds` 12, HIBP timeout.

## Out of scope
- Registration, email verification, Turnstile, disposable-email and username rules, `UserRegistered`/`EmailVerified` → **P1-08** (new row, split from this task)
- `role` / `status` columns, `EnsureAccountIsActive`, the banned-login block → P1-02
- Session device label, `ip_hash` column, absolute 30-day lifetime, session list/revoke, 15-min re-auth → P1-05
- New-device sign-in email (11, 16 §1) → P1-05 (owner, Open question 3)
- 2FA → Phase 2; `username_history` → P1-03

## Acceptance criteria
- Functional: FR-AUTH-5 (limited, identical responses), FR-AUTH-6 (60-min single-use token, all other sessions ended), FR-AUTH-2 on the new password.
- Authorization: guests only on login/forgot/reset; `POST /logout` for signed-in users only; no route reveals whether an email exists.
- Edge cases: reset for an unknown email → same response and similar timing, no mail; second use of a reset link → "already used or expired".
- States: idle, submitting, field error, generic error, throttled, link sent, reset done — designed for all three pages at 375 px and desktop.

## Tests
- Feature (`assertInertia`): each page renders with its props; login happy path + intended redirect; remember-me cookie set; logout clears the session and cycles the token; reset request → mail queued on `high`; reset → password changed, other sessions gone, token deleted.
- Security (`tests/Security/Auth`): enumeration (login, reset: same status, message and redirect for unknown vs known), fixation (session id changes on login), reset-token reuse, 6th login in a minute → 429 keyed ip + email, 4th reset request in an hour → 429, compromised password rejected on reset, mass assignment (`email_verified_at`, `remember_token` not fillable).
- Unit: IP hash helper; timing-equal lookup (the dummy hash runs on an unknown email).
- Vitest: none expected (Inertia `useForm` pages with no custom logic).

## Notes

### Decisions
- Fortify's routes are off (`Fortify::ignoreRoutes()`). `routes/web/auth.php` maps Fortify's controllers with our limiters and names. synced → specs/19 §4.
- Limits live in `config/platform.php` `auth.*` (`login_per_minute` 5, `login_per_hour` 20, `password_reset_per_hour` 3, `min_password_length` 10, `hibp_timeout` 2). Scope's `auth.*` names changed because the backend rules list the config files limits may live in. synced → specs/19 §5.
- A throttled auth form comes back as a field error with the wait time ("Too many attempts. Try again in 60 seconds."), not a bare 429, which Inertia would show as an error modal. synced → specs/04 §4.
- `POST /forgot-password` is our `ResetLinkRequestController`, not Fortify's. It validates the email and queues `SendPasswordResetLinkJob` (`high`) for every well-formed address, then answers with one generic status. Fortify hashed the token inside the request, so known emails were ~40–100 ms slower (security review). The broker's 60 s per-account throttle stays on, inside the job, where it cannot leak. synced → specs/11 "Account enumeration", specs/20 §2.
- `auth.timebox_duration` is 700 ms (`AUTH_TIMEBOX_DURATION`, 1 ms in `phpunit.xml`), above the bcrypt-12 token check, so `POST /reset-password` takes the same time whether or not a token row exists (security review). synced → specs/11.
- Per-IP ceilings sit next to the ip + email buckets: `login` 30/min and `password-reset` 20/h per IP. Without them, one client rotating emails makes the server hash without limit (security review). synced → specs/04 §4.
- Accepted: guessing one account from many IPs is not capped by the limiters (a per-email cap would let anyone lock the owner out). Failed sign-ins carry the account ULID in the security log, which feeds the specs/11 §3 alert. synced → specs/11 "Authentication attacks".
- `POST /reset-password` has **no** limiter. The single-use 64-char token already prevents guessing, and counting new-password typos against `password-reset` locked a user out of the link they had just received (found in the browser run). synced → specs/04 §4.
- A used, expired or mismatched link shows one message: "This link has already been used or has expired. Ask for a new one." synced → specs/23 §1.
- Unknown-email timing: `AuthenticationService` checks against a cached bcrypt hash at the current cost (1-day TTL, regenerated when the cost changes), so an unknown email costs one hash check like a known one.
- `RecordLogin` is a **synchronous** `Login` listener (one narrow update), because the IP is only known in the request. specs/05 §2 says I/O listeners are queued; this is the exception. synced → specs/05 §2.
- HIBP: `HibpVerifier` extends `NotPwnedVerifier` and is registered with `extend()`. `bind()` was silently replaced by the framework's deferred `ValidationServiceProvider`, so the stock verifier ran; a test caught it. 2 s timeout, fail-open, logs `auth.hibp_unavailable`. synced → specs/04 §4.
- Uses Laravel's `Illuminate\Auth\Events\PasswordReset` (fired by Fortify) instead of our own event.
- `App\Support\Privacy\IpHash`: an HMAC-SHA256 keyed by `platform.ip_hash_salt` (`IP_HASH_SALT`, falls back to `APP_KEY`). synced → specs/07 `users`, specs/11 §5.
- `users`: `citext` on Postgres (the migration creates the extension; it is trusted on PG 13+), `NOCASE` on SQLite. `name` is dropped. Rows that existed before the migration (the dev sign-in user) get `username = user_{id}`. `SoftDeletes` means deleted users cannot sign in. synced → specs/07.
- Remember-me: the cookie lasts 30 days (`auth.guards.web.remember` 43200; the framework default is 400 days, security review). The token is cycled on logout and on password reset, not on every use; specs/04's "rotating recaller token" is reworded to match. synced → specs/04 §4.
- Security log (spec review): `auth.login`, `auth.logout` and `auth.password_reset` come from `LogSecurityEvents`. `auth.login_failed` comes from `AuthenticationService`, because Fortify's `Failed` event carries no user when a callback checks the password. `auth.rate_limited` comes from the limiter response. Each entry has the ULID (or null) and the hashed IP, never the email or input. synced → specs/11 §3.
- Sentry: the scrubber replaces the `/reset-password/{token}` path segment with `[filtered]`, because the token is in the path, not the query (security review). synced → specs/11 §3.
- Page props are `LoginPageData`, `ForgotPasswordPageData` and `ResetPasswordPageData` (spec review). Vue's `defineProps` needs an object literal, so pages reference the DTO field types (`Props['status']`) rather than the DTO as a whole.
- `users.email_verified_at`, `created_at`, `updated_at` and `password_reset_tokens.created_at` become `timestamptz` on Postgres (spec review).
- Turnstile on `/forgot-password` (specs/11 "API abuse") is added to the P1-08 row next to registration.
- Session defaults: `lifetime` 20160 (14 days idle), `encrypt` true, `secure` true unless `APP_ENV=local`. `.env.example` is updated; an existing `.env` that still sets `SESSION_LIFETIME=120` / `SESSION_ENCRYPT=false` overrides them.
- New primitives `UiCheckbox` and `UiAlert` (specs/18 §4 lists both). `UiStateIcon` is split out of `UiToast` so both share one icon set. The gallery gains "Checkboxes" and "Alerts".
- `focusFirstError()` (`Composables/useFirstErrorFocus.ts`) moves focus to the first invalid field after a failed submit (specs/18 §8).
- Header: `AccountControls` shows "Sign in" for guests (hidden on `/login`), and the username plus "Sign out" for signed-in users.
- Copy: `lang/en/auth.php` (one message for unknown email and wrong password) and a partial `lang/en/validation.php` (`confirmed`, `password.uncompromised`) that merges over Laravel's file. The reset email greets by username and signs off "Clash Commons".
- Queue workers need `queue:restart` to pick up new routes; the reset email failed until that ran. This is the existing deploy step (specs/20 §5).

### Follow-ups
- The mail layout still carries the framework footer ("© 2026 Clash Commons. All rights reserved.") → P1-07 (security emails / mail theme).
- `platform:rotate-ip-salt` (specs/20 §3): a rotating key breaks matching on older hashes. Decide how it works when it is built.
- `users` partial indexes on status/role/deletion → P1-02 / P1-05.

### Verification
- `scripts/check.sh`: all green, Pest on SQLite + Postgres.
- Browser (desktop + 375 px):
  - Sign-in: empty submit gives field errors with focus on email. Wrong password and unknown email get the same message.
  - A mixed-case email signs in, the header shows the username, `last_login_at` and a 64-char IP hash are stored, and the remember cookie is not readable from JS. Sign out works.
  - Forgot password: the same status shows for unknown and known emails, and Mailpit got one mail (known address only).
  - The link opens the reset page. Short, mismatched and breached (live HIBP) passwords are rejected. A valid one redirects to sign-in with the status. Reusing the link gives "already used or expired".
  - No horizontal overflow; checkbox rows are 44 px.
  - After the review fixes: the queued link path gives one status for both emails and sends one mail. The security log shows `auth.login_failed` (with ULID) and `auth.login`.
- Reviews: antislop audit-008, no findings. Spec review: 4 findings, all fixed. Security review: 7 findings, 6 fixed; distributed guessing accepted and documented (above).

### Open questions
Resolved by the owner, 2026-09-30:
1. `App\Models\User` stays the shared authenticatable identity (relationships and casts only); all writes go through `Domain/Auth` services. synced → specs/19 §1 (record the exception), specs/05 §2.
2. Split confirmed: registration + verification are **P1-08** (depends on P1-01); P1-02 depends on P1-01 only. synced → tasks/BOARD.md, specs/25 §4.
3. The new-device sign-in email ships with P1-05. synced → tasks/BOARD.md P1-05 row.
4. HIBP runs synchronously inside validation with a 2 s timeout; if unreachable, accept and log `auth.hibp_unavailable`. synced → specs/04 §4 "Password policy".
