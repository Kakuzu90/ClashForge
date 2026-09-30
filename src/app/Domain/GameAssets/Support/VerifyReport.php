<?php

namespace App\Domain\GameAssets\Support;

final readonly class VerifyReport
{
    /**
     * @param  list<string>  $missing  in the manifest, not in the bucket
     * @param  list<string>  $extra  in the bucket, not in the manifest
     * @param  list<string>  $altered  in both, but the bytes no longer match
     */
    public function __construct(
        public string $version,
        public array $missing,
        public array $extra,
        public array $altered,
    ) {}

    public function isClean(): bool
    {
        return $this->missing === [] && $this->extra === [] && $this->altered === [];
    }
}
