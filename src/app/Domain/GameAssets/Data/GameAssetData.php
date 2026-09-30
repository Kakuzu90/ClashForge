<?php

namespace App\Domain\GameAssets\Data;

use App\Domain\GameAssets\Enums\GameAssetKind;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A resolved game asset for <GameAsset>. `url` is null whenever our placeholder must show instead:
 * category switched off, no active pack, unknown entry, or a remote URL off the allowlist.
 */
#[TypeScript]
class GameAssetData extends Data
{
    public function __construct(
        public GameAssetKind $kind,
        public ?string $url,
        /** Accessible name: the unit, Town Hall level, league or clan it identifies. */
        public string $alt,
        /** Short text for the placeholder, e.g. "12" or "AQ". */
        public string $short,
        public ?int $width,
        public ?int $height,
    ) {}
}
