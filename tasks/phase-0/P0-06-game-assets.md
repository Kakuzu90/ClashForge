---
id: P0-06
title: Build the GameAssets module: resolver with kill switch and fallbacks, <GameAsset>, pack publish/verify commands, lint ban on asset paths
phase: 0
status: done
depends_on: [P0-02, P0-03]
---

# Build the GameAssets module and asset policy plumbing

## Spec refs
- Core: specs/18 §2 (asset policy, §2.3 technical handling), §4 "GameAsset" row, §9 frontend rules (game assets only via resolver + `<GameAsset>`); specs/10 §11 (pack procedure, rules, sizing); specs/25 Phase 0 "Asset policy"
- Plus: specs/19 §1–2, §5 (`config/assets.php`), §7 (`assets:*` commands); specs/05 §2 (GameAssets edge module: `GameAssetResolver`, `GameAssetPolicy`); specs/09 §8 (league emblem fallback to API URL, unknown units → generic icon); specs/20 §2–3 (weekly `assets:verify-pack`); specs/24 A22 (kill switch)
- FR: none (foundation); NFR-PRIV-5, NFR-PRIV-7
- Edge cases: specs/09 §8 rule 2 (unknown unit names render a placeholder, never dropped); missing asset or disabled category → placeholder + label (18 §2.3)

## Scope
- **Config** (`config/assets.php`): `enabled` (kill switch), `pack_version` (null until P2-05 ships a pack), committed manifest path per version, CDN base (defaults to the media CDN), storage disk, `game/` prefix, allowlisted remote hosts for API-supplied URLs (clan badges, league icons).
- **Domain** (`Domain/GameAssets`, edge module): enums `GameAssetKind` (unit, town_hall, league, clan_badge), `GameAssetCategory` (troop, hero, spell, equipment, town_hall, league), `Village`; `PackManifest` value object (loads + validates the committed JSON; entries keyed by category + API name/level/id); `GameAssetPolicy` (enabled flag); `GameAssetResolver` with `unit(name, village)`, `townHall(level)`, `league(id, name, apiIconUrl)`, `clanBadge(badgeUrls, clanName, size)` → `GameAssetData { kind, url|null, alt, width, height }`. Manifest lookup for the catalogue; league falls back to the API URL; badges pass through. Remote URLs off the host allowlist, unknown entries, a disabled category or no active pack → `url: null` (placeholder).
- **Commands** (specs/10 §11.2, specs/19 §7):
  - `assets:make-manifest {path}`: scaffolds/updates `manifest.json` from the files (slug, category from folder, village, sha256, bytes, width/height), keeping staff-edited fields (display name, API name, source).
  - `assets:publish-pack {path} --version=`: verifies local files against the manifest (no missing/extra/changed), refuses an existing version, uploads byte-for-byte to `game/{n}/` with `Content-Type` from the real signature and immutable `Cache-Control`, re-reads each object and compares SHA-256, uploads the manifest last, deletes what it uploaded on any failure, copies the manifest into the repo path.
  - `assets:verify-pack`: active version's bucket objects vs manifest → missing / extra / altered, non-zero exit + error log; scheduled weekly Sun 05:15 (`withoutOverlapping`, `onOneServer`).
- **UI**: `Components/game/GameAsset.vue` (sizes 24/32/48/64; `<img>` with alt, explicit width/height, `loading="lazy"`; fallback = our original placeholder shape + label on `url: null` or load error; accessible name always). Gallery section in `/dev/components` (fallback, missing, loaded with an original sample image).
- **Lint**: a script in `npm run lint` failing on `game/` bucket paths or Supercell asset hosts in `.vue`/`.ts` under `resources/js` (specs/18 §9, specs/19 §2).
- Migrations / policy / form request: none (no tables, no HTTP surface).

## Out of scope
- The pack itself (asset pack v1 → P2-05); PlayerCard / ThBadge / ClanChip usages (their tasks)
- `media:reconcile-storage` and its prefix allowlist → P0-08 (owner, open question 1)
- Cloudflare `game/` binding with Image Resizing / Polish off → staging (P0-07); locally `minio-init` already opens `game/`
- Footer disclaimer (done in P0-04); audit-logged pack deletion (manual ops, 10 §11.3)

## Acceptance criteria
- Functional: NFR-PRIV-5/7; every asset URL leaves PHP through `GameAssetResolver`; `assets.enabled=false` → placeholders everywhere; publish is atomic and byte-exact; verify reports missing/extra/altered.
- Authorization: no user-facing surface; no user-controlled path reaches `game/` (commands are CLI only).
- Edge cases: unknown unit, unknown TH level, league missing from the manifest (API fallback), badge URL off the allowlist, no active pack, image load error.
- States: loaded, fallback, missing designed and implemented in `<GameAsset>`.

