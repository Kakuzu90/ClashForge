<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\PlayerAccounts\Jobs\RestoreFeaturedAccountJob;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Services\AccountOwnershipService;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Coc\InteractsWithCoc;

// Detach and featured account (P2-14): specs/13 §6, FR-COC-12, FR-COC-13.

uses(InteractsWithCoc::class, CapturesSecurityLog::class);

beforeEach(function () {
    Date::setTestNow('2026-10-05 12:00:00');
    $this->captureSecurityLog();
    $this->user = User::factory()->create(['username' => 'chief']);
    $this->featured = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->verified()->featured()->create(['verified_at' => now()->subDays(10)]);
    $this->older = CocAccount::factory()->for($this->user)->forTag('#8LQ9JPY2')->verified()->create(['verified_at' => now()->subDays(30)]);
    $this->newer = CocAccount::factory()->for($this->user)->forTag('#9VJ2YRQP')->verified()->create(['verified_at' => now()->subDays(2)]);
    $this->user->forceFill(['verified_accounts_count' => 3])->save();
});

it('detaches an account: released, unowned, claimable, audited (FR-COC-13, specs/13 §6)', function () {
    CocAccountSnapshot::factory()->create(['coc_account_id' => $this->featured->id]);
    $pending = CocAccountClaim::factory()->create(['coc_account_id' => $this->featured->id, 'user_id' => $this->user->id, 'status' => ClaimStatus::Pending]);
    $earlier = CocAccountClaim::factory()->create(['coc_account_id' => $this->featured->id, 'status' => ClaimStatus::Pending]);

    $this->actingAs($this->user)->delete("/accounts/{$this->featured->ulid}", ['current_password' => 'password'])
        ->assertRedirect('/u/chief')
        ->assertSessionHas('success', '#2PQ8GRJC was removed from your account.');

    $row = $this->featured->refresh();
    $audit = AuditLog::query()->where('action', AuditAction::CocAccountReleased)->sole();
    expect($row->user_id)->toBeNull()
        ->and($row->status)->toBe(CocAccountStatus::Released)
        ->and($row->verified_at)->toBeNull()
        ->and($row->verification_method)->toBeNull()
        ->and($row->is_featured)->toBeFalse()
        ->and(CocAccountSnapshot::query()->where('coc_account_id', $row->id)->count())->toBe(1)
        ->and($pending->refresh()->status)->toBe(ClaimStatus::Superseded)
        ->and($earlier->refresh()->status)->toBe(ClaimStatus::Pending)
        ->and($this->user->refresh()->verified_accounts_count)->toBe(2)
        ->and($audit->auditable_type)->toBe(AuditSubject::CocAccount)
        ->and($audit->auditable_id)->toBe($row->id)
        ->and($audit->actor_id)->toBe($this->user->id)
        ->and($audit->before)->toBe(['status' => 'verified', 'user_id' => $this->user->id])
        ->and($audit->after)->toBe(['status' => 'released', 'user_id' => null])
        ->and($audit->context)->toBe(['tag' => '#2PQ8GRJC', 'reason' => 'detach']);
});

it('moves the featured flag to the earliest-verified account left (owner decision 2026-10-05)', function () {
    app(AccountOwnershipService::class)->detach($this->user, $this->featured->ulid, 'password', null);

    expect($this->older->refresh()->is_featured)->toBeTrue()
        ->and($this->newer->refresh()->is_featured)->toBeFalse();
});

it('leaves the featured account alone when another one is detached', function () {
    app(AccountOwnershipService::class)->detach($this->user, $this->older->ulid, 'password', null);

    expect($this->featured->refresh()->is_featured)->toBeTrue()
        ->and(CocAccount::query()->where('user_id', $this->user->id)->where('is_featured', true)->count())->toBe(1);
});

it('drops the count to 0 when the last verified account goes', function () {
    $user = User::factory()->create();
    $only = CocAccount::factory()->for($user)->verified()->featured()->create();
    $unverified = CocAccount::factory()->for($user)->create();
    $user->forceFill(['verified_accounts_count' => 1])->save();

    app(AccountOwnershipService::class)->detach($user, $only->ulid, 'password', null);

    expect($user->refresh()->verified_accounts_count)->toBe(0)
        ->and($unverified->refresh()->is_featured)->toBeFalse();
});

