---
id: P0-05
title: Build the image upload pipeline end to end against MinIO: tables, presigned intent/complete, ProcessMediaJob, variants
phase: 0
status: done
depends_on: [P0-02]
---

# Build the image upload pipeline end to end against MinIO

## Spec refs
- Core: specs/10 §1–5, §7 (URL resolution), §10; specs/07 "Media" (`media`, `media_variants`); specs/25 Phase 0 "Media pipeline" + exit criterion
- Plus: specs/20 §1 (media queue + worker split, `retry_after`), §2 "Media", §4–5; specs/11 "File upload attacks", §4 upload suite; specs/04 §3 (MediaPolicy, IDOR, write gating), §4 (`upload-intent` limiter); specs/05 §2 (Media is an edge module; `MediaReady`/`MediaFailed`); specs/19 §5 (`config/media.php`)
- FR: FR-MEDIA-1, -2, -3, -5, -6 (images), -7, -10 (URL resolution only); FR-AUTH-4 (verified gate)
- Edge cases: specs/23 §4 rows "enormous canvas", "animated WebP/APNG", "HEAD not readable", "same file twice"; §9 "worker killed mid-media-job", "deploy during an active upload"

## Scope
- **Storage wiring** (10 §2, §2.1): `league/flysystem-aws-s3-v3`; one S3-driver disk in `config/filesystems.php` pointed at MinIO by `.env`, R2 by `.env`; `.env.example` keys; `MediaUrlResolver` (public CDN URL from `MEDIA_CDN_URL`, 5-min signed GET for private, `MEDIA_PRESIGN_HOST` host rewrite when set).
- **Migrations / models / factories** (07 "Media"): `media`, `media_variants` with the CHECKs, unique keys and partial sweeper index; `Media`, `MediaVariant` models + factories (internal to `Domain/Media`).
- **Domain** (`Domain/Media`): enums `MediaStatus`, `MediaCollection`, `MediaKind`, `MediaVisibility`, `VariantName`; `UploadIntentService` (validate, create `pending` row with `expires_at = now+24h`, app-chosen `quarantine/{yyyy}/{mm}/{ulid}/original.{ext}` key, presigned PUT 300 s signing `Content-Type` + `Content-Length`); `CompleteUploadAction` (owner + `pending` → `uploaded`, dispatch job; no-op when already `uploaded`/`processing`); `MediaProcessor` contract + `ImageProcessor` (Intervention Image v3 on GD); `MediaScanner` contract + no-op impl; events `MediaReady`, `MediaFailed`.
- **`ProcessMediaJob`** (queue `media`, 10 §3 steps a–k for images): HEAD + size match → free-space pre-flight → download to a per-job temp dir (size-capped) → magic-byte MIME → allowlist → dimension check **before** decode → GD decode → animated reject → WebP q82 variants per 10 §5, EXIF stripped, never upscaled → upload to `public/{collection}/{media_ulid}/{variant}.webp` → `media_variants` rows, `ready`, checksum, `processed_at` → delete quarantine original → `MediaReady`. Validation failure → `failed` + reason; intent-suggesting failure (MIME mismatch, trailing script/zip markers) → `quarantined`. `failed()` cleans the temp dir and marks `failed`. Idempotent on retry.
- **Policy + Form Request**: `MediaPolicy` (`create`: verified user; `complete`/`view`: owner); `CreateUploadIntentRequest` (collection known + image-kind, filename, size ≤ collection max, declared MIME + extension allowlist, SVG rejected). Complete queries are scoped to the caller (other user's ULID → 404).
- **HTTP** (`Http/Controllers/Upload`, JSON): `POST /uploads/intent`, `POST /uploads/{ulid}/complete`, `GET /uploads/{ulid}` (owner-only status, failure reason, variant URLs); `auth` + `verified` + `throttle:upload-intent` (30/h/user, defined centrally).
- **UI**: `useUpload` composable (the one allowed `fetch` to app routes, specs/19 §2): intent → PUT with progress, 2 client retries → complete → poll status with backoff; `/dev/media` page showing idle / uploading / processing / ready (variants with intrinsic `width`/`height`) / failed (reason + retry) using existing `Ui*` components.
- **Dev sign-in**: `/dev/media` signs in a seeded verified dev user only when `APP_ENV=local`; 404 in every other environment, staging included.
- **Workers**: split compose `queue` into the three specs/20 §1 workers (media: `--tries=2 --memory=512`, CPU-capped); media queue gets its own connection with `retry_after = 1200`.
- **Config** (`config/media.php`): disk, collections (kind, visibility, max bytes, min/max dimensions, variants), allowed MIME/extensions, intent TTL 300 s, pending expiry 24 h, WebP quality, temp dir + min free space, queue name.

## Out of scope
- Sweeper, purge, retry-failed, `DeleteMediaObjectsJob`, worker boot-time temp sweep → **P0-08** (new row)
- `media:reconcile-storage` + `game/` allowlist → P0-06 (per board)
- Video processing and the `base_video`/`evidence` collections → P3-02 / P3-06 (intent rejects them)
- Attachment (`MediaAttachmentService`, per-parent quotas) → first parent task (P1-03 avatar)
- Per-user 500 MB cap (needs `user_stats`), `EnsureAccountIsActive` (P1-02), moderation flag on quarantine (P3-06), ClamAV impl
- CDN headers / hotlink / Worker rules (verified on staging, 10 §2.1)

## Acceptance criteria
- Functional: FR-MEDIA-1/2/3/5/6/7 for image collections; exit criterion from specs/25 Phase 0 demonstrated against MinIO; no code outside `config/filesystems.php` names a provider.
- Authorization: guests 401/redirect; unverified 403; complete/status on another user's media 404; `/dev/media` 404 outside `local`; client cannot choose the key; no presigned key outside `quarantine/`.
- Edge cases: 30000×30000 rejected before decode; animated WebP/APNG rejected; missing/short object → `failed`; duplicate complete is a no-op; retried job produces the same variants.
- States: idle, uploading, processing, ready, failed and "uploads unavailable" designed and implemented.

## Tests
- Feature: intent happy path (row, key prefix, TTL), validation matrix, 31st intent/h → 429; complete + status ownership/state/idempotency; `/dev/media` 404 outside `local`; job against `Storage::fake` with fixture images → variants, dimensions, `MediaReady`.
- Security (`tests/Security/Upload`): polyglot, MIME mismatch → `quarantined`, oversized, SVG, dimension bomb, 0-byte, wrong magic bytes, EXIF/GPS absent in output, IDOR on complete and status.
- Unit: enums, key builder (always `quarantine/`), `MediaUrlResolver` host rewrite on/off, provider-name arch test, config-driven limits.
- Vitest: `useUpload` state machine (retry twice then error, progress, failed reason).

## Notes

### Decisions
- Presigned PUT enforces neither size nor type: SigV4 presigning never signs `Content-Type`/`Content-Length`, and R2 has no POST-policy uploads. Controls: server-chosen `quarantine/` key, HEAD size check before download, download capped at declared + 1 byte, magic bytes. synced → specs/10 §3, specs/11 "File upload attacks".
- `MEDIA_PRESIGN_HOST` is the endpoint URLs are **signed against**, not a rewrite: the signature covers Host, so rewriting a signed URL breaks it. synced → specs/10 §2.1.
- Disk named `media`; the `disk` column stores the disk name, not the provider. synced → specs/07, specs/10 §2.1.
- Avatar sizes 512/128/48 use the variant names `full`/`card`/`thumb`. synced → specs/10 §5.
- `mime_type`/`extension` are nullable until processing sets them from the real bytes. synced → specs/07.
- Sweeper index is `(expires_at) WHERE attachable_id IS NULL`: with the CHECK dropped, `ready` rows can be unattached too. synced → specs/07, specs/10 §9.
- Real MIME outside the allowlist, script markers or an appended zip → `quarantined`. A real MIME inside the allowlist but different from the declared one (a PNG named `.jpg`) is benign and processed as its real type. synced → specs/10 §4.
- Deterministic rejections delete the quarantine original at once; `failed/processing_error` (retries exhausted) keeps it for `media:retry-failed` (P0-08). synced → specs/10 §4, §9.
- Authorization runs inside the Media services/action through `MediaPolicy` (`Gate::forUser`) after an owner-scoped lookup (404 first): Deptrac forbids Http → Models, so controllers pass the user and ULID. synced → specs/04 §3, docs/ai/rules/backend.md.
- `User` implements `MustVerifyEmail`, which `verified` and the policy need (FR-AUTH-4).
- Media worker consumes a dedicated `media` queue connection (same `jobs` table, `retry_after` 1200); jobs are dispatched on the default connection. synced → specs/20 §1.
- Quarantine logs `media.quarantined` with the uploader id; the moderation flag lands with P3-06.
- `phpunit.xml` gained the Security, Contract and Architecture suites: they existed but never ran (P0-02 arch tests included).
- ESLint now bans `fetch`/`XMLHttpRequest`/`axios` outside `useUpload` (specs/19 §2).
- `UiProgress` (bar: determinate + indeterminate) added to `/dev/components`; the ring variant waits for a use.
- Board split: lifecycle jobs moved to new task P0-08 to keep this under a page; FR-MEDIA-8 is unmet until it lands. synced → specs/25 Phase 0 and §4.
- Payloads: intent `201 {mediaUlid, uploadUrl, uploadMethod, uploadHeaders, expiresIn, maxSize}`; complete `202` and `GET /uploads/{ulid}` return `{mediaUlid, status, finished, failureMessage, width, height, variants[]}`. synced → specs/10 §3, specs/19 §4.
- Job retries: `maxExceptions` 2 inside a 60-minute `retryUntil` window, timeouts count as failures (`failOnTimeout` false); a full temp volume releases without using the budget. synced → specs/20 §1, specs/10 §10.
- (Security review) The presigned PUT outlives processing, so `PurgeQuarantineObjectJob` deletes the quarantine key again at intent TTL + 60 s (ready/failed rows only). Backstop: a 31-day lifecycle rule on `quarantine/` (set by `minio-init`; R2 needs the same rule). synced → specs/10 §2–3, specs/20 §2, specs/11.
- (Security review) 404s on `uploads/*` render `{"message": "Not found."}` so model class names never leak.
- Variants are written with `Cache-Control` by visibility (`private, no-store` for private). Filename lengths and the "JPEG, PNG or WebP" label are config keys.

### Follow-ups
- P0-08: sweep unattached `ready` rows too; retry only `processing_error` failures (their original is kept).
- Staging: R2 bucket CORS must allow `PUT` from the app origin (MinIO allows all origins by default), and the bucket needs the 31-day `quarantine/` lifecycle rule.

### Open questions
Resolved by the owner, 2026-09-30:
1. Drop the specs/07 `ready`-requires-attachment CHECK (conflicts with attaching `ready` media, specs/10 §3); the sweeper handles unattached rows. synced → specs/07.
2. Public keys are `{visibility}/{collection}/{media_ulid}/{variant}.webp` (the parent is unknown at processing time; attach never moves objects). synced → specs/10 §2.
3. Demo auth: local-only dev sign-in on `/dev/media` (above).
4. Add owner-only `GET /uploads/{ulid}` for polling. synced → specs/10 §3, specs/19 §4.

### Verification
- End to end against MinIO (CLI, real S3 calls): intent → PUT 200 → complete → job → `ready`, three WebP variants served publicly with `Cache-Control: public, max-age=31536000, immutable`, quarantine key 403 to anonymous reads, original deleted. Host-signed PUT to `localhost:9000` 200; CORS preflight from `localhost:8080` 204.
- Reviews: antislop audit-004 no findings; spec review 8 findings and security review 2 findings, all fixed (see Decisions).
- Browser: `/dev/media` renders at 375px and desktop; with no storage keys in `.env` the upload shows the "unavailable" state.
