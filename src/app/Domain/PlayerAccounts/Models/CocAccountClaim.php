<?php

namespace App\Domain\PlayerAccounts\Models;

use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimMethod;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PlayerAccounts\CocAccountClaimFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to attach or verify a tag, successful or not (specs/07 `coc_account_claims`): the
 * forensic record behind disputes. Never holds the in-game token.
 *
 * @property int $id
 * @property int|null $coc_account_id
 * @property string $tag_normalized
 * @property int $user_id
 * @property ClaimMethod $method
 * @property ClaimStatus $status
 * @property ClaimFailureReason|null $failure_reason
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
#[UseFactory(CocAccountClaimFactory::class)]
class CocAccountClaim extends Model
{
    /** @use HasFactory<CocAccountClaimFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Nothing: every column is history the service writes with forceCreate, never input
     * (specs/11 "Mass assignment").
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'failure_reason' => null,
        'ip_hash' => null,
        'user_agent' => null,
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
            'method' => ClaimMethod::class,
            'status' => ClaimStatus::class,
            'failure_reason' => ClaimFailureReason::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
