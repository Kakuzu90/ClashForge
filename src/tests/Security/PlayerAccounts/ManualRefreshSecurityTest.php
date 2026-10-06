<?php

use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\Support\Coc\InteractsWithCoc;

// P2-20: manual refresh is the owner's own account write (specs/04 §2–3), behind `account.active`,
// CSRF, and the `coc-refresh` limit per user and account (specs/04 §4, specs/11).

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->owner = User::factory()->create();
    $this->account = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->verified()->create(['th_level' => 15]);
});

it("answers another user's account with a 404 and never calls the API (IDOR)", function (?string $role) {
    $other = ($role === null ? User::factory() : User::factory()->{$role}())->create();
    $hidden = CocAccount::factory()->for($this->owner)->forTag('#9VQ0YRJ8')->create();

    $this->actingAs($other)->post("/accounts/{$this->account->ulid}/refresh")->assertNotFound();
    $this->actingAs($other)->post("/accounts/{$hidden->ulid}/refresh")->assertNotFound();
    $this->actingAs($other)->post('/accounts/01J0000000000000000000NONE/refresh')->assertNotFound();

    expect($this->fakeCoc()->calls())->toBe([])
        ->and($this->account->fresh()->th_level)->toBe(15);
})->with(['user' => [null], 'moderator' => ['moderator'], 'admin' => ['admin'], 'super admin' => ['superAdmin']]);

it('refuses a released row its old owner once held', function () {
    $released = CocAccount::factory()->released()->create();

    $this->actingAs($this->owner)->post("/accounts/{$released->ulid}/refresh")->assertNotFound();
});

it('keeps guests and owners who may not write out', function (?string $state, int $status, ?string $redirect) {
    $user = $state === null ? null : User::factory()->{$state}()->create();
    $account = $user === null ? $this->account : CocAccount::factory()->for($user)->forTag('#9VQ0YRJ8')->verified()->create();

    $response = ($user === null ? $this : $this->actingAs($user))->post("/accounts/{$account->ulid}/refresh")->assertStatus($status);
    if ($redirect !== null) {
        $response->assertRedirect($redirect);
    }
    expect($this->fakeCoc()->calls())->toBe([]);
})->with([
    'guest' => [null, 302, '/login'],
    'suspended' => ['suspended', 302, '/account/suspended'],
    'banned' => ['banned', 302, '/login'],
    // `account.active` lets a pending-deletion account through; the policy refuses it.
    'pending deletion' => ['pendingDeletion', 403, null],
]);

it('needs the CSRF token', function () {
    $this->app['env'] = 'production';

    $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/refresh")->assertStatus(419);
    expect($this->fakeCoc()->calls())->toBe([]);
});

it('keys the cooldown per account, so one refresh never blocks the owner\'s other accounts', function () {
    $second = CocAccount::factory()->for($this->owner)->forTag('#9VQ0YRJ8')->verified()->create();
    $this->fakeCoc()->withPlayer([...$this->cocFixture('players/2PQ8GRJC.json'), 'tag' => '#9VQ0YRJ8']);

    $this->actingAs($this->owner)->from('/')->post("/accounts/{$this->account->ulid}/refresh")->assertSessionHas('success');
    $this->actingAs($this->owner)->from('/')->post("/accounts/{$this->account->ulid}/refresh")->assertSessionHas('error');
    $this->actingAs($this->owner)->from('/')->post("/accounts/{$second->ulid}/refresh")->assertSessionHas('success');

    expect($this->fakeCoc()->calls())->toHaveCount(2);
});

it('lets two quick clicks reach the API only once', function () {
    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->owner)->from('/')->post("/accounts/{$this->account->ulid}/refresh");
    }

    expect($this->fakeCoc()->calls())->toHaveCount(1);
});

it('never exposes the cooldown of another user', function () {
    $this->actingAs($this->owner)->from('/')->post("/accounts/{$this->account->ulid}/refresh");

    $this->actingAs(User::factory()->create())->get("/accounts/{$this->account->ulid}")
        ->assertInertia(fn ($page) => $page->where('account.canRefresh', false)->where('account.refreshWaitSeconds', 0));
});

it('records at most a capped number of accounts per viewer, so one login cannot keep every account hot', function () {
    config(['coc.sync.views_per_viewer_per_hour' => 2]);
    $viewer = User::factory()->create();
    $accounts = collect(['#9VQ0YRJ8', '#8QU2PLGR', '#2Q8URJ9L'])->map(fn (string $tag) => CocAccount::factory()->forTag($tag)->verified()->create());

    $accounts->each(fn (CocAccount $account) => $this->actingAs($viewer)->get("/accounts/{$account->ulid}")->assertOk());

    expect($accounts->map(fn (CocAccount $account) => $account->fresh()->last_viewed_at !== null)->all())->toBe([true, true, false]);

    // The capped view did not use up the account's hour: someone else's view still counts.
    $this->actingAs(User::factory()->create())->get("/accounts/{$accounts[2]->ulid}");
    expect($accounts[2]->fresh()->last_viewed_at)->not->toBeNull();
});

it('never records a view of an account the viewer may not see', function () {
    $hidden = CocAccount::factory()->forTag('#9VQ0YRJ8')->create();

    $this->actingAs(User::factory()->create())->get("/accounts/{$hidden->ulid}")->assertNotFound();
    expect($hidden->fresh()->last_viewed_at)->toBeNull();
});
