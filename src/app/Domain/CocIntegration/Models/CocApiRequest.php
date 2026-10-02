<?php

namespace App\Domain\CocIntegration\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CocIntegration\CocApiRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One outbound API call or cache hit (specs/07 `coc_api_requests`). Never a key or a token.
 *
 * @property int $id
 * @property string $endpoint
 * @property string|null $tag
 * @property int|null $status_code
 * @property int $duration_ms
 * @property bool $was_cached
 * @property string|null $error_code
 * @property CarbonImmutable $created_at
 */
#[UseFactory(CocApiRequestFactory::class)]
class CocApiRequest extends Model
{
    /** @use HasFactory<CocApiRequestFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'endpoint',
        'tag',
        'status_code',
        'duration_ms',
        'was_cached',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'was_cached' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
