<?php

namespace App\Domain\PlayerAccounts\Models;

use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use Carbon\CarbonImmutable;
use Database\Factories\PlayerAccounts\CocAccountSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account's progression at one moment (specs/07 `coc_account_snapshots`), written only when a
 * progression value changed. Append-only: a sync never edits an earlier row.
 *
 * @property int $id
 * @property int $coc_account_id
 * @property CarbonImmutable $captured_at
 * @property int|null $th_level
 * @property int|null $builder_hall_level
 * @property int|null $xp_level
 * @property int|null $trophies
 * @property int|null $best_trophies
 * @property int|null $war_stars
 * @property int|null $attack_wins
 * @property int|null $defense_wins
 * @property int|null $donations
 * @property string|null $clan_tag
 * @property int|null $league_id
 * @property list<array<string, mixed>> $heroes
 * @property list<array<string, mixed>> $troops
 * @property list<array<string, mixed>> $spells
 * @property list<array<string, mixed>> $hero_equipment
 * @property SnapshotSource $source
 */
#[UseFactory(CocAccountSnapshotFactory::class)]
class CocAccountSnapshot extends Model
{
    /** @use HasFactory<CocAccountSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'captured_at',
        'th_level',
        'builder_hall_level',
        'xp_level',
        'trophies',
        'best_trophies',
        'war_stars',
        'attack_wins',
        'defense_wins',
        'donations',
        'clan_tag',
        'league_id',
        'heroes',
        'troops',
        'spells',
        'hero_equipment',
        'source',
    ];

    /**
     * @return BelongsTo<CocAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CocAccount::class, 'coc_account_id');
    }

    protected function casts(): array
    {
        return [
            'captured_at' => 'immutable_datetime',
            'heroes' => 'array',
            'troops' => 'array',
            'spells' => 'array',
            'hero_equipment' => 'array',
            'source' => SnapshotSource::class,
        ];
    }
}
