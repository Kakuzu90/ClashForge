<?php

namespace App\Domain\Media\Models;

use App\Domain\Media\Enums\VariantName;
use Carbon\CarbonImmutable;
use Database\Factories\Media\MediaVariantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A derived rendition of a media row (specs/07 `media_variants`).
 *
 * @property int $id
 * @property int $media_id
 * @property VariantName $variant
 * @property string $path
 * @property int $width
 * @property int $height
 * @property int $size_bytes
 * @property string $mime_type
 * @property CarbonImmutable|null $created_at
 */
#[UseFactory(MediaVariantFactory::class)]
class MediaVariant extends Model
{
    /** @use HasFactory<MediaVariantFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'variant',
        'path',
        'width',
        'height',
        'size_bytes',
        'mime_type',
    ];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    protected function casts(): array
    {
        return [
            'variant' => VariantName::class,
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
