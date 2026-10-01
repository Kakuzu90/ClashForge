---
id: P1-11
title: Add account deletion and the Danger zone
phase: 1
status: done
depends_on: [P1-05, P1-06]
---

# Add account deletion and the Danger zone

## Spec refs
- Core: specs/04 §1, §3–4 (pending deletion, own-account policies, re-confirmation); specs/11 "CSRF", "Data exposure via page props", §5 (deletion).
- Plus: specs/08 §6 (anonymisation); specs/07 `users`, `profiles`, `audit_logs`; specs/18 §6 Settings; specs/10 §9 (media purge); specs/20 §2 Platform, §3 (nightly pipeline); specs/19 §7 (command).
- FR: FR-AUTH-9, FR-AUTH-4 (unverified settings writes); NFR-PRIV-2 (specs/03 §5).
- Edge cases: specs/23 §1 (same-email registration after 30 days; deletion held for disputes/orders).

## Scope
- Migrations / models / factories: nullable tombstone password, internal tombstone username exception, deletion timestamps/index; create `username_history` with `reserved_forever` for deletion reservations (specs/07 `users`, `username_history`; specs/08 §6).
- Domain: Auth service requests deletion, cancels on sign-in and anonymises due accounts transactionally; Users service clears profile data and invalidates its caches; AuditLogger records anonymisation without PII. Cross-module writes use public services (specs/05 §2–3, specs/08 §6).
- Policy + Form Request: authorize own-account deletion server-side; enforce account standing, global-write and password-confirm throttles and inline current-password confirmation (specs/04 §1, §3–4; specs/11 "CSRF").
- UI: Danger zone in settings navigation, controller with DTO props and Inertia page using existing Ui primitives; red-bordered confirmation form explains the 30-day window and sign-in cancellation (specs/18 §6; specs/04 §1). Regenerate types/routes (specs/19 §8).
- Jobs / schedule: `platform:anonymize-deleted --dry-run`, daily 04:00; idempotent, catch-up-safe processing and audit entry; release avatar through Media's existing lifecycle, preserve quarantine and moderation/audit records (specs/19 §7; specs/20 §2–5; specs/08 §6; specs/10 §3, §9).
- Config: name the 30-day deletion window in `platform` config, with tests reading it (specs/19 §5; specs/08 §6).

## Out of scope
- Data export, staff hard-delete, never-verified purge (P1-16); deletion of future CoC/base/recruitment/marketplace data and dispute/order holds join when those modules exist (specs/08 §6; specs/23 §1; tasks/BOARD.md).

## Acceptance criteria
- Functional: request ends all sessions/remember-me; pending deletion hides the profile and reserves the email; fresh sign-in cancels; anonymisation frees the email, permanently reserves the original username and clears Phase 1 data (FR-AUTH-9; specs/08 §6; specs/23 §1).
- Authorization: own account only; password confirmation enforced independently of UI; unverified/restricted accounts may request, suspended/banned cannot; cancellation preserves effective sanctions (specs/04 §3–4).
- Edge cases: cancellation racing the nightly pipeline cannot anonymise a restored account; reruns create no duplicate audit entry; retained compliance records survive (specs/08 §6; specs/20 §4–5).
- States: idle, submitting, confirmation, validation/throttle errors; keyboard focus and phone/desktop layout (specs/18 §5–6, §8).

## Tests
- Feature (`assertInertia`): Danger zone props; request, cancellation, 30-day boundary, same-email re-registration, permanent username reservation, nightly command and dry-run (FR-AUTH-9; specs/23 §1).
- Security: ownership/status matrix, stale confirmation, CSRF, mass assignment, DTO exposure, sessions/remember-me and retained sanctions (specs/04 §3–4; specs/11 §2).
- Unit: deadline/state rules if extracted; Vitest only for new confirmation logic (specs/19 §6).
- Integration: idempotence and cancellation race, profile/media cleanup, immutable audit retention (specs/08 §6; specs/20 §4–5).

## Notes
### Owner decisions (2026-10-01)
All three recommendations approved; no open questions.
1. End all sessions/remember-me on request; cancel on fresh sign-in, preserving effective sanctions; suspended self-deletion blocked. Synced → specs/04 §4, specs/02 FR-AUTH-9, specs/23 §1.
2. Clear remaining profile/login data, notifications/preferences and reset tokens; retain privacy/stats with defaults/zero counts and all moderation/audit records. Synced → specs/08 §6.
3. Nullable tombstone passwords, longer internal `deleted_user_{ulid}` names and permanent original-username reservations; public validation stays 3–20 chars. Synced → specs/07 `users`, `username_history`; specs/08 §6; specs/23 §1.

### Implementation decisions
- `deletion_previous_status` preserves restrictions/reasons/expiry; `deleted_at` is set at anonymisation so password sign-in can cancel. Both paths lock the user; cancellation is private to `AuthenticationService`. synced → specs/04 §4, specs/07 `users`.
- Nightly command runs inline in configurable batches; `user.anonymised` audit entries carry only status and command. All owned media except quarantine is queued for purge after commit. synced → specs/20 §2, specs/07 `audit_logs`, specs/10 §9.
- Notification writers lock the user and skip tombstones; notification preferences cleanup joins when its table exists (no table in Phase 1). synced → specs/08 §6.
- Module public surfaces document the deletion/cleanup services; Auth calls them transactionally. synced → specs/05 §2.
- R-31: Danger zone uses the existing settings surface, spacing and radius with spec 18's required danger border; no new component variant. Checkbox + current-password field + danger button and unavailable state. synced → specs/18 §6.
- Review fixes: unique per-account email hashes; post-insert username reservation recheck; media finalisation/failure state locks; password-reset/token/completion locks prevent stale workers restoring tombstone data. Regression coverage exercises each overlap. synced → specs/08 §6, specs/10 §9.

### Verification (2026-10-01)
- `scripts/check.sh`: all 13 checks passed; PostgreSQL 1,102 tests / 9,328 assertions; SQLite 1,101 passed / 9,325 assertions, one PostgreSQL-only trigger test skipped; Vitest 154 tests across 42 files; client + SSR builds passed.
- Spec/security reviews: no remaining findings after race fixes. antislop: no findings (`anti-slop/audit-020-2026-10-01.md`); R-35 browser click-through deferred to phase end per workflow.

- Follow-up owner request (2026-10-01): current password entered directly on every deletion submission, checked under the account lock; no password-confirmation redirect. Reuses UiInput and shared guess limiter, clears password after submission. synced → specs/04 §4, specs/11 "CSRF", specs/18 §6.

### Inline-password verification (2026-10-01)
PASS formatting
PASS PHP static analysis
PASS Domain static analysis
PASS architecture checks
PASS TypeScript
PASS lint
PASS SQLite: 1,103 tests / 9,345 assertions; one PostgreSQL-only skip
PASS PostgreSQL: 1,104 tests / 9,348 assertions
PASS type generation
PASS route generation
PASS generated-file freshness
PASS Vitest: 154 tests
PASS client and SSR builds
PASS spec/security reviews: no findings
PASS antislop: no findings (`anti-slop/audit-021-2026-10-01.md`); R-35 deferred to phase end
