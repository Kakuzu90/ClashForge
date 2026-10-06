<?php

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// specs/11 "Props exposure" and specs/04 §3 for the PlayerCards on /u/{username} (P2-22): a card
// reaches a viewer only when its account page would open for them, and carries nothing private.

beforeEach(function () {
    $this->owner = User::factory()->create(['username' => 'chief', 'email' => 'owner@example.test']);
    $this->member = User::factory()->create();
    foreach ([
        ['#GRJ0P8UV', CocAccountStatus::Verified],
        ['#2PQ8GRJC', CocAccountStatus::Disputed],
        ['#Q0RJ9P2L', CocAccountStatus::Unverified],
        ['#LQ2RJ9P0', CocAccountStatus::Suspended],
    ] as [$tag, $status]) {
        $account = CocAccount::factory()->for($this->owner)->forTag($tag)->create([
            'status' => $status,
            'verified_at' => now(),
            'raw_payload' => ['marker' => 'raw-payload-marker'],
            'api_sync_failures' => 1,
        ]);
        CocAccountClaim::factory()->create(['coc_account_id' => $account->id, 'user_id' => $this->owner->id, 'tag_normalized' => $account->tag_normalized, 'ip_hash' => 'ip-hash-marker']);
    }
});

it('only sends cards whose account page opens for the same viewer', function (string $who, ProfileVisibility $visibility) {
    PrivacySettings::query()->whereKey($this->owner->id)->update(['profile_visibility' => $visibility->value]);
    app(PrivacyPolicyResolver::class)->refresh($this->owner->id);
    $viewer = $who === 'member' ? $this->member : null;
    $viewer === null ? auth()->logout() : $this->actingAs($viewer);

    $ulids = [];
    $this->get('/u/chief')->assertOk()->assertInertia(function (Assert $page) use (&$ulids) {
        $ulids = array_column($page->toArray()['props']['accounts']['cards'], 'ulid');
    });

    expect($ulids)->toHaveCount(2);
    foreach ($ulids as $ulid) {
        $this->get("/accounts/{$ulid}")->assertOk();
    }
})->with([
    'member, public' => ['member', ProfileVisibility::Public],
    'guest, public' => ['guest', ProfileVisibility::Public],
    'member, members-only' => ['member', ProfileVisibility::Members],
]);

it('sends nothing about the owner, the claims, the raw payload or the sync failures', function () {
    $html = $this->get('/u/chief')->assertOk()->getContent();

    expect($html)->not->toContain('owner@example.test')
        ->not->toContain('raw-payload-marker')
        ->not->toContain('ip-hash-marker')
        ->not->toContain('apiSyncFailures')
        ->not->toContain('"userId"')
        ->not->toContain('showCocAccounts')
        ->not->toContain('showClan');
});

it('escapes an in-game name in the page data', function () {
    CocAccount::query()->where('status', CocAccountStatus::Verified)->update(['ign' => '<script>alert(1)</script>']);

    $html = $this->get('/u/chief')->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('gives staff no more cards than any member', function (string $role) {
    $staff = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->moderator()->create();

    $this->actingAs($staff)->get('/u/chief')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('accounts.cards', 2));

    PrivacySettings::query()->whereKey($this->owner->id)->update(['show_coc_accounts' => false]);
    app(PrivacyPolicyResolver::class)->refresh($this->owner->id);

    $this->actingAs($staff)->get('/u/chief')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('accounts.cards', [])->where('accounts.verified', false));
})->with(['moderator', 'admin']);
