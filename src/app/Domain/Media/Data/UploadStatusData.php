<?php

namespace App\Domain\Media\Data;

use App\Domain\Media\Enums\MediaStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An upload as its owner sees it while polling GET /uploads/{ulid}.
 */
#[TypeScript]
class UploadStatusData extends Data
{
    /**
     * @param  list<MediaVariantData>  $variants
     */
    public function __construct(
        public string $mediaUlid,
        public MediaStatus $status,
        public bool $finished,
        public ?string $failureMessage,
        public ?int $width,
        public ?int $height,
        public array $variants,
    ) {}
}
