---
id: P2-05
title: Assemble, manifest and publish game asset pack v1, and widen the manifest to the categories the pack holds
phase: 2
status: done
depends_on: [P0-06]
---

# Assemble and publish game asset pack v1

## Spec refs
- Core: specs/10 §11 (11.1 what is self-hosted, 11.2 runbook, 11.3 rules, 11.4 sizing); specs/18 §2.1 (conditions 1–7), §2.3
- Plus: specs/09 §8 (league fallback, game-update rule 2); specs/19 §5 (`config/assets.php`), §7 (`assets:*`); specs/25 Phase 2 "Asset pack v1" + exit; specs/24 Q13, Q14; tasks/phase-0/P0-06 (resolver, commands)
- FR: none (catalogue work); NFR-PRIV-5, NFR-PRIV-7
- Edge cases: specs/09 §8 rule 2 (unknown unit → placeholder plus name); missing asset → placeholder (18 §2.3)

## Scope
- **Pack layout** (owner-staged at `src/resources/game-assets/1/`, 176 files): folders `units/ heroes/ spells/ equipments/ pets/ machines/ townhalls/`, each with an optional `builder-base/` subfolder. `leagues/` and `guardians/` move out of the pack folder (decisions 3, 4). Widen `PackManifest::KEY_PATTERN`, `GameAssetCategory` (+ `pet`, `siege_machine`) and `folder()` to match. A key under `builder-base/` means `village = builderBase` (10 §11.1 paths, synced).
- **Builder Hall**: Town Hall entries take a village, so `GameAssetResolver::townHall(level, village)` resolves Builder Hall levels too (18 §2.1 "Town Hall imagery").
- **File hygiene** (renames only; bytes never touched, 10 §11.2 step 1): the 5 WebP files saved as `.png` get a `.webp` extension; `nigth-witch` → `night-witch`; `iron-pants` → `metal-pants`. `make-manifest` and `publish-pack` refuse a key whose extension disagrees with its real signature. Delete the Windows `*:Zone.Identifier` file and have `LocalPack` skip such files.
- **Size guard** (10 §11.4): `assets.max_bytes` = 1 MB, no pixel limit (decision 6). `make-manifest` lists oversize files; `publish-pack` refuses them. Files are never resized (decision 1): an oversize file leaves the pack folder until a smaller Fan Kit file is found, and shows the placeholder meanwhile.
- **Manifest v1**: `ref` = the API's exact name (`P.E.K.K.A`, `L.A.S.S.I`, `Lightning Spell`, `Metal Pants`, …), display name, village and `source` (`Supercell Fan Kit`, decision 2) filled in for every entry. Commit it at `resources/game-assets/1/manifest.json` (10 §11.2 step 4). Fix `resources/game-assets/.gitignore`: today `*` excludes the version folders, so `!manifest.json` never re-includes the manifest. Use `*`, `!*/`, `!manifest.json`, `!.gitignore`, `!.gitkeep`, plus a test that `git check-ignore` passes a pack image and fails the manifest.
- **Catalogue order config** (owner commit e569222): line up the `config/assets.php` lists with the manifest keys (`barbarian_king` → `barbarian-king`, `eternal tome`, `heroic torch`, `siege-machines` vs `machines/`). A test checks that every packed Home Village unit is listed. P2-04 reads these lists; this task does not.
- **Publish**: `assets:make-manifest` → `assets:publish-pack --pack-version=1` to local MinIO, then `assets:verify-pack` reports clean. Add `ASSETS_PACK_VERSION=1` to `.env.example`.
- Migrations / policy / form request / UI: none (CLI only, no HTTP surface).

## Out of scope
- Publishing to R2 staging/prod: waits on P0-09 (runbook step for that task).
- League emblems: v1 ships none, so the resolver uses the API's `iconUrls` (09 §8; decision 3). Self-hosting them needs the `/leagues` id list (P2-08, blocked) and a v2 pack.
- Progression grid ordering and display (P2-04); clan badges (stay API-referenced, 10 §11.1).

## Acceptance criteria
- Functional: NFR-PRIV-5/7; the pack in MinIO is byte-identical to the staged files (verify-pack clean); resolver returns pack URLs for v1 entries, the placeholder for anything else.
- Authorization: no user path to `game/`; the commands are CLI only (10 §11.3).
- Edge cases: an unknown unit from the `YC8V2QG9` fixture (TH 18, unknown units) resolves to the placeholder plus its name; a Builder Hall level missing from the pack → placeholder; kill switch still wins.
- States: n/a (no new UI; `<GameAsset>` states shipped in P0-06).

## Tests
- Unit: key pattern accepts the new folders and `builder-base/`, rejects traversal and capitals; village taken from the folder; extension/signature mismatch refused; a file over 1 MB refused; `Zone.Identifier` files skipped.
- Feature: committed v1 manifest parses and every API-shaped name in the fixtures maps as expected (known → URL, unknown → placeholder); `townHall(n, builderBase)`; every config catalogue slug exists in the manifest.
- Security: resolver URLs stay under `game/1/` (existing P0-06 test, re-run against v1).

