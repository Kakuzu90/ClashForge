---
id: P2-17
title: Give admins a dispute queue, a review page with a decision form, and a pending-disputes panel
phase: 2
status: done
depends_on: [P2-03]
---

# Admin dispute queue and review

## Spec refs
- Core: specs/13 §5 step 4 (side-by-side evidence, both users' history, prior disputes by either party, claim history, snapshot history; transfer / deny / ask / suspend), "As built" (transfer and suspend from `awaiting_admin` only, no deny for a banned holder), guardrails (no admin decides their own dispute; evidence is staff-only and every access is audited), evidence weights, decision bias; specs/12 §4 (one screen with everything), §9 (read access to evidence in `audit_logs`)
- Plus: specs/04 §2 ("Resolve ownership dispute": admin+, `resolve-disputes`), §2 structural rules 1–2, §3 (Gates, `can` flags); specs/07 `coc_account_disputes`, `coc_account_claims`, `coc_account_snapshots`, `user_sanctions`; specs/10 §8 (private media: 5-minute signed GET, `no-store`); specs/18 §4 (Admin* components), §6 Admin (nav, DataTable, deferred rows, empty / loading / error, dashboard panels); specs/11 (props exposure); specs/05 §2 (`DisputeService`, `AccountReadModel`)
- FR: FR-ADMIN-2 (disputes), FR-ADMIN-5 (pending disputes panel), FR-COC-8
- Edge cases: specs/13 §9 (holder banned mid-dispute: no deny; claimant verifies while open: closed by the token); specs/23 §2

## Scope
- **Read model** (`PlayerAccounts\Queries\DisputeAdminQuery`, used through `Services`):
  - `queue(filters)`: status (default: the four active statuses), waiting on (holder / claimant / admins), assigned to me, oldest first; tag, both usernames, status, waiting since. Rows are a deferred prop.
  - `review(admin, ulid)`: both statements and every evidence entry side by side, with images as signed private URLs (specs/10). Each party's account age, status, verified accounts, prior sanctions (Moderation `SanctionService`/read surface), and prior disputes on either side with their outcomes. The tag's claim history (`coc_account_claims`) and snapshot history (IGN, clan and TH changes with dates). The current row state.
- **Evidence access audit:** every review render that includes evidence writes `audit_logs` `coc_dispute.evidence_viewed` (new `AuditAction`) with the media ULIDs. It is written before the URLs are returned.
- **Decision form:** transfer / deny / ask claimant / ask holder / suspend, with a required internal note, through `DisputeService::decide`. The service's refusals (`holder_cannot_keep`, `claimant_unavailable`, not yet `awaiting_admin`, closed) show inline. Destructive choices confirm in a `UiModal` that names who gets the tag. `can.decide` and the allowed decisions come from the policy and the service.
- **Policy:** queue and review need `resolve-disputes`. Staff with a stake in the tag (the parties included) get a 404 and do not see the dispute in the queue (Open question 1, widened 2026-10-06). Deciding also needs to strictly outrank both parties (specs/04 §2 rule 1): with an admin party only a super admin can decide, and a super admin party has nobody in-app (Open question 2).
- **Dashboard panel** (FR-ADMIN-5, from P1-13): pending disputes, meaning the `awaiting_admin` count, the oldest wait, and how many are past the holder's window. A deferred group with its own skeleton, empty state and error, linking to the queue.
- **UI:** `/admin/disputes` (`Admin/Disputes/Index`) and `/admin/disputes/{ulid}` (`Admin/Disputes/Show`), built from `AdminTable`, `AdminFilterBar`, `AdminPanel`, `AdminActionPanel`, `AdminSanctionHistory` and `AdminAuditTrailList`. The "Disputes" nav item sits between Reports and Users (specs/18 §6). No SSR, as for every `/admin` page.
- Config: `coc.disputes.queue_per_page`.

## Out of scope
- Releasing a suspended tag, and removing an evidence image that shows an identity document (13 §9) → P2-25
- Party screens (P2-16); dispute notifications, including the decision notice (P2-18); a general CoC account / claims admin list (FR-ADMIN-2, later row)

## Acceptance criteria
- Functional: FR-ADMIN-2 disputes and FR-ADMIN-5 panel; every 13 §5 step 4 input on one screen; decisions go only through `DisputeService::decide`.
- Authorization: moderators and users stop at the `/admin` gate (403); an admin without `resolve-disputes` gets a 403 queue and a 404 dispute; staff with a stake in the tag get a 404, and an outranked admin sees why only a super admin can decide; evidence URLs only on the review page, never in the queue.
- Edge cases: the 13 §9 rows; a dispute closed by a token while it is open on screen refuses the decision with "This dispute is closed."
- States: queue empty ("No disputes waiting"), loading skeleton rows, inline error with request id; panel empty / loading / error.

## Tests
- Feature (`assertInertia`): queue filters and order, props with no evidence; review props per party; each decision and each refusal; panel counts; query counts ≤ 25.
- Security: moderator / user / party 404s; one evidence audit entry per view; no evidence URL or internal note in the queue or the panel; signed URLs are private (no CDN host).
- Vitest: decision form (allowed choices, required note, confirm modal), queue filters.

## Notes

### Open questions
Resolved by the owner, 2026-10-05 (all as recommended). Q3 synced → tasks/BOARD.md (P2-25); the rest synced at implement → Finish.
1. **Can an admin who is a party see the review page?** The policy lets parties `view` and refuses them `decide`. The staff review shows the other party's private evidence and history. Recommended: no; the dispute is a 404 in the admin pages and left out of the queue for them. Their own screens come with P2-16.
2. **Does rule 1 (act only on someone you strictly outrank, specs/04 §2) apply to disputes?** `DisputeService::decide` only refuses a party. Recommended: yes. An admin cannot decide a dispute where either party is an admin or above; it shows "Needs a super admin", and super admins decide those. A dispute with a super admin party has nobody to decide it in-app; it is left to the console, as roles are.
3. **Split:** releasing a suspended tag (13 §2, "the release action comes with the admin pages") and deleting an identity-document image from the evidence (13 §9) are separate admin writes with their own audit. Recommended: a new row, P2-25 "Admin tag release and evidence removal", depending on P2-17.

### Decisions and divergences (implement, 2026-10-05)
1. Read side, split by side effects:
   - `Queries\DisputeAdminQuery` (`queue`, `pending`) serves the queue and the panel.
   - `Services\DisputeReviewService::review()` writes the evidence audit, so it is a service.
   - `review()` returns `DisputeReviewResult`: the page data plus both party `User`s. The controller passes those users to Moderation's `SanctionHistoryQuery` for the sanctions list, as on the admin user detail.
   synced → specs/05 §2.
2. Policy: `CocAccountDisputePolicy::review` requires `resolve-disputes` and not being a party. `decide` = `review` + `outranksParties`. A party admin gets a 404 on the review page and is left out of the queue. `DisputeService::decide` still answers a party with 403, unchanged from P2-03. synced → specs/04 §3, specs/13 §5 guardrails.
3. Blocked reasons:
   - An admin party: "An admin is a party to this dispute, so only a super admin can decide it." (antislop audit-037, finding 2)
   - A super admin party: "A super admin is part of this dispute, so it cannot be decided here."
   - A closed dispute: "This dispute is closed."
   Each unavailable option also states its own reason (the holder's window, a claimant who cannot receive the account, a banned holder, no holder to ask). The service decides again on submit. synced → specs/13 §5.
4. Evidence images come from Media's new `MediaReadService::readyVariantUrlsByUlid` (`full` and `thumb`). The `evidence` collection has no variants and takes no uploads yet, so the page shows "Image not available" until P2-16 opens evidence uploads with `full` and `thumb` variants (follow-up for P2-16). synced → specs/10 §8.
5. The evidence access audit is `coc_dispute.evidence_viewed`: one entry per review render that includes any image, with `context.media`, written before the URLs are built. A page with no images writes none. synced → specs/12 §9, specs/13 §5 guardrails.
6. Snapshot history shows clan and Town Hall changes only. In-game names have no history by design (23 §2), so the "name changes" input of 13 §5 step 4 is not available. synced → specs/13 §5 step 4.
7. Queue views: `DisputeQueueView` (all running, waiting for an admin / the holder / the claimant, closed). Running disputes sort oldest wait first, closed ones newest first. Pages use cursors. "Assigned to me" filters on `assigned_admin_id`. synced → specs/18 §6 Admin.
8. Dashboard: `AdminDashboardPageData.disputes` (`resolve-disputes`) adds the deferred `pendingDisputes` panel: waiting for an admin with the oldest wait, `open` past the holder's window, and running. A retry reloads only the panels the viewer has. The shared `auth.can.resolveDisputes` drives the "Disputes" nav item after Dashboard (Reports is not built yet). synced → specs/18 §6.
9. Decision form (built from `UiRadioGroup`, `UiTextarea`, `UiModal`; `AdminActionPanel` is the sanctions panel and does not fit): radios for the available decisions and a required internal note. Transfer, suspend and deny confirm in a `UiModal` that states the outcome; the two questions are sent directly. Routes: `GET /admin/disputes`, `GET /admin/disputes/{ulid}`, `POST /admin/disputes/{ulid}/decision` (`global-write`). synced → specs/19 §4.
10. Config: `coc.disputes.queue_per_page` (25), `coc.disputes.review_history_limit` (20 claims and snapshots). No spec change.

### Review fixes (verify, 2026-10-06)
- antislop audit-037: both findings approved by the owner (2026-10-06) and fixed:
  - 1: each decision has its own success message ("Claim denied. The holder keeps the account.").
  - 2: the admin-party reason reads "An admin is a party to this dispute, so only a super admin can decide it."
- Spec (medium): the acceptance said 404 for moderators and users, while the controller's `Gate::authorize` made it 403 even for an admin without the ability. The review route now leaves that to `DisputeReviewService`, so a dispute is a 404 without `resolve-disputes` or for a party (specs/04 §3). The queue stays a 403. Moderators and users stop at the `/admin` gate. The acceptance text is updated; tested.
- Spec (medium): every decision (transfer, deny, suspend, ask either party) and the `holder_cannot_keep` and `claimant_unavailable` refusals are now tested through the controller. The submit button waits for a note (Vitest).
- Spec (low): the queue row now carries the review page's `blockedReason`, from a shared `Support\DisputeRank`, so a super admin is no longer told to find a super admin. The policy's `outranksParties` delegates to it.
- Spec (low): the review page shows the dispute's own audit trail (`AdminAuditTrailList`), evidence views included. `AuditLogFilterData` gains `subject` (default: account).
- Spec (low): the panel leaves out disputes the viewer is part of, as the queue does. "Past the holder's time" counts `open` and `awaiting_holder`, which is what the sweep escalates.
- Spec (low): an earlier dispute the viewer is part of is listed without a link (`DisputePriorData.reviewable`).
- Spec (nit): the closed-dispute text matches the code; the board row's File column is trimmed.
- Security (low): queue cursors are checked (`DisputeAdminQuery::acceptsCursor`: the paginator's shape, an integer id, a timestamp in the view's sort column). A tampered or other-view cursor is a validation error instead of a 500; tested with four shapes.
- Security (low): `MediaReadService::readyVariantUrlsByUlid` now requires the parent and the collection, so only evidence attached to this dispute is signed.
- Security (low), owner decision 2026-10-06 (as recommended): staff with a stake in the tag count as a party. A stake is a row on the tag, any claim attempt on it, or a side in any dispute over it. `Support\DisputeStake` feeds the policy's `review`/`decide`, the queue and panel filter, and the review page's prior-dispute links. Tested for each kind of stake. synced → specs/04 §3, specs/13 §5.
- Side effect: a debugging `tinker` run created three users, two accounts and two disputes in the local dev database (reported to the owner).
