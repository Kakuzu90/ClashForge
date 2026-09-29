# 10 — Media & Storage Architecture

> **Scope:** this pipeline handles **user-uploaded** media only. Clash of Clans game assets never
> enter it — the curated catalogue (unit icons, Town Hall imagery, league emblems) is uploaded by
> staff to a dedicated `game/` prefix byte-for-byte (§11), and clan badges are referenced from the
> API's own URLs. Both are rendered unmodified through `GameAssetResolver`, because the mandatory
> re-encode in §4 would be a modification the fan-content policy does not permit. See
> [18 §2](18-design-system.md).

## 1. Principles

1. **No binary data in Postgres.** The database stores metadata and storage keys only.
2. **The app server never touches file bytes on the way in.** Browsers upload directly to R2 with
   presigned URLs. A 100 MB video must never occupy a PHP-FPM worker.
3. **Nothing is trusted until it is validated and re-encoded.** The uploaded bytes are treated as
   hostile until a worker has inspected and rewritten them.
4. **Public media is only ever served from the CDN**, on a cookieless domain, from a bucket path
   that contains nothing private.
5. **Every byte has an owner and an expiry.** Orphans are a cost and a liability; a sweeper deletes
   them daily.

## 2. Storage layout

Bucket: one private R2 bucket (`clashcommons-media`), no public bucket-level access. A Cloudflare
Worker or custom domain binding exposes the `public/` and `game/` prefixes through the CDN, and
nothing else.

```
quarantine/{yyyy}/{mm}/{media_ulid}/original.{ext}    ← raw upload lands here, never served
public/avatars/{user_ulid}/{variant}.webp
public/accounts/{account_ulid}/{media_ulid}/{variant}.webp
public/bases/{base_ulid}/{media_ulid}/{variant}.webp
public/bases/{base_ulid}/{media_ulid}/video_720p.mp4
public/bases/{base_ulid}/{media_ulid}/poster.webp
private/evidence/{report_ulid}/{media_ulid}/original.{ext}   ← signed URLs only, staff access

game/{pack_version}/units/{slug}.png          ← curated game assets, uploaded by staff, byte-exact
game/{pack_version}/townhalls/{level}.png
game/{pack_version}/leagues/{league_id}.png
game/{pack_version}/manifest.json
```

Why `quarantine/` is a separate prefix: it makes "unvalidated bytes are not publicly reachable" a
property of the bucket layout rather than of application logic. A bug in a URL resolver cannot
expose an unscanned file, because the public CDN binding does not cover that prefix.

Why `game/` is a separate prefix: those objects are **not user media**. They have no `media` row,
they never pass through the upload pipeline, and they must never be re-encoded. Keeping them under
their own prefix is what lets every sweeper, quota and reconcile job ignore them by rule rather
than by accident. The CDN binding covers `public/` **and** `game/`; both are read-only and public.
Full policy in [18 §2](18-design-system.md); the upload procedure is §11 below.

### 2.1 Local development

Local dev runs **MinIO** (compose service `minio`, profile `storage`, console :9001) in place of
R2. It is off by default to keep the idle stack small — start it with
`docker compose --profile storage up -d` before any media work. It is S3-compatible, so presigned
PUT/GET, path-style addressing and prefix-level public access all behave the same, and the real
upload flow is exercised rather than stubbed. Moving to R2 is an
`.env` change — endpoint, bucket, credentials, CDN URL — with no code change. That property is a
requirement, not a convenience: a test asserts nothing outside `config/filesystems.php` names a
provider.

Two local-only wrinkles, both confined to configuration:

- **Presign host.** The app signs URLs against `http://minio:9000`, which the browser on the host
  cannot resolve. `MediaUrlResolver` rewrites the host of generated URLs to `MEDIA_PRESIGN_HOST`
  when that variable is set, and leaves them untouched when it is not. It is empty in
  staging/production. (The alternative — a `minio` entry in the host's `/etc/hosts` — works too and
  needs no code, but requires a manual machine-level change.)
