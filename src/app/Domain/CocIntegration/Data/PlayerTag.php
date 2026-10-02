<?php

namespace App\Domain\CocIntegration\Data;

final class PlayerTag extends CocTag
{
    protected static function noun(): string
    {
        return 'player';
    }
}
