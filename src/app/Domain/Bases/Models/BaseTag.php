<?php

namespace App\Domain\Bases\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Bases\BaseTagFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A base tag (specs/07 `base_tags`, FR-BASE-4): lowercase-kebab, created on first use. Suggested
 * tags are the ones in `bases.suggested_tags`; a blocked tag refuses a publish.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $usage_count
 * @property bool $is_suggested
 * @property bool $is_blocked
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(BaseTagFactory::class)]
class BaseTag extends Model
{
    /** @use HasFactory<BaseTagFactory> */
    use HasFactory;

    /**
     * Tags are created by the publish service; flags are staff tools (P3-06).
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_suggested' => 'boolean',
            'is_blocked' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
