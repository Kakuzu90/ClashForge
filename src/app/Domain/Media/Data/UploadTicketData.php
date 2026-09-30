<?php

namespace App\Domain\Media\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response of POST /uploads/intent: where to PUT the bytes (specs/10 §3 step 2).
 */
#[TypeScript]
class UploadTicketData extends Data
{
    /**
     * @param  array<string, string>  $uploadHeaders
     */
    public function __construct(
        public string $mediaUlid,
        public string $uploadUrl,
        public string $uploadMethod,
        public array $uploadHeaders,
        public int $expiresIn,
        public int $maxSize,
    ) {}
}
