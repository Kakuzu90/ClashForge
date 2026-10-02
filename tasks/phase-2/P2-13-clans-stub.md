---
id: P2-13
title: Create the clans stub and link each CoC account to its clan
phase: 2
status: done
depends_on: [P2-02]
---

# Clans stub

## Spec refs
- Core: specs/07 `clans` ("read-only stub in M"), `coc_accounts.clan_id` (`ON DELETE SET NULL`, added with P2-13), indexes
- Plus: specs/08 §3 (coc_account → clan N:1 nullable, set null), §5 (denormalised `coc_accounts.clan_tag` from the account sync); specs/05 §2 (Clans owns `clans`, depends on CocIntegration; Clans consumes `CocAccountVerified` for "ensure clan"); specs/09 §8 (`PlayerClanData`: tag, name, role, level, badgeUrls), §8 "Asset URLs" (badges stored verbatim, never mirrored); specs/18 §2.3 (badge URLs referenced, allowlisted at render)
- FR: FR-COC-3 (clan and clan role stored on attach); FR-COC-15 is P4-01's
- Edge cases: specs/23 §2 (a private clan or a hidden war log: store what the API gives), §6 (a disbanded and recreated clan is a new clan, by tag)

## Scope
- **Migrations / models / factories** (`Domain/Clans`): `clans` in the specs/07 shape. Columns the player payload cannot fill are nullable until the clan sync (P4-01) fills them (Q2). Unique `tag_normalized`, plus the specs/07 indexes, with the GIN on `name` on Postgres only. `coc_accounts.clan_id` (FK, `nullOnDelete`, index) through a PlayerAccounts migration. `Clan` model and factory.
- **Domain**: `Clans/Services/ClanDirectory::ensure(PlayerClanData): int`.
  - Finds the row by `tag_normalized`, or inserts it race-safely on the unique key.
  - Rewrites `name`, `badge_urls` and `level` only when they changed (Q3).
  - PlayerAccounts sets `clan_id` wherever it applies player data (Q1): `AccountRows::createOrReuse` on attach, and `AccountSyncService::store`. No clan in the payload sets `clan_id` to null.
  - A dispute `grant` already copies the holder's fillable columns, so it carries `clan_id`.
- **Read model** (Q4): `Clans/Services/ClanReadModel::summaries(list<int>)` returns `array<int, ClanSummaryData>` (tag, name, level, badge URLs) for P2-04's ClanChip.
- **Policy + Form Request**: none. There is no user write path, and clans are written from API data only.
- **UI**: none (ClanChip is P2-04's).
- Jobs / listeners / schedule: none (Q1).
- Config: none.

## Out of scope
- `/clans/{tag}` fetch, `SyncClanJob`, `coc:sync-clans`, `tracked_reason` rules, `clan_memberships`, `clan_snapshots` (P4-01).
- ClanChip, PlayerCard (P2-04). A backfill of existing rows (Q5).

## Acceptance criteria
- Functional:
  - Attaching an account in a clan creates the stub, or reuses it, and sets `clan_id`.
  - A sync that sees a new clan relinks the account; leaving a clan clears `clan_id`.
  - A changed clan name or badge updates the stub; an unchanged one writes nothing.
- Authorization: no new surface. The arch test passes: PlayerAccounts reaches Clans through `Services` and `Data` only, and Clans depends on CocIntegration `Data` only.
- Edge cases: two accounts in one clan share one row; two concurrent ensures for a new tag make one row; deleting a clan sets `clan_id` to null (`ON DELETE SET NULL`); a clan block without badges stores `[]`.
- States: n/a (no UI).

## Tests
- Feature:
  - Attach links or creates the clan.
  - A sync relinks the account, or unlinks it when it left the clan.
  - Name and badge refresh; no write when unchanged (`updated_at` stays).
  - Dispute grant keeps `clan_id`.
  - `ClanReadModel` summaries.
- Database (Postgres): unique `tag_normalized`; the FK sets null on delete.
- Unit: none (the logic is in the database round trip).
- Vitest: none.

## Notes

### Open questions
Resolved by the owner, 2026-10-02 (all as recommended):
1. PlayerAccounts sets `clan_id` through `ClanDirectory::ensure()` in the same write that sets `clan_tag` (attach, sync), so the two always match. No listener on `CocAccountVerified` in this task; the "ensure clan" consumer becomes P4-01's `SyncClanJob` dispatch for a new clan. Sync into specs/05 (PlayerAccounts depends on Clans; the event table's Clans consumer arrives with P4-01).
2. The full specs/07 `clans` table now; columns only the `/clans` endpoint fills are nullable until P4-01. Sync the nullability into specs/07.
3. An account sync rewrites the stub's name, badge and level only when they differ; P4-01's `/clans` data takes precedence once it exists.
4. `ClanReadModel::summaries()` lands now for P2-04's ClanChip.
5. No backfill: the next sync links verified accounts, unverified ones link on their next attach.

### Decisions and divergences (implement, 2026-10-03)
1. API-only `clans` columns are nullable (`level`, points, war fields, `is_war_log_public`, `members_count`, requirements, `type`, `tracked_reason`, `api_synced_at`); `badge_urls` defaults to `{}` and stores `[]` when the API sends none; `type` and `tracked_reason` are varchar with a Postgres CHECK and stay plain strings in the model until P4-01 writes them. synced → specs/07 `clans`.
2. `languages` (a platform-supplied Postgres array) is left to recruitment, its only writer. synced → specs/07 `clans`, tasks/BOARD.md (P4-02).
3. `ClanDirectory::ensure()` never lets a payload without `clanLevel` erase a known level, and changes nothing once `api_synced_at` is set: the clan sync's data wins (Q3). synced → specs/07 `clans`.
4. Badge change detection compares loosely: jsonb returns object keys in its own order, so a strict comparison would rewrite the row on every sync.
5. A concurrent insert of a new tag is resolved by `createOrFirst` (savepoint inside the caller's transaction, then a re-read).
6. SQLite adds the `clan_id` foreign key by rebuilding `coc_accounts`, and the rebuild recreated `coc_accounts_one_verified_owner` and `coc_accounts_one_featured` without their WHERE clauses. The migration recreates both on SQLite, in `up` and `down`. Postgres alters in place.
7. `clan_id` is fillable game data, so a dispute grant's copy of the holder's columns carries it.
8. Module surface: PlayerAccounts depends on Clans (`ClanDirectory`); Clans consumes `CocAccountVerified` only from P4-01. synced → specs/05 §2, specs/08 §5, specs/09 §6 and §8.
