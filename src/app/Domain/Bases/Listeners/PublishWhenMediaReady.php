<?php

namespace App\Domain\Bases\Listeners;

use App\Domain\Bases\Enums\BaseStatus;
use App\Domain\Bases\Models\BaseLayout;
use App\Domain\Bases\Services\PublishBaseService;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Events\MediaReady;

/**
 * A screenshot or video finished: the uploader's bases still `processing` publish once all their
 * media are ready (FR-BASE-5, specs/10 §3). The event names the uploader, not the parent, and an
 * author has few bases waiting at once, so each is checked.
 */
class PublishWhenMediaReady
{
    public function __construct(private readonly PublishBaseService $publisher) {}

    public function handle(MediaReady $event): void
    {
        if (! in_array($event->collection, [MediaCollection::BaseScreenshot, MediaCollection::BaseVideo], true)) {
            return;
        }

        $waiting = BaseLayout::query()->where('user_id', $event->userId)->where('status', BaseStatus::Processing)->orderBy('id')->pluck('id');

        foreach ($waiting as $id) {
            $this->publisher->publishIfReady((int) $id);
        }
    }
}
