<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Users\Data\UpdatePrivacyData;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\PrivacySettingsService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

// specs/11 "Account enumeration", "Data exposure via page props", "Mass assignment" and XSS on
// /u/{username} and /settings/privacy. specs/04 §1, §3: who may see and who may change privacy.

/**
 * @return array{status: int, body: string}
 */
function profileResponse(mixed $test, string $username, ?User $viewer): array
{
    $response = $viewer === null ? $test->get("/u/{$username}") : $test->actingAs($viewer)->get("/u/{$username}");

    // The requested URL (page JSON, canonical, og:url) is the only part allowed to differ.
    $body = str_replace($username, '{username}', (string) $response->getContent());

    return ['status' => $response->getStatusCode(), 'body' => $body];
}

it('applies the visibility × viewer matrix, with staff getting no bypass', function (ProfileVisibility $visibility, string $viewer, bool $visible) {
    $owner = User::factory()->withPrivacy(['profile_visibility' => $visibility])->create(['username' => 'chief']);

    $as = match ($viewer) {
        'guest' => null,
        'member' => User::factory()->create(),
        'moderator' => User::factory()->moderator()->create(),
        'super admin' => User::factory()->superAdmin()->create(),
        'owner' => $owner,
    };

    expect(profileResponse($this, 'chief', $as)['status'])->toBe($visible ? 200 : 404);
})->with([
    'public, guest' => [ProfileVisibility::Public, 'guest', true],
    'public, member' => [ProfileVisibility::Public, 'member', true],
    'public, owner' => [ProfileVisibility::Public, 'owner', true],
    'members, guest' => [ProfileVisibility::Members, 'guest', false],
    'members, member' => [ProfileVisibility::Members, 'member', true],
    'members, owner' => [ProfileVisibility::Members, 'owner', true],
    'private, guest' => [ProfileVisibility::Private, 'guest', false],
    'private, member' => [ProfileVisibility::Private, 'member', false],
    'private, moderator' => [ProfileVisibility::Private, 'moderator', false],
    'private, super admin' => [ProfileVisibility::Private, 'super admin', false],
    'private, owner' => [ProfileVisibility::Private, 'owner', true],
]);

it('gives an unknown, hidden, banned or pending-deletion profile the same 404', function (?string $viewer) {
    User::factory()->withPrivacy(['profile_visibility' => 'private'])->create(['username' => 'hiddenchief']);
    User::factory()->withPrivacy(['profile_visibility' => 'members'])->create(['username' => 'memberchief']);
    User::factory()->banned()->create(['username' => 'bannedchief']);
    User::factory()->pendingDeletion()->create(['username' => 'leavingchief']);
    User::factory()->create(['username' => 'deletedchief'])->delete();
    $as = $viewer === null ? null : User::factory()->create();

    $unknown = profileResponse($this, 'nosuchchief', $as);
    expect($unknown['status'])->toBe(404)
        ->and($unknown['body'])->toContain('Profile\\/NotFound');

    $hidden = $viewer === null ? ['hiddenchief', 'memberchief'] : ['hiddenchief'];
    foreach ([...$hidden, 'bannedchief', 'leavingchief', 'deletedchief'] as $username) {
        expect(profileResponse($this, $username, $as))->toBe($unknown);
    }

    // Names that cannot be stored never reach the database and get the same page.
    foreach (['chief%20', str_repeat('a', 21), 'chief.dot', 'ch%C3%AFef'] as $malformed) {
        $response = $as === null ? $this->get("/u/{$malformed}") : $this->actingAs($as)->get("/u/{$malformed}");
        $response->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('Profile/NotFound'));
    }

    // Invalid UTF-8 and NUL bytes: either the framework refuses the path (400, for any path) or the
    // lookup does, never a database error.
    foreach (['%FF', '%00'] as $bytes) {
        $status = ($as === null ? $this->get("/u/{$bytes}") : $this->actingAs($as)->get("/u/{$bytes}"))->getStatusCode();
        expect($status)->toBeIn([400, 404]);
    }
})->with(['guest' => [null], 'member' => ['member']]);

it('keeps a suspended or restricted owner\'s profile visible', function (string $state) {
    User::factory()->{$state}()->create(['username' => 'chief']);

    $this->get('/u/chief')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Profile/Show'));
})->with(['suspended', 'restricted']);

it('applies a ban on the next request, without waiting for the profile cache', function () {
    $owner = User::factory()->create(['username' => 'chief']);
    $this->get('/u/chief')->assertOk();

    $owner->forceFill(['status' => UserStatus::Banned])->save();

    $this->get('/u/chief')->assertNotFound();
});

it('puts no email, role, status, ids or privacy settings in the props', function () {
    $owner = User::factory()->admin()->restricted()->create(['username' => 'chief', 'email' => 'chief@example.com']);

    $response = $this->actingAs($owner)->get('/u/chief');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Show')
        ->missing('profile.email')
        ->missing('profile.id')
        ->missing('profile.userId')
        ->missing('profile.ulid')
        ->missing('profile.role')
        ->missing('profile.status')
        ->missing('profile.visibility')
        ->missing('profile.searchable')
        ->missing('profile.privacy'));

    expect($response->getContent())
        ->not->toContain('chief@example.com')
        ->not->toContain($owner->ulid)
        ->not->toContain('profile_visibility')
        ->not->toContain('&quot;'.Role::Admin->value.'&quot;');
});

