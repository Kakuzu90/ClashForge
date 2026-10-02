<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Data\MediaVariantData;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\VariantName;
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
     * One rendition's URL for many `ready` media in one query, keyed by media id; media that is not
     * ready or lacks the variant is left out.
     *
     * @param  list<int>  $mediaIds
     * @return array<int, string>
     */
    public function readyVariantUrls(array $mediaIds, VariantName $variant): array
    {
        if ($mediaIds === []) {
            return [];
        }

        return MediaVariant::query()
            ->join('media', 'media.id', '=', 'media_variants.media_id')
            ->whereIn('media.id', $mediaIds)
            ->where('media.status', MediaStatus::Ready)
            ->where('media_variants.variant', $variant)
            ->get(['media_variants.media_id', 'media_variants.path', 'media.visibility'])
            ->mapWithKeys(fn (MediaVariant $row): array => [
                (int) $row->media_id => $this->urls->url($row->path, MediaVisibility::from((string) $row->getAttribute('visibility'))),
            ])
            ->all();
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
