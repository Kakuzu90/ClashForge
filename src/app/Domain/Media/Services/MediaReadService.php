<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Data\MediaVariantData;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;

/**
 * Read access to attached media for other modules, which hold only the media id (specs/05 §2).
 */
class MediaReadService
{
    public function __construct(private readonly MediaUrlResolver $urls) {}

    public function status(int $mediaId): ?MediaStatus
    {
        return Media::query()->whereKey($mediaId)->first(['id', 'status'])?->status;
    }

    /**
     * The renditions of `ready` media, keyed by variant name; empty for anything else.
     *
     * @return array<string, MediaVariantData>
     */
    public function readyVariants(int $mediaId): array
    {
        $media = Media::query()->whereKey($mediaId)->where('status', MediaStatus::Ready)->with('variants')->first();

        if ($media === null) {
            return [];
        }

        return $media->variants
            ->mapWithKeys(fn (MediaVariant $variant): array => [$variant->variant->value => new MediaVariantData(
                name: $variant->variant,
                url: $this->urls->url($variant->path, $media->visibility),
                width: $variant->width,
                height: $variant->height,
            )])
            ->all();
    }
}
