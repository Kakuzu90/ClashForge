<?php

use App\Domain\Clans\Models\Clan;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Notifications\CocAccountTakenOverNotification;
use App\Domain\PlayerAccounts\Queries\AccountReadModel;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// specs/18 §6 "Player profile" (P2-22): PlayerCards in the Accounts tab, the featured hero card,
// the verified badge and war stars across accounts, each following what the viewer may see.

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->owner = User::factory()->create(['username' => 'chief']);
    $this->member = User::factory()->create();
});

function hideFromOthers(User $owner, array $settings): void
{
    PrivacySettings::query()->whereKey($owner->id)->update($settings);
    app(PrivacyPolicyResolver::class)->refresh($owner->id);
}

it('gives the owner an empty list, so the tab shows the attach CTA', function () {
    $this->actingAs($this->owner)->get('/u/chief')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profile/Show')
            ->where('accounts', ['cards' => [], 'featured' => null, 'warStars' => null, 'verified' => false]));
});

it('shows the owner every row but released ones, featured first, then newest', function () {
    $older = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->create(['ign' => 'Old Chief']);
    $featured = CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->featured()->create(['ign' => 'Main']);
    $suspended = CocAccount::factory()->for($this->owner)->forTag('#Q0RJ9P2L')->create(['status' => CocAccountStatus::Suspended]);
    CocAccount::factory()->for($this->owner)->forTag('#LQ2RJ9P0')->create(['status' => CocAccountStatus::Released]);

    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->has('accounts.cards', 3)
        ->where('accounts.cards.0.ulid', $featured->ulid)
        ->where('accounts.cards.0.featured', true)
        ->where('accounts.cards.1.ulid', $suspended->ulid)
        ->where('accounts.cards.1.statusLabel', 'Suspended')
        ->where('accounts.cards.2.ulid', $older->ulid)
        ->where('accounts.cards.2.status', 'unverified')
        ->where('accounts.featured.ulid', $featured->ulid)
        ->where('accounts.verified', true));
});

it('shows others only the verified and disputed rows, with the featured hero card', function () {
    $featured = CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->featured()->create();
    $disputed = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->disputed()->create();
    CocAccount::factory()->for($this->owner)->forTag('#Q0RJ9P2L')->create();
    CocAccount::factory()->for($this->owner)->forTag('#LQ2RJ9P0')->create(['status' => CocAccountStatus::Suspended]);

    foreach ([$this->member, null] as $viewer) {
        $viewer === null ? auth()->logout() : $this->actingAs($viewer);

        $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page
            ->has('accounts.cards', 2)
            ->where('accounts.cards.0.ulid', $featured->ulid)
            ->where('accounts.cards.1.ulid', $disputed->ulid)
            ->where('accounts.featured.ulid', $featured->ulid)
            ->where('accounts.verified', true));
    }
});

it('adds up war stars over verified and disputed rows only, counting a missing value as zero', function () {
    CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->create(['war_stars' => 1480]);
    CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->disputed()->create(['war_stars' => 20]);
    CocAccount::factory()->for($this->owner)->forTag('#LQ2RJ9P0')->verified()->create(['war_stars' => null]);
    CocAccount::factory()->for($this->owner)->forTag('#Q0RJ9P2L')->create(['war_stars' => 5000]);

    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('accounts.warStars', 1500));
    $this->actingAs($this->member)->get('/u/chief')->assertInertia(fn (Assert $page) => $page->where('accounts.warStars', 1500));
});

it('leaves out war stars and the badge when the owner has only unverified rows', function () {
    CocAccount::factory()->for($this->owner)->forTag('#Q0RJ9P2L')->create();

    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->has('accounts.cards', 1)->where('accounts.warStars', null)->where('accounts.verified', false));
});

it('hides cards, hero, badge and war stars from others when "show accounts" is off', function () {
    CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->featured()->create();
    hideFromOthers($this->owner, ['show_coc_accounts' => false]);

    $this->actingAs($this->member)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('accounts', ['cards' => [], 'featured' => null, 'warStars' => null, 'verified' => false]));

    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->has('accounts.cards', 1)->where('accounts.verified', true));
});

it('reads "Clan not shared" to others when "show clan" is off, but not to the owner', function () {
    $clan = Clan::factory()->forTag('#2Q8URJ9L')->create(['name' => 'Night Owls']);
    CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->create(['clan_id' => $clan->id, 'clan_role' => 'coLeader']);

    $this->actingAs($this->member)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('accounts.cards.0.clan.name', 'Night Owls')->where('accounts.cards.0.clanHidden', false));

    hideFromOthers($this->owner, ['show_clan' => false]);

    $this->actingAs($this->member)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('accounts.cards.0.clan', null)->where('accounts.cards.0.clanHidden', true));
    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('accounts.cards.0.clan.name', 'Night Owls'));
});

it('shows no hero when the featured row is one the viewer may not see, and no substitute', function () {
    CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->create();
    CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->featured()->create();

    expect(app(AccountReadModel::class)->forProfile($this->member, $this->owner->id))
        ->featured->toBeNull()
        ->cards->toHaveCount(1);
});

it('marks stale data on the card, read from storage only', function () {
    CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->create(['api_synced_at' => now()->subDays(10)]);

    $this->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('accounts.cards.0.stale', true)->where('accounts.cards.0.syncedAgeSeconds', 10 * 86400));
});

it('keeps the profile within the query budget with several cards in clans', function () {
    $clans = Clan::factory()->count(3)->sequence(['tag' => '#2Q8URJ9L'], ['tag' => '#8QU0PLR2'], ['tag' => '#PL2Q8URJ'])->create();
    foreach (['#2PQ8GRJC', '#GRJ0P8UV', '#LQ2RJ9P0'] as $i => $tag) {
        CocAccount::factory()->for($this->owner)->forTag($tag)->verified()->create(['clan_id' => $clans[$i]->id]);
    }
    $this->actingAs($this->member)->get('/u/chief');

    DB::enableQueryLog();
    $this->get('/u/chief')->assertOk()->assertInertia(fn (Assert $page) => $page->has('accounts.cards', 3));

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(15);
});

it('links the takeover notice to the attach page with the tag filled in', function () {
    expect(NotificationType::CocAccountTakenOver->render(['tag' => '#2PQ8GRJC'])->url)->toBe('/accounts/attach?tag=%232PQ8GRJC')
        ->and(NotificationType::CocAccountTakenOver->render([])->url)->toBeNull();

    $mail = (new CocAccountTakenOverNotification(['tag' => '#2PQ8GRJC', 'method' => 'api_token']))->toMail($this->owner);
    expect($mail->actionText)->toBe('Verify it again')
        ->and($mail->actionUrl)->toBe(url('/accounts/attach?tag=%232PQ8GRJC'));
});
