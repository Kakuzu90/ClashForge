<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Models\UserStats;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Media\InteractsWithMedia;

// FR-PROFILE-5 (fields that exist today) and FR-PROFILE-6 at /u/{username}, with its SEO head
// (specs/17 §6) and the `profile:{username}` cache (specs/21 §3).

uses(InteractsWithMedia::class);

beforeEach(fn () => $this->fakeMediaStorage());

function profileOwner(): User
{
    $user = User::factory()->withProfileData([
        'display_name' => 'Chief',
        'bio' => 'Builds anti-3 war bases.',
        'country_code' => 'DE',
        'languages' => ['de', 'en'],
        'socials' => ['youtube' => '@clashchief', 'discord' => 'clashchief'],
    ])->create(['username' => 'chief', 'created_at' => '2026-09-15 10:00:00']);

    UserStats::query()->whereKey($user->id)->update(['bases_published' => 12, 'total_base_likes' => 3400, 'total_base_copies' => 980]);

    return $user;
}

it('renders a public profile to a guest', function () {
    profileOwner();

    $this->get('/u/chief')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.username', 'chief')
            ->where('profile.displayName', 'Chief')
            ->where('profile.bio', 'Builds anti-3 war bases.')
            ->where('profile.country', ['code' => 'DE', 'label' => 'Germany'])
            ->where('profile.languages', [['code' => 'de', 'label' => 'German'], ['code' => 'en', 'label' => 'English']])
            ->where('profile.socials', [
                ['network' => 'youtube', 'label' => 'YouTube', 'handle' => '@clashchief', 'url' => 'https://www.youtube.com/@clashchief'],
                ['network' => 'discord', 'label' => 'Discord', 'handle' => 'clashchief', 'url' => null],
            ])
            ->where('profile.memberSince', fn (string $iso) => str_starts_with($iso, '2026-09-15'))
            ->where('profile.stats', ['basesPublished' => 12, 'likesReceived' => 3400, 'copies' => 980])
            ->where('profile.avatarUrl512', null)
            ->where('profile.isOwn', false)
            ->where('meta.title', 'Chief (@chief)')
        );
});

it('marks the owner\'s own profile and leaves it unmarked for other members', function () {
    $owner = profileOwner();

    $this->actingAs($owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('profile.isOwn', true));
    $this->actingAs(User::factory()->create())->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('profile.isOwn', false));
});

it('finds the profile whatever the case of the username', function () {
    profileOwner();

    $this->get('/u/CHIEF')->assertOk()->assertInertia(fn (Assert $page) => $page->where('profile.username', 'chief'));
});

it('shows the avatar once it is ready', function () {
    $owner = profileOwner();
    $media = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $owner->id]);
    foreach ([VariantName::Full->value, VariantName::Card->value, VariantName::Thumb->value] as $variant) {
        MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => $variant, 'path' => "public/avatar/{$media->ulid}/{$variant}.webp"]);
    }
    Profile::query()->where('user_id', $owner->id)->update(['avatar_media_id' => $media->id]);

    $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('profile.avatarUrl512', fn (string $url) => str_ends_with($url, 'full.webp'))
        ->where('profile.avatarUrl128', fn (string $url) => str_ends_with($url, 'card.webp')));
});

it('uses the username as the title when there is no display name', function () {
    User::factory()->create(['username' => 'plainchief']);

    $this->get('/u/plainchief')
        ->assertInertia(fn (Assert $page) => $page->where('profile.displayName', null)->where('meta.title', '@plainchief'))
        ->assertSee('<meta name="description" content="plainchief on '.e(config('app.name')).'.">', false);
});

it('renders the SEO head: description, canonical, Person JSON-LD, indexable', function () {
    profileOwner();

    $html = $this->get('/u/chief?ref=share')->getContent();

    expect($html)
        ->toContain('<title inertia>Chief (@chief) · '.config('app.name').'</title>')
        ->toContain('<meta name="description" content="Builds anti-3 war bases.">')
        ->toContain('<link rel="canonical" href="'.url('/u/chief').'">')
        ->toContain('<meta property="og:type" content="profile">')
        ->not->toContain('noindex');

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
    expect(json_decode($match[1], true))->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => 'Chief',
        'alternateName' => '@chief',
        'url' => url('/u/chief'),
        'description' => 'Builds anti-3 war bases.',
        'sameAs' => ['https://www.youtube.com/@clashchief'],
    ]);
});

it('cuts a long bio to the meta description length', function () {
    User::factory()->withProfileData(['bio' => str_repeat('war base ', 50)])->create(['username' => 'longbio']);

    preg_match('#<meta name="description" content="([^"]*)">#', $this->get('/u/longbio')->getContent(), $match);

    expect(mb_strlen(html_entity_decode($match[1])))->toBeLessThanOrEqual((int) config('platform.profile.meta_description_max') + 3);
});

it('keeps the profile within the query budget', function () {
    profileOwner();
    $viewer = User::factory()->create();

    DB::enableQueryLog();
    $this->actingAs($viewer)->get('/u/chief')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('caches the viewer-independent profile and drops it when the profile changes', function () {
    $owner = profileOwner();

    $this->get('/u/chief')->assertOk();
    expect(Cache::has(CacheInvalidator::profileKey('chief')))->toBeTrue();

    $this->actingAs($owner)->patch('/settings/profile', ['display_name' => 'New Chief', 'languages' => [], 'socials' => []])->assertSessionHasNoErrors();
    expect(Cache::has(CacheInvalidator::profileKey('chief')))->toBeFalse();

    $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('profile.displayName', 'New Chief'));
});

it('drops the cached profile when the avatar is set, removed or finishes processing', function () {
    $owner = profileOwner();
    $key = CacheInvalidator::profileKey('chief');
    $media = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $owner->id]);

    Cache::put($key, ['stale' => true]);
    $this->actingAs($owner)->put('/settings/profile/avatar', ['media' => $media->ulid]);
    expect(Cache::has($key))->toBeFalse();

    Cache::put($key, ['stale' => true]);
    $this->actingAs($owner)->delete('/settings/profile/avatar');
    expect(Cache::has($key))->toBeFalse();

    Cache::put($key, ['stale' => true]);
    event(new MediaReady($media->ulid, $owner->id, MediaCollection::BaseScreenshot));
    expect(Cache::has($key))->toBeTrue();
    event(new MediaReady($media->ulid, $owner->id, MediaCollection::Avatar));
    expect(Cache::has($key))->toBeFalse();
});

it('reads the cached profile without rebuilding it', function () {
    profileOwner();
    $this->get('/u/chief');

    Profile::query()->update(['display_name' => 'Changed behind the cache']);

    $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('profile.displayName', 'Chief'));
});

it('renders the not-found page for a missing username', function () {
    $this->get('/u/nosuchchief')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Profile/NotFound')->where('meta.title', 'Profile not found')->missing('profile'));
});

it('ignores a profile entry written under an older version, as a late reader would leave it', function () {
    $owner = profileOwner();
    $this->get('/u/chief')->assertOk();
    $staleVersion = CacheInvalidator::profileVersion('chief');

    // A reader built the old profile, the owner saves, then the reader's write lands.
    Profile::query()->where('user_id', $owner->id)->update(['display_name' => 'New Chief']);
    CacheInvalidator::profile('chief');
    Cache::put(CacheInvalidator::profileKey('chief'), ['version' => $staleVersion, 'data' => ['displayName' => 'Chief']], 300);

    $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('profile.displayName', 'New Chief'));
});
