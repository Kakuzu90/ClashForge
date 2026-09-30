<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Data\MediaVariantData;
use App\Domain\Media\Data\UploadStatusData;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class UploadStatusService
{
    public function __construct(private readonly MediaUrlResolver $urls) {}

    /**
     * Scoped to the owner first, so another user's ULID is a 404 before the policy runs (specs/04 §3).
     */
    public function forOwner(Authenticatable $user, string $ulid): UploadStatusData
    {
        $media = Media::query()
            ->ownedBy($user->getAuthIdentifier())
            ->where('ulid', $ulid)
            ->with('variants')
            ->firstOrFail();

        Gate::forUser($user)->authorize('view', $media);

        return $this->toData($media);
    }

    public function toData(Media $media): UploadStatusData
    {
        $variants = $media->relationLoaded('variants') ? $media->variants : $media->variants()->get();

        return new UploadStatusData(
            mediaUlid: $media->ulid,
            status: $media->status,
            finished: $media->status->isTerminal(),
            failureMessage: $media->failure_reason?->label(),
            width: $media->width,
            height: $media->height,
            variants: array_values($variants
                ->sortBy(fn (MediaVariant $variant): int => $variant->width)
                ->map(fn (MediaVariant $variant): MediaVariantData => new MediaVariantData(
                    name: $variant->variant,
                    url: $this->urls->url($variant->path, $media->visibility),
                    width: $variant->width,
                    height: $variant->height,
                ))
                ->all()),
        );
    }
}
