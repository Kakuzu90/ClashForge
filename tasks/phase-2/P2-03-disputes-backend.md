---
id: P2-03
title: Ownership disputes backend (schema, service, guardrails, schedule, admin decision)
phase: 2
status: done
depends_on: [P2-02, P1-06]
---

# Ownership disputes (backend)

## Spec refs
- Core: specs/13 §2 (state machine; `disputed` keeps rights), §3.1 step 8 (claimant's token auto-resolves), §5 (workflow, evidence, decision bias, guardrails); specs/07 `coc_account_disputes`, `moderation_actions`; specs/04 §2 ("Resolve ownership dispute": admin+, `resolve-disputes`)
- Plus: specs/08 §2 (account → disputes: restrict), §3.1 (transfer), §5 (`verified_accounts_count` counts `disputed`); specs/10 (private `evidence` media, 3 per item, staff-only signed URLs); specs/11 §3 (security log), specs/12 (moderation actions, audit trail); specs/05 §2 (`DisputeService`, events); specs/20 (scheduled jobs)
- FR: FR-COC-6, FR-COC-7, FR-COC-8, FR-ADMIN-2 (disputes)
- Edge cases: specs/13 §9 (claimant verifies while their dispute is open; holder banned mid-dispute; evidence containing an ID document); specs/23 §1 (deletion while a dispute involves the user)

## Scope
- **Migration / model / factory:** `coc_account_disputes` per specs/07, statuses `open`, `awaiting_admin`, `awaiting_claimant`, `awaiting_holder`, `resolved_transfer`, `resolved_denied`, `resolved_suspended`, `withdrawn`, `auto_resolved` (Open question 2). It has a ulid, a partial unique (one active dispute per claimant per tag) and the indexes. Evidence is a JSON list of up to 3 `evidence` media ULIDs per party, plus notes. Claimant and holder evidence are kept apart.
- **Enums:** `DisputeStatus`, `DisputeDecision` (transfer / deny / suspend / request_info).
- **`DisputeService`:**
  - **`open`** (claimant): the tag must have a verified holder who is someone else. One active dispute per claimant per tag; at most `coc.disputes.max_open_per_user` (2) open; refused for `bar_days` (90) after 2 denials. The holder's row becomes `disputed`.
  - **`respond`** (holder): a counter-statement with evidence → awaiting admin.
  - **`release`** (holder) → `resolved_transfer`: the holder's row is `released` and the claimant gets a `verified` row (`verification_method=admin`, `decided_by` null, a voluntary release in the audit entry) (Open question 4).
  - **`decide`** (admin): transfer (the claimant gets a `verified` row with `verification_method=admin`, the holder's row goes `unverified`), deny (back to `verified`), suspend (the tag is `suspended`), or request info. An admin who is a party is refused. The decision note is required.
  - **Token auto-resolution:** a claimant's token gives `auto_resolved` (13 §3.1 step 8). A holder's token on their own `disputed` row (now allowed by `CocAccountPolicy::verify`) gives `resolved_denied` and the row is `verified` again (Open question 3).
  - **Transactions:** every change runs in one transaction with row locks in the P2-02 order, a `moderation_actions` row, an `audit_logs` entry (`coc_dispute.*`) and recounts.
- **UI (small):** the attach page and the token messages explain the new `tag_suspended` outcome (`Attach.vue`, `attachMessages.ts`).
- **Policy:** `CocAccountDisputePolicy` (open: verified email and account writes; respond / release: the holder; decide: `resolve-disputes` and not a party; view: the parties and admin+).
- **Events (after commit):** `CocAccountDisputeOpened`, `CocAccountDisputeDecided`, plus `CocAccountOwnershipTransferred` (method `admin`) on transfer.
- **Schedule:** `coc:process-disputes` (hourly) escalates disputes with no holder response after `holder_response_days` (7). Non-response is never a transfer by itself. It withdraws disputes after `claimant_inactive_days` (30) of claimant inactivity.
- **Security log:** `coc.dispute_opened`, `coc.dispute_denied_bar`. A third denial is logged as `coc.dispute_false_claim` for review; the report reason joins with P3-06.
- **Config:** `coc.disputes{max_open_per_user, bar_after_denials, bar_days, holder_response_days, claimant_inactive_days, evidence_max}`.

## Out of scope
- **P2-16:** claimant and holder screens (open from the conflict card with evidence upload, the dispute page, holder response).
- **P2-17:** the admin queue and review page, evidence access audit, and the dashboard's pending disputes panel.
- **P2-18:** notifications (opened, day 3 and 6 reminders, decision).
- **Later rows:** re-verification requests (13 §7), and holding a deletion while a dispute is open (23 §1, with P2-14).

## Acceptance criteria
- **Functional:** the FR ids above; every transition in 13 §5 through the service; one verified owner per tag at all times; decision bias documented in the deny path.
- **Authorization:**
  - Only a non-holder can open.
  - Only the holder can respond or release.
  - Only `resolve-disputes` admins who are not a party can decide.
  - Suspended, banned and pending-deletion accounts are refused.
  - Another user's dispute gives a 404 (IDOR).
- **Edge cases:** the 13 §9 rows above; concurrent token verification and decision are serialised.

## Tests
- **Feature:** each transition; guardrails (limits, bar, party admin); the schedule at its edges (time frozen); token auto-resolution both ways; recounts, audit and moderation rows; events after commit.
- **Security:** IDOR; mass assignment; evidence ULIDs must belong to the uploader and the `evidence` collection; limits from config.
- **Unit:** enum value lists match specs/07.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended). Q1 synced → tasks/BOARD.md (P2-16, P2-17, P2-18).
1. **Split:** this file is the backend; P2-16 (dispute screens for both parties), P2-17 (admin queue, review page, pending disputes panel), P2-18 (dispute notifications). P2-16 also depends on P2-18.
2. **Statuses:** `open`, `awaiting_admin` (new), `awaiting_claimant`, `awaiting_holder`, `resolved_transfer`, `resolved_denied`, `resolved_suspended` (new), `withdrawn`, `auto_resolved`. synced → specs/07, specs/13 §5.
3. **The holder's token:** `verify` is allowed on one's own `disputed` row; success closes the dispute as `resolved_denied` and the row is `verified` again. synced → specs/13 §3.1 and §5, specs/04.
4. **Release:** closes the dispute as `resolved_transfer`; the holder's row is `released`, and the claimant gets a `verified` row (`verification_method=admin`, `decided_by` null, a voluntary release in the audit entry). synced → specs/13 §5, specs/08 §3.1.

### Decisions and divergences (implement, 2026-10-02)
1. **Statuses** as Open question 2, plus columns `awaiting_since` (when the current wait began) and `escalated_at`. `evidence` is an append-only list `{party, note, media, at}`: the claimant's opening statement is `reason`, and every later statement or upload is an entry. synced → specs/07, specs/13 §5.
2. **A second partial unique:** one active dispute per held account, since a disputed account takes no new disputes (13 §2). synced → specs/07.
3. **The 30-day withdrawal** counts days the dispute waits on the claimant (`awaiting_claimant` since `awaiting_since`), so an admin's delay never withdraws a claim. No `claimant_active_at` column. synced → specs/13 §5.
4. **`moderation_actions` rows** for admin decisions, through a new public `Moderation\Services\ModerationActionLog` (PlayerAccounts now depends on Moderation).
   - The mapping: transfer → `transfer_ownership`, deny → `dismiss`, suspend → `suspend`, with `target_type = coc_account_dispute` and reason `false_ownership`.
   - Asking for more, a release, a token and a withdrawal are audit-only, since no staff action is involved.
   synced → specs/05 §2, specs/07, specs/13 §5.
5. **An admin transfer** leaves the holder their row, unverified (as after a token takeover). The claimant's row is their own row, else the latest released row, else a new row with the holder's game data. It is verified by `admin`, and the claim row uses method `admin` (a release uses `dispute`). synced → specs/13 §5, specs/08 §3.1.
6. **No token-takeover notice for an admin transfer** (`SendOwnershipNotice` skips method `admin`); the decision notice is P2-18's. A release dispatches no `CocAccountOwnershipTransferred`. synced → specs/05 §2.
7. **A token closes every running dispute on the tag:** the holder's own token gives `resolved_denied`, anyone else's gives `auto_resolved` (`context.by` records which).
   - A holder re-verifying keeps their featured flag: the featured check now excludes the row itself.
   - The verification audit entry records the real status before.
   synced → specs/13 §3.1, §5.
8. **Suspended tags:** preview, attach, verify and `verifyTag` refuse them before any API call, with new `tag_suspended` outcomes, and the attach page explains it. This was the P2-02 follow-up. Releasing a suspended tag is an admin action for P2-17. synced → specs/13 §2.
9. **A banned holder** cannot be given a deny (`holder_cannot_keep`, 13 §9). A third denial logs `coc.dispute_false_claim` for review; the report reason joins with P3-06. synced → specs/13 §5.
10. **Refusals return** `DisputeResultData` with a `DisputeRefusal`, since Http cannot catch domain exceptions. Text and evidence limits throw `ValidationException`. Config adds `text_max` (1000). No spec change.
11. **Not built:** holding an account deletion while a dispute involves the user (23 §1) needs an Auth-side contract. Added to P2-14's board row, next to the deletion-window release.
12. **Names differ from the Scope's sketch:**
    - The events are `CocAccountDisputeOpened`, `CocAccountDisputeInfoRequested` and `CocAccountDisputeClosed`. `Closed` covers every end, where the sketch had a narrower `Decided`.
    - "Request info" became `ask_claimant` / `ask_holder`.
    - `respond` also takes the claimant's answer to an admin.
    - `withdraw` and `DisputeParty` are additions.

    synced → specs/05 §2, specs/13 §5.

### Review fixes (verify, 2026-10-02)
- Spec (medium) + security (low): `withdraw` and the sweep's withdrawal locked the dispute before the tag's rows, the reverse of verification, so they could deadlock. They now lock rows, then the dispute, then users, like `decide`. Not testable on SQLite (no row locks); covered by the shared `lockForChange`.
- Spec (medium) + security (low): a token in flight could verify a tag that a decision had just suspended. `promote` now refuses inside the lock (`TagSuspended`, rolled back) and records a failed claim. Tested with a suspension made while the token is checked (new `FakeCocApiClient::onVerify`).
- Spec (medium) + security (low): a transfer or release could hand the tag to a banned, suspended or leaving claimant. Both now refuse with `claimant_unavailable`; a deny is still possible. Tested for all three states.
- Security (medium): withdrawing before a denial dodged the 2-denials bar. Now:
  - the claimant may withdraw only while `open`;
  - a sweep withdrawal (`closed_by = sweep`, a new column) counts toward the bar;
  - reopening the same tag waits `reopen_cooldown_days` (30).

  Tested. synced → specs/07, specs/13 §5.
- Spec (low): transfer and suspend now wait for `awaiting_admin`, so the holder gets their window. Tested. synced → specs/13 §5.
- Spec (low): the evidence cap counts a party's uploads over the whole dispute, not per round. Tested.
- Spec (low): a non-admin deciding gets the same 404 for a real or an unknown dispute. Tested.
- Security (low): a claimant's claim row and the false-claim log line no longer carry the admin's or holder's IP hash and user agent (`fromRequest: false`); the false-claim line names the admin.
- Security (low): `MediaAttachmentService::attach` refuses media already attached to another parent, so a decided dispute's evidence cannot be moved. Tested. synced → specs/10 §3.
- Security (low), handed on: release is an ownership transfer, so it needs password re-confirmation (specs/11) and named limiters on its route. Added to P2-16's board row.
- Spec (low): tests added for a pending-deletion user (open, respond, release, withdraw), the bar log line, events held until commit for `Closed` and `InfoRequested`, and a banned holder losing to a transfer.

- antislop audit-031: no findings.
- scripts/check.sh: all green (sqlite + postgres).
