<?php

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Media\InteractsWithMedia;

uses(InteractsWithMedia::class);

beforeEach(function () {
    $this->fakeMediaStorage();
    Queue::fake();
});

it('marks a pending upload as uploaded and queues processing on the media queue', function () {
    $media = Media::factory()->create();

    $this->actingAs(User::query()->findOrFail($media->user_id))
        ->postJson("/uploads/{$media->ulid}/complete")
        ->assertAccepted()
        ->assertJsonPath('mediaUlid', $media->ulid)
        ->assertJsonPath('status', 'uploaded')
        ->assertJsonPath('finished', false);

    expect($media->refresh()->status)->toBe(MediaStatus::Uploaded);
    Queue::assertPushedOn(config('media.processing.queue'), ProcessMediaJob::class, fn (ProcessMediaJob $job) => $job->mediaId === $media->id);
});

it('is idempotent: a repeated call queues nothing more', function () {
    $media = Media::factory()->create();
    $owner = User::query()->findOrFail($media->user_id);

    $this->actingAs($owner)->postJson("/uploads/{$media->ulid}/complete")->assertAccepted();
    $this->actingAs($owner)->postJson("/uploads/{$media->ulid}/complete")->assertAccepted();

    Queue::assertPushed(ProcessMediaJob::class, 1);
});

it('leaves rows past pending alone', function (string $state) {
    $media = Media::factory()->{$state}()->create();

    $this->actingAs(User::query()->findOrFail($media->user_id))
        ->postJson("/uploads/{$media->ulid}/complete")
        ->assertAccepted()
        ->assertJsonPath('status', $media->status->value);

    Queue::assertNothingPushed();
})->with(['uploaded', 'processing', 'ready', 'failed', 'quarantined']);

it('requires a signed-in, verified user', function () {
    $media = Media::factory()->create();

    $this->postJson("/uploads/{$media->ulid}/complete")->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->postJson("/uploads/{$media->ulid}/complete")->assertForbidden();

    Queue::assertNothingPushed();
});

it('404s for an unknown or malformed ulid without naming internals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/uploads/01hzzzzzzzzzzzzzzzzzzzzzzz/complete')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);
    $this->actingAs($user)->postJson('/uploads/1/complete')->assertNotFound();
});

it('hands the row back to pending when the job cannot be queued', function () {
    $media = Media::factory()->create();
    $this->mock(Dispatcher::class)->shouldReceive('dispatch')->andThrow(new RuntimeException('queue insert failed'));

    $this->actingAs(User::query()->findOrFail($media->user_id))
        ->postJson("/uploads/{$media->ulid}/complete")
        ->assertServerError();

    expect($media->refresh()->status)->toBe(MediaStatus::Pending);
});
