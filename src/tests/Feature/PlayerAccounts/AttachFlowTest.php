<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Http\Controllers\Accounts\AttachController;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Coc\InteractsWithCoc;

// specs/18 §6 attach flow, specs/13 §3–4; FR-COC-1, FR-COC-2, FR-COC-5, FR-COC-6.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->user = User::factory()->create();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->fakeCoc()->acceptToken($this->tag, 'good-token');
});

function attachLookup(string $tag = '#2PQ8GRJC'): Assert
{
    $page = null;
    test()->post('/accounts/attach/preview', ['tag' => $tag])->assertRedirect();
    test()->get('/accounts/attach?tag='.urlencode($tag))->assertOk()->assertInertia(function (Assert $inertia) use (&$page) {
        $page = $inertia->component('Accounts/Attach');
    });

    return $page;
}

it('sends guests to sign in', function (string $method, string $uri) {
    $this->call($method, $uri)->assertRedirect('/login');
})->with([
    ['GET', '/accounts/attach'],
    ['POST', '/accounts/attach/preview'],
    ['POST', '/accounts/attach'],
    ['POST', '/accounts/attach/verify-tag'],
    ['GET', '/accounts/01J0000000000000000000000A/verify'],
    ['POST', '/accounts/01J0000000000000000000000A/verify'],
]);

it('renders step 1 and prefills a tag from the link', function () {
    $this->actingAs($this->user)->get('/accounts/attach?tag=%232pq8grjc')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Accounts/Attach')
            ->where('tag', '#2PQ8GRJC')->where('preview', null)->where('verifyResult', null)->where('block', null)
            ->where('meta.title', 'Attach an account'));
});

it('shows the confirmation card for a found player', function () {
    $this->actingAs($this->user);

    attachLookup()->where('preview.outcome', 'ready')->where('preview.tag', '#2PQ8GRJC')
        ->where('preview.player.name', fn (string $name) => $name !== '')->where('preview.holderUsername', null);
});

it('keeps the card for that tag only', function () {
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);

    $this->get('/accounts/attach?tag=%23GRJ0P8UV')->assertInertia(fn (Assert $page) => $page->where('preview', null));
    $this->get('/accounts/attach')->assertInertia(fn (Assert $page) => $page->where('preview', null));
});

it('says when the tag does not exist (specs/23 §2)', function () {
    $this->actingAs($this->user);
    $this->fakeCoc()->notFoundNext();

    attachLookup()->where('preview.outcome', 'not_found')->where('preview.player', null);
});

it('says when the API is unavailable, with the wait', function () {
    $this->actingAs($this->user);
    $this->fakeCoc()->failNext(CocFailureReason::Maintenance, 300);

    attachLookup()->where('preview.outcome', 'unavailable')->where('preview.retryAfter', 300);
});

it('says when too many new tags were looked up', function () {
    config(['coc.accounts.attach_per_hour' => 1]);
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#GRJ0P8UV']);

    attachLookup()->where('preview.outcome', 'rate_limited')->where('preview.retryAfter', fn (int $wait) => $wait > 0);
});

it('shows a stale preview with the age of its data (specs/13 §9)', function () {
    $preview = ['outcome' => 'ready', 'tag' => '#2PQ8GRJC', 'player' => [
        'tag' => '#2PQ8GRJC', 'name' => 'Fixture Chief', 'townHallLevel' => 16, 'trophies' => 4800, 'expLevel' => 200,
        'clanName' => null, 'leagueName' => null, 'stale' => true, 'fetchedAt' => '2026-10-02T09:00:00+00:00',
    ], 'accountUlid' => null, 'holderUsername' => null, 'retryAfter' => null];

    $this->actingAs($this->user)->withSession([AttachController::PREVIEW => $preview])->get('/accounts/attach?tag=%232PQ8GRJC')
        ->assertInertia(fn (Assert $page) => $page->where('preview.player.stale', true)->where('preview.player.fetchedAt', '2026-10-02T09:00:00+00:00'));
});

it('links an already attached tag to its verification step', function () {
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();
    $this->actingAs($this->user);

    attachLookup()->where('preview.outcome', 'already_attached')->where('preview.accountUlid', $account->ulid);
});

it('attaches and moves to the token step', function () {
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);

    $response = $this->post('/accounts/attach', ['tag' => '#2PQ8GRJC']);

    $account = CocAccount::query()->where('user_id', $this->user->id)->sole();
    $response->assertRedirect("/accounts/{$account->ulid}/verify");
    expect(session()->has(AttachController::PREVIEW))->toBeFalse();

    $this->get("/accounts/{$account->ulid}/verify")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Accounts/Verify')
            ->where('account.ulid', $account->ulid)->where('account.tag', '#2PQ8GRJC')->where('account.status', 'unverified')
            ->where('result', null)->missing('account.userId'));
});