it('detaches an unverified account too', function () {
    $user = User::factory()->create();
    $row = CocAccount::factory()->for($user)->create();

    app(AccountOwnershipService::class)->detach($user, $row->ulid, 'password', null);

    expect($row->refresh()->status)->toBe(CocAccountStatus::Released)
        ->and(AuditLog::query()->where('action', AuditAction::CocAccountReleased)->sole()->before)->toBe(['status' => 'unverified', 'user_id' => $user->id]);
});

it('dispatches CocAccountReleased only after commit, and the owner gets the in-app notice (specs/16 §2)', function () {
    Event::fake([CocAccountReleased::class]);

    DB::transaction(function () {
        app(AccountOwnershipService::class)->detach($this->user, $this->featured->ulid, 'password', null);
        Event::assertNotDispatched(CocAccountReleased::class);
    });

    Event::assertDispatched(CocAccountReleased::class, fn ($e) => $e->accountId === $this->featured->id && $e->userId === $this->user->id && $e->reason === ReleaseReason::Detach);
});

it('writes the Tag released notice, with no link to the account any more', function () {
    app(AccountOwnershipService::class)->detach($this->user, $this->featured->ulid, 'password', null);

    $notice = Notification::query()->where('notifiable_id', $this->user->id)->sole();
    $rendered = NotificationType::CocAccountReleased->render($notice->data['params']);
    expect($notice->type)->toBe(NotificationType::CocAccountReleased->value)
        ->and($notice->data['params'])->toBe(['tag' => '#2PQ8GRJC', 'name' => $this->featured->ign])
        ->and($rendered->title)->toBe('An account was removed from your profile')
        ->and($rendered->body)->toStartWith("#2PQ8GRJC ({$this->featured->ign}) is no longer on your Clash Commons account.")
        ->and($rendered->url)->toBeNull()
        ->and(NotificationType::CocAccountReleased->category())->toBe(NotificationCategory::Ownership);
});

it('reuses the released row when someone attaches the tag again (specs/13 §6)', function () {
    app(AccountOwnershipService::class)->detach($this->user, $this->featured->ulid, 'password', null);
    $next = User::factory()->create();

    $ulid = app(AttachAccountService::class)->attach($next, PlayerTag::from('#2PQ8GRJC'))->accountUlid;

    expect($ulid)->toBe($this->featured->ulid)
        ->and($this->featured->refresh()->user_id)->toBe($next->id)
        ->and($this->featured->status)->toBe(CocAccountStatus::Unverified);
});

it('changes nothing on a wrong password, and logs the guess', function () {
    $this->actingAs($this->user)->from("/accounts/{$this->featured->ulid}")
        ->delete("/accounts/{$this->featured->ulid}", ['current_password' => 'wrong'])
        ->assertRedirect("/accounts/{$this->featured->ulid}")
        ->assertSessionHasErrors(['current_password' => 'That is not your current password.']);

    expect($this->featured->refresh()->status)->toBe(CocAccountStatus::Verified)
        ->and($this->featured->user_id)->toBe($this->user->id)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(collect($this->securityEvents())->pluck('message'))->toContain('auth.password_confirm_failed');
});

