---
id: P1-15
title: Add queued non-security email with a daily cap and unsubscribe
phase: 1
status: done
depends_on: [P1-07]
---

# Non-security email

## Spec refs
- Core: specs/16 §2 (Media processing failed), §4 (email), §5 (preferences), §8 (testing); specs/07 Notifications (`notification_preferences`).
- Plus: specs/20 §1, §2 Notifications, §4–5 (queue, delivery, retries); specs/04 §1–3 (ownership and write gating); specs/11 §2 (CSRF, IDOR, XSS, props), §5 (email privacy); specs/08 §6 (deletion); specs/18 §4, §6 Settings (UI).
- FR: FR-NOTIF-4 (email controls and global opt-out); FR-NOTIF-3 remains a regression constraint. Media-failure email is specified by specs/16 §2, without its own FR id.
- Edge cases: specs/23 §1 (permanent bounce); provider-dependent handling deferred below.

## Scope
- Migrations / models / factories: preferences/global email flag and completed-delivery receipts; internal models, factories and deletion cleanup (07 Notifications; 08 §6).
- Domain: Notifications owns non-security delivery and preference changes through services and DTOs; consume `MediaRetriesExhausted` without adding dependencies to Media. Keep the existing in-app notice (05 §2–3; 16 §1–2).
- Policy + Form Request: owner-only email preference edits, including unverified/restricted/suspended/pending-deletion owners; Security locked. Signed unsubscribe capability permits only disabling mail, without sign-in; preserve browser CSRF and throttle writes (04 §3; 16 §5; 11 §2).
- UI: thin controllers and DTO-only Inertia pages for unsubscribe and its preferences destination, using existing button, toggle, card and alert components; regenerate types/routes (16 §4; 18 §4, §6; 19 §8).
- Jobs / listeners / schedule: `SendEmailNotificationJob` on `low`, recipient/event identifiers only, preferences checked at delivery; cache-backed maximum 10 non-security emails/user/day. Apply bounded retries and idempotency; no new schedule. First caller: media processing exhausted its retries (16 §2, §4; 20 §1–2, §4–5).
- Config: name the daily cap, counter window and unsubscribe-link lifetime rather than hardcoding; follow existing `platform.notifications.*` conventions (16 §4–5; 19 §5).
- Mail: responsive Markdown layout plus plain text, List-Unsubscribe on every non-security message; exclude secrets and third-party addresses (16 §4).

## Out of scope
- Provider-specific bounce/complaint webhooks, bouncing banner and DNS/stream setup await the mail-provider account in P0-09; retain these requirements for that follow-up (BOARD P1-15; 16 §4; 23 §1).
- Digests, grouping, full Phase 5 preferences, future catalogue events and migration of existing security emails (16 §3, §5; 20 §2).

## Acceptance criteria
- Functional: exhausted media processing queues its email on `low`; opt-outs suppress delivery; concurrent jobs cannot exceed 10/day; retries do not duplicate completed sends. Security email remains non-disablable and outside this cap (16 §2, §4–5; 20 §4).
- Authorization: preference edits affect only the owner, including staff; unsubscribe is recipient-bound, repeat-safe, expires and is invalidated by email change/anonymisation. GET never mutates. No address exposed in public props (04 §3; 16 §5; 11 §2, §5).
- Edge cases / states: deleted recipients receive nothing; queued mail re-checks preferences; cap exhaustion is a clean skip. Preferences and unsubscribe show submitting, success and invalid-link/error states at mobile and desktop sizes (08 §6; 20 §4; 18 §4–6).

## Tests
- Feature (`assertInertia`): preferences/unsubscribe props, valid submission and validation; real job delivery with mail fake, multipart content and headers; media event preserves its in-app notice (16 §8; 25 §2).
- Security: foreign-account edits, tampered/stale unsubscribe links, wrong signed-in account, mass assignment, CSRF/signature boundary, secret-free mail and props (04 §3; 16 §5; 11 §2, §4–5).
- Unit / job: cap concurrency and day rollover, preference changes after enqueue, retries, deletion cleanup and security-category exclusion; Vitest for any form logic (16 §8; 20 §4; 08 §6; 19 §6).

## Notes
### Open questions
None. Both recommendations approved by the owner, 2026-10-01.
1. Bring `notification_preferences` and email controls forward; keep in-app controls and digests for Phase 5. Synced → specs/02 FR-NOTIF-4, specs/07 Notifications, specs/16 §5, specs/18 §6, specs/25 Phase 1/5 and §4.
2. Signed, recipient-bound unsubscribe without sign-in disables all non-security email. GET shows the page, POST applies it with browser CSRF; links expire and die on email change/anonymisation. Synced → specs/04 §3, specs/16 §4–5, specs/18 §6.

### Implementation decisions
- Lazy preference rows keep GET read-only; email saves preserve in-app/digest settings. HMAC-bound unsubscribe expires after 30 days. Synced → specs/07 Notifications, specs/16 §5.
- UTC cap plus durable event receipts survives cache eviction and completed-job replay; recipient locks serialize sends, preference changes and anonymisation. SMTP acceptance before receipt commit remains a possible duplicate on crash. Synced → specs/05 §2, specs/07 Notifications, specs/08 §2/6, specs/16 §4, specs/20 §2.
- R-31: reuse plain settings forms and a single confirmation card so email choices and the unsubscribe consequence are easy to read (DESIGN.md work register; specs/18 §6).
- Verified: all 13 `scripts/check.sh` checks passed; SQLite 1196 passed / 2 skipped, PostgreSQL 1198 passed (including concurrent workers), Vitest 167 passed. Spec/security reviews and [audit-023](../../anti-slop/audit-023-2026-10-01.md): no findings. Local migration applied and queue restart signalled; R-35 remains phase-end work.
