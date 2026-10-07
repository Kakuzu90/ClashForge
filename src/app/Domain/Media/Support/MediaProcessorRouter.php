<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Contracts\MediaProcessor;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaKind;

/**
 * The bound MediaProcessor: images and videos each go to their own processor, by the collection's
 * kind. A hosted transcoder replaces the video side only (specs/10 §6).
 */
final class MediaProcessorRouter implements MediaProcessor
{
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly VideoProcessor $videos,
    ) {}

    public function process(string $localPath, MediaCollection $collection): ProcessedMedia
    {
        return match ($collection->kind()) {
            MediaKind::Image => $this->images->process($localPath, $collection),
            MediaKind::Video => $this->videos->process($localPath, $collection),
        };
    }
}