## Notes

### Decisions (owner, 2026-10-02)
1. Files stay byte-exact: no resizing or re-encoding; an oversize file is replaced by a smaller original or left out. synced → specs/10 §11.2 step 1, §11.4.
2. Source: Supercell's Fan Kits; every entry's `source` is `Supercell Fan Kit`. synced → specs/24 Q13, specs/10 §11.2 step 1, specs/18 §2.3.
3. Leagues: the API's icon; no league emblems in pack 1. synced → specs/10 §2 layout, §11.1, specs/18 §2.3.
4. Guardians stay out of pack 1 (no API data). The Builder Base Baby Dragon ships as `units/builder-base/baby-dragon.png`, a byte-identical copy of the home file. synced → specs/10 §11.1.
5. Re-cuts: the owner, within about a week of a game update. synced → specs/24 Q14, specs/10 §11.2 step 6.
6. Size limit: `assets.max_bytes` = 1 MB per file, no pixel limit; the manifest keeps the P0-06 fields. synced → specs/10 §11.4, specs/19 §5.

### Implementation decisions
- Category and village come from the folder (`GameAssetCategory::fromFolder()`, `PackManifest::villageForKey()`), so staff fill in only `ref` (where the API name differs), display name and source. Village is required for units and halls and must match the folder; leagues have none. synced → specs/10 §11.2 step 2.
- New categories `pet` and `siege_machine` (`pets/`, `machines/`); hero, spell and equipment moved to their own folders. `isUnit()` now means "not a hall or league". synced → specs/10 §2, §11.1, specs/18 §2.1.
- `make-manifest` writes the canonical `toJson()` form once the manifest is valid, so the in-repo pack folder and the committed copy are the same file and publish's "different pack" check compares like with like.
- Extension must match the real signature (`assets.mimes`); P0-06's "Content-Type from the signature, not the file name" test became "a WebP named .png is refused". synced → specs/10 §11.2 step 2.
- Catalogue order config: the test checks that every packed Home Village unit is listed (manifest ⊆ config), not the reverse, because 52 listed items are held out of pack 1. `heroes_equipments` keys must equal `heroes`.
- `ref` values: the API names known from the docs (`P.E.K.K.A`, `Power P.E.K.K.A`, `L.A.S.S.I`, `<Name> Spell`, `Healing Spell`); the rest are the headline of the file name. The fixtures are synthetic, so the newest names (Meteor Golem, Ruin Witch, Monolith Arrow, …) are unconfirmed until a real response is recorded.
- Pack 1 holds 109 files. Held out (over 1 MB; originals stay in `D:\game-assets`): 34 equipment (all but action-figure, dark-crown, electro-boots, electro-fangs, frost-flake, lavaloon-puppet, monolith-arrow, rocket-spear), Builder Base heroes (battle-copter, battle-machine), machines (battle-drill, sky-wagon), pets (angry-jelly, greedy-raven), spells (angry-spell, ice-block, overgrowth, totem), Town Halls 12–18, units (druid). Re-sourced files go into pack 2; `iron-pants` should be named `metal-pants` and `angry-spell` `angry` there.
- The owner kept `healing` and `angry` in the spell lists (config edit, 2026-10-02), so the config names win: `heal.png` was renamed `healing.png` (bytes unchanged). Pack 1 had only been published to local MinIO, so `game/1/` was cleared and re-published there, then verified clean.
- `LocalPack` reads and hashes the folder once per instance; `make-manifest` and `publish-pack` used to hash every file twice.
- The `.gitignore` check is manual: only `src/` is mounted in the container, so Pest cannot run `git check-ignore`. Checked: images and `:Zone.Identifier` files ignored; `1/manifest.json`, `.gitkeep`, `.gitignore` tracked.

### Open questions
None.

### Follow-ups
- Pin `COC_API_DRIVER=fake` in `phpunit.xml`: tests read the driver from `.env`, so a local `.env` with `http` fails ~350 tests (found while verifying; unrelated to this task).
- P2-04: the catalogue lists use file slugs; held-out items have no manifest entry, so ordering them by API name needs a slug ↔ name mapping.
- P0-09: publish pack 1 to R2 with the same commands, then set `ASSETS_PACK_VERSION=1` on staging.
- Set `ASSETS_PACK_VERSION=1` in your local `.env` (protected path, not edited here).

### Verification
- Checks (recheck after the `healing` rename, full `scripts/check.sh`, local `.env` on the fake CoC driver): all 13 pass. Pest sqlite 1655 passed (10 skipped), Postgres 1665 passed, Vitest and build green, generated files up to date.
- Reviews: spec review skipped (single module, no policy/schema/shared-props change); security review no findings; antislop n/a (no UI).
- MinIO, real S3 calls: make-manifest clean → publish-pack 109 assets to `game/1/` → verify-pack clean; fetched objects byte-identical with `Content-Type` from the signature (`image/webp`, `image/png`), immutable caching and `nosniff`.
