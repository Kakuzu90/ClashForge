<?php

use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\CacheInvalidator;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// specs/05 §2 PrivacyPolicyResolver: who may view a profile, read through `user:{id}:privacy`
// (specs/21 §3) and memoised per request (specs/21 L3).

uses(TestCase::class, RefreshDatabase::class);

function account(int $id): User
{
    return (new User)->forceFill(['id' => $id]);
}

function cachePrivacy(int $userId, string $visibility, bool $searchable = true): void
{
    Cache::put(CacheInvalidator::privacyKey($userId), [
        'profile_visibility' => $visibility,
        'show_coc_accounts' => true,
        'show_clan' => true,
        'show_activity' => true,
        'allow_recruitment_contact' => true,
        'allow_marketplace_contact' => false,
        'searchable' => $searchable,
    ]);
}

it('lets anyone see public, signed-in viewers see members, and only the owner see private', function (string $visibility, bool $guest, bool $member, bool $owner) {
    cachePrivacy(1, $visibility);
    $resolver = new PrivacyPolicyResolver;

    expect($resolver->canView(null, account(1)))->toBe($guest)
        ->and($resolver->canView(account(2), account(1)))->toBe($member)
        ->and($resolver->canView(account(1), account(1)))->toBe($owner);
})->with([
    'public' => ['public', true, true, true],
    'members' => ['members', false, true, true],
    'private' => ['private', false, false, true],
]);

it('allows indexing only for public and searchable profiles', function (string $visibility, bool $searchable, bool $indexable) {
    cachePrivacy(1, $visibility, $searchable);

    expect((new PrivacyPolicyResolver)->isIndexable(account(1)))->toBe($indexable);
})->with([
    ['public', true, true],
    ['public', false, false],
    ['members', true, false],
    ['private', true, false],
]);

it('memoises each row for the request and reloads it after forget', function () {
    cachePrivacy(1, 'public');
    $resolver = new PrivacyPolicyResolver;
    expect($resolver->settingsFor(1)->visibility)->toBe(ProfileVisibility::Public);

    cachePrivacy(1, 'private');
    expect($resolver->settingsFor(1)->visibility)->toBe(ProfileVisibility::Public);

    $resolver->forget(1);
    expect($resolver->settingsFor(1)->visibility)->toBe(ProfileVisibility::Private);
});

it('is scoped to the request by the container', function () {
    expect(app(PrivacyPolicyResolver::class))->toBe(app(PrivacyPolicyResolver::class));

    $first = app(PrivacyPolicyResolver::class);
    app()->forgetScopedInstances();

    expect(app(PrivacyPolicyResolver::class))->not->toBe($first);
});

it('loads the row from the database into the cache on a miss', function () {
    $user = User::factory()->withPrivacy(['profile_visibility' => 'members'])->create();
    Cache::flush();

    expect((new PrivacyPolicyResolver)->settingsFor($user->id)->visibility)->toBe(ProfileVisibility::Members)
        ->and(Cache::get(CacheInvalidator::privacyKey($user->id))['profile_visibility'])->toBe('members');
});

it('treats an account without a row as private, so a gap never opens a profile', function () {
    $user = User::factory()->create();
    PrivacySettings::query()->whereKey($user->id)->delete();
    Cache::flush();

    $resolver = new PrivacyPolicyResolver;

    expect($resolver->canView(account(999), $user))->toBeFalse()
        ->and($resolver->canView(null, $user))->toBeFalse()
        ->and($resolver->canView($user, $user))->toBeTrue()
        ->and($resolver->isIndexable($user))->toBeFalse();
});

it('keys the profile cache case-insensitively, like usernames', function () {
    expect(CacheInvalidator::profileKey('Chief'))->toBe(CacheInvalidator::profileKey('chief'))
        ->and(CacheInvalidator::privacyKey(7))->toBe('user:7:privacy');
});

it('does not overwrite a newer cached row on a miss', function () {
    $user = User::factory()->withPrivacy(['profile_visibility' => 'members'])->create();
    $resolver = new PrivacyPolicyResolver;

    $resolver->refresh($user->id);
    expect(Cache::get(CacheInvalidator::privacyKey($user->id))['profile_visibility'])->toBe('members');

    PrivacySettings::query()->whereKey($user->id)->delete();
    $resolver->refresh($user->id);
    expect(Cache::has(CacheInvalidator::privacyKey($user->id)))->toBeFalse();
});
