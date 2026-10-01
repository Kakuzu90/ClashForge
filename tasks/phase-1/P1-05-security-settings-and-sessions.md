---
id: P1-05
title: Add the Security settings page with password change, session management and new-device sign-in emails
phase: 1
status: done
depends_on: [P1-02]
---

# Add Security settings: password, sessions, new-device emails

## Spec refs
- Core: specs/04 §4 (session security: regenerate, 14-day idle / 30-day absolute, device + IP + last-active rows, password change ends other sessions; password policy), §3 (settings writes open to restricted); specs/11 "Authentication attacks" (new-device emails, HIBP on change), "CSRF" (15-min re-confirmation), "Session security", §5 (IP hashing)
- Plus: specs/07 `sessions`, `users`; specs/16 §2 Security rows ("Password changed", "New sign-in from an unrecognised device", `E*`); specs/18 §6 Settings; specs/19 §4 `/settings/*`
- FR: FR-AUTH-7 (see and revoke sessions), FR-AUTH-2 (policy on the new password)
- Edge cases: specs/23 §1 "Session hijack suspected" (no auto-invalidation; new-device email + listed for revocation)

## Scope
- **Migration** (07 `sessions`): add `ip_hash`, `device_label`, `country_code` (`CF-IPCountry`) and `created_at`; drop the raw `ip_address`.
- **Domain** (`Domain/Auth`):
  - `SessionService`: `listFor(User)` → `SessionData` list (device label, country, last active, `isCurrent`; no ids beyond an opaque per-row key); `revoke(User, key)` (owner-scoped, 404 on a foreign key); `revokeOthers(User)`; stamps `ip_hash` / `device_label` / `country_code` on login.
  - Absolute 30-day cap (04 §4): sign-in time kept in the session, enforced by middleware; idle stays `session.lifetime`.
  - `PasswordChangeService::change(User, current, new)`: current password checked, new one ≥10 + `uncompromised()`, ends every other session, cycles the remember token, logs `auth.password_changed`, queues the "Password changed" email (E*).
  - New-device detection on `Login` via the encrypted `known_devices` cookie → queued "New sign-in" email with device label, country and time, and a link to the Security page. The in-app copy joins with Notifications v1 (P1-07).
  - 15-minute re-confirmation (11 "CSRF"): `auth.password_timeout` = 900 and the `Auth/ConfirmPassword` page, used by P1-10 (email change) and P1-11 (deletion). The password form asks for the current password inline instead.
- **Policy + Form Requests**: `UserPolicy::manageSessions` (own only, account-write standing); `ChangePasswordRequest`, `RevokeSessionRequest`.
- **HTTP + UI**: `GET /settings/security`; `PUT /settings/security/password`; `DELETE /settings/security/sessions/{key}`; `DELETE /settings/security/sessions` (all others) (`auth`, `account.active`, `global-write`). Page `Settings/Security`: password form (idle, saving, saved, field errors) and a session list (this device marked, revoke buttons with confirm, empty "only this device" state, row skeleton). `settingsNav` gains "Security". The header avatar opens an account menu (specs/18 §4 Dropdown menu, keyboard navigable): Your profile · Settings · Sign out (specs/18 §6, from P1-04).
- **Config keys**: `platform.auth.absolute_session_days` (30), `platform.auth.known_device_days` (cookie lifetime); see Notes 1 for the rest.

## Out of scope
- Email change (FR-AUTH-8) → P1-10; account deletion and the Danger zone (FR-AUTH-9) → P1-11
- In-app copies of security notifications → P1-07; 2FA → Phase 2; data export (NFR-PRIV-1) → later task

## Acceptance criteria
- Functional: FR-AUTH-7; FR-AUTH-2 on change; other sessions and the remember token die on change; current session survives.
- Authorization: own sessions only (a foreign key 404s); restricted accounts can change password and revoke; suspended can reach `/settings/*` reads but not writes (04 §1, §3).
- Edge cases: 23 §1 hijack row; a session past 30 days is signed out even while active.
- States: password form idle/saving/saved/errors; session list loading, single-session empty, revoke confirm; 375 px and desktop.

