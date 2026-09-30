<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Models\User;
use Tests\Support\Media\InteractsWithMedia;

uses(InteractsWithMedia::class);

beforeEach(fn () => $this->fakeMediaStorage());

it('shows a ready upload with CDN variant URLs, smallest first', function () {
    $media = Media::factory()->ready()->create();

    foreach ([['full', 1200], ['thumb', 320], ['card', 800]] as [$name, $width]) {
        MediaVariant::factory()->for($media)->create([
            'variant' => $name,
            'path' => "public/base_screenshot/{$media->ulid}/{$name}.webp",
            'width' => $width,
            'height' => intdiv($width * 3, 4),
        ]);
    }

    $this->actingAs(User::query()->findOrFail($media->user_id))
        ->getJson("/uploads/{$media->ulid}")
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('finished', true)
        ->assertJsonPath('failureMessage', null)
        ->assertJsonPath('variants.0.name', 'thumb')
        ->assertJsonPath('variants.0.url', "https://cdn.test/public/base_screenshot/{$media->ulid}/thumb.webp")
        ->assertJsonPath('variants.0.width', 320)
        ->assertJsonPath('variants.0.height', 240)
        ->assertJsonPath('variants.2.name', 'full')
        ->assertJsonMissingPath('path')
        ->assertJsonMissingPath('user_id');
});

it('shows the failure message for a failed upload', function () {
    $media = Media::factory()->failed(MediaFailureReason::TooSmall)->create();

    $this->actingAs(User::query()->findOrFail($media->user_id))
        ->getJson("/uploads/{$media->ulid}")
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('finished', true)
        ->assertJsonPath('failureMessage', MediaFailureReason::TooSmall->label())
        ->assertJsonPath('variants', []);
});

it('signs private variant URLs instead of using the CDN', function () {
    $media = Media::factory()->ready()->collection(MediaCollection::Evidence)->create();
    MediaVariant::factory()->for($media)->create(['path' => "private/evidence/{$media->ulid}/card.webp"]);

    $url = $this->actingAs(User::query()->findOrFail($media->user_id))
        ->getJson("/uploads/{$media->ulid}")
        ->assertOk()
        ->json('variants.0.url');

    expect($url)->toStartWith('https://storage.test/private/evidence/')->toContain('signature=');
});

it('requires a signed-in, verified user', function () {
    $media = Media::factory()->create();

    $this->getJson("/uploads/{$media->ulid}")->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->getJson("/uploads/{$media->ulid}")->assertForbidden();
});
