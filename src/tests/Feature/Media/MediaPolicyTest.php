<?php

use App\Domain\Media\Data\CreateUploadIntentData;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\UploadIntentService;
use App\Domain\Media\Services\UploadStatusService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Media\InteractsWithMedia;

// MediaPolicy is the authorization source for uploads; the services call it, so both are tested
// here without the route middleware in front.

uses(InteractsWithMedia::class);

beforeEach(fn () => $this->fakeMediaStorage());

it('lets only verified users create uploads', function () {
    expect(Gate::forUser(User::factory()->create())->allows('create', Media::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->unverified()->create())->allows('create', Media::class))->toBeFalse();
});

it('lets only the uploader complete or view an upload', function () {
    $media = Media::factory()->create();
    $owner = User::query()->findOrFail($media->user_id);
    $other = User::factory()->create();

    expect(Gate::forUser($owner)->allows('complete', $media))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $media))->toBeTrue()
        ->and(Gate::forUser($other)->allows('complete', $media))->toBeFalse()
        ->and(Gate::forUser($other)->allows('view', $media))->toBeFalse();
});

it('refuses an unverified user in the intent service itself', function () {
    app(UploadIntentService::class)->create(
        User::factory()->unverified()->create(),
        new CreateUploadIntentData(MediaCollection::BaseScreenshot, 'a.jpg', 1000, 'image/jpeg'),
    );
})->throws(AuthorizationException::class);

it('checks the policy after the owner-scoped lookup in the status service', function () {
    $media = Media::factory()->create();
    Gate::before(fn () => false);

    app(UploadStatusService::class)->forOwner(User::query()->findOrFail($media->user_id), $media->ulid);
})->throws(AuthorizationException::class);

it('refuses uploads from accounts that may not write content', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $media = Media::factory()->create(['user_id' => $user->id]);

    expect(Gate::forUser($user)->allows('create', Media::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('complete', $media))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', $media))->toBeTrue();
})->with(['restricted', 'suspended', 'pendingDeletion']);

it('treats an avatar as a profile write that restricted accounts may make', function () {
    $user = User::factory()->restricted()->create();
    $avatar = Media::factory()->collection(MediaCollection::Avatar)->create(['user_id' => $user->id]);
    $screenshot = Media::factory()->create(['user_id' => $user->id]);

    expect(Gate::forUser($user)->allows('create', [Media::class, MediaCollection::Avatar]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', [Media::class, MediaCollection::BaseScreenshot]))->toBeFalse()
        ->and(Gate::forUser($user)->allows('complete', $avatar))->toBeTrue()
        ->and(Gate::forUser($user)->allows('complete', $screenshot))->toBeFalse();
});

// P3-02: a replay video needs what publishing needs (specs/04 §1, specs/24 R6).
it('lets only holders of a verified CoC account with content writes upload a video', function () {
    $holder = function (User $user): User {
        $user->forceFill(['verified_accounts_count' => 1])->save();

        return $user;
    };
    $video = Media::factory()->collection(MediaCollection::BaseVideo)->create(['user_id' => $holder(User::factory()->create())->id]);
    $owner = User::query()->findOrFail($video->user_id);

    expect(Gate::forUser($owner)->allows('create', [Media::class, MediaCollection::BaseVideo]))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('complete', $video))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('create', [Media::class, MediaCollection::BaseVideo]))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('create', [Media::class, MediaCollection::BaseScreenshot]))->toBeTrue()
        ->and(Gate::forUser($holder(User::factory()->restricted()->create()))->allows('create', [Media::class, MediaCollection::BaseVideo]))->toBeFalse()
        ->and(Gate::forUser($holder(User::factory()->unverified()->create()))->allows('create', [Media::class, MediaCollection::BaseVideo]))->toBeFalse();

    // Lost the last verified account between intent and complete.
    $owner->forceFill(['verified_accounts_count' => 0])->save();
    expect(Gate::forUser($owner)->allows('complete', $video))->toBeFalse();
});