- **No CDN.** There is no Cloudflare in front locally, so cache headers, hotlink rules and the
  `game/`-prefix resizing opt-out (§7, §11.3) are configuration that only takes effect in
  staging/production. They must be verified there, not assumed from a green local run.

The `minio-init` one-shot container creates the bucket and marks `public/` and `game/` readable,
mirroring the CDN binding in §2.

## 3. Upload pipeline

```
 ┌─ Browser ──────────────────────────────────────────────────────────────┐
 │ 1. POST /uploads/intent {collection, filename, size, mime}             │
 │ 2. ← {media_ulid, presigned_put_url, expires_in: 300, max_size}        │
 │ 3. PUT bytes ──────────────────────────────────────▶ R2 quarantine/    │
 │ 4. POST /uploads/{media_ulid}/complete                                 │
 └────────────────────────────────────────────────────────────────────────┘
                                   │
                          ProcessMediaJob (queue: media)
                                   │
   ┌───────────────────────────────┴────────────────────────────────┐
   │ a. HEAD object: exists? size matches the declared size?         │
   │ b. Download to a temp file (worker-local, size-capped)          │
   │ c. Magic-byte signature check → real MIME                       │
   │ d. Allowlist check: real MIME ∈ {jpeg,png,webp} | {mp4}         │
   │ e. Image: decode with GD, reject on decode failure              │
   │    Video: ffprobe — container, codecs, duration, resolution     │
   │ f. Dimension / duration / bitrate limits                        │
   │ g. Re-encode:                                                   │
   │      images → WebP variants, EXIF stripped, ICC normalised      │
   │      video  → h264/aac mp4 ≤720p + poster frame                 │
   │ h. Upload derived files to public/ (or private/)                │
   │ i. Write media_variants rows; media.status = ready              │
   │ j. Delete the quarantine original                               │
   │ k. Dispatch MediaReady                                          │
   └─────────────────────────────────────────────────────────────────┘
        any failure → status = failed (reason) or quarantined (suspicious)
```

### Intent endpoint rules
- Requires auth, verified email, active status.
- Validates: collection is known, the user's quota for that collection has room, declared size is
  within the collection's limit, declared MIME is in the allowlist (a first-pass filter only).
- Creates the `media` row in `pending` with `expires_at = now + 24h`.
- Presigned PUT is valid for 5 minutes, constrained by `Content-Length` range and
  `Content-Type`, and targets a key the client cannot choose.
- Rate limited: 30 intents/hour/user.

### Complete endpoint rules
- Verifies the media row belongs to the caller and is `pending`.
- Marks it `uploaded`, dispatches `ProcessMediaJob`.
- Idempotent — a duplicate call while `processing` is a no-op.

### Attachment
Attachment happens when the parent form is submitted (publish base, save account images), inside the
parent's transaction:
- assert every media id belongs to the user, is in the right collection, and is `ready` or
  `processing`;
- assert the parent's quota (≤2 screenshots, ≤1 video, ≤5 account images, 1 avatar);
- set `attachable_type/id`, clear `expires_at`, set `position`.

A base whose media is still `processing` is created in `processing` status and published
automatically by the `MediaReady` listener when the last item finishes.

## 4. Validation rules

| Check | Images | Videos |
|---|---|---|
| Extension allowlist | jpg, jpeg, png, webp | mp4 |
| Declared MIME allowlist | image/jpeg, image/png, image/webp | video/mp4 |
| **Real MIME from magic bytes** | must match the allowlist | must match |
| Max size | 5 MB (avatar 2 MB) | 100 MB |
| Max dimensions | 6000 × 6000 | 1920 × 1080 after transcode |
| Min dimensions | 200 × 200 | — |
| Max duration | — | 60 s (reject over; do not truncate silently) |
| Codec | — | video h264/hevc in, h264 out; audio aac/mp3 in, aac out |
| Decode test | GD must decode it | ffprobe must parse it |
| Re-encode | always | always |
| EXIF / metadata | stripped (GPS removal is the point) | all metadata stripped |
| Polyglot defence | re-encode discards non-image trailing data | re-mux discards everything outside the container |
| Filename | never used as a storage key; original kept for display, sanitised and length-capped | same |
| SVG | **rejected outright** — SVG is script-capable | n/a |
| Animated formats | animated webp/gif rejected in image collections | n/a |

