<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Contracts\MediaScanner;

final class NullMediaScanner implements MediaScanner
{
    public function isClean(string $localPath): bool
    {
        return true;
    }
}
