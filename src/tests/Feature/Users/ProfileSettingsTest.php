<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Services\ProfileService;
use App\Domain\Users\Support\IsoCodes;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Media\InteractsWithMedia;

// FR-PROFILE-1–3: one profile per user, edited at /settings/profile, with an avatar upload.

uses(InteractsWithMedia::class);

beforeEach(fn () => $this->fakeMediaStorage());

function readyAvatar(User $user): Media
{
    $media = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $user->id]);

    foreach ([VariantName::Full->value => 512, VariantName::Card->value => 128, VariantName::Thumb->value => 48] as $name => $size) {
        MediaVariant::factory()->create([
            'media_id' => $media->id,
            'variant' => $name,
            'path' => "public/avatar/{$media->ulid}/{$name}.webp",
            'width' => $size,
            'height' => $size,
        ]);
    }

    return $media;
}

function profileOf(User $user): Profile
{
    return Profile::query()->where('user_id', $user->id)->firstOrFail();
}

it('gives every user exactly one profile', function () {
    $user = User::factory()->create();
    app(ProfileService::class)->createFor($user);

    expect(Profile::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('fills the existing profile from ProfileFactory instead of adding a second one', function () {
    $profile = Profile::factory()->filled()->create();
    $user = User::factory()->create();
    $mine = Profile::factory()->create(['user_id' => $user->id, 'display_name' => 'Chief']);

    expect(Profile::query()->where('user_id', $profile->user_id)->count())->toBe(1)
        ->and($profile->refresh()->country_code)->toBe('DE')
        ->and(Profile::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($mine->refresh()->display_name)->toBe('Chief');
});

it('sends a guest to sign in', function () {
    $this->get('/settings/profile')->assertRedirect('/login');
});

it('renders the form with the owner\'s profile and the choice lists', function () {
    $user = User::factory()->withProfileData([
        'display_name' => 'Chief',
        'bio' => 'War base builder.',
        'country_code' => 'DE',
        'languages' => ['de', 'en'],
        'timezone' => 'Europe/Berlin',
        'socials' => ['youtube' => '@clashchief'],
    ])->create();

    $this->actingAs($user)->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Profile')
            ->where('profile.username', $user->username)
            ->where('profile.displayName', 'Chief')
            ->where('profile.languages', ['de', 'en'])
            ->where('profile.socials.youtube', '@clashchief')
            ->where('profile.avatar.status', null)
            ->where('avatarUpload.value', 'avatar')
            ->where('limits.languagesMax', config('platform.profile.languages_max'))
            ->has('countries', count(IsoCodes::countries()))
            ->has('languages')
            ->has('timezones')
            ->missing('profile.email')
            ->missing('profile.userId')
        );
});

it('saves the profile fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/settings/profile')->patch('/settings/profile', [
        'display_name' => '  Chief  ',
        'bio' => 'Builds anti-3 bases.',
        'country_code' => 'ph',
        'languages' => ['en', 'tl'],
        'timezone' => 'Asia/Manila',
        'socials' => ['youtube' => 'clashchief', 'twitch' => '', 'x' => '@chief', 'discord' => 'chief.99'],
    ])->assertRedirect('/settings/profile')->assertSessionHas('success');

    $profile = profileOf($user);
    expect($profile->display_name)->toBe('Chief')
        ->and($profile->bio)->toBe('Builds anti-3 bases.')
        ->and($profile->country_code)->toBe('PH')
        ->and($profile->languages)->toBe(['en', 'tl'])
        ->and($profile->timezone)->toBe('Asia/Manila')
        ->and($profile->socials)->toEqual(['youtube' => '@clashchief', 'x' => 'chief', 'discord' => 'chief.99']); // jsonb reorders keys
});

it('clears fields sent empty, as the form sends "Not set"', function () {
    $user = User::factory()->withProfileData(['display_name' => 'Chief', 'country_code' => 'DE', 'timezone' => 'Europe/Berlin', 'languages' => ['de']])->create();

    $this->actingAs($user)->patch('/settings/profile', ['display_name' => '', 'country_code' => '', 'timezone' => '', 'languages' => [], 'socials' => []])
        ->assertSessionHasNoErrors();

    expect(profileOf($user)->display_name)->toBeNull()
        ->and(profileOf($user)->country_code)->toBeNull()
        ->and(profileOf($user)->timezone)->toBeNull()
        ->and(profileOf($user)->languages)->toBe([]);
});

it('validates each field', function (array $input, string $field) {
    $payload = [...['languages' => [], 'socials' => []], ...$input];

    $this->actingAs(User::factory()->create())->from('/settings/profile')->patch('/settings/profile', $payload)
        ->assertSessionHasErrors($field);
})->with([
    'display name too long' => [['display_name' => str_repeat('a', 51)], 'display_name'],
    'bio too long' => [['bio' => str_repeat('a', 501)], 'bio'],
    'unknown country' => [['country_code' => 'EU'], 'country_code'],
    'too many languages' => [['languages' => ['en', 'de', 'fr', 'es']], 'languages'],
    'unknown language' => [['languages' => ['xx']], 'languages'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'url as handle' => [['socials' => ['youtube' => 'https://youtube.com/@x']], 'socials.youtube'],
]);

it('sets an avatar and shares its small version in the header props', function () {
    $user = User::factory()->create();
    $media = readyAvatar($user);

    $this->actingAs($user)->from('/settings/profile')->put('/settings/profile/avatar', ['media' => $media->ulid])
        ->assertRedirect('/settings/profile');

    $media->refresh();
    expect(profileOf($user)->avatar_media_id)->toBe($media->id)
        ->and($media->attachable_id)->toBe(profileOf($user)->id)
        ->and($media->expires_at)->toBeNull();

    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('profile.avatar.status', 'ready')
        ->where('profile.avatar.url128', "https://cdn.test/public/avatar/{$media->ulid}/card.webp")
        ->where('auth.user.avatarUrl', "https://cdn.test/public/avatar/{$media->ulid}/thumb.webp")
    );
});

it('replaces the previous avatar and releases it for deletion', function () {
    $user = User::factory()->create();
    $first = readyAvatar($user);
    $second = readyAvatar($user);

    $this->actingAs($user)->put('/settings/profile/avatar', ['media' => $first->ulid]);
    $this->actingAs($user)->put('/settings/profile/avatar', ['media' => $second->ulid]);

    // The job ran (sync queue) and removed the old row and its objects.
    expect(profileOf($user)->avatar_media_id)->toBe($second->id)
        ->and(Media::withTrashed()->find($first->id))->toBeNull()
        ->and(Media::query()->find($second->id))->not->toBeNull();
});

it('accepts an avatar that is still processing and shows it once ready', function () {
    $user = User::factory()->create();
    $media = Media::factory()->collection(MediaCollection::Avatar)->processing()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put('/settings/profile/avatar', ['media' => $media->ulid])->assertSessionHasNoErrors();

    $this->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
        ->where('profile.avatar.status', 'processing')
        ->where('profile.avatar.url128', null)
        ->where('auth.user.avatarUrl', null)
    );
});

