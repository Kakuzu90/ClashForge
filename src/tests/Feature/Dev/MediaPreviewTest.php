<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Http\Controllers\Dev\MediaPreviewController;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('signs in a verified dev user and lists the uploadable collections locally', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/dev/media')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dev/Media')
            ->has('collections', count(MediaCollection::uploadable()))
            ->has('collections.0', fn (Assert $collection) => $collection
                ->hasAll(['value', 'label', 'maxBytes', 'accept', 'typesLabel'])));

    $user = User::query()->where('email', MediaPreviewController::DEV_USER_EMAIL)->sole();

    expect($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('reuses the same dev user', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/dev/media')->assertOk();
    auth()->logout();
    $this->get('/dev/media')->assertOk();

    expect(User::query()->where('email', MediaPreviewController::DEV_USER_EMAIL)->count())->toBe(1);
});

it('does not exist outside the local environment', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $this->get('/dev/media')->assertNotFound();

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
})->with(['testing', 'staging', 'production']);