### Malware posture
Content-type confusion and polyglot files are the realistic threat, and **mandatory re-encoding is
the primary control** — it destroys embedded payloads by construction. On top of that:
- Files live in a non-public prefix until validated.
- Public media is served from a separate cookieless domain with
  `X-Content-Type-Options: nosniff` and `Content-Disposition: inline` with an explicit,
  server-set `Content-Type`.
- A `Content-Security-Policy` on the media domain (`default-src 'none'; sandbox`) neutralises
  anything that does get served.
- **Optional ClamAV step**: a `MediaScanner` interface with a no-op implementation at launch and a
  ClamAV implementation behind a feature flag. Enable it when user-uploaded evidence files
  (arbitrary formats) ship with the report system, where re-encoding cannot be applied.
- Any file failing validation in a way that suggests intent (MIME mismatch, embedded script
  markers, zip-in-image) is set to `quarantined`, retained 30 days for review, and its uploader is
  flagged for moderation.

## 5. Image processing

| Collection | Variants |
|---|---|
| avatar | 512, 128, 48 (square, centre-cropped) |
| account_image | full 1600w, card 800w, thumb 320w |
| base_screenshot | full 1600w, card 800w, thumb 320w |
| portfolio | card 800w, thumb 320w |

- Output format: **WebP** (quality 82), with a JPEG fallback variant only if analytics show
  meaningful traffic from browsers without WebP support (in practice: none).
- Images are never upscaled; a smaller original keeps its size.
- Aspect ratio preserved except avatars.
- `width`/`height` stored so every `<img>` ships intrinsic dimensions (CLS budget, NFR-PERF-5).
- Processing library: Intervention Image v3 on GD (already in the Docker image). Memory limit on the
  media worker set explicitly; decode bombs are bounded by the dimension check before decode.

## 6. Video processing

**Strategy: in-house ffmpeg on the `media` queue worker for the MVP.**

| Step | Detail |
|---|---|
| Probe | `ffprobe` for container, streams, duration, resolution, bitrate. Reject early — no transcode of a 10-minute file |
| Transcode | h264 `veryfast`, CRF 26, ≤720p (scale down only), audio aac 96 kbps mono/stereo, `+faststart` for progressive playback |
| Poster | Frame at 10% of duration, WebP |
| Output cap | If the transcode exceeds 40 MB, re-run at CRF 30; if still over, fail with a user-facing message |
| Timeout | Job timeout 900 s; hard `-timelimit` on the ffmpeg process |
| Concurrency | The `media` queue runs with exactly 1 worker process at MVP so transcodes never starve the box |
| Resource guard | `nice`/`cpulimit` on the ffmpeg invocation; the media worker is a separate container with a CPU cap |

**Migration trigger (NFR-COST-3):** move to Cloudflare Stream or a hosted transcoder when any of —
median transcode > 90 s, media queue depth > 50 for over 15 min, worker CPU sustained > 80%, or
video uploads > 200/day. The `MediaProcessor` interface exists so this is an implementation swap.

**Deliberately not supported:** HLS/DASH adaptive streaming (a single 720p progressive mp4 is
correct for ≤60 s clips), multiple resolutions, subtitles, GIF output.

## 7. Delivery

| Media | Path | Caching |
|---|---|---|
| Public images/videos | CDN on `cdn.<domain>` bound to `public/` | `Cache-Control: public, max-age=31536000, immutable` — keys contain the media ULID, so content never changes under a key |
| Private (evidence, pending previews) | App-generated presigned GET, 5-minute TTL, never cached by the CDN | `Cache-Control: private, no-store` |
| Avatars | Public, same as images | same |

- The media domain is **cookieless** (no session cookie scope) and serves
  `X-Content-Type-Options: nosniff`, `Cross-Origin-Resource-Policy: same-site`,
  and the sandbox CSP.
- Signed URLs are generated by `MediaUrlResolver` and cached for slightly less than their TTL so a
  feed of 24 private items does not generate 24 signatures per render.
