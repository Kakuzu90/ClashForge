<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Data\CreateUploadIntentData;
use App\Domain\Media\Data\UploadTicketData;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Exceptions\StorageUnavailable;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Support\MediaPaths;
use App\Domain\Media\Support\OriginalFilename;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Step 1–2 of the upload pipeline (specs/10 §3): a `pending` row and a presigned PUT to a
 * quarantine key the client cannot choose.
 */
class UploadIntentService
{
    public function __construct(private readonly MediaUrlResolver $urls) {}

    public function create(Authenticatable $user, CreateUploadIntentData $data): UploadTicketData
    {
        Gate::forUser($user)->authorize('create', [Media::class, $data->collection]);

        $now = Date::now();
        $ttl = (int) config('media.intent_ttl');
        $ulid = Str::lower((string) Str::ulid());
        $filename = OriginalFilename::from($data->filename);
        $extension = $this->storedExtension($data->mimeType);
        $path = MediaPaths::quarantine($ulid, $extension, $now);

        try {
            $upload = $this->urls->presignUpload($path, $data->mimeType, $now->addSeconds($ttl));
        } catch (Throwable $e) {
            report($e);

            throw new StorageUnavailable('Could not presign an upload URL.', previous: $e);
        }

        $media = new Media([
            'collection' => $data->collection,
            'original_filename' => $filename->value,
            'size_bytes' => $data->sizeBytes,
        ]);
        $media->forceFill([
            'ulid' => $ulid,
            'user_id' => $user->getAuthIdentifier(),
            'kind' => $data->collection->kind(),
            'visibility' => $data->collection->visibility(),
            'disk' => config('media.disk'),
            'path' => $path,
            'status' => MediaStatus::Pending,
            'expires_at' => $now->addHours((int) config('media.pending_expiry_hours')),
        ])->save();

        return new UploadTicketData(
            mediaUlid: $ulid,
            uploadUrl: $upload['url'],
            uploadMethod: 'PUT',
            uploadHeaders: $upload['headers'],
            expiresIn: $ttl,
            maxSize: $data->collection->maxBytes(),
        );
    }

    /**
     * The quarantine key's extension comes from the declared type, never from the filename.
     */
    private function storedExtension(string $mimeType): string
    {
        /** @var array<string, string> $mimes */
        $mimes = config('media.image.mimes');

        return $mimes[$mimeType] ?? 'bin';
    }
}
