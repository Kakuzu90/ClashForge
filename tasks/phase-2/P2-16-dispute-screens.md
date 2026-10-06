---
id: P2-16
title: Dispute screens for both parties: open with evidence, follow the dispute, answer, release, withdraw
phase: 2
status: done
depends_on: [P2-03, P2-18]
---

# Dispute screens for both parties

## Spec refs
- Core: specs/13 §4 B (the conflict card's dispute path), §5 (workflow, statuses, holder options 3a–3c, withdrawals, evidence cap, guardrails), §8 (the claimant's notices gain the dispute link), §9; specs/18 §6 attach flow (conflict card with both paths)
- Plus: specs/04 §2 + `CocAccountDisputePolicy` (open as attach; respond / release / withdraw: the party), §4 named limiters; specs/11 CSRF section (ownership transfer needs password re-confirmation), IDOR section, "Named rate limiters on every write"; specs/10 §3 (intent / complete, attach transaction), private `evidence` media (≤5 MB, signed URLs); specs/16 §2 (Ownership notices); specs/05 §2 (`DisputeService`)
- FR: FR-COC-6 (the dispute path), FR-COC-7, FR-COC-8
- Edge cases: specs/13 §9 (claimant verifies while their dispute is open; holder banned mid-dispute; evidence with an ID document: the upload hint says not to); specs/23 §1 (no dispute against a holder whose deletion is pending)

## Scope
- **Uploads** (specs/10 §3): `evidence` accepts uploads; `MediaPolicy` lets it follow account writes like the avatar, since opening needs account writes, not content writes (Open question 3).
- **Routes** (`routes/web/disputes.php`, `auth` + `account.active`, see Decision 10, ULIDs, a foreign or unknown ULID is a 404):
  - `GET /disputes/create?tag=` (the conflict re-checked server-side), `POST /disputes` → `DisputeService::open`.
  - `GET /disputes/{ulid}` (parties only), `POST …/respond`, `POST …/withdraw`, `POST …/release`.
- **Release** takes the current password inline and shares the `password-confirm` limiter, as the detach form does (specs/11, specs/13 §6, P2-14). The dialog confirms the outcome.
- **Limiters** (specs/11, specs/04 §4): `coc-dispute-open`, `coc-dispute-write` (respond, withdraw); numbers in `coc.disputes.*` (Open question 4).
- **Requests + DTOs:** `OpenDisputeRequest`, `RespondDisputeRequest` (statement ≤ `text_max`, ≤ `evidence_max` media ULIDs); `DisputeCreatePageData`, and `DisputeShowPageData` with `PartyDisputeData` per viewer: tag, status, whose turn, deadline, the allowed actions, the viewer's own submissions (Open question 2). `DisputeRefusal` labels show as form errors.
- **UI** (specs/18 §4 `Ui*`, `useUpload`):
  - `Attach.vue` conflict card: "Open a dispute" next to the token path (13 §4 B); "View your dispute" when one is running for that tag.
  - `Pages/Disputes/Create.vue`: reason, up to 3 images with per-file progress and retry, a hint against ID documents (13 §9), the guardrails in plain words.
  - `Pages/Disputes/Show.vue`: status and next step per party. The holder gets "Verify with a token" (`accounts.verify` on their `disputed` row, 3a), "Answer" (3b) and "Give the account up" (3c). The claimant gets "Withdraw" while `open`. The asked party answers in `awaiting_*`.
  - `Accounts/Show.vue`: the "Ownership is under review" alert links to the dispute.
- **Notification links:** the claimant's `coc_dispute_*` notices, and the holder's opened / reminder / info-requested notices, link to `/disputes/{ulid}` (13 §8; Open question 1).

## Out of scope
- Admin review, decision, tag release and evidence removal (P2-17 done, P2-25); re-verification requests (13 §7); the `false_ownership` report reason (P3-06); a dispute list page (Open question 1).

## Acceptance criteria
- Functional: a blocked user opens a dispute from the conflict card with evidence; each party sees their turn and acts; every 13 §5 party transition is reachable from the UI (phase 2 exit, specs/25).
- Authorization: only the parties see a dispute (others: 404); actions per `CocAccountDisputePolicy`; restricted users may open and answer, suspended / banned / pending-deletion may not; evidence must be the uploader's own `evidence` media.
- Edge cases: the 13 §9 rows above; a stale page acting on a closed dispute shows `closed`; the API being down never blocks a dispute.
- States: create (uploading, failed upload, refused with reason), show (each status, closed with outcome), empty evidence.

## Tests
- Feature (`assertInertia`): open with and without evidence; each refusal mapped to a form error; respond, withdraw, release (wrong password, limiter shared); create page refuses a tag with no conflict.
- Security: IDOR 404s; the other party's identity, statements and evidence never in props (Open question 2); foreign or non-evidence media refused; each named limiter enforced and keyed per user; release needs the password every time.
- Vitest: the evidence field (cap, progress, retry, remove); the per-status next-step mapping.

## Notes