- R2 egress to Cloudflare CDN is zero-rated — this is why R2 rather than S3 (NFR-COST-2).
- Hotlink protection via Cloudflare referer rules; a Worker adds the headers and blocks the
  `quarantine/` and `private/` prefixes unconditionally.
- **Image Resizing / Polish must be disabled for the `game/` prefix** — those are transformations,
  and game assets must be served byte-identical to what was uploaded (§11.3).

## 8. Quotas

| Scope | Limit | Enforced |
|---|---|---|
| Avatar | 1, ≤2 MB | On attach (replaces the previous, which is queued for deletion) |
| CoC account images | 5 per account, ≤5 MB each | `coc_accounts.images_count` checked in the attach transaction |
| Base screenshots | 2 per base, ≤5 MB each | attach transaction |
| Base video | 1 per base, ≤100 MB, ≤60 s | attach transaction |
| Report evidence | 3 per report, ≤5 MB each | attach transaction |
| Marketplace portfolio | 5 per listing | attach transaction |
| Per-user total storage | 500 MB soft cap, warn at 80%, block new uploads at 100% | nightly recompute into `user_stats` |
| Upload intents | 30/hour/user | rate limiter |

Quota changes are config values, not code.

## 9. Cleanup & orphan handling

Four scheduled jobs. Every one of them is the reason the storage bill stays predictable.

| Job | Schedule | Action |
|---|---|---|
| `media:sweep-orphans` | Hourly | Delete `media` rows in `pending`/`uploaded` past `expires_at` (24 h) with no `attachable_id`; delete their objects |
| `media:purge-deleted` | Daily | Hard-delete storage objects + variants for media soft-deleted more than 7 days ago |
| `media:reconcile-storage` | Weekly | List bucket keys **under `public/`, `quarantine/` and `private/` only**, diff against `media`+`media_variants`; delete bucket objects with no database row (log first, delete on the second consecutive detection); alert on database rows with no object. **The `game/` prefix is excluded by an explicit allowlist in code, not by convention** — every game asset has no `media` row by design, so an unguarded reconcile would delete the entire asset pack. A test asserts the exclusion |
| `assets:verify-pack` | Weekly | Verifies every manifest entry resolves to an object whose SHA-256 matches the recorded checksum; alerts on missing, extra or modified objects |
| `media:retry-failed` | Every 6 h | Retry `failed` media younger than 24 h, up to 3 total attempts, then notify the owner |

Cascade rules:
- Deleting a base → its media soft-deleted → purged after 7 days (a window for accidental-delete
  recovery and for moderation review).
- Deleting a user → 30-day soft delete → anonymisation → media purge.
- **Quarantined media is never auto-deleted**; it is retained 30 days for moderator review and then
  purged by a separate job that logs to `audit_logs`.

## 10. Failure modes and responses

| Failure | Response |
|---|---|
| R2 unavailable at intent | Upload UI disabled with "uploads are temporarily unavailable"; forms that need media are blocked, others proceed |
| Upload PUT fails client-side | Client retries twice, then surfaces a retry button; the `pending` row expires harmlessly |
| `complete` never called | Row expires in 24 h; sweeper removes the object |
| Processing fails | `failed` + reason shown to the user with a re-upload affordance; the parent base stays in `processing` and is not published |
| Processing times out | Job fails, retried twice, then `failed`; alert if the failure rate exceeds 5% |
| Worker dies mid-transcode | Temp files are in a per-job directory cleaned by the job's `failed()` hook and by a boot-time sweep |
| Storage key collision | Impossible by construction (ULID in the path) |
| Base published, media deleted by moderator | Base falls back to a placeholder; author is notified; base stays published unless the moderation action says otherwise |
| Disk full on the worker | Pre-flight free-space check before download; job releases back to the queue and alerts |

## 11. Game asset pack (staff-managed, self-hosted)

Clash of Clans assets that the API does not supply — troop, hero, spell and hero-equipment icons,
Town Hall imagery — are **manually curated by staff and uploaded to R2** under the `game/` prefix.
This is a deliberate, narrow exception to "all media goes through the pipeline", and it exists
because the pipeline's mandatory re-encode would modify the artwork, which the fan-content policy
does not permit ([18 §2](18-design-system.md)).

