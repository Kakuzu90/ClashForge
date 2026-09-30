<?php

use App\Domain\Media\Enums\MediaStatus;

it('treats ready, failed, quarantined and deleting as finished', function () {
    $finished = array_values(array_filter(MediaStatus::cases(), fn (MediaStatus $s) => $s->isTerminal()));

    expect($finished)->toBe([MediaStatus::Ready, MediaStatus::Failed, MediaStatus::Quarantined, MediaStatus::Deleting]);
});