it('needs the password', function () {
    $this->actingAs($this->user)->delete("/accounts/{$this->featured->ulid}", [])
        ->assertSessionHasErrors(['current_password' => 'Enter your current password.']);

    expect($this->featured->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('switches the featured account (FR-COC-12)', function () {
    $this->actingAs($this->user)->from("/accounts/{$this->newer->ulid}")
        ->put("/accounts/{$this->newer->ulid}/featured")
        ->assertRedirect("/accounts/{$this->newer->ulid}")
        ->assertSessionHas('success', 'This is now your featured account.');

    expect($this->newer->refresh()->is_featured)->toBeTrue()
        ->and($this->featured->refresh()->is_featured)->toBeFalse()
        ->and(CocAccount::query()->where('user_id', $this->user->id)->where('is_featured', true)->count())->toBe(1);
});

it('features a disputed account the user still holds', function () {
    $disputed = CocAccount::factory()->for($this->user)->forTag('#Q0CR2JU8')->disputed()->create();

    app(AccountOwnershipService::class)->feature($this->user, $disputed->ulid);

    expect($disputed->refresh()->is_featured)->toBeTrue();
});

it('is a no-op to feature the featured account again', function () {
    app(AccountOwnershipService::class)->feature($this->user, $this->featured->ulid);

    expect($this->featured->refresh()->is_featured)->toBeTrue();
});

it('moves the featured flag when the featured account is superseded by a token', function () {
    $tag = PlayerTag::from('#2PQ8GRJC');
    $taker = User::factory()->create();
    $this->fakeCoc()->acceptToken($tag, 'in-game-token');

    app(VerifyOwnershipService::class)->verifyTag($taker, $tag, 'in-game-token');

    expect($this->featured->refresh()->is_featured)->toBeFalse()
        ->and($this->older->refresh()->is_featured)->toBeTrue()
        ->and($this->user->refresh()->verified_accounts_count)->toBe(2);
});

it('moves the featured flag when a dispute takes the featured account away', function (string $how) {
    $claimant = User::factory()->create();
    $disputes = app(DisputeService::class);
    $ulid = $disputes->open($claimant, PlayerTag::from('#2PQ8GRJC'), 'I lost the phone with this account.')->disputeUlid;

    if ($how === 'release') {
        $disputes->release($this->user, $ulid, 'password');
    } else {
        $disputes->respond($this->user, $ulid, 'It is mine.');
        $disputes->decide(User::factory()->admin()->create(), $ulid, $how === 'transfer' ? DisputeDecision::Transfer : DisputeDecision::Suspend, 'Decided on the evidence.');
    }

    // On a release the claimant reuses the row and it may be featured for them: look at the holder's.
    expect(CocAccount::query()->where('user_id', $this->user->id)->where('is_featured', true)->sole()->id)->toBe($this->older->id);
})->with(['release', 'transfer', 'suspend']);

it('gives the owner the detach and featured flags on the account page, and nobody else', function () {
    $this->actingAs($this->user)->get("/accounts/{$this->newer->ulid}")
        ->assertInertia(fn (Assert $page) => $page->component('Accounts/Show')
            ->where('account.canDetach', true)
            ->where('account.canFeature', true));

    $this->actingAs($this->user)->get("/accounts/{$this->featured->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.canDetach', true)->where('account.canFeature', false));

    $this->actingAs(User::factory()->create())->get("/accounts/{$this->newer->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.canDetach', false)->where('account.canFeature', false));

    auth()->logout();
    $this->get("/accounts/{$this->newer->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('account.canDetach', false)->where('account.canFeature', false));
});

it('offers the featured prompt on the success screen only when the account is not featured (specs/18 §6)', function () {
    $this->actingAs($this->user)->get("/accounts/{$this->newer->ulid}/verified")
        ->assertInertia(fn (Assert $page) => $page->component('Accounts/Verified')
            ->where('account.featured', false)
            ->where('account.canFeature', true));

    $this->actingAs($this->user)->get("/accounts/{$this->featured->ulid}/verified")
        ->assertInertia(fn (Assert $page) => $page->where('account.featured', true)->where('account.canFeature', false));
});

it('restores a missing featured account in the repair job, and leaves an existing one alone', function () {
    $this->featured->forceFill(['is_featured' => false])->save();

    (new RestoreFeaturedAccountJob($this->user->id))->handle(app(UserStatusService::class));
    expect($this->older->refresh()->is_featured)->toBeTrue();

    $this->older->forceFill(['is_featured' => false])->save();
    $this->newer->forceFill(['is_featured' => true])->save();
    (new RestoreFeaturedAccountJob($this->user->id))->handle(app(UserStatusService::class));
    expect(CocAccount::query()->where('user_id', $this->user->id)->where('is_featured', true)->sole()->id)->toBe($this->newer->id);
});

it('features nothing when the user holds no account any more', function () {
    $user = User::factory()->create();
    $row = CocAccount::factory()->for($user)->create();

    (new RestoreFeaturedAccountJob($user->id))->handle(app(UserStatusService::class));

    expect($row->refresh()->is_featured)->toBeFalse();
});
