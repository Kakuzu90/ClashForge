<?php

namespace App\Domain\Clans\Services;

use App\Domain\Clans\Models\Clan;
use App\Domain\CocIntegration\Data\PlayerClanData;

/**
 * The clan row behind a player's clan block (specs/07 `clans`, P2-13). PlayerAccounts calls it in
 * the same write that stores `clan_tag`, so `clan_id` always matches it. A clan is one row per tag:
 * a disbanded and recreated clan has a new tag and is a new clan (specs/23 §6).
 */
class ClanDirectory
{
    /**
     * The clan's id, creating the stub on first sight. Name, badge and level are rewritten only when
     * they differ, so the members' syncs do not rewrite the row on every visit, and a missing level
     * never erases a known one. Once the clan sync (P4-01) has fetched `/clans/{tag}`, its data
     * wins and a member's payload changes nothing. Safe inside the caller's transaction: a
     * concurrent insert of the same tag is resolved on the unique key.
     */
    public function ensure(PlayerClanData $clan): int
    {
        $values = [
            'tag' => $clan->tag->value,
            'name' => mb_substr($clan->name, 0, 30),
            'badge_urls' => $clan->badgeUrls,
            'level' => $clan->level,
        ];

        $row = Clan::query()->where('tag_normalized', $clan->tag->bare())->first()
            ?? Clan::query()->createOrFirst(['tag_normalized' => $clan->tag->bare()], $values);

        if ($row->api_synced_at !== null) {
            return $row->id;
        }

        $values['level'] ??= $row->level;

        // Loose `!=` for the badges: jsonb returns object keys in its own order, so a strict
        // comparison would see every read as a change.
        $changed = $row->name !== $values['name'] || $row->level !== $values['level'] || $row->badge_urls != $values['badge_urls'];

        if ($changed) {
            $row->fill($values)->save();
        }

        return $row->id;
    }
}
