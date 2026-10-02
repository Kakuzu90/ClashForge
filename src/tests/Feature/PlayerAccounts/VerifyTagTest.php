<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Support\Coc\InteractsWithCoc;

// specs/13 §4 path A: the real owner takes a held tag back with a token, without attaching first
// (attach is refused while someone else holds it).

uses(InteractsWithCoc::class);

beforeEach(function () {
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->holder = User::factory()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->featured()->create();
    $this->owner = User::factory()->create();
    $this->fakeCoc()->acceptToken($this->tag, 'owner-token');
});

it('takes a held tag with a token, creating and promoting the row in one go', function () {
    Event::fake([CocAccountOwnershipTransferred::class]);
    expect(app(AttachAccountService::class)->attach($this->owner, $this->tag)->outcome)->toBe(AttachOutcome::VerifiedElsewhere);

    $result = app(VerifyOwnershipService::class)->verifyTag($this->owner, $this->tag, 'owner-token');

    $mine = CocAccount::query()->where('user_id', $this->owner->id)->sole();
    expect($result->outcome)->toBe(VerifyOutcome::Verified)
        ->and($result->accountUlid)->toBe($mine->ulid)
        ->and($result->superseded)->toBeTrue()
        ->and($mine->status)->toBe(CocAccountStatus::Verified)
        ->and($mine->ign)->toBe('Fixture Chief')
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Unverified)
        ->and(AuditLog::query()->count())->toBe(1)
        ->and(CocAccountClaim::query()->where('user_id', $this->owner->id)->where('status', ClaimStatus::Succeeded)->count())->toBe(2);

    Event::assertDispatched(CocAccountOwnershipTransferred::class, fn ($e) => $e->fromUserId === $this->holder->id && $e->toUserId === $this->owner->id);
});

it('writes no account row when the token fails', function (string $token, ?CocFailureReason $failure, VerifyOutcome $outcome, ClaimFailureReason $reason) {
    if ($failure !== null) {
        $this->fakeCoc()->failNext($failure);
    }

    $result = app(VerifyOwnershipService::class)->verifyTag($this->owner, $this->tag, $token);

    expect($result->outcome)->toBe($outcome)
        ->and($result->accountUlid)->toBeNull()
        ->and(CocAccount::query()->where('user_id', $this->owner->id)->exists())->toBeFalse()
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified)
        ->and(CocAccountClaim::query()->where('user_id', $this->owner->id)->sole()->failure_reason)->toBe($reason);
})->with([
    'invalid token' => ['stolen-guess', null, VerifyOutcome::InvalidToken, ClaimFailureReason::InvalidToken],
    'API down' => ['owner-token', CocFailureReason::Timeout, VerifyOutcome::Unavailable, ClaimFailureReason::ApiError],
]);

it('uses the existing row when the user already attached the tag', function () {
    $other = PlayerTag::from('#LQ2RJ9P0');
    $ulid = app(AttachAccountService::class)->attach($this->owner, $other)->accountUlid;
    $this->fakeCoc()->acceptToken($other, 'other-token');

    $result = app(VerifyOwnershipService::class)->verifyTag($this->owner, $other, 'other-token');

    expect($result->accountUlid)->toBe($ulid)
        ->and(CocAccount::query()->where('user_id', $this->owner->id)->count())->toBe(1);
});

it('shares the coc-verify limit', function () {
    foreach (range(1, (int) config('coc.accounts.verify_per_hour')) as $i) {
        app(VerifyOwnershipService::class)->verifyTag($this->owner, $this->tag, 'wrong');
    }

    expect(app(VerifyOwnershipService::class)->verifyTag($this->owner, $this->tag, 'owner-token')->outcome)->toBe(VerifyOutcome::RateLimited)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified);
});
