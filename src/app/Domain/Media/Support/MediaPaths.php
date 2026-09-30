<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use DateTimeInterface;

/**
 * Every storage key is built here from ids the server chose (specs/10 §2). Uploads can only ever
 * target quarantine/; derived files go under the collection's visibility prefix.
 */
final class MediaPaths
{
    public const QUARANTINE_PREFIX = 'quarantine/';

    public static function quarantine(string $ulid, string $extension, DateTimeInterface $at): string
    {
        return sprintf('%s%s/%s/%s/original.%s', self::QUARANTINE_PREFIX, $at->format('Y'), $at->format('m'), $ulid, $extension);
    }

    public static function variant(MediaCollection $collection, string $ulid, VariantName $variant, string $extension): string
    {
        return sprintf('%s/%s/%s/%s.%s', $collection->visibility()->prefix(), $collection->value, $ulid, $variant->value, $extension);
    }
}