## Tests
- Feature (`assertInertia`): Security page props (no raw IP, no session ids, no payload); password change; revoke one / others; absolute cap; new-device email sent once, not for a known device; `settingsNav`.
- Security: IDOR on session revoke; mass assignment on both writes; status matrix; wrong current password limited and logged; props exposure.
- Unit: device label parsing; absolute-cap check.
- Vitest: session list revoke-confirm logic, if any.

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended):
1. Split in three: P1-05 here; email change → P1-10 (after P1-08); deletion and Danger zone → P1-11 (after P1-06). synced → tasks/BOARD.md.
2. A device is known when the browser holds the long-lived encrypted `known_devices` cookie listing the accounts that signed in from it; any other sign-in sends the email. No new table. synced → specs/11 "Authentication attacks".
3. Session region = Cloudflare `CF-IPCountry` in `sessions.country_code`, null locally. synced → specs/07 `sessions`, FR-AUTH-7.
4. The raw `sessions.ip_address` is dropped for `ip_hash`. synced → specs/11 §5.
5. Password change asks for the current password inline; the 15-min confirm page ships here for P1-10 / P1-11.

### Decisions and divergences (implement, 2026-10-01)
1. Config keys beyond the two planned: `known_devices_max` (10), `password_confirm_per_minute` (5) / `_per_hour` (20) for the new `password-confirm` limiter shared by the confirm page and the password form, `country_header` (`CF-IPCountry`; no code may name the CDN, per the storage-provider arch test). `auth.password_timeout` default 10800 → 900. synced → specs/04 §4.
2. Session columns come from `TrackedDatabaseSessionHandler`, registered over the `database` driver; `created_at` is timestamptz on insert, `last_activity` stays Laravel's int. synced → specs/07 `sessions`.
3. Session keys are an HMAC of the id (32 hex); a foreign, current, idle-expired or unknown key 404s. `RevokeSessionRequest` is not built: the routes take no body and the `{key}` constraint already 404s malformed keys. synced → specs/11 "Session security".
4. Revoking sessions and changing the password cycle the single remember token and clear this browser's remember cookie without re-issuing it; this browser stays signed in for its session. synced → specs/04 §4, specs/07 `users.remember_token`.
5. The 30-day cap counts from the password sign-in: a remember-me sign-in inherits it from the encrypted `remember_since` cookie, and without that cookie counts as expired (remembered browsers from before the deploy sign in once more). synced → specs/04 §4, specs/03 NFR-PRIV-3.
6. Every sign-in from a browser without the account in `known_devices` emails, so each existing account gets one email on its first sign-in after deploy. Carried → P1-08 (set the cookie at registration).
7. Suspended accounts read `/settings/security` but cannot change the password or revoke (04 §1).
8. New primitive `UiDropdownMenu` (header account menu, `/dev/components`); the header "Sign out" button moved into it. R-31: raised surface with the modal shadow, like the other floating layers. synced → specs/18 §4, §6.
9. Tests use real database sessions via `tests/Support/Auth/InteractsWithBrowsers`, which resets the per-request singletons the test app keeps and pins the 14-day idle limit (the local `.env` sets `SESSION_LIFETIME=120`).
10. Security emails ship as email only; in-app copies carried → P1-07. synced → specs/16 §2.

### Review fixes (verify, 2026-10-01)
- Security (medium): the session id is regenerated after a password change and after "sign out every other device", so a copied cookie dies.
- Security (medium): no remember cookie is ever re-issued, so a garbage recaller cannot be swapped for a real one.
- Security (medium): remember-me can no longer outlast 30 days (Decision 5).
- Security (low): the country header is read only through a trusted proxy; the `password-confirm` limiter gained an hourly tier.
- Spec (medium): the absolute-cap redirect is a 303, so an Inertia PUT or DELETE is not repeated against `/login`.
- Spec (medium): idle-expired rows are left out of the list and the counts.
- Spec (low): `ext-intl` in `composer.json`; suspended writes asserted as 403; confirm-page throttle tested.
- antislop audit-012: no findings.
