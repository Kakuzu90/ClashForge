<?php

namespace Database\Factories\Media;

use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaVariant>
 */
class MediaVariantFactory extends Factory
{
    protected $model = MediaVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_id' => Media::factory()->ready(),
            'variant' => VariantName::Card,
            'path' => fn (array $attributes) => 'public/base_screenshot/'.Media::query()->whereKey($attributes['media_id'])->value('ulid').'/card.webp',
            'width' => 800,
            'height' => 600,
            'size_bytes' => 60_000,
            'mime_type' => 'image/webp',
        ];
    }
}
