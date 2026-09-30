<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Domain\Users\Models\Profile;
use App\Models\User;
use Tests\Support\Media\InteractsWithMedia;

// specs/11: IDOR, mass assignment, stored XSS and account status on the profile surface.

uses(InteractsWithMedia::class);

beforeEach(fn () => $this->fakeMediaStorage());

const EMPTY_PROFILE = ['languages' => [], 'socials' => []];

it('answers another user\'s avatar upload with a 404', function () {
    $owner = User::factory()->create();
    $media = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $owner->id]);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->put('/settings/profile/avatar', ['media' => $media->ulid])->assertNotFound();

    expect($media->refresh()->attachable_id)->toBeNull()
        ->and(Profile::query()->where('user_id', $intruder->id)->value('avatar_media_id'))->toBeNull();
});

it('ignores owner, avatar and privileged fields posted with the profile', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $media = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $other->id]);

    $this->actingAs($user)->patch('/settings/profile', [
        ...EMPTY_PROFILE,
        'display_name' => 'Chief',
        'user_id' => $other->id,
        'avatar_media_id' => $media->id,
        'role' => 'admin',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    $profile = Profile::query()->where('user_id', $user->id)->firstOrFail();
    expect($profile->display_name)->toBe('Chief')
        ->and($profile->avatar_media_id)->toBeNull()
        ->and(Profile::query()->where('user_id', $other->id)->value('display_name'))->toBeNull()
        ->and($user->refresh()->role)->toBe(Role::User);
});

it('strips markup from the display name and bio, keeping plain text as typed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        ...EMPTY_PROFILE,
        'display_name' => '<img src=x onerror=alert(1)>Chief',
        'bio' => '<script>alert(1)</script>I <3 clash & <b>war</b><!-- hidden -->',
    ]);

    $profile = Profile::query()->where('user_id', $user->id)->firstOrFail();
    expect($profile->display_name)->toBe('Chief')
        ->and($profile->bio)->toBe('alert(1)I <3 clash & war');
});

it('strips nested and split tags until none are left', function (string $input) {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [...EMPTY_PROFILE, 'bio' => $input]);

    expect((string) Profile::query()->where('user_id', $user->id)->value('bio'))->not->toMatch('/<[a-zA-Z\/!]/');
})->with([
    'nested' => ['<<b>script>alert(1)<</b>/script>'],
    'split by a comment' => ['<scr<!-- -->ipt>alert(1)</script>'],
    'doctype' => ['<!DOCTYPE html>hi'],
    'deeply nested' => ['<<<i>b>script>x<<<i>/b>/script>'],
]);

it('escapes what is left when rendering it back', function () {
    $user = User::factory()->withProfileData(['bio' => 'I <3 clash & "war"'])->create();

    $html = $this->actingAs($user)->get('/settings/profile')->getContent();

    expect($html)->not->toContain('I <3 clash');
});

it('lets profile writes follow account status', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [...EMPTY_PROFILE, 'display_name' => 'Changed']);

    expect(Profile::query()->where('user_id', $user->id)->value('display_name') === 'Changed')->toBe($allowed);
    if (! $allowed) {
        expect($response->getStatusCode())->toBeIn([302, 403]);
    }
})->with([
    'active' => ['admin', true],
    'restricted' => ['restricted', true],
    'unverified' => ['unverified', true],
    'suspended' => ['suspended', false],
    'pending deletion' => ['pendingDeletion', false],
]);

it('lets avatar writes follow account status', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();
    $old = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $user->id]);
    $new = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $user->id]);
    $profile = Profile::query()->where('user_id', $user->id)->firstOrFail();
    $profile->forceFill(['avatar_media_id' => $old->id])->save();

    $this->actingAs($user)->put('/settings/profile/avatar', ['media' => $new->ulid]);
    expect($profile->refresh()->avatar_media_id)->toBe($allowed ? $new->id : $old->id);

    $this->actingAs($user)->delete('/settings/profile/avatar');
    expect($profile->refresh()->avatar_media_id)->toBe($allowed ? null : $old->id);

    $intent = $this->actingAs($user)->postJson('/uploads/intent', ['collection' => 'avatar', 'filename' => 'a.jpg', 'size' => 1000, 'mime' => 'image/jpeg']);
    expect($intent->getStatusCode())->toBe($allowed ? 201 : 403);
})->with([
    'active' => ['admin', true],
    'restricted' => ['restricted', true],
    'suspended' => ['suspended', false],
    'pending deletion' => ['pendingDeletion', false],
]);

it('lets a restricted account upload an avatar but not a base screenshot', function () {
    $user = User::factory()->restricted()->create();
    $payload = ['filename' => 'a.jpg', 'size' => 1000, 'mime' => 'image/jpeg'];

    $this->actingAs($user)->postJson('/uploads/intent', [...$payload, 'collection' => 'avatar'])->assertCreated();
    $this->actingAs($user)->postJson('/uploads/intent', [...$payload, 'collection' => 'base_screenshot'])->assertForbidden();
});

it('keeps avatar uploads closed to unverified accounts', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/uploads/intent', ['collection' => 'avatar', 'filename' => 'a.jpg', 'size' => 1000, 'mime' => 'image/jpeg'])
        ->assertForbidden();
});