### Open questions
Resolved by the owner, 2026-10-06 (all as recommended); specs synced at implement → Finish.
1. **Finding the dispute.** No list page. Recommended entry points: the notices (claimant: all; holder: opened, reminders, info requested now link to the dispute page instead of the account page, P2-18 decision 3), the holder's "under review" alert, and the conflict card for the same tag. Change 13 §8 accordingly.
2. **What each party sees.** 13 §5 guardrails make evidence staff-only, and P2-18 keeps the other party out of notices. Recommended: each party sees the status, whose turn and the deadline, and their own statements and images (signed URLs, uploader only, not audited). Never the other party's name, statement or evidence.
3. **Evidence uploads for restricted users.** `MediaPolicy` treats every non-avatar collection as content, but disputes need account writes. Recommended: `evidence` follows account writes, like the avatar. Sync 10 §3 and 04 §3.
4. **Limiter numbers and early withdrawals** (P2-03 and P2-18 security reviews). Recommended:
   - `coc-dispute-open`: 3 per day per user.
   - `coc-dispute-write`: 10 per hour per user, for respond and withdraw.
   - A withdrawal within 24 h of opening counts toward the 2-denials bar, as a sweep withdrawal does. The holder is still told.
   Sync 04 §4 and 13 §5.

### Decisions and divergences (implement, 2026-10-06)
1. **Evidence uploads:** `evidence` accepts uploads, with `full` 1600w and `thumb` 320w renditions; specs/10 §5 had no row for it. `MediaPolicy` lets it follow account writes, like the avatar (Open question 3). synced → specs/10 §1, §5, quotas, ClamAV note; specs/04 §3; specs/11.
2. **Limits** (Open question 4):
   - `coc-dispute-open` is counted from the disputes table: 3 accepted per rolling 24 h, under the claimant's lock, with a new refusal `too_many_today`. A refused attempt or a typo never uses one up. It is not a cache limiter.
   - `coc-dispute-write` (route limiter, 10 / h per user) covers open, respond and withdraw.
   - Release uses `password-confirm`.
   - Early withdrawal (`coc.disputes.early_withdraw_hours`, 24) is counted from `created_at` / `decided_at`, so no column and no change to the `closed_by` check.

   synced → specs/04 §4, specs/13 §5.
3. **Release** takes the current password on every submission. It is checked on the locked account inside `DisputeService::release`, which now takes the password and IP. synced → specs/13 §5, specs/11, specs/04 §4, specs/05 §2.
4. **Party view:** `PartyDisputeData` with `PartyDisputeOutcome`. The outcome covers every ending for both sides, including the ones nobody is notified about (`released_by_you`, `kept_token`, `withdrawn_by_you`, `verified_by_token`). A party's own thumbnails are signed for the uploader only and are not audited (Open question 2). synced → specs/13 §5, specs/11, specs/05 §2.
5. **Links** (Open question 1):
   - Opened, reminder and info-requested notices link to `/disputes/{ulid}`; the account page is the fallback for older rows.
   - Closed outcomes that had no link (`suspended`, `withdrawn_inactive`, `verified_by_other`, unknown) now link to the dispute page.
   - The holder's "under review" alert links to the dispute (`AccountDetailData::disputeUlid`).
   - The conflict card links a running dispute (`AttachPageData::disputeUlid`). `/disputes/create` redirects to it.

   synced → specs/13 §4 B, §8; specs/16 §2; specs/18 §6.
6. **Bug fixed:** `/accounts/{ulid}/verify` answered 404 for a `disputed` row, although P2-03 allows a holder's token on it (3a) and the account page linked there. The page now opens for `disputed` rows, with its own intro. synced → specs/13 §5, specs/18 §6.
7. **SSR** is off for `/disputes/*` (`inertia.ssr.except`), like the other signed-in areas. synced → specs/06 §2, docs/ai/rules/backend.md.
8. Not added to `/dev/components`: `DisputeEvidenceField` is not a `Ui*` variant, and evidence uploads show on `/dev/media`.
9. R-31: dispute pages use the plain "work" register (DESIGN.md): cards, alerts and forms, with no game styling. A dispute is a support process, not a reward surface. synced → specs/18 §6.
10. **No `verified` middleware** on the dispute routes, as on `routes/web/accounts.php`. Opening checks the verified email in `CocAccountDisputePolicy::open`. Answering, giving up and withdrawing do not, so a holder whose email lapsed is never locked out of defending their account. synced → specs/04 §3.

### Review fixes (verify, 2026-10-06)
- antislop audit-039: no findings.
- Spec (medium) + security (low): `GET /disputes/create` showed any tag's state with no limit, including tags whose holder is leaving (`not_held`, hidden elsewhere) and hidden accounts under review (`already_disputed`). Fixed three ways:
  - `eligibility()` now spends a `coc-attach` lookup per new tag (5 / h, a tag the attach flow just showed is free), with the new refusal `too_many_tags`.
  - `not_held` and `already_disputed` read the same on the form and on submit: "A dispute cannot be opened for this account now. If it is yours, verify it with your in-game API token."
  - The props carry the refusal in words only, never its code.
  Tested. synced → specs/13 §5, specs/04 §4.
- Spec (low): a `coc-dispute-write` breach on the open and answer forms is a field error with the wait time (`reason` / `statement`). Withdraw is a button, so it gets a flash error. Tested.
- Spec (low): a foreign or cleaned-up evidence ULID gave a bare 404 and lost the form. It is now the `evidence` field error, with the same words for both cases. Tested.
- Spec (low): `coc-dispute-write` stays on `POST /disputes` as well (Decision 2). The daily cap is the one that spares typos.
- Spec (low): tests added for a retry on a failed upload (Vitest), a restricted holder answering, the dispute card staying open while the API is down (Vitest), and a stale page acting on a closed dispute (respond, release, withdraw).
- Spec (trivial): the docblocks of `disputeUrl` / `accountUrl` were swapped back, and over-long comment lines were wrapped.

