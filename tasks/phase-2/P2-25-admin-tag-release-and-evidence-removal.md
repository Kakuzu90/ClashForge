---
id: P2-25
title: Let admins release a suspended tag and remove an identity-document image from dispute evidence
phase: 2
status: done
depends_on: [P2-17]
---

# Admin tag release and evidence removal

## Spec refs
- Core: specs/13 §2 ("`suspended` ... until staff release it; the release action comes with the admin pages"), §9 ("Dispute evidence contains a real-world ID document": delete the media, point the claimant to a token or an in-game screenshot), §6 (release mechanics)
- Plus:
  - specs/04 §2 (rule 1: act only on someone you strictly outrank; rule 2: a reason on every staff action; "Remove content permanently": admin+), §3 (`resolve-disputes`, stake = party, P2-17)
  - specs/12 §9 (`moderation_actions`, audit trail); specs/10 §3 ("release", deleted after commit, no recovery), §8 (evidence 3 per party)
  - specs/16 §2 ("Tag released" notice); specs/18 §6 Admin (dispute review page); specs/11 (staff writes, private evidence)
- FR: FR-ADMIN-2 (disputes, CoC account management), FR-COC-7, FR-COC-8 (ownership changes audited)
- Edge cases: specs/23 §2 (suspended tag refuses attach / verify / dispute; deletion and ban releases skip it)

## Scope
- **Domain** (PlayerAccounts):
  - `AccountOwnershipService::releaseSuspended(admin, accountUlid, note)`: locks the tag's rows and the holder; the row must be `suspended`; rule 1 against the holder (Q1); `release()` with new `ReleaseReason::Admin` (clears owner, images, featured, recount, `audit_logs` `coc_account.released` with the note); `moderation_actions` `release_tag` (new `ModerationActionType`, reason `false_ownership`).
  - `DisputeService::removeEvidence(admin, disputeUlid, mediaUlid)`: `review` ability and rule 1 against the uploader; releases the media (`MediaAttachmentService::detachFrom`, deleted after commit); replaces the entry's ULID with a removed marker so the record shows a staff removal; `audit_logs` `coc_dispute.evidence_removed`; `moderation_actions` `remove`, reason `other` with the fixed note "identity document" (Q2); the removed image stops counting toward the party's 3 (Q3); in-app notice to the uploader (Q4).
- **Policy**: `CocAccountPolicy::releaseSuspended` (`resolve-disputes`, outranks the holder, no stake in the tag); evidence removal uses `CocAccountDisputePolicy::review` + outranks the uploader.
- **HTTP**: `POST /admin/disputes/{ulid}/release-tag` (note required), `DELETE /admin/disputes/{ulid}/evidence/{media}` (`global-write`), Form Requests, flash results.
- **UI** (`Admin/Disputes/Show`):
  - Closed as `resolved_suspended` with the row still `suspended`: a "Release the tag" panel (required note, `UiModal` confirm naming the tag and that anyone can claim it next).
  - Each evidence image: "Remove: shows an identity document", confirm modal; removed entries read "Removed by staff: showed an identity document". The parties' dispute page (`Disputes/Show`) shows the same line.
- **Notifications**: new `dispute_evidence_removed` (in-app, to the uploader), copy per specs/16 tone.

## Out of scope
- A general admin CoC account list or an admin "suspend tag" action outside disputes; quarantine review (P3-06); report evidence (P3-06).

## Acceptance criteria
- Functional: 13 §2 release; 13 §9 removal; both audited (FR-COC-8) with a reason.
- Authorization: `resolve-disputes` only; staff with a stake get a 404; rule 1 refuses an admin acting on an admin holder / uploader ("Needs a super admin"); moderators stop at the `/admin` gate.
- Edge cases: a row no longer `suspended` (already released, or another path) refuses with "This tag is no longer suspended."; removing evidence twice is a 404; removal works on open and closed disputes; a released tag is claimable at once (attach / verify no longer refuse `tag_suspended`).
- States: buttons hidden without the ability; confirm modals; inline refusal messages; 375 px and desktop.

## Tests
- Feature: release (status, owner cleared, images released, audit, moderation action, "Tag released" notice), attach works afterwards; removal (media deleting, marker, audit, notice, slot returned, party page line).
- Security: ability / stake / rank matrix on both routes, IDOR (media of another dispute → 404), missing note → validation, mass assignment, evidence URL not leaked after removal.
- Vitest: the release panel and the removal confirm on `Admin/Disputes/Show`.

