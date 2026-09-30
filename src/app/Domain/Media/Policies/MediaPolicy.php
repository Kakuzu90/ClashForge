<?php

namespace App\Domain\Media\Policies;

use App\Domain\Media\Models\Media;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Uploading needs a verified email (FR-AUTH-4); an upload is only ever visible to its uploader
 * until something attaches it. Account status joins the check with P1-02.
 */
class MediaPolicy
{
    public function create(Authenticatable $user): bool
    {
        return $user instanceof MustVerifyEmail && $user->hasVerifiedEmail();
    }

    public function complete(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    public function view(Authenticatable $user, Media $media): bool
    {
        return $this->owns($user, $media);
    }

    private function owns(Authenticatable $user, Media $media): bool
    {
        return $media->user_id === (int) $user->getAuthIdentifier();
    }
}
