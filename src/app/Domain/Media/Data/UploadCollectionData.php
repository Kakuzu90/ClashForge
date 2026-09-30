<?php

namespace App\Domain\Media\Data;

use App\Domain\Media\Enums\MediaCollection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A collection the upload UI may offer, with the limits the server enforces.
 */
#[TypeScript]
class UploadCollectionData extends Data
{
    public function __construct(
        public MediaCollection $value,
        public string $label,
        public int $maxBytes,
        public string $accept,
        public string $typesLabel,
    ) {}

    public static function fromCollection(MediaCollection $collection): self
    {
        $kind = $collection->kind()->value;

        /** @var array<string, string> $mimes */
        $mimes = config("media.{$kind}.mimes", []);
        /** @var list<string> $extensions */
        $extensions = config("media.{$kind}.extensions", []);

        return new self(
            value: $collection,
            label: $collection->label(),
            maxBytes: $collection->maxBytes(),
            accept: implode(',', [...array_keys($mimes), ...array_map(fn (string $ext): string => '.'.$ext, $extensions)]),
            typesLabel: (string) config("media.{$kind}.types_label"),
        );
    }
}
