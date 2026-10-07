<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
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

/**
 * A file of the collection's kind: an mp4 for videos, a jpeg otherwise.
 */
function intentFor(MediaCollection $collection, array $overrides = []): array
{
    $kind = $collection->kind() === MediaKind::Video ? ['filename' => 'replay.mp4', 'mime' => 'video/mp4'] : [];

    return intentPayload(['collection' => $collection->value, ...$kind, ...$overrides]);
}

function videoUploader(): User
{
    $user = User::factory()->create();
    $user->forceFill(['verified_accounts_count' => 1])->save();

    return $user;
}

it('accepts each uploadable collection up to its own size limit', function () {
    $user = videoUploader();

    foreach (MediaCollection::uploadable() as $collection) {
        $this->actingAs($user)
            ->postJson('/uploads/intent', intentFor($collection, ['size' => $collection->maxBytes()]))
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/uploads/intent', intentFor($collection, ['size' => $collection->maxBytes() + 1]))
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

// P3-02: replay videos (specs/10 §3, §4; specs/24 R6).
describe('replay videos', function () {
    it('creates a pending video row under an .mp4 quarantine key', function () {
        $this->actingAs(videoUploader())
            ->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo, ['size' => 50_000_000]))
            ->assertCreated()
            ->assertJsonPath('uploadHeaders.Content-Type', 'video/mp4')
            ->assertJsonPath('maxSize', MediaCollection::BaseVideo->maxBytes());

        $media = Media::query()->sole();

        expect($media->kind)->toBe(MediaKind::Video)
            ->and($media->path)->toBe("quarantine/2026/09/{$media->ulid}/original.mp4");
    });

    it('takes MP4 only', function (array $payload) {
        $this->actingAs(videoUploader())
            ->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo, $payload))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mime' => 'This file type is not supported. Use an MP4 video.']);
    })->with([
        'mov' => [['filename' => 'replay.mov', 'mime' => 'video/quicktime']],
        'webm' => [['filename' => 'replay.webm', 'mime' => 'video/webm']],
        'mp4 name, image type' => [['mime' => 'image/jpeg']],
        'image as video' => [['filename' => 'replay.jpg', 'mime' => 'image/jpeg']],
    ]);

    it('keeps the image message for image collections', function () {
        $this->actingAs(User::factory()->create())
            ->postJson('/uploads/intent', intentPayload(['filename' => 'replay.mp4', 'mime' => 'video/mp4']))
            ->assertJsonValidationErrors(['mime' => 'This file type is not supported. Use a JPEG, PNG or WebP image.']);
    });

    it('needs a verified CoC account, as publishing does', function () {
        $this->actingAs(User::factory()->create())
            ->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))
            ->assertForbidden();

        expect(Media::query()->count())->toBe(0);
    });

    it('limits video intents per user per day, apart from images', function () {
        $user = videoUploader();
        $max = (int) config('media.rate_limits.video_intents_per_day');
        config(['media.rate_limits.intents_per_hour' => $max + 10]);

        for ($i = 0; $i < $max; $i++) {
            $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated();
        }

        $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['collection' => "You can upload {$max} videos a day. Try again tomorrow."]);
        $this->actingAs($user)->postJson('/uploads/intent', intentPayload())->assertCreated();
        $this->actingAs(videoUploader())->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated();

        $this->travel(1)->days();
        $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated();
    });

    it('gives the day slot back when storage cannot presign', function () {
        $user = videoUploader();
        config(['media.rate_limits.video_intents_per_day' => 1]);
        $this->mediaDisk()->buildTemporaryUploadUrlsUsing(fn () => throw new RuntimeException('endpoint down'));

        $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertServiceUnavailable();

        $this->fakeMediaStorage();
        $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated();
    });

    it('takes one video at a time: the next waits until the last is processed', function () {
        $user = videoUploader();
        $first = $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated()->json('mediaUlid');
        $second = $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated()->json('mediaUlid');

        Queue::fake();
        $this->actingAs($user)->postJson("/uploads/{$first}/complete")->assertAccepted();

        // Taken before the first was completed: its complete waits.
        $this->actingAs($user)->postJson("/uploads/{$second}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['collection' => 'Your last video is still processing. Upload the next one when it is done.']);
        $this->actingAs($user)->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertUnprocessable();
        expect(Media::query()->where('ulid', $second)->sole()->status)->toBe(MediaStatus::Pending);

        // Images and other users are not held up.
        $this->actingAs($user)->postJson('/uploads/intent', intentPayload())->assertCreated();
        $this->actingAs(videoUploader())->postJson('/uploads/intent', intentFor(MediaCollection::BaseVideo))->assertCreated();

        Media::query()->where('ulid', $first)->update(['status' => MediaStatus::Ready]);
        $this->actingAs($user)->postJson("/uploads/{$second}/complete")->assertAccepted();
    });
});
