<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Data\UploadStatusData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\UploadIntentService;
use App\Domain\Media\Services\UploadStatusService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Step 4 of the upload pipeline (specs/10 §3): the browser says the PUT finished. Idempotent: only
 * the call that moves the row out of `pending` queues processing.
 */
class CompleteUpload
{
    public function __construct(private readonly UploadStatusService $status) {}

    public function handle(Authenticatable $user, string $ulid): UploadStatusData
    {
        $media = Media::query()
            ->ownedBy($user->getAuthIdentifier())
            ->where('ulid', $ulid)
            ->firstOrFail();

        Gate::forUser($user)->authorize('complete', $media);

        // Two intents taken before either was completed: the second waits (UploadIntentService).
        if ($media->status === MediaStatus::Pending && $media->collection === MediaCollection::BaseVideo
            && Media::query()->videoInFlightFor($media->user_id)->whereKeyNot($media->id)->exists()) {
            throw ValidationException::withMessages(['collection' => UploadIntentService::VIDEO_IN_FLIGHT]);
        }

        $claimed = Media::query()
            ->whereKey($media->id)
            ->where('status', MediaStatus::Pending)
            ->update(['status' => MediaStatus::Uploaded]);

        if ($claimed === 1) {
            try {
                ProcessMediaJob::dispatch($media->id);
            } catch (Throwable $e) {
                // Hand the row back so the client's retry of complete can queue it again.
                Media::query()->whereKey($media->id)->update(['status' => MediaStatus::Pending]);

                throw $e;
            }
        }

        return $this->status->toData($media->refresh());
    }
}
