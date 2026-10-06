<?php

namespace App\Http\Data\Accounts;

use App\Domain\PlayerAccounts\Data\AttachResultData;
use App\Domain\PlayerAccounts\Data\VerifyResultData;
use App\Domain\PlayerAccounts\Enums\AttachBlock;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Accounts/Attach (step 1, specs/18 §6): the tag in the field, the last lookup of that
 * tag, the answer to a token sent from the conflict card, why attaching is closed, if it is, and the
 * viewer's running dispute over the tag, for the conflict card's link to it (P2-16).
 */
#[TypeScript]
class AttachPageData extends Data
{
    public function __construct(
        public ?string $tag,
        public ?AttachResultData $preview,
        public ?VerifyResultData $verifyResult,
        public ?AttachBlock $block,
        public ?string $disputeUlid = null,
    ) {}
}
