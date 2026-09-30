<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\Support\Media\InteractsWithMedia;

uses(InteractsWithMedia::class);

beforeEach(function () {
    $this->fakeMediaStorage();
    Date::setTestNow('2026-09-30 12:00:00');
});

function intentPayload(array $overrides = []): array
{
    return array_merge([
        'collection' => 'base_screenshot',
        'filename' => 'war base.jpg',
        'size' => 250_000,
        'mime' => 'image/jpeg',
    ], $overrides);
}

it('creates a pending row and returns a presigned PUT into quarantine', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/uploads/intent', intentPayload())
        ->assertCreated()
        ->assertJsonPath('uploadMethod', 'PUT')
        ->assertJsonPath('uploadHeaders.Content-Type', 'image/jpeg')
        ->assertJsonPath('expiresIn', config('media.intent_ttl'))
        ->assertJsonPath('maxSize', MediaCollection::BaseScreenshot->maxBytes());

    $media = Media::query()->sole();

    expect($response->json('mediaUlid'))->toBe($media->ulid)
        ->and($response->json('uploadUrl'))->toContain($media->path)
        ->and($media->user_id)->toBe($user->id)
        ->and($media->status)->toBe(MediaStatus::Pending)
        ->and($media->path)->toBe("quarantine/2026/09/{$media->ulid}/original.jpg")
        ->and($media->original_filename)->toBe('war base.jpg')
        ->and($media->size_bytes)->toBe(250_000)
        ->and($media->mime_type)->toBeNull()
        ->and($media->expires_at?->toDateTimeString())->toBe(Date::now()->addHours((int) config('media.pending_expiry_hours'))->toDateTimeString());
});

it('accepts each uploadable collection up to its own size limit', function () {
    $user = User::factory()->create();

    foreach (MediaCollection::uploadable() as $collection) {
        $this->actingAs($user)
            ->postJson('/uploads/intent', intentPayload(['collection' => $collection->value, 'size' => $collection->maxBytes()]))
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/uploads/intent', intentPayload(['collection' => $collection->value, 'size' => $collection->maxBytes() + 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('size');
    }
});

it('rejects invalid declarations', function (array $payload, string $field) {
    $this->actingAs(User::factory()->create())
        ->postJson('/uploads/intent', intentPayload($payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Media::query()->count())->toBe(0);
})->with([
    'missing collection' => [['collection' => null], 'collection'],
    'unknown collection' => [['collection' => 'banner'], 'collection'],
    'video before P3-02' => [['collection' => 'base_video', 'mime' => 'video/mp4', 'filename' => 'a.mp4'], 'collection'],
    'evidence before P3-06' => [['collection' => 'evidence'], 'collection'],
    'svg type' => [['mime' => 'image/svg+xml', 'filename' => 'a.svg'], 'mime'],
    'svg extension' => [['filename' => 'a.svg'], 'mime'],
    'gif' => [['mime' => 'image/gif', 'filename' => 'a.gif'], 'mime'],
    'executable extension' => [['filename' => 'a.exe'], 'mime'],
    'no extension' => [['filename' => 'screenshot'], 'mime'],
    'zero bytes' => [['size' => 0], 'size'],
    'missing filename' => [['filename' => ''], 'filename'],
]);

it('requires a signed-in user', function () {
    $this->postJson('/uploads/intent', intentPayload())->assertUnauthorized();
});

it('requires a verified email', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/uploads/intent', intentPayload())
        ->assertForbidden();

    expect(Media::query()->count())->toBe(0);
});

it('limits intents per user per hour', function () {
    $user = User::factory()->create();
    $limit = (int) config('media.rate_limits.intents_per_hour');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($user)->postJson('/uploads/intent', intentPayload())->assertCreated();
    }

    $this->actingAs($user)->postJson('/uploads/intent', intentPayload())->assertTooManyRequests();
    $this->actingAs(User::factory()->create())->postJson('/uploads/intent', intentPayload())->assertCreated();
});

it('reports uploads as unavailable when storage cannot presign', function () {
    $this->mediaDisk()->buildTemporaryUploadUrlsUsing(fn () => throw new RuntimeException('endpoint down'));

    $this->actingAs(User::factory()->create())
        ->postJson('/uploads/intent', intentPayload())
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Uploads are temporarily unavailable. Try again in a few minutes.');

    expect(Media::query()->count())->toBe(0);
});