it('shows why an attach was refused on the card', function () {
    CocAccount::factory()->forTag('#2PQ8GRJC')->verified()->create();

    $this->actingAs($this->user)->post('/accounts/attach', ['tag' => '#2PQ8GRJC'])->assertRedirect('/accounts/attach?tag=%232PQ8GRJC');

    $this->get('/accounts/attach?tag=%232PQ8GRJC')->assertInertia(fn (Assert $page) => $page->where('preview.outcome', 'verified_elsewhere'));
    expect(CocAccount::query()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('verifies with a token and celebrates the first account once', function () {
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();

    $this->actingAs($this->user)->post("/accounts/{$account->ulid}/verify", ['api_token' => ' good-token '])
        ->assertRedirect("/accounts/{$account->ulid}/verified");

    $this->get("/accounts/{$account->ulid}/verified")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Accounts/Verified')
            ->where('firstAccount', true)->where('account.status', 'verified')->where('account.featured', true)
            ->where('profileUsername', $this->user->username));
    $this->get("/accounts/{$account->ulid}/verified")->assertInertia(fn (Assert $page) => $page->where('firstAccount', false));
});

it('does not celebrate a second account', function () {
    CocAccount::factory()->for($this->user)->verified()->featured()->create();
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();

    $this->actingAs($this->user)->post("/accounts/{$account->ulid}/verify", ['api_token' => 'good-token']);

    $this->get("/accounts/{$account->ulid}/verified")->assertInertia(fn (Assert $page) => $page->where('firstAccount', false)->where('account.featured', false));
});

it('explains a refused token on the token step', function (string $setup, string $outcome) {
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();
    match ($setup) {
        'unavailable' => $this->fakeCoc()->failNext(CocFailureReason::ServerError),
        'rate_limited' => config(['coc.accounts.verify_per_hour' => 0]),
        default => null,
    };

    $this->actingAs($this->user)->post("/accounts/{$account->ulid}/verify", ['api_token' => 'stale-token'])
        ->assertRedirect("/accounts/{$account->ulid}/verify");

    $this->get("/accounts/{$account->ulid}/verify")->assertInertia(fn (Assert $page) => $page->where('result.outcome', $outcome));
    expect($account->refresh()->status->value)->toBe('unverified');
})->with([
    'invalid token' => ['invalid', 'invalid_token'],
    'API down' => ['unavailable', 'unavailable'],
    'too many tries' => ['rate_limited', 'rate_limited'],
]);

it('takes over a held tag from the conflict card (specs/13 §4 A)', function () {
    CocAccount::factory()->forTag('#2PQ8GRJC')->verified()->create();
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);

    $response = $this->post('/accounts/attach/verify-tag', ['tag' => '#2PQ8GRJC', 'api_token' => 'good-token']);

    $account = CocAccount::query()->where('user_id', $this->user->id)->sole();
    $response->assertRedirect("/accounts/{$account->ulid}/verified");
    expect($account->status->value)->toBe('verified');
});

it('keeps the conflict card after a failed token', function () {
    CocAccount::factory()->forTag('#2PQ8GRJC')->verified()->create();
    $this->actingAs($this->user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);

    $this->post('/accounts/attach/verify-tag', ['tag' => '#2PQ8GRJC', 'api_token' => 'wrong'])->assertRedirect('/accounts/attach?tag=%232PQ8GRJC');

    $this->get('/accounts/attach?tag=%232PQ8GRJC')->assertInertia(fn (Assert $page) => $page
        ->where('preview.outcome', 'verified_elsewhere')->where('verifyResult.outcome', 'invalid_token'));
});

it('sends a verified row from the token step to the success screen, and a second submit too', function () {
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->verified()->create();

    $this->actingAs($this->user)->get("/accounts/{$account->ulid}/verify")->assertRedirect("/accounts/{$account->ulid}/verified");
    $this->post("/accounts/{$account->ulid}/verify", ['api_token' => 'good-token'])->assertRedirect("/accounts/{$account->ulid}/verified");
});

it('validates the tag and the token', function (string $uri, array $body, string $field) {
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();

    $this->actingAs($this->user)->from('/accounts/attach')->post(str_replace('{ulid}', $account->ulid, $uri), $body)->assertSessionHasErrors($field);
})->with([
    'bad characters' => ['/accounts/attach/preview', ['tag' => '#ABCDE'], 'tag'],
    'too short' => ['/accounts/attach', ['tag' => '#2P'], 'tag'],
    'missing tag' => ['/accounts/attach/verify-tag', ['api_token' => 'x'], 'tag'],
    'empty token' => ['/accounts/{ulid}/verify', ['api_token' => ''], 'api_token'],
    'long token' => ['/accounts/{ulid}/verify', ['api_token' => str_repeat('a', 65)], 'api_token'],
    'token not text' => ['/accounts/attach/verify-tag', ['tag' => '#2PQ8GRJC', 'api_token' => ['a']], 'api_token'],
]);

it('explains an unconfirmed email instead of failing (specs/04 §1)', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/accounts/attach')->assertOk()->assertInertia(fn (Assert $page) => $page->where('block', 'email_unverified'));
    $this->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC'])->assertForbidden();
    expect($this->fakeCoc()->calls())->toBe([]);
});

it('lets a restricted account attach and stops a suspended one', function () {
    $this->actingAs(User::factory()->restricted()->create())->post('/accounts/attach', ['tag' => '#2PQ8GRJC'])->assertRedirect();
    expect(CocAccount::query()->count())->toBe(1);

    // A suspended account only reaches its notice, settings and notifications (specs/23 §7).
    $this->actingAs(User::factory()->suspended()->create())->post('/accounts/attach', ['tag' => '#2PQ8GRJC'])->assertRedirect('/account/suspended');
    expect(CocAccount::query()->count())->toBe(1);
});

it('has no success screen for an unverified row', function () {
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->create();

    $this->actingAs($this->user)->get("/accounts/{$account->ulid}/verified")->assertNotFound();
});
