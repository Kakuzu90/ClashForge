<?php

namespace Database\Factories\Media;

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Defaults to a fresh `pending` base screenshot, as the intent endpoint creates it.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ulid = Str::lower((string) Str::ulid());
        $now = Date::now();

        return [
            'ulid' => $ulid,
            'user_id' => User::factory(),
            'collection' => MediaCollection::BaseScreenshot,
            'kind' => MediaKind::Image,
            'disk' => config('media.disk'),
            'path' => sprintf('quarantine/%s/%s/%s/original.jpg', $now->format('Y'), $now->format('m'), $ulid),
            'original_filename' => 'screenshot.jpg',
            'size_bytes' => 250_000,
            'status' => MediaStatus::Pending,
            'visibility' => MediaVisibility::Public,
            'position' => 0,
            'expires_at' => $now->addHours((int) config('media.pending_expiry_hours')),
        ];
    }

    public function collection(MediaCollection $collection): static
    {
        return $this->state(fn () => [
            'collection' => $collection,
            'kind' => $collection->kind(),
            'visibility' => $collection->visibility(),
        ]);
    }

    public function uploaded(): static
    {
        return $this->state(fn () => ['status' => MediaStatus::Uploaded]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => MediaStatus::Processing]);
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Ready,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'width' => 1200,
            'height' => 900,
            'processed_at' => Date::now(),
        ]);
    }

    public function failed(MediaFailureReason $reason = MediaFailureReason::Undecodable): static
    {
        return $this->state(fn () => ['status' => MediaStatus::Failed, 'failure_reason' => $reason]);
    }

    public function quarantined(): static
    {
        return $this->state(fn () => ['status' => MediaStatus::Quarantined, 'failure_reason' => MediaFailureReason::Suspicious]);
    }
}
