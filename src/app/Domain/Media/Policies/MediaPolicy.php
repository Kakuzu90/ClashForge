<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Models\Media;
use App\Support\Auth\HasAccountStanding;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Uploading needs a verified email (FR-AUTH-4) and an active account: restricted, suspended and
 * pending-deletion accounts cannot upload (specs/04 §1, §3). An upload is only ever visible to its
 * uploader until something attaches it.
 */
class MediaPolicy
{
    public function create(Authenticatable $user): bool
    {
        return $user instanceof MustVerifyEmail && $user->hasVerifiedEmail() && $this->mayUpload($user);
    }

    public function complete(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media) && $this->mayUpload($user);
    }

    public function view(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    private function mayUpload(Authenticatable $user): bool
    {
        return $user instanceof HasAccountStanding && $user->allowsContentWrites();
    }

    private function owns(Authenticatable $user, Media $media): bool
    {
        return $media->user_id === (int) $user->getAuthIdentifier();
    }
}
