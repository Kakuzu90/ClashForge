---
id: P2-23
title: Let owners add up to five images to a CoC account and show them on the account page
phase: 2
status: done
depends_on: [P2-04, P0-05]
---

# Account images

## Spec refs
- Core: specs/10 §3 "Attachment", §5 (`account_image` variants), §8 (5 per account, ≤5 MB, `coc_accounts.images_count`), §9 (release, cleanup); specs/18 §6 "CoC account detail" (custom images gallery; *Empty (no images)*)
- Plus:
  - specs/07 `coc_accounts.images_count` (CHECK ≤ 5), `media` (`account_image`); specs/08 (quota on the parent's counter column, in the attach transaction)
  - specs/04 §2, §3 (content write: verified email, `allowsContentWrites`, so restricted accounts cannot upload, already in `MediaPolicy`); specs/13 §6 (detach), §5 (transfer)
  - specs/11 uploads (re-encode, EXIF stripped, IDOR on attach and delete); specs/05 §2 (Media `MediaAttachmentService`, PlayerAccounts owns the write)
- FR: FR-COC-11, FR-MEDIA-3
- Edge cases: specs/23 §4 (media still processing; failed processing notice already exists, P1-15); specs/23 §2 (owner banned: account hidden, so the images are too)

## Scope
- **Domain** (PlayerAccounts): `AccountImageService`
  - `add(User, ulid, mediaUlid)`: locks the account, authorizes `manageImages`, checks `images_count < coc.images.max` (config, 5), `MediaAttachmentService::attach(..., MediaCollection::AccountImage, $account, position)`, increments `images_count`, in one transaction.
  - `remove(User, ulid, mediaUlid)`: same lock and policy, `MediaAttachmentService::release()`, decrements the count.
  - `releaseAll(CocAccount)`: called where a row leaves its owner (Q2).
- **Policy**: `CocAccountPolicy::manageImages` (Q1). Views follow `CocAccountPolicy::view`.
- **Read**: `AccountDetailData` gains `images` (list of `AccountImageData`: media ulid, `card` / `full` URLs with width and height, `processing`) and `canManageImages`, `imagesMax`. Others get ready images only.
- **HTTP**: `POST /accounts/{ulid}/images` (`media` ulid) and `DELETE /accounts/{ulid}/images/{media}` (`auth`, `account.active:content`, verified email, named throttle `account-images`), redirect back with a flash.
- **UI** (`Pages/Accounts/Show.vue`, below the village tabs):
  - `GameAccountGallery`: a grid of `card` variants with intrinsic sizes (2 columns on a phone, 4 from `md`), each a button opening a `UiModal` viewer with the `full` image, previous / next buttons and arrow keys (Q3). Alt text "{IGN}, image {n} of {total}".
  - Owner: a `useUpload` dropzone (`account_image`) that attaches each finished upload; a remove button per image with a confirm; a "{n} of 5" counter; the dropzone hides at 5. Processing images show a skeleton tile.
  - Others: the gallery only when there are ready images; nothing when there are none (specs/18 §6).
  - `/dev/components`: the gallery (owner, viewer, processing, empty) and the viewer modal.

## Out of scope
- Reordering and captions; reporting an image (P3-06) and staff removal (P3-06 / P2-25 pattern); images on PlayerCards or profiles.

## Acceptance criteria
- Functional: FR-COC-11 (5 per account, ≤5 MB, jpeg / png / webp, re-encoded per specs/10 §4–5).
- Authorization: only the owner adds or removes, per Q1; another user's account or media ulid is a 404; restricted, suspended, banned and unverified-email users cannot add.
- Edge cases: a sixth image is refused (also with two requests racing: the row lock and the CHECK); media from another collection, another user, or already attached is refused; an image still processing shows to the owner only.
- States: empty (owner dropzone / nothing), uploading with progress, processing, failed upload message, full (5 of 5), 375 px and desktop.

## Tests
- Feature (`assertInertia`): add, remove, the counter, ready-only for others, the quota and race, `releaseAll` on detach and transfer (Q2).
- Security: IDOR on both routes (another user's account, another user's media), media from another collection, mass assignment, status matrix (restricted / suspended / unverified email), props carry no storage keys or user ids.
- Vitest: `GameAccountGallery` viewer navigation (buttons, arrow keys, wrap), owner vs viewer rendering, the dropzone hidden at the cap.

## Notes

### Open questions
Resolved by the owner, 2026-10-06 (all as recommended). Q2 synced → specs/13 §6; Q4 → specs/10 §8.
1. **Who may manage images.** The owner, on a `verified` or `disputed` row (as `feature`), with content-write standing (verified email, not restricted). Not on `unverified` rows (their page is the owner's alone and the data is not trustworthy, specs/17 §2) nor `suspended` ones. Recommended.
2. **When the row leaves its owner.** Detach, supersede by another user's token, a dispute transfer or release, and the deletion / ban release (P2-24) all release every image (`releaseAll`, deleted after commit, specs/10 §3) and zero `images_count`, since they are the previous owner's uploads. Recommended.
3. **Viewer.** A modal with the `full` image, previous / next and arrow keys, no swipe gestures or zoom. Recommended.
4. **Config.** `coc.images.max` (5) mirrors the CHECK; the per-file limit stays `media.collections.account_image.max_bytes`. Recommended.

### Decisions and divergences (implement, 2026-10-06)
1. **Media API.** `MediaReadService::attachedTo(parent, collection)` (every attached item but `deleting`, in position order, with renditions once ready) and `MediaAttachmentService::detachFrom(parent, collection, ?ulid)` (releases each item; quarantined media is only unlinked, so it stays for review and stops counting). synced → specs/05 §2, specs/10 §3.
2. **Release hook.** `PlayerAccounts\Support\AccountImages::releaseAll()` runs in `AccountOwnershipService::release()` (detach, deletion, ban), the token supersede, the dispute holder release (before the row is reused for the claimant) and the admin transfer (Q2). A sanctioned (`suspended`) row keeps its images. synced → specs/13 §6.
3. **Owner's view.** Processing images show a skeleton tile and the page reloads the `account` prop every 4 s, at most 15 times. Failed and quarantined uploads show "This image could not be used." with a remove button, since they hold a place. Others see ready images only. synced → specs/18 §6.
4. **Limiter.** `coc-account-images` (`coc.images.writes_per_hour`, 60) on add and remove; upload intents keep their own 30/hour. synced → specs/09 §11, specs/10 §8, specs/19 §4 (routes), specs/04 §3 (`manageImages`).
5. **Components.** `DisputeEvidenceItem` became the shared `uploads/UploadQueueItem` (with a `refused` message); `UiModal` gains `wide` for the viewer. synced → specs/18 §4, §6.
6. **Layout.** Images sit below the village tabs, above the sync footer: 2 columns on a phone, 4 from `md`, 4:3 cover tiles. R-31: a cropped 4:3 tile keeps rows even whatever the upload's shape; the viewer shows the whole image.
7. **Suspended uploaders** are redirected to their notice by an earlier middleware rather than getting the write gate's 403; restricted and unverified-email accounts get the 403.
8. **Name.** The gallery is `Components/accounts/AccountImageGallery.vue`, not `GameAccountGallery`: it shows user uploads, not game content, so it stays out of `Components/game/`.

### Review fixes (verify, 2026-10-06)
- Security (low) and spec (high): an upload posted twice was counted twice, since Media re-attaches to the same parent. `add()` now refuses media already on the account ("This image is already on this account."), and the gallery sends one attach per queued file (`ready` fires again when processing ends). Tests: the double post (security), the single send (Vitest).
- Spec (medium): attach, remove and the processing poll were sync Inertia visits that cancel each other; all three are `async` now.
- Spec (low): the board row had a doubled pipe; fixed. Race coverage: a serialized last-place test and the PostgreSQL CHECK test (skipped on SQLite, where the CHECK does not exist). The attach path and its refusal message are covered in Vitest. An unrelated Prettier change to `PlayerPreviewCard.vue` was reverted.
- antislop audit-043: no findings.
- Owner report (2026-10-06): an add could fail with "This upload is not ready to use." The gallery attaches once storage accepted the file, often while the media is still `uploaded` (its job queued, not started), and attachment only took `ready` / `processing`. `MediaAttachmentService::attach` now also takes `uploaded`, which processing treats like `processing`. Test: "attaches an upload still waiting in the processing queue". synced → specs/10 §3.
