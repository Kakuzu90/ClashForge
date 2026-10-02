<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Binds uploaded media to its parent when the parent's form is submitted (specs/10 §3
 * "Attachment"). Runs inside the caller's transaction and opens none of its own.
 */
class MediaAttachmentService
{
    /**
     * Returns the media id for the parent's foreign key. Another user's ULID is a 404 before any
     * other check, so it reveals nothing.
     *
     * @throws ValidationException when the media is in the wrong collection or not usable yet
     */
    public function attach(Authenticatable $user, string $ulid, MediaCollection $collection, Model $attachable, int $position = 0, string $field = 'media'): int
    {
        $media = Media::query()
            ->ownedBy($user->getAuthIdentifier())
            ->where('ulid', $ulid)
            ->lockForUpdate()
            ->firstOrFail();

        if ($media->collection !== $collection) {
            throw ValidationException::withMessages([$field => 'This upload was made for something else. Upload the file again.']);
        }

        if (! in_array($media->status, [MediaStatus::Ready, MediaStatus::Processing], true)) {
            throw ValidationException::withMessages([$field => 'This upload is not ready to use. Upload the file again.']);
        }

        // Media already belonging to another parent stays there: moving it would change that
        // record (evidence of a decided dispute, specs/13 §5).
        if ($media->attachable_id !== null && ($media->attachable_type !== $attachable->getMorphClass() || $media->attachable_id !== $attachable->getKey())) {
            throw ValidationException::withMessages([$field => 'This upload is already in use. Upload the file again.']);
        }

        $media->forceFill([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'position' => $position,
            'expires_at' => null,
        ])->save();

        return $media->id;
    }

    /**
     * Deletes media its owner replaced or removed (an avatar, specs/10 §8). The row is claimed as
     * `deleting`, which attachment refuses, and its objects are deleted once the caller's
     * transaction commits. No recovery window: someone who removes a photo expects it gone.
     * Quarantined media is never released; it stays for review (specs/10 §9).
     */
    public function release(int $mediaId): void
    {
        $claimed = Media::query()
            ->whereKey($mediaId)
            ->where('status', '!=', MediaStatus::Quarantined)
            ->update([
                'attachable_type' => null,
                'attachable_id' => null,
                'status' => MediaStatus::Deleting,
            ]);

        if ($claimed === 1) {
            DeleteMediaObjectsJob::dispatch([$mediaId])->afterCommit();
        }
    }
}
