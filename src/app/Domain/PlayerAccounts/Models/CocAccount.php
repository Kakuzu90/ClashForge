<?php

namespace App\Domain\PlayerAccounts\Models;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PlayerAccounts\CocAccountFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A CoC player tag attached to a website user (specs/07 `coc_accounts`). Rows are per user: several
 * users may hold unverified rows for one tag, at most one row per tag is verified (specs/08 §3.1).
 * Status, ownership and the featured flag change only through PlayerAccounts services.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $user_id
 * @property string $tag
 * @property string $tag_normalized
 * @property CocAccountStatus $status
 * @property CarbonImmutable|null $verified_at
 * @property VerificationMethod|null $verification_method
 * @property string $ign
 * @property int|null $th_level
 * @property int|null $builder_hall_level
 * @property int|null $xp_level
 * @property int|null $trophies
 * @property int|null $best_trophies
 * @property int|null $builder_trophies
 * @property int|null $war_stars
 * @property int|null $attack_wins
 * @property int|null $defense_wins
 * @property int|null $donations
 * @property int|null $donations_received
 * @property string|null $clan_tag
 * @property string|null $clan_role
 * @property int|null $league_id
 * @property string|null $league_name
 * @property string|null $league_icon_url
 * @property list<array<string, mixed>> $troops
 * @property list<array<string, mixed>> $heroes
 * @property list<array<string, mixed>> $spells
 * @property list<array<string, mixed>> $hero_equipment
 * @property list<array<string, mixed>> $achievements
 * @property list<array<string, mixed>> $labels
 * @property array<string, mixed>|null $raw_payload
 * @property CarbonImmutable|null $api_synced_at
 * @property int $api_sync_failures
 * @property bool $is_featured
 * @property int $images_count
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(CocAccountFactory::class)]
class CocAccount extends Model
{
    /** @use HasFactory<CocAccountFactory> */
    use HasFactory, HasUlids;

    /**
     * Game data only. Identity and ownership columns (`tag`, `tag_normalized`, `user_id`, `status`,
     * `verified_at`, `verification_method`, `is_featured`) are set with forceFill by the services
     * (specs/11 "Mass assignment").
     *
     * @var list<string>
     */
    protected $fillable = [
        'ign',
        'th_level',
        'builder_hall_level',
        'xp_level',
        'trophies',
        'best_trophies',
        'builder_trophies',
        'war_stars',
        'attack_wins',
        'defense_wins',
        'donations',
        'donations_received',
        'clan_tag',
        'clan_role',
        'league_id',
        'league_name',
        'league_icon_url',
        'troops',
        'heroes',
        'spells',
        'hero_equipment',
        'achievements',
        'labels',
        'raw_payload',
        'api_synced_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'troops' => '[]',
        'heroes' => '[]',
        'spells' => '[]',
        'hero_equipment' => '[]',
        'achievements' => '[]',
        'labels' => '[]',
        'api_sync_failures' => 0,
        'is_featured' => false,
        'images_count' => 0,
        'verified_at' => null,
        'verification_method' => null,
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CocAccountClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(CocAccountClaim::class);
    }

    /**
     * @return HasMany<CocAccountSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(CocAccountSnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CocAccountStatus::class,
            'verification_method' => VerificationMethod::class,
            'verified_at' => 'immutable_datetime',
            'troops' => 'array',
            'heroes' => 'array',
            'spells' => 'array',
            'hero_equipment' => 'array',
            'achievements' => 'array',
            'labels' => 'array',
            'raw_payload' => 'array',
            'api_synced_at' => 'immutable_datetime',
            'api_sync_failures' => 'integer',
            'is_featured' => 'boolean',
            'images_count' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
