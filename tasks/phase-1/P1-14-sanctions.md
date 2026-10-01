---
id: P1-14
title: Let admins suspend, ban and lift sanctions from the user detail, with expiry and emails
phase: 1
status: done
depends_on: [P1-12]
---

# Add sanctions: suspend, ban, lift

## Spec refs
- Core: specs/12 §6 (sanction table, "all sanctions write user_sanctions + moderation_actions + audit_logs", expiry job, lifting needs a reason), §4 (action panel: mandatory reason and note, confirmation, "what the user will see" preview), §9; specs/07 `moderation_actions`, `user_sanctions`, `users.status*`
- Plus: specs/04 §1 (status effects), §2 (suspend / ban / lift: admin+), rule 1 (strictly outrank), rule 2 (reason mandatory in the service); specs/05 §1 (Moderation owns the tables; `SanctionService`), events (`SanctionApplied`); specs/16 §2 Security ("Account suspended / banned" I + E*, "Sanction lifted / expired" I + E); specs/20 §2–3 (`moderation:expire-sanctions` every 15 min); specs/11 §3 (sanctions are security events); specs/08 §2 (user → user_sanctions restrict); specs/18 §4 ActionPanel, AuditTrailList
- FR: FR-ADMIN-3, FR-MOD-5 (suspend, ban; each writes a moderation action), FR-MOD-6, FR-MOD-8 (user told the reason; appeal instructions arrive with P5-02)
- Edge cases: specs/23 §7 "A sanction expires while the user is mid-session" (per-request check, already enforced), "A banned user's content is still cached" (drop the profile cache now)

## Scope
- **Migrations** (07): `moderation_actions` (append-only like `audit_logs`; `case_id` nullable without FK until `report_cases`, P3-06) and `user_sanctions`, with their indexes; FKs restrict (08 §2).
- **Domain** (`Domain/Moderation`):
  - Enums `SanctionType` (warning, restriction, suspension, ban), `ModerationActionType` (07 list), `ReasonCode` (12 §2 taxonomy), with labels.
  - `SanctionService::suspend(actor, target, days 1–90, reason code, public reason ≤255, internal note)`, `ban(…)` (no duration), `lift(actor, sanction, note)`. One transaction with the target row locked: `moderation_actions` row, `user_sanctions` row, status set through Auth's `UserStatusService` (new `applySanction` / `clearSanction`), `audit_logs` entry (`sanction.applied` / `sanction.lifted`, before/after status), `security` log line. Reason and note required in the service (04 rule 2). Policy checked in the service (`UserPolicy::suspend|ban|liftSanction`, rank rule).
  - A ban also ends the account's sessions and cycles the remember token; every change drops the profile cache (`CacheInvalidator::profile`).
  - `SanctionApplied` / `SanctionLifted` after commit; queued listeners send the emails (in-app copies → P1-07).
  - `ExpireSanctions` + `moderation:expire-sanctions` (every 15 min, `withoutOverlapping`, `onOneServer`): lifts sanctions past `expires_at`, resets the status, audit entry with the scheduler as actor, "Sanction ended" email.
  - `SanctionHistoryQuery` → `SanctionData` list for the detail.
- **HTTP + UI**: `POST /admin/users/{ulid}/suspensions`, `POST /admin/users/{ulid}/bans`, `DELETE /admin/users/{ulid}/sanctions/{sanction}` (scoped, `global-write`), Form Requests per action. On `Admin/Users/Show`: an `AdminActionPanel` (Suspend / Ban / Lift, shown from policy `can` flags) opening a modal form: reason code, days (suspend), message to the user, internal note, a live "What they will see" preview, confirm. Sanction history list (type, reason, message, note, issued by, dates, lifted by and when). Validation errors inline; success toast.
- **Emails** (E*, queued on `high`): "Your account is suspended" (reason, end date), "Your account is banned" (reason), "Your suspension has ended" (lifted or expired).
- **Config keys** (`config/moderation.php`): `suspension_max_days` (90), `public_reason_max` (255).

## Out of scope
- Warnings and restrictions (moderator+), reports, cases and the moderation log viewer → P3-06; appeals and the appeal link → P5-02
- Tag release 30 days after a ban → Phase 2 (`ReleaseBannedUserTagsJob`); hiding content → when content exists (P3); ban-evasion flags → P5-02
- The user's own sanction history page (12 §9) → P5-02 with appeals; in-app notifications → P1-07

## Acceptance criteria
- Functional: FR-ADMIN-3 (suspend / ban with reason, duration and internal note; visible on the record); FR-MOD-5 (each writes a moderation action); FR-MOD-8 (the user is told the reason); Phase 1 exit: an admin suspends a user and the suspension takes effect on that user's next request.
- Authorization: admin+ only, strictly outranking the target (an admin cannot sanction an admin; nobody sanctions themselves); restricted / pending-deletion admins cannot act (staff actions need an active account); lifting needs `lift-sanction`.
- Edge cases: 23 §7 rows above; suspending an already banned account is refused; a new suspension or a ban replaces an active suspension (Open question 1).
- States: modal idle / saving / errors; history empty; 375 px and desktop.

## Tests
- Feature: suspend, ban and lift end to end (rows, status, audit entry, security line, email queued); suspension effective on the next request; ban signs the user out; expiry command lifts, resets status and emails, and is idempotent; history on the detail.
- Security: role × status × rank matrix on all three routes; foreign sanction id on another user's route 404s (scoped binding); mass assignment (`status`, `role`, `issued_by` ignored); reason / note required in the service, not only the form; XSS in message and note.
- Unit: duration bounds; overlap rules; email copy.
- Vitest: `AdminActionPanel` preview and confirm.

## Notes

