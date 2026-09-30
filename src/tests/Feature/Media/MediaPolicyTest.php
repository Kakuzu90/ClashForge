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
