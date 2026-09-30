<?php

namespace App\Domain\GameAssets\Exceptions;

use RuntimeException;

final class InvalidManifest extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct("Invalid asset manifest:\n- ".implode("\n- ", $problems));
    }
}
