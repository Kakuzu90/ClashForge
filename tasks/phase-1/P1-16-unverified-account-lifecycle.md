---
id: P1-16
title: Remind and purge never-verified accounts
phase: 1
status: done
depends_on: [P1-08]
---

# Remind and purge never-verified accounts

## Spec refs
- Core: specs/23 §1 ("User never verifies their email"); specs/04 §4 (Registration, Account deletion); specs/07 Auth & identity (`users`). Bundle: specs/25 §4 P1-16.
- Plus: specs/16 §2, §4 (security mail, templates); specs/20 §1, §3–5 (queues, scheduling, idempotency); specs/08 §6 (cleanup and retained records); specs/11 §2–3 (authentication, secrets, logging); specs/19 §5–8 (config, commands, tests).
- FR: FR-AUTH-3/4 (verification and existing restrictions), FR-NOTIF-3 (verification mail); purge timing comes from specs/23 §1, not a separate FR.
- Edge cases: specs/23 §1 (never verifies, deletion/cancellation races); §9 (UTC clocks, cache eviction, worker retries).

## Scope
- Migrations / models / factories: persist lifecycle progress only as needed for repeat-safe reminder/warning dispatch; extend `users` and its factory with schema/index changes documented in specs/07 (specs/20 §4; specs/19 §8).
- Domain: Auth lifecycle service selects never-verified accounts, locks and re-checks eligibility before each action, and performs cleanup through module public services; reuse the deletion pipeline subject to Decision 2 (specs/05 §2; specs/08 §6; specs/23 §1).
- Policy + Form Request: trusted scheduler/console operation, no new web write path or Form Request; document system eligibility rather than weakening existing self-deletion policies (specs/04 §3–4; Decision 3).
- UI: reminder and warning Markdown emails with plain-text alternatives and the existing signed 60-minute verification flow; no new Inertia page or controls (specs/16 §4; specs/04 §4; Decision 1).
- Jobs / schedule: chunked daily command with `--dry-run`, summary counts, scheduler overlap/single-server/failure protection; queued mail with bounded retries, scalar payloads and fresh eligibility checks (specs/19 §7; specs/20 §3–5).
- Config: named reminder (3 days), warning (Decision 1), purge (30 days), batch and schedule settings under `platform.auth`; UTC age measured from registration, not last login (specs/23 §1, §9; specs/19 §5).

## Out of scope
- Provider bounce/complaint webhooks (P0-09), changing content gates, staff hard-delete UI, and future CoC/order cleanup (specs/16 §4; specs/04 §2; specs/08 §6).

## Acceptance criteria
- Functional: one day-3 reminder, one final warning, and purge at the approved deadline; verification stops subsequent actions; repeated runs/retries do not repeat completed actions (specs/23 §1; specs/20 §4; Decision 1–2).
- Authorization: process only the approved eligible accounts; no public purge endpoint; existing verification signature, address binding and POST confirmation remain effective (specs/04 §4; specs/11 §2; Decision 3).
- Edge cases: lock against verification/deletion races, skip tombstones, preserve moderation/audit records, invalidate sessions/reset tokens, and prevent delayed jobs recreating cleaned data; cache eviction does not reset lifecycle progress (specs/08 §6; specs/23 §1, §9).
- States: dry-run changes nothing and sends nothing; zero eligible accounts is a successful empty run; mail/cleanup failures are observable and retryable; emails name the deadline and verification action (specs/19 §7; specs/20 §4–5; Decision 1).

## Tests
- Feature: UTC boundaries at 3/warning/30 days, verified/ineligible skips, repeated runs, dry-run, mail rendering/link expiry, cleanup and audit once (specs/19 §6–7; specs/23 §1).
- Security: verification racing purge, stale mail after verification/email change/anonymisation, session and reset-token invalidation, retained sanctions, no PII/secrets in logs or queue payloads (specs/11 §2–4; specs/08 §6).
- Unit: lifecycle timing/eligibility if extracted; Vitest / new `assertInertia`: not applicable, existing verification UI is reused (specs/19 §6).

## Notes
### Approved decisions
Owner approved all recommendations, 2026-10-01; sync → specs/04 §4, specs/08 §6, specs/16 §2, specs/23 §1.
1. **Warning/mail:** day 27; both notices always-on security mail on `high`; overdue runs grant three days after enqueue.
2. **Purge:** specs/08 §6 anonymisation at day 30, without another grace period; retain audit/sanctions, permanently reserve the username and release the email.
3. **Eligibility:** ordinary users (active/restricted/suspended/banned); exclude staff and pending self-deletion (specs/04 §1, §4).

### Implementation notes
- Delayed mail receives three days from successful send as well as enqueue, so queue backlog cannot consume the warning period; transport failures block purge. Synced → specs/04 §4, specs/07 `users`, specs/20 §2, specs/23 §1.
- Four guarded/hidden timestamps and a dispatch ULID record dispatch and completed sends. Database queue insert and dispatch marker share the transaction (`beforeCommit`); SMTP acceptance/crash duplication remains the P1-15/P0-09 limitation. Synced → specs/07 `users`, specs/20 §4.
- The scheduled operation uses nullable-actor `UserPolicy` methods for system eligibility, without widening web self-deletion permissions. Shared cleanup captures the actual prior status; verification re-checks the address under the same account lock. Synced → specs/04 §4, specs/08 §6.
- Security review fixes: existing settings/role/login/session writes share the account lock and reload the identity; skipped unsent notices clear their matching dispatch marker so self-deletion cancellation cannot strand cleanup. PostgreSQL races cover verification/profile/email/role writes in both orders. Synced → specs/04 §4, specs/07 `users`, specs/08 §6, specs/11 §5, specs/20 §4.

### Verification
2026-10-02: all 13 `scripts/check.sh` checks pass; Pest SQLite 1,244 passed / 10 PostgreSQL-only skips, PostgreSQL 1,254 passed; Vitest 167 passed. Spec/security reviews and [antislop audit 024](../../anti-slop/audit-024-2026-10-02.md): no findings. Both local migrations applied and workers restarted; no cleanup command run locally. Phase-end accessibility/R-35 checks remain pending.