## Tests
- Unit/Feature: resolver per kind incl. kill switch, fallbacks, host allowlist; `PackManifest` validation (bad JSON, duplicate keys, bad checksum format); `make-manifest` keeps edited fields; publish happy path, existing version refused, checksum mismatch rolls back every uploaded object, Content-Type from signature; verify-pack clean / missing / extra / altered; schedule entry present; config-driven values.
- Security: resolver never returns a URL outside the CDN `game/{version}/` prefix or the allowlisted hosts (`javascript:`, `data:`, other hosts).
- Vitest: `<GameAsset>` fallback on null URL and on error, alt/label always present, size → width/height; lint script flags asset paths and hosts.

## Notes

### Decisions
- Manifest gains `ref` (API name / TH level / league id) plus `width`/`height`, so lookups need no guessing and DTOs carry intrinsic dimensions (18 §9). synced → specs/10 §11.2.
- The commands take `--pack-version=`, not `--version=`: Symfony reserves `--version` globally, so the specced option cannot be registered. synced → specs/10 §11.2, specs/19 §7.
- Added `assets:make-manifest` for step 2 of the runbook (the spec says the manifest "is generated" without naming the tool). synced → specs/10 §11.2, specs/19 §7.
- `assets:verify-pack` runs inline from the scheduler; no separate `VerifyGameAssetPackJob`. synced → specs/20 §2.
- Committed manifests live at `resources/game-assets/{version}/manifest.json`; the active pack is `ASSETS_PACK_VERSION` (null → placeholders). synced → specs/10 §11.2.
- Remote URLs (clan badges, league API fallback) render only when `https` on an allowlisted host (`api-assets.clashofclans.com`), no userinfo or port. synced → specs/18 §2.3, specs/11.
- `Village` values match the API (`home`, `builderBase`).
- The resolver is container-scoped: the manifest is read once per request/job, and a broken or mismatched manifest logs `assets.manifest_unreadable` and degrades to placeholders.
- `<GameAsset>` never adds rounding, borders or filters to the image (18 §2.1 (2)); placeholders are our own shapes per kind with short text (initials or TH numeral). R-31: the four shapes (rounded square, hexagon, shield, notched shield) tell unit / Town Hall / league / clan apart without colour.
- `GameAssetData` carries `short` (placeholder text: initials or the TH numeral) besides `{ kind, url, alt, width, height }`. synced → specs/18 §4 GameAsset row, §9.
- Packs may contain only `image/png` and `image/webp` by real signature (`assets.mimes`); anything else fails publish. synced → specs/10 §11.2.
- `assets:verify-pack` takes `--pack-version=` (default: the active pack), compares the bucket `manifest.json` byte for byte with the committed one, succeeds with nothing to do when no pack is configured, and fails (plus `assets.pack_version_invalid` warning) when the configured version is malformed. synced → specs/10 §9, §11.2, specs/19 §7.
- The kill switch is the category switch: there is one game-asset category, so no per-kind flags. synced → specs/18 §2.3.
- (Security review) Publish holds `Cache::lock("assets:publish:{version}")` around the empty-prefix check and the uploads, and refuses a version whose committed manifest describes another pack. The S3 adapter does not forward `IfNoneMatch`, so the lock is the guard. synced → specs/10 §11.2.
- (Security review) Version, key and checksum patterns use `D`, so `$` never matches before a trailing newline.
- Commands log summary lines (`assets.manifest_built`, `assets.pack_published`, `assets.pack_verified`), and rollback failures log `assets.rollback_failed` with the leftover keys (specs/19 §7).
- The gallery's loaded-state sample is an original data-URI SVG with its own fill colours: image content, not UI colour, so tokens do not apply.
- Lint: `scripts/lint-game-assets.mjs` in `npm run lint` bans the CoC asset host and pack paths in `resources/js` and `resources/views`.

### Follow-ups
- P0-07 / staging: CDN binding for `game/` with Image Resizing and Polish off.
- P2-05: build and publish asset pack v1 with `assets:make-manifest` → `assets:publish-pack`, then set `ASSETS_PACK_VERSION`.

### Open questions
Resolved by the owner, 2026-09-30:
1. The reconcile job and its `game/` exclusion test move to P0-08: Media scans its own prefix allowlist (`public/`, `quarantine/`, `private/`), so `game/` is excluded by construction and Media never depends on GameAssets. synced → specs/25 Phase 0 and §4, tasks/BOARD.md.

### Verification
- Reviews: antislop audit-006 no findings; spec review 10 findings (8 fixed, 2 recorded as decisions); security review 4 low findings, all fixed.
- MinIO, real S3 calls: make-manifest → publish (2 assets) → verify clean; re-publish refused; the resolver returned the CDN URL; the public object was byte-identical with `Content-Type: image/png` and immutable caching; a tampered object was reported as altered (exit 1). The throwaway pack was deleted afterwards.
