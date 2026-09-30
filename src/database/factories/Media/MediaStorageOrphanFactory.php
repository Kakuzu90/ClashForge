<?php

namespace Database\Factories\Media;

use App\Domain\Media\Models\MediaStorageOrphan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * A key first seen by the previous weekly reconcile run.
 *
 * @extends Factory<MediaStorageOrphan>
 */
class MediaStorageOrphanFactory extends Factory
{
    protected $model = MediaStorageOrphan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seen = Date::now()->subWeek();

        return [
            'path' => 'public/base_screenshot/'.Str::lower((string) Str::ulid()).'/card.webp',
            'first_seen_at' => $seen,
            'last_seen_at' => $seen,
        ];
    }
}