## Notes

### Open questions
Resolved by the owner, 2026-10-07 (all as recommended). synced → specs/13 §2, §9, specs/16 §2.
1. **Where the release lives and whom it checks.** On the dispute review page of a `resolved_suspended` dispute (today the only way a tag gets suspended), for `resolve-disputes` admins with no stake, who strictly outrank the suspended holder. The former holder gets the existing in-app "Tag released" notice; the claimant gets nothing extra. Recommended.
2. **Removal reason.** Only "shows an identity document" (13 §9), no free choice; the moderation reason code is `other` with that fixed note. Recommended.
3. **Evidence slot.** A removed image no longer counts toward the party's 3, so they can send an in-game screenshot instead while the dispute is running. Recommended.
4. **Telling the uploader.** An in-app notice (no email): "An image you sent with your dispute over {tag} was removed because it showed an identity document. We do not keep identity documents: verify with the in-game API token or send an in-game screenshot instead." Recommended.

### Decisions and divergences (implement, 2026-10-07)
1. **Where the code sits.** Both actions are keyed by the dispute, so they live in `DisputeService` (`releaseTag`, `removeEvidence`) and `CocAccountDisputePolicy` (`releaseTag`, `removeEvidence`), not in `AccountOwnershipService` / `CocAccountPolicy` as Scope said. The release still goes through `AccountOwnershipService::release()` with the new `ReleaseReason::Admin`. synced → specs/05 §2, specs/04 §3.
2. **Release.** Locks the tag's rows, then the holder (verification's order); refuses with "This tag is no longer suspended." when the row moved on. Audited as `coc_account.released` (reason `admin`) and logged as `moderation_actions` `release_tag` with the note. The existing "Tag released" notice reaches the former holder. The release panel shows only on a `resolved_suspended` dispute whose row is still `suspended`; an outranked admin sees "The holder is an admin, so only a super admin can release this tag." synced → specs/13 §2, specs/12 §9.
3. **Evidence removal.** The ULID leaves the entry's `media` list (so the slot frees itself) and the entry gains `removed` (a count). `MediaAttachmentService::detachFrom` releases the media (deleted after commit). Audited as `coc_dispute.evidence_removed`, logged as `moderation_actions` `remove` (reason `other`, note "Showed an identity document"), and `CocAccountDisputeEvidenceRemoved` sends the in-app `coc_dispute_evidence_removed` notice, linking to the dispute page. The admin page says "Removed by staff: 1 image showed an identity document."; the party page says "An image was removed by staff because it showed an identity document." synced → specs/13 §9, specs/16 §2, specs/18 §6.
4. **Routes.** `POST /admin/disputes/{ulid}/release-tag`, `DELETE /admin/disputes/{ulid}/evidence/{media}`, both in the `global-write` admin group. Spec 19 lists no per-action admin routes, so recorded in specs/18 §6 with the page.
5. **Test placement.** The party page's "removed" line is covered by the feature test's props, not a Vitest render.
6. **Button label.** The removal button reads "Delete: ID document", not "Remove: shows an identity document" as Scope said: shorter under a 96 px thumbnail, and "delete" says it is gone for good. synced → specs/13 §9, specs/18 §6.

### Review fixes (verify, 2026-10-07)
- Spec (medium): a refused release ("This tag is no longer suspended.", such as when another admin released it first) had nowhere to show, since the panel disappears with the suspension. The panel now stays up in that case with the refusal as a warning alert. Tested in Vitest.
- Spec (low): an older `resolved_suspended` dispute could release a tag a later dispute had suspended again, logging the wrong dispute. `Support\SuspendedTag::releasableFrom()` also requires that no later dispute over the tag exists. The review page and the locked service check use it. The policy keeps only status and rank, so a late or stale click gets the refusal instead of a 403. Tested.
- Spec (low): the review page ran the evidence policy once per entry, and each run reads the admin's stake (3 queries). `review` is already settled there, so the page derives `removable` per party and the release flags from `DisputeRank::outranks()` once. The policies use the same helper.
- Spec (low): the label divergence is recorded above (Decision 6).
- Security: no findings. antislop audit-044: no findings.
