<?php

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Services\AccountOwnershipService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

// Detach and featured (P2-14): own rows only, an account allowed to write, the password typed again
// (specs/04 §2–3, specs/11 "CSRF").

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->account = CocAccount::factory()->for($this->owner)->verified()->create();
});

it("answers another user's account with a 404 on detach and feature (IDOR)", function () {
    $attacker = User::factory()->create();

    $this->actingAs($attacker)->delete("/accounts/{$this->account->ulid}", ['current_password' => 'password'])->assertNotFound();
    $this->actingAs($attacker)->put("/accounts/{$this->account->ulid}/featured")->assertNotFound();

    expect($this->account->refresh()->user_id)->toBe($this->owner->id)
        ->and($this->account->status)->toBe(CocAccountStatus::Verified)
        ->and($this->account->is_featured)->toBeFalse();
});

it('keeps guests out', function () {
    $this->delete("/accounts/{$this->account->ulid}", ['current_password' => 'password'])->assertRedirect('/login');
    $this->put("/accounts/{$this->account->ulid}/featured")->assertRedirect('/login');

    expect($this->account->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('lets restricted owners detach and feature: an account write, not a content write (specs/04 §3)', function () {
    $owner = User::factory()->restricted()->create();
    $account = CocAccount::factory()->for($owner)->verified()->create();

    expect(Gate::forUser($owner)->allows('feature', $account))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('detach', $account))->toBeTrue();
});

it('refuses owners who may not write', function (string $state) {
    $owner = User::factory()->{$state}()->create();
    $account = CocAccount::factory()->for($owner)->verified()->create();

    expect(fn () => app(AccountOwnershipService::class)->detach($owner, $account->ulid, 'password', null))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AccountOwnershipService::class)->feature($owner, $account->ulid))->toThrow(AuthorizationException::class)
        ->and($account->refresh()->status)->toBe(CocAccountStatus::Verified);
})->with(['suspended', 'banned', 'pendingDeletion']);

it('rechecks the standing under the lock', function () {
    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}");
    User::query()->whereKey($this->owner->id)->update(['status' => 'suspended', 'status_expires_at' => now()->addDay()]);

    expect(fn () => app(AccountOwnershipService::class)->detach($this->owner, $this->account->ulid, 'password', null))->toThrow(AuthorizationException::class)
        ->and($this->account->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('does not detach disputed or suspended rows, nor feature unverified ones (owner decision 2026-10-05)', function () {
    $disputed = CocAccount::factory()->for($this->owner)->disputed()->create();
    $suspended = CocAccount::factory()->for($this->owner)->state(['status' => CocAccountStatus::Suspended])->create();
    $unverified = CocAccount::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->delete("/accounts/{$disputed->ulid}", ['current_password' => 'password'])->assertForbidden();
    $this->actingAs($this->owner)->delete("/accounts/{$suspended->ulid}", ['current_password' => 'password'])->assertForbidden();
    $this->actingAs($this->owner)->put("/accounts/{$unverified->ulid}/featured")->assertForbidden();
    $this->actingAs($this->owner)->put("/accounts/{$suspended->ulid}/featured")->assertForbidden();

    expect($disputed->refresh()->status)->toBe(CocAccountStatus::Disputed)
        ->and($suspended->refresh()->status)->toBe(CocAccountStatus::Suspended)
        ->and($unverified->refresh()->is_featured)->toBeFalse();
});

it('cannot detach a released row it once held', function () {
    $released = CocAccount::factory()->released()->create();

    expect(fn () => app(AccountOwnershipService::class)->detach($this->owner, $released->ulid, 'password', null))->toThrow(ModelNotFoundException::class);
});

it('limits password guesses with the shared password-confirm bucket', function () {
    $this->actingAs($this->owner)->from("/accounts/{$this->account->ulid}");
    for ($attempt = 0; $attempt < (int) config('platform.auth.password_confirm_per_minute'); $attempt++) {
        $this->delete("/accounts/{$this->account->ulid}", ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    }

    $this->delete("/accounts/{$this->account->ulid}", ['current_password' => 'password'])
        ->assertSessionHasErrors('current_password');

    expect(session('errors')->first('current_password'))->toStartWith('Too many attempts.')
        ->and($this->account->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('keeps the password out of the session after a failed attempt', function () {
    $this->actingAs($this->owner)->from("/accounts/{$this->account->ulid}")
        ->delete("/accounts/{$this->account->ulid}", ['current_password' => 'not-it-but-secret']);

    expect(session()->getOldInput('current_password'))->toBeNull();
});
