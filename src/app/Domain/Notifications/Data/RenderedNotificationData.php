<?php

namespace App\Domain\Notifications\Data;

use Spatie\LaravelData\Data;

/**
 * A notification's words and link, rendered from its type and parameters. `url` is a path on this
 * site, or null when there is nowhere to go; `actionLabel` is the email button's text for it.
 */
class RenderedNotificationData extends Data
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url,
        public ?string $actionLabel = null,
    ) {}
}