### 11.1 What is self-hosted vs referenced

| Asset | Source | Why |
|---|---|---|
| Troop / hero / spell / equipment icons | **Self-hosted** in `game/{version}/units/` | Finite, versioned set; not in the API |
| Town Hall imagery | **Self-hosted** in `game/{version}/townhalls/` | Finite set, one per level |
| League emblems | **Self-hosted** in `game/{version}/leagues/`, keyed by league id | Finite set; mirroring removes a third-party dependency on every page render |
| **Clan badges** | **Referenced** from the API's `badgeUrls` | One per clan, unbounded and mutable — thousands of clans, badges change when a clan edits them. Mirroring would be a sync problem with no upside |

So `GameAssetResolver` has two paths: manifest lookup for the static catalogue, pass-through of the
stored API URL for clan badges. Callers do not know or care which.

### 11.2 Upload procedure (a runbook, not a feature)

1. Staff assemble the asset pack locally, preserving the **original files byte-for-byte** — no
   resizing, no format conversion, no optimisation pass, no sprite-sheeting. `pngcrush`,
   `imageoptim` and similar are explicitly out: lossless or not, they rewrite the file and
   forfeit the "unmodified" claim.
2. A `manifest.json` is generated listing, per asset: key, slug, display name, category, village
   (home/builder), source, SHA-256 and byte size.
3. `php artisan assets:publish-pack {path} --version={n}` uploads the whole tree to
   `game/{n}/` with `Content-Type` set from the real file signature and
   `Cache-Control: public, max-age=31536000, immutable`, verifies each uploaded object's checksum
   against the manifest, and fails atomically — a partial pack is never activated.
4. The manifest is committed to the repository **and** stored alongside the pack. The repo copy is
   what the resolver reads at runtime (no per-request bucket listing); the bucket copy is what
   `assets:verify-pack` audits against.
5. Activation is a config change (`config('assets.pack_version')`). The previous version stays in
   the bucket, so a rollback is a one-line revert with no re-upload.

### 11.3 Rules

- **Immutable versioned prefixes.** A pack version is never edited in place. A new or corrected
  asset means a new version. This is what makes `immutable` caching safe and rollback trivial.
- **No re-encode, ever.** Not on upload, not at the CDN. Cloudflare Image Resizing / Polish must be
  **off** for the `game/` prefix — they are transformations, and "we only compressed it" is not a
  defence. Assets are sized with CSS and `srcset` over separately-supplied source files, never by
  transforming one file.
- **Provenance recorded.** The manifest carries source and checksum per asset so the pack's
  integrity and its unmodified state are demonstrable.
- **No `media` rows.** Game assets are invisible to the media pipeline, to user quotas, to the
  orphan sweeper and to the purge job. The reconcile job excludes the prefix by explicit
  allowlist (§9).
- **No user path to this prefix.** Presigned upload URLs are only ever issued for
  `quarantine/`; a resolver or intent that can produce a `game/` key is a bug with a test
  against it.
- **Kill switch.** `config('assets.enabled')` false → the resolver returns our own placeholder plus
  the label everywhere, and no game asset is served. This is the operational form of "fan content
  permission is revocable".
- **Deletion is manual and audited.** Removing a pack version is an ops action with an audit-log
  entry, never an automated sweep.

### 11.4 Sizing and delivery

- Ship each asset at a single sensible source resolution (the largest we display, typically
  ≤256 px) and scale down in CSS. Multiple baked resolutions would mean multiple derivative files
  from one original — avoid unless Supercell supplies them at those sizes.
- Served from the same cookieless CDN origin as public media, with the same
  `X-Content-Type-Options: nosniff` and sandbox CSP headers.
- Long-cached and versioned, so the asset pack contributes effectively nothing to bandwidth cost
  after the first request per edge.
- Every `<x-game.asset>` renders with explicit `width`/`height`, `loading="lazy"` below the fold,
  and an accessible name — a progression grid of 60 units must not cost layout shift or 60
  render-blocking requests.
