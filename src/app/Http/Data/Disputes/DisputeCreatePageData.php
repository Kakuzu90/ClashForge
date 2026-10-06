<?php

namespace App\Http\Data\Disputes;

use App\Domain\Media\Data\UploadCollectionData;
use App\Domain\PlayerAccounts\Data\CocPlayerPreviewData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Disputes/Create (P2-16): the tag, the game account from the attach flow's last lookup
 * when it is the same tag, why a dispute cannot be opened now in words (checked again on submit;
 * never the refusal code, see DisputeController::refusalText), and the evidence limits.
 */
#[TypeScript]
class DisputeCreatePageData extends Data
{
    public function __construct(
        public string $tag,
        public ?CocPlayerPreviewData $player,
        public ?string $refusal,
        public UploadCollectionData $evidenceUpload,
        public int $evidenceMax,
        public int $textMax,
        public int $responseDays,
    ) {}
}
