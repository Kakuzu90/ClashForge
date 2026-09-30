<?php

namespace App\Domain\Media\Contracts;

/**
 * Optional malware scan of a raw upload (specs/10 §4). No-op at launch; ClamAV arrives behind a
 * flag with report evidence uploads.
 */
interface MediaScanner
{
    public function isClean(string $localPath): bool;
}
