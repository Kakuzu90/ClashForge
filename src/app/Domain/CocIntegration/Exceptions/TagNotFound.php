<?php

namespace App\Domain\CocIntegration\Exceptions;

use RuntimeException;

/**
 * 404 `notFound`: the API knows no player or clan with this tag.
 */
final class TagNotFound extends RuntimeException
{
    public function __construct(public readonly string $tag)
    {
        parent::__construct("No CoC player or clan with tag {$tag}");
    }
}