it('builds social links only from fixed https hosts and marks them nofollow ugc noopener', function () {
    User::factory()->withProfileData(['socials' => ['youtube' => '@clashchief', 'twitch' => 'chief_tv', 'x' => 'chief', 'discord' => 'chief.99']])
        ->create(['username' => 'chief']);

    $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('profile.socials.0.url', 'https://www.youtube.com/@clashchief')
        ->where('profile.socials.1.url', 'https://www.twitch.tv/chief_tv')
        ->where('profile.socials.2.url', 'https://x.com/chief')
        ->where('profile.socials.3.url', null));

    expect(file_get_contents(resource_path('js/Pages/Profile/Show.vue')))->toContain('rel="nofollow ugc noopener"')
        ->not->toContain('v-html');
});

it('escapes a stored XSS payload in the bio and display name in the HTML, meta and JSON-LD', function () {
    $payload = '</script><script>alert(1)</script>"><img src=x onerror=alert(1)>';
    // Written straight to the row: the service would strip tags, this checks the output side.
    User::factory()->withProfileData(['bio' => $payload, 'display_name' => '"><svg onload=alert(1)>'])->create(['username' => 'chief']);

    $html = (string) $this->get('/u/chief')->assertOk()->getContent();

    expect($html)
        ->not->toContain('<script>alert(1)')
        ->not->toContain('<img src=x')
        ->not->toContain('<svg onload')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('marks a profile noindex unless it is public and searchable', function (array $privacy, bool $noindex, ?string $viewer) {
    $owner = User::factory()->withPrivacy($privacy)->create(['username' => 'chief']);
    $as = match ($viewer) {
        'owner' => $owner,
        'member' => User::factory()->create(),
        default => null,
    };

    $html = (string) ($as === null ? $this->get('/u/chief') : $this->actingAs($as)->get('/u/chief'))->assertOk()->getContent();

    expect(str_contains($html, '<meta name="robots" content="noindex, nofollow">'))->toBe($noindex);
})->with([
    'public, searchable' => [['profile_visibility' => 'public', 'searchable' => true], false, null],
    'public, not searchable' => [['profile_visibility' => 'public', 'searchable' => false], true, null],
    'members' => [['profile_visibility' => 'members', 'searchable' => true], true, 'member'],
    'private, to its owner' => [['profile_visibility' => 'private', 'searchable' => true], true, 'owner'],
]);

it('marks the not-found page noindex', function () {
    $this->get('/u/nosuchchief')->assertNotFound()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('ignores owner, privileged and hidden fields posted with the privacy form', function () {
    $user = User::factory()->withPrivacy(['allow_marketplace_contact' => false, 'show_activity' => true])->create();
    $other = User::factory()->create();

    $this->actingAs($user)->patch('/settings/privacy', [
        'profile_visibility' => 'members',
        'show_coc_accounts' => true,
        'show_clan' => true,
        'allow_recruitment_contact' => true,
        'searchable' => true,
        'user_id' => $other->id,
        'role' => 'admin',
        'status' => 'active',
        'allow_marketplace_contact' => true,
        'show_activity' => false,
    ])->assertSessionHasNoErrors();

    $mine = PrivacySettings::query()->whereKey($user->id)->firstOrFail();
    expect($mine->profile_visibility)->toBe(ProfileVisibility::Members)
        ->and($mine->allow_marketplace_contact)->toBeFalse()
        ->and($mine->show_activity)->toBeTrue()
        ->and(PrivacySettings::query()->whereKey($other->id)->value('profile_visibility'))->toBe(ProfileVisibility::Public)
        ->and($user->refresh()->role)->toBe(Role::User);
});

it('lets privacy writes follow account status', function (string $state, bool $allowed) {
    $user = User::factory()->{$state}()->create();

    $response = $this->actingAs($user)->patch('/settings/privacy', [
        'profile_visibility' => 'private',
        'show_coc_accounts' => true,
        'show_clan' => true,
        'allow_recruitment_contact' => true,
        'searchable' => true,
    ]);

    expect(PrivacySettings::query()->whereKey($user->id)->value('profile_visibility') === ProfileVisibility::Private)->toBe($allowed);
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

it('checks the policy in the service too, not only in the middleware', function () {
    $owner = User::factory()->restricted()->create();
    $settings = PrivacySettings::query()->whereKey($owner->id)->firstOrFail();
    $data = new UpdatePrivacyData(ProfileVisibility::Private, true, true, true, true);

    expect(Gate::forUser($owner)->allows('update', $settings))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('update', $settings))->toBeFalse()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('update', $settings))->toBeFalse();

    $suspended = User::factory()->suspended()->create();
    expect(fn () => app(PrivacySettingsService::class)->update($suspended, $data))->toThrow(AuthorizationException::class);
    expect(PrivacySettings::query()->whereKey($suspended->id)->value('profile_visibility'))->toBe(ProfileVisibility::Public);
});
