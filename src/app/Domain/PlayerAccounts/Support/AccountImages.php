<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Services\MediaAttachmentService;
use App\Domain\PlayerAccounts\Models\CocAccount;

/**
 * Clears an account's custom images when the row stops being its holder's (P2-23 Q2): a detach, a
 * release (dispute, deletion, ban), a token supersede or an admin transfer. They are the previous
 * holder's uploads, so they are deleted once the caller's transaction commits (specs/10 §3).
 */
final class AccountImages
{
    public function __construct(private readonly MediaAttachmentService $media) {}

    public function releaseAll(CocAccount $row): void
    {
        if ($row->images_count === 0) {
            return;
        }

        $this->media->detachFrom($row, MediaCollection::AccountImage);
        $row->forceFill(['images_count' => 0])->save();
    }
}
