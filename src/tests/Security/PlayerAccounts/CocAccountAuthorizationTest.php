<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\Coc\InteractsWithCoc;

// specs/04 §1–3: attach and verify own accounts only, verified email, an account allowed to write.

uses(InteractsWithCoc::class);

beforeEach(function () {
    $this->tag = PlayerTag::from('#2PQ8GRJC');
});

it('lets active and restricted accounts with a verified email attach', function (string $state) {
    $user = User::factory()->{$state}()->create();

    expect(app(AttachAccountService::class)->attach($user, $this->tag)->outcome)->toBe(AttachOutcome::Attached);
})->with(['moderator', 'restricted']);

it('refuses attach and preview to accounts that may not', function (string $state) {
    $user = User::factory()->{$state}()->create();

    expect(fn () => app(AttachAccountService::class)->preview($user, $this->tag))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AttachAccountService::class)->attach($user, $this->tag))->toThrow(AuthorizationException::class)
        ->and(CocAccount::query()->count())->toBe(0)
        ->and($this->fakeCoc()->calls())->toBe([]);
})->with(['unverified', 'suspended', 'banned', 'pendingDeletion']);

it("refuses to verify another user's account, as a 404 (IDOR)", function () {
    $owner = User::factory()->create();
    $ulid = app(AttachAccountService::class)->attach($owner, $this->tag)->accountUlid;
    $attacker = User::factory()->create();

    expect(fn () => app(VerifyOwnershipService::class)->verify($attacker, (string) $ulid, 'local-dev-token'))
        ->toThrow(ModelNotFoundException::class)
        ->and(CocAccount::query()->where('ulid', $ulid)->value('status'))->toBe(CocAccountStatus::Unverified);
});

it('refuses to verify for an account that lost its standing after attaching', function () {
    $user = User::factory()->create();
    $ulid = app(AttachAccountService::class)->attach($user, $this->tag)->accountUlid;
    $user->forceFill(['status' => 'suspended', 'status_expires_at' => now()->addDay()])->save();

    expect(fn () => app(VerifyOwnershipService::class)->verify($user, (string) $ulid, 'local-dev-token'))
        ->toThrow(AuthorizationException::class);
});

it('cannot mass-assign ownership columns (specs/11 "Mass assignment")', function (string $column, mixed $value) {
    expect(fn () => (new CocAccount)->fill([$column => $value]))->toThrow(MassAssignmentException::class);
})->with([
    ['tag', '#2PQ8GRJC'],
    ['tag_normalized', '2PQ8GRJC'],
    ['user_id', 1],
    ['status', 'verified'],
    ['verified_at', '2026-10-02 12:00:00'],
    ['verification_method', 'api_token'],
    ['is_featured', true],
]);

it('cannot mass-assign claim history', function (string $column, mixed $value) {
    expect(fn () => (new CocAccountClaim)->fill([$column => $value]))->toThrow(MassAssignmentException::class);
})->with([['user_id', 1], ['coc_account_id', 1], ['status', 'succeeded'], ['failure_reason', null], ['tag_normalized', '2PQ8GRJC']]);

it('refuses verifyTag to accounts that may not attach', function (string $state) {
    $user = User::factory()->{$state}()->create();

    expect(fn () => app(VerifyOwnershipService::class)->verifyTag($user, $this->tag, 'local-dev-token'))->toThrow(AuthorizationException::class)
        ->and($this->fakeCoc()->calls())->toBe([]);
})->with(['unverified', 'suspended', 'banned', 'pendingDeletion']);
