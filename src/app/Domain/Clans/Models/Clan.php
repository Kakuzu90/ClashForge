<?php

namespace App\Domain\Clans\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Clans\ClanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A clan referenced by an account or, later, a recruitment post (specs/07 `clans`). Written from
 * API data only. Until the clan sync (P4-01) the row is a stub: tag, name, badge and level from a
 * member's player payload, the rest null.
 *
 * @property int $id
 * @property string $tag
 * @property string $tag_normalized
 * @property string $name
 * @property string|null $description
 * @property array<string, string> $badge_urls
 * @property int|null $level
 * @property int|null $points
 * @property int|null $builder_points
 * @property int|null $capital_points
 * @property string|null $war_frequency
 * @property int|null $war_win_streak
 * @property int|null $war_wins
 * @property int|null $war_losses
 * @property int|null $war_ties
 * @property bool|null $is_war_log_public
 * @property int|null $war_league_id
 * @property string|null $war_league_name
 * @property int|null $capital_hall_level
 * @property int|null $members_count
 * @property int|null $required_th_level
 * @property int|null $required_trophies
 * @property string|null $type
 * @property int|null $location_id
 * @property string|null $location_name
 * @property string|null $country_code
 * @property CarbonImmutable|null $api_synced_at
 * @property int $api_sync_failures
 * @property string|null $tracked_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(ClanFactory::class)]
class Clan extends Model
{
    /** @use HasFactory<ClanFactory> */
    use HasFactory;

    /**
     * API data only. `tracked_reason` and the sync counters belong to the clan sync (P4-01).
     *
     * @var list<string>
     */
    protected $fillable = [
        'tag',
        'tag_normalized',
        'name',
        'description',
        'badge_urls',
        'level',
        'points',
        'builder_points',
        'capital_points',
        'war_frequency',
        'war_win_streak',
        'war_wins',
        'war_losses',
        'war_ties',
        'is_war_log_public',
        'war_league_id',
        'war_league_name',
        'capital_hall_level',
        'members_count',
        'required_th_level',
        'required_trophies',
        'type',
        'location_id',
        'location_name',
        'country_code',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'badge_urls' => '{}',
        'api_sync_failures' => 0,
    ];

    protected function casts(): array
    {
        return [
            'badge_urls' => 'array',
            'level' => 'integer',
            'is_war_log_public' => 'boolean',
            'api_synced_at' => 'immutable_datetime',
            'api_sync_failures' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