### Open questions
Resolved by the owner, 2026-10-01 (all as recommended):
1. One active suspension or ban per account. A new suspension replaces an active one (the old one is lifted, noted "replaced"); a ban replaces an active suspension; suspending a banned account is refused. synced → specs/12 §6.
2. `lift` joins `moderation_actions.action` for ending suspensions (later restrictions); `unban` stays for bans. synced → specs/07 `moderation_actions`.
3. One task: backend, emails, expiry and the action panel together.

### Decisions and divergences (implement, 2026-10-01)
1. Lifting needs no sanction id: an account has at most one active suspension or ban, so the route is `DELETE /admin/users/{ulid}/sanction`, and apply is `POST …/suspension` / `POST …/ban`. No autoincrement id reaches a URL (specs/04 §3). No spec change (routes live in `routes/admin.php`).
2. `moderation_actions` is append-only by trigger on Postgres and SQLite, like `audit_logs`. Apply actions target the account (`target_type = user`); lift / unban actions target the sanction (`user_sanction`, its id), which is how the history finds the lift note. A replaced sanction gets a `lift` action noted "Replaced by a new suspension." / "… ban." and the new action's `metadata.replaced_sanction_id`. synced → specs/07 `moderation_actions`.
3. `lift` added to `moderation_actions.action` (Open question 2). synced → specs/07.
4. Expiry writes no moderation action (no human actor) and leaves `lifted_at` null: a sanction past `expires_at` is "ended", not "lifted". The job resets `users.status*`, writes `sanction.expired` with the scheduler as actor (`context.via = scheduler`), and emails once; it picks only accounts whose status is still set, so a rerun does nothing. synced → specs/12 §6, specs/07 `user_sanctions`.
5. Schedule: `7-59/15 * * * *`, so it never starts at :00 (specs/20 §3: only the heartbeat may). synced → specs/20 §3.
6. A ban cycles the target's remember token and deletes its session rows directly in `UserStatusService::applySanction`. `RememberCookie::cycle` is not used: it would also clear the acting admin's own cookie. synced → specs/04 §1, specs/12 §6.
7. Suspending or banning a pending-deletion or soft-deleted account is refused, until account deletion (P1-11) defines how the two interact. synced → specs/12 §6.
8. Restricted and pending-deletion admins cannot act: the staff-action Gates need an active account. The Form Requests check the ability first (403 before validation); `SanctionService` checks the rank rule with the account, and the controller 404s accounts the viewer cannot see (own, super admins) before either.
9. A refusal (`SanctionRefused`, a `ValidationException` on `sanction`, since Http may not reference Domain exceptions) comes back as a field error shown in the dialog; success is a client toast (no page shows flash messages yet).
10. Audit actions `sanction.applied`, `sanction.lifted`, `sanction.expired`; before/after are `{status, reason, until}`; context carries the type, reason code, days, the lift note and what was replaced. Security lines `moderation.sanction_applied|lifted|expired`. synced → specs/11 §3.
11. `ReasonCode` follows specs/12 §2, including `wrong_category`, which specs/07 `reports.reason_code` lacked. synced → specs/07 `reports`.
12. Emails (queued on `high`): "Your Clash Commons account is suspended" (reason, end in UTC), "… is banned" (reason), "Your Clash Commons suspension / ban is over" (lifted or ended). No appeal link until P5-02; in-app copies → P1-07. synced → specs/16 §2.
13. New UI: `AdminActionPanel` (buttons from `SanctionAbilitiesData`, dialogs with reason, length, message, internal note and a live "What they will see" preview) and `AdminSanctionHistory`, both in `/dev/components`. The dialog uses `UiModal` as it is, display-font title included. R-31: the preview box uses the page background to read as "their screen", not ours. synced → specs/18 §4.
14. Config `config/moderation.php` `sanctions.*`: `suspension_max_days` 90, `public_reason_max` 255, `note_max` 2000 (internal and lift notes).
15. Moderation calls Auth's `UserStatusService` inside the sanction transaction (not an Auth listener on `SanctionApplied`), and writes the audit entry itself (not an Audit listener). synced → specs/05 §1, §2 (events table).
16. Expiry runs inline in `moderation:expire-sanctions` through `SanctionService::expireDue` (no queued job). synced → specs/20 §2.
17. `SanctionApplied` / `SanctionLifted` implement `ShouldDispatchAfterCommit`, so a later caller wrapping the service in its own transaction cannot send a notice for a rolled-back sanction. The listener skips stale notices: an "applied" one for a sanction no longer active, an "over" one while another sanction is active.
18. A ban ignores any `days` passed in (the record is permanent); the rank rule is checked again on the locked row inside the transaction.
19. Mail Markdown uses secured encoding app-wide (`Markdown::withSecuredEncoding()` in `AppServiceProvider`), so an admin's message shows as typed, never as a link or image. synced → specs/11 "XSS".
20. One migration creates both moderation tables, like earlier multi-table migrations (`create_media_tables`).

### Review fixes (verify, 2026-10-01)
- Spec (medium): email copy tests render each `toMail()` (subject, reason, UTC end, lifted vs ended, `high` queue): `tests/Feature/Moderation/SanctionNotificationsTest.php`.
- Spec (medium): the rank matrix runs on all three routes (user, moderator, admin targets), and the service test covers equal-rank ban and lift and self-lift.
- Spec (low): Decisions 15 and 16 added and synced.
- Spec (low): events after commit and stale-notice guards (Decision 17).
- Spec (low): migration name left as is (Decision 20).
- Security (low): ban `days` ignored; rank re-checked under the lock (Decision 18).
- Security (low): Markdown in sanction emails rendered literally (Decision 19), with a rendering test.
- antislop audit-015: no findings.
