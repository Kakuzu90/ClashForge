<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Support\Auth\HasAccountStanding;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Uploading needs a verified email (FR-AUTH-4) and an account whose status allows the write: an
 * avatar is a profile write, open to restricted accounts; every other collection is content,
 * active accounts only (specs/04 §1, §3). An upload is only ever visible to its uploader until
 * something attaches it.
 */
class MediaPolicy
{
    public function create(Authenticatable $user, ?MediaCollection $collection = null): bool
    {
        return $user instanceof MustVerifyEmail && $user->hasVerifiedEmail() && $this->mayUpload($user, $collection);
    }

    public function complete(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media) && $this->mayUpload($user, $media->collection);
    }

    public function view(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    private function mayUpload(Authenticatable $user, ?MediaCollection $collection): bool
    {
        if (! $user instanceof HasAccountStanding) {
            return false;
        }

        return $collection === MediaCollection::Avatar ? $user->allowsAccountWrites() : $user->allowsContentWrites();
    }

    private function owns(Authenticatable $user, Media $media): bool
    {
        return $media->user_id === (int) $user->getAuthIdentifier();
    }
}