it('removes the avatar', function () {
    $user = User::factory()->create();
    $media = readyAvatar($user);
    $this->actingAs($user)->put('/settings/profile/avatar', ['media' => $media->ulid]);

    $this->actingAs($user)->delete('/settings/profile/avatar')->assertSessionHas('success');

    expect(profileOf($user)->avatar_media_id)->toBeNull()
        ->and(Media::withTrashed()->find($media->id))->toBeNull();
});

it('refuses an upload from another collection or one that is not usable', function (string $state) {
    $user = User::factory()->create();
    $media = match ($state) {
        'screenshot' => Media::factory()->ready()->create(['user_id' => $user->id]),
        'pending' => Media::factory()->collection(MediaCollection::Avatar)->create(['user_id' => $user->id]),
        'failed' => Media::factory()->collection(MediaCollection::Avatar)->failed()->create(['user_id' => $user->id]),
        'quarantined' => Media::factory()->collection(MediaCollection::Avatar)->quarantined()->create(['user_id' => $user->id]),
    };

    $this->actingAs($user)->from('/settings/profile')->put('/settings/profile/avatar', ['media' => $media->ulid])
        ->assertSessionHasErrors('media');

    expect(profileOf($user)->avatar_media_id)->toBeNull();
})->with(['screenshot', 'pending', 'failed', 'quarantined']);

it('queues the released avatar for deletion only after the change commits', function () {
    Queue::fake();
    $user = User::factory()->create();
    $media = readyAvatar($user);
    $this->actingAs($user)->put('/settings/profile/avatar', ['media' => $media->ulid]);

    $this->actingAs($user)->delete('/settings/profile/avatar');

    expect($media->refresh()->status->value)->toBe('deleting')
        ->and($media->attachable_id)->toBeNull();
    Queue::assertPushed(DeleteMediaObjectsJob::class, fn (DeleteMediaObjectsJob $job) => $job->mediaIds === [$media->id]);
});

it('limits settings writes with global-write', function () {
    $user = User::factory()->create();
    $limit = (int) config('platform.rate_limits.global_write_per_minute');

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($user)->patch('/settings/profile', ['languages' => [], 'socials' => []]);
    }

    $this->actingAs($user)->from('/settings/profile')->patch('/settings/profile', ['display_name' => 'Late', 'languages' => [], 'socials' => []])
        ->assertRedirect('/settings/profile')
        ->assertSessionHas('error');

    expect(profileOf($user)->display_name)->toBeNull();
});
