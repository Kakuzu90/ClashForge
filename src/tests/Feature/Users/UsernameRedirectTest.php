<?php

use App\Domain\Auth\Models\UsernameHistory;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

// FR-PROFILE-7, specs/23 §1: /u/{old} sends visitors to the current name for the hold, then 404s;
// it never points at a profile the visitor could not open (specs/11 "Account enumeration").

beforeEach(fn () => Date::setTestNow('2026-10-01 12:00:00'));

function renamed(string $from, string $to, int $daysAgo = 10, array $privacy = []): User
{
    $factory = User::factory();
    if ($privacy !== []) {
        $factory = $factory->withPrivacy($privacy);
    }
    $user = $factory->create(['username' => $to]);
    UsernameHistory::factory()->create(['user_id' => $user->id, 'username' => $from, 'released_at' => Date::now()->subDays($daysAgo)]);

    return $user;
}

function assertProfileMissing(mixed $response): void
{
    $response->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('Profile/NotFound'));
}

it('redirects an old name to the current one, uncached', function () {
    renamed('chief', 'new_chief');

    $this->get('/u/chief')
        ->assertStatus(301)
        ->assertRedirect('/u/new_chief')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/u/CHIEF')->assertRedirect('/u/new_chief');
});

it('follows a chain of changes straight to the current name', function () {
    $user = renamed('first', 'third', 50);
    UsernameHistory::factory()->create(['user_id' => $user->id, 'username' => 'second', 'released_at' => Date::now()->subDays(5)]);

    $this->get('/u/first')->assertRedirect('/u/third');
    $this->get('/u/second')->assertRedirect('/u/third');
});

it('stops redirecting when the hold ends', function () {
    renamed('chief', 'new_chief', (int) config('platform.auth.username_reservation_days') + 1);

    assertProfileMissing($this->get('/u/chief'));
});

it('shows whoever holds the name now instead of redirecting', function () {
    renamed('chief', 'new_chief', (int) config('platform.auth.username_reservation_days') + 1);
    User::factory()->create(['username' => 'chief']);

    $this->get('/u/chief')->assertOk()->assertInertia(fn (Assert $page) => $page->where('profile.username', 'chief'));
});

it('never redirects to a profile the visitor cannot see', function () {
    renamed('hidden', 'hidden_now', privacy: ['profile_visibility' => 'private']);
    renamed('members', 'members_now', privacy: ['profile_visibility' => 'members']);
    renamed('banned', 'banned_now')->forceFill(['status' => 'banned'])->save();
    renamed('leaving', 'leaving_now')->forceFill(['status' => 'pending_deletion'])->save();

    assertProfileMissing($this->get('/u/hidden'));
    assertProfileMissing($this->get('/u/members'));
    assertProfileMissing($this->get('/u/banned'));
    assertProfileMissing($this->get('/u/leaving'));

    $this->actingAs(User::factory()->create())->get('/u/members')->assertRedirect('/u/members_now');
});

it('redirects to a suspended account, which stays listed', function () {
    renamed('chief', 'new_chief')->forceFill(['status' => 'suspended', 'status_reason' => 'Harassment'])->save();

    $this->get('/u/chief')->assertRedirect('/u/new_chief');
});

it('keeps a name deleted accounts hold forever a 404', function () {
    $user = User::factory()->create(['username' => 'someone']);
    UsernameHistory::factory()->permanent()->create(['user_id' => $user->id, 'username' => 'gone', 'released_at' => Date::now()->subDay()]);

    assertProfileMissing($this->get('/u/gone'));
});
