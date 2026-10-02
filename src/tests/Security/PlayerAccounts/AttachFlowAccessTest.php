<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\Users\Data\UpdatePrivacyData;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Services\PrivacySettingsService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Coc\InteractsWithCoc;

// specs/04 §3 (owner-scoped, 404 first) and specs/11 "Account enumeration" on the attach flow's
// routes: another user's rows, the holder's name on the conflict card, the own-accounts list.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->user = User::factory()->create();
    $this->holder = User::factory()->create(['username' => 'holder']);
});

function conflictCard(): Assert
{
    $page = null;
    test()->get('/accounts/attach?tag=%232PQ8GRJC')->assertInertia(function (Assert $inertia) use (&$page) {
        $page = $inertia->where('preview.outcome', 'verified_elsewhere');
    });

    return $page;
}

function setPrivacy(User $user, ProfileVisibility $visibility, bool $showAccounts): void
{
    app(PrivacySettingsService::class)->update($user, new UpdatePrivacyData($visibility, $showAccounts, true, true, true));
}

it('names a holder only where their profile shows the tag (owner decision 2026-10-02)', function (ProfileVisibility $visibility, bool $showAccounts, bool $named) {
    setPrivacy($this->holder, $visibility, $showAccounts);
    CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);

    conflictCard()->where('preview.holderUsername', $named ? 'holder' : null);
})->with([
    'public, shown' => [ProfileVisibility::Public, true, true],
    'members, shown' => [ProfileVisibility::Members, true, true],
    'private' => [ProfileVisibility::Private, true, false],
    'accounts hidden' => [ProfileVisibility::Public, false, false],
]);

it('does not name a banned holder', function () {
    $this->holder->forceFill(['status' => 'banned'])->save();
    CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);

    conflictCard()->where('preview.holderUsername', null);
});

it('stops naming a holder who goes private after the lookup', function () {
    CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);
    conflictCard()->where('preview.holderUsername', 'holder');

    setPrivacy($this->holder, ProfileVisibility::Private, true);

    conflictCard()->where('preview.holderUsername', null);
    expect(json_encode(session()->all()))->not->toContain('"holder"');
});

it("hides another user's rows behind a 404 (IDOR)", function () {
    $unverified = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->create();
    $verified = CocAccount::factory()->for($this->holder)->forTag('#GRJ0P8UV')->verified()->create();
    $this->fakeCoc()->acceptToken(PlayerTag::from('#2PQ8GRJC'), 'good-token');
    $this->actingAs($this->user);

    $this->get("/accounts/{$unverified->ulid}/verify")->assertNotFound();
    $this->post("/accounts/{$unverified->ulid}/verify", ['api_token' => 'good-token'])->assertNotFound();
    $this->get("/accounts/{$verified->ulid}/verified")->assertNotFound();
    expect($unverified->refresh()->status->value)->toBe('unverified');
});

it("never shows the owner's account list to anyone else", function () {
    CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();

    $this->actingAs($this->user)->get('/u/holder')->assertInertia(fn (Assert $page) => $page->where('ownAccounts', null));
    auth()->logout();
    $this->get('/u/holder')->assertInertia(fn (Assert $page) => $page->where('ownAccounts', null));
});
