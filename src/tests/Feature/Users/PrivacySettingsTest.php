<?php

use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Models\UserStats;
use App\Domain\Users\Services\CacheInvalidator;
use App\Domain\Users\Services\ProfileService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

// FR-PROFILE-4 at /settings/privacy, and the `privacy_settings` / `user_stats` rows (specs/07).

const PRIVACY_FORM = [
    'profile_visibility' => 'members',
    'show_coc_accounts' => false,
    'show_clan' => true,
    'allow_recruitment_contact' => false,
    'searchable' => false,
];

function privacyOf(User $user): PrivacySettings
{
    return PrivacySettings::query()->whereKey($user->id)->firstOrFail();
}

it('gives every user one privacy row with the owner defaults and one zeroed stats row', function () {
    $user = User::factory()->create();
    app(ProfileService::class)->createFor($user);

    $settings = privacyOf($user);
    expect(PrivacySettings::query()->whereKey($user->id)->count())->toBe(1)
        ->and($settings->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and($settings->show_coc_accounts)->toBeTrue()
        ->and($settings->show_clan)->toBeTrue()
        ->and($settings->show_activity)->toBeTrue()
        ->and($settings->allow_recruitment_contact)->toBeTrue()
        ->and($settings->allow_marketplace_contact)->toBeFalse()
        ->and($settings->searchable)->toBeTrue()
        ->and(UserStats::query()->whereKey($user->id)->count())->toBe(1)
        ->and(UserStats::query()->whereKey($user->id)->value('bases_published'))->toBe(0);
});

it('creates the rows for a user that has none, as registration will', function () {
    $user = User::factory()->create();
    PrivacySettings::query()->whereKey($user->id)->delete();
    UserStats::query()->whereKey($user->id)->delete();

    app(ProfileService::class)->createFor($user);

    expect(PrivacySettings::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(UserStats::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('uses the column defaults from specs/07 and drops the rows with the user', function () {
    $user = User::factory()->create();
    PrivacySettings::query()->whereKey($user->id)->delete();
    DB::table('privacy_settings')->insert(['user_id' => $user->id]);

    expect(privacyOf($user)->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and(privacyOf($user)->allow_marketplace_contact)->toBeFalse()
        ->and(Schema::hasColumns('user_stats', ['bases_published', 'total_base_likes', 'total_base_copies', 'total_base_views', 'comments_posted', 'recomputed_at']))->toBeTrue();

    $user->forceDelete();

    expect(PrivacySettings::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and(UserStats::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('fills the existing row from the factories instead of adding a second one', function () {
    $settings = PrivacySettings::factory()->private()->create();
    $stats = UserStats::factory()->active()->create();

    expect(PrivacySettings::query()->whereKey($settings->user_id)->count())->toBe(1)
        ->and($settings->refresh()->profile_visibility)->toBe(ProfileVisibility::Private)
        ->and(UserStats::query()->whereKey($stats->user_id)->count())->toBe(1)
        ->and($stats->refresh()->bases_published)->toBe(12);
});

it('sends a guest to sign in', function () {
    $this->get('/settings/privacy')->assertRedirect('/login');
    $this->patch('/settings/privacy', PRIVACY_FORM)->assertRedirect('/login');
});

it('renders the form with the five exposed settings and the visibility choices', function () {
    $user = User::factory()->withPrivacy(['profile_visibility' => 'members', 'searchable' => false])->create();

    $this->actingAs($user)->get('/settings/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Privacy')
            ->where('settings', [
                'visibility' => 'members',
                'showCocAccounts' => true,
                'showClan' => true,
                'allowRecruitmentContact' => true,
                'searchable' => false,
            ])
            ->where('visibilityOptions.0.value', 'public')
            ->has('visibilityOptions', 3)
            ->where('username', $user->username)
            ->where('meta.title', 'Privacy settings')
            ->missing('settings.showActivity')
            ->missing('settings.allowMarketplaceContact')
        );
});

it('saves the privacy settings and leaves the hidden ones alone', function () {
    $user = User::factory()->withPrivacy(['show_activity' => false, 'allow_marketplace_contact' => true])->create();

    $this->actingAs($user)->from('/settings/privacy')->patch('/settings/privacy', PRIVACY_FORM)
        ->assertRedirect('/settings/privacy')
        ->assertSessionHas('success');

    $settings = privacyOf($user);
    expect($settings->profile_visibility)->toBe(ProfileVisibility::Members)
        ->and($settings->show_coc_accounts)->toBeFalse()
        ->and($settings->show_clan)->toBeTrue()
        ->and($settings->allow_recruitment_contact)->toBeFalse()
        ->and($settings->searchable)->toBeFalse()
        ->and($settings->show_activity)->toBeFalse()
        ->and($settings->allow_marketplace_contact)->toBeTrue();
});

it('validates each field', function (array $input, string $field) {
    $this->actingAs(User::factory()->create())->from('/settings/privacy')->patch('/settings/privacy', [...PRIVACY_FORM, ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'unknown visibility' => [['profile_visibility' => 'friends'], 'profile_visibility'],
    'missing visibility' => [['profile_visibility' => null], 'profile_visibility'],
    'not a boolean' => [['show_clan' => 'sometimes'], 'show_clan'],
    'missing toggle' => [['searchable' => null], 'searchable'],
]);

it('replaces the cached privacy row and drops the profile on update, so the change applies at once', function () {
    $user = User::factory()->create(['username' => 'chief']);

    $this->get('/u/chief')->assertOk();
    expect(Cache::get(CacheInvalidator::privacyKey($user->id))['profile_visibility'])->toBe('public')
        ->and(Cache::has(CacheInvalidator::profileKey('chief')))->toBeTrue();

    $this->actingAs($user)->patch('/settings/privacy', [...PRIVACY_FORM, 'profile_visibility' => 'private']);
    expect(Cache::get(CacheInvalidator::privacyKey($user->id))['profile_visibility'])->toBe('private')
        ->and(Cache::has(CacheInvalidator::profileKey('chief')))->toBeFalse();

    auth()->logout();
    $this->get('/u/chief')->assertNotFound();
});

it('reads privacy through the cache for an hour', function () {
    $user = User::factory()->create(['username' => 'chief']);
    $this->get('/u/chief')->assertOk();

    PrivacySettings::query()->whereKey($user->id)->update(['profile_visibility' => 'private']);

    $this->get('/u/chief')->assertOk();
    $this->travel((int) config('platform.profile.privacy_cache_ttl') + 1)->seconds();
    // A new request gets a new resolver in production; the test app lives across requests.
    $this->app->forgetScopedInstances();
    $this->get('/u/chief')->assertNotFound();
});

it('keeps the cache TTLs and the description length in config', function () {
    expect(config('platform.profile.cache_ttl'))->toBe(300)
        ->and(config('platform.profile.privacy_cache_ttl'))->toBe(3600)
        ->and(config('platform.profile.meta_description_max'))->toBe(160);
});

it('writes the new privacy row through to the cache, so a late reader cannot restore the old one', function () {
    $user = User::factory()->create(['username' => 'chief']);
    $this->get('/u/chief')->assertOk();

    $this->actingAs($user)->patch('/settings/privacy', [...PRIVACY_FORM, 'profile_visibility' => 'private'])->assertSessionHasNoErrors();

    $key = CacheInvalidator::privacyKey($user->id);
    expect(Cache::get($key)['profile_visibility'])->toBe('private');

    // A reader that loaded the old row before the save only adds on a miss.
    $this->app->forgetScopedInstances();
    Cache::add($key, ['profile_visibility' => 'public'], 3600);
    auth()->logout();
    $this->get('/u/chief')->assertNotFound();
});
