<?php

namespace App\Domain\CocIntegration\Data;

final class ClanTag extends CocTag
{
    protected static function noun(): string
    {
        return 'clan';
    }
}
