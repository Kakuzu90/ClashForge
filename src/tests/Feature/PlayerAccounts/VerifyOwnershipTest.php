<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\CocIntegration\Enums\CocFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Coc\InteractsWithCoc;

// specs/13 §3.1 and §9; FR-COC-4, FR-COC-5, FR-COC-6, FR-COC-7, FR-COC-8.

uses(InteractsWithCoc::class, CapturesSecurityLog::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->captureSecurityLog();
    $this->user = User::factory()->create();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->account = app(AttachAccountService::class)->attach($this->user, $this->tag)->accountUlid;
    $this->token = 'in-game-token';
    $this->fakeCoc()->acceptToken($this->tag, $this->token);
    $this->verify = fn (?User $as = null, ?string $token = null, ?string $ulid = null) => app(VerifyOwnershipService::class)
        ->verify($as ?? $this->user, $ulid ?? $this->account, $token ?? $this->token);
});

it('verifies with a valid token and features the first account (FR-COC-5)', function () {
    Event::fake([CocAccountVerified::class, CocAccountOwnershipTransferred::class]);

    $result = ($this->verify)();

    $account = CocAccount::query()->where('ulid', $this->account)->sole();
    expect($result->outcome)->toBe(VerifyOutcome::Verified)
        ->and($result->featured)->toBeTrue()
        ->and($result->superseded)->toBeFalse()
        ->and($account->status)->toBe(CocAccountStatus::Verified)
        ->and($account->verification_method)->toBe(VerificationMethod::ApiToken)
        ->and($account->verified_at?->equalTo(Date::now()))->toBeTrue()
        ->and($account->is_featured)->toBeTrue()
        ->and($this->user->refresh()->verified_accounts_count)->toBe(1);

    expect(CocAccountClaim::query()->orderBy('id')->pluck('status')->all())->toBe([ClaimStatus::Succeeded, ClaimStatus::Succeeded]);

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::CocAccountVerified)
        ->and($audit->auditable_type)->toBe(AuditSubject::CocAccount)
        ->and($audit->auditable_id)->toBe($account->id)
        ->and($audit->actor_id)->toBe($this->user->id)
        ->and($audit->before)->toEqual(['status' => 'unverified', 'verified_user_ids' => []])
        ->and($audit->after)->toEqual(['status' => 'verified', 'verified_user_ids' => [$this->user->id]])
        ->and($audit->context)->toEqual(['tag' => '#2PQ8GRJC', 'method' => 'api_token']);

    Event::assertDispatched(CocAccountVerified::class, fn ($e) => $e->accountId === $account->id && $e->userId === $this->user->id);
    Event::assertNotDispatched(CocAccountOwnershipTransferred::class);
});

it('does not feature a second verified account', function () {
    CocAccount::factory()->for($this->user)->verified()->featured()->create();

    expect(($this->verify)()->featured)->toBeFalse()
        ->and($this->user->refresh()->verified_accounts_count)->toBe(2);
});

it('refuses an invalid token with retry guidance and changes nothing (specs/13 §3 step 8)', function () {
    $result = ($this->verify)(token: 'expired-token');

    expect($result->outcome)->toBe(VerifyOutcome::InvalidToken)
        ->and(CocAccount::query()->where('ulid', $this->account)->value('status'))->toBe(CocAccountStatus::Unverified)
        ->and(CocAccountClaim::query()->latest('id')->first()->failure_reason)->toBe(ClaimFailureReason::InvalidToken)
        ->and(collect($this->securityEvents())->pluck('message'))->toContain('coc.verification_failed');
});

it('changes nothing when the API is down (specs/09 §7, specs/13 §9)', function () {
    $this->fakeCoc()->failNext(CocFailureReason::ServerError);

    $result = ($this->verify)();

    expect($result->outcome)->toBe(VerifyOutcome::Unavailable)
        ->and(CocAccount::query()->where('ulid', $this->account)->value('status'))->toBe(CocAccountStatus::Unverified)
        ->and(CocAccountClaim::query()->latest('id')->first()->failure_reason)->toBe(ClaimFailureReason::ApiError)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('reports a tag the API no longer knows', function () {
    $this->fakeCoc()->notFoundNext();

    expect(($this->verify)()->outcome)->toBe(VerifyOutcome::NotFound)
        ->and(CocAccountClaim::query()->latest('id')->first()->failure_reason)->toBe(ClaimFailureReason::NotFound);
});

it('supersedes a verified holder, who keeps an unverified row (specs/13 §3.1, owner decision 2026-10-02)', function () {
    Event::fake([CocAccountVerified::class, CocAccountOwnershipTransferred::class]);
    $holder = User::factory()->create();
    $held = CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->featured()->create();
    app(UserStatusService::class)->syncVerifiedAccounts($holder->id, 1);
    CocAccountClaim::factory()->create(['coc_account_id' => $held->id, 'user_id' => $holder->id, 'tag_normalized' => '2PQ8GRJC']);

    $result = ($this->verify)();

    $held->refresh();
    expect($result->superseded)->toBeTrue()
        ->and($held->status)->toBe(CocAccountStatus::Unverified)
        ->and($held->user_id)->toBe($holder->id)
        ->and($held->is_featured)->toBeFalse()
        ->and($held->verified_at)->toBeNull()
        ->and($holder->refresh()->verified_accounts_count)->toBe(0)
        ->and($this->user->refresh()->verified_accounts_count)->toBe(1)
        ->and(CocAccountClaim::query()->where('coc_account_id', $held->id)->value('status'))->toBe(ClaimStatus::Superseded)
        ->and(AuditLog::query()->sole()->before)->toEqual(['status' => 'unverified', 'verified_user_ids' => [$holder->id]])
        ->and(collect($this->securityEvents())->pluck('message'))->toContain('coc.ownership_superseded');

    Event::assertDispatched(CocAccountOwnershipTransferred::class, fn ($e) => $e->fromUserId === $holder->id
        && $e->toUserId === $this->user->id && $e->method === VerificationMethod::ApiToken);
});

it('supersedes a disputed holder too', function () {
    $held = CocAccount::factory()->forTag('#2PQ8GRJC')->disputed()->create();

    ($this->verify)();

    expect($held->refresh()->status)->toBe(CocAccountStatus::Unverified)
        ->and(CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->where('status', 'verified')->count())->toBe(1);
});

it('beats other unverified claims without touching them (specs/13 rule 3)', function () {
    $other = CocAccount::factory()->forTag('#2PQ8GRJC')->create();

    ($this->verify)();

    expect($other->refresh()->status)->toBe(CocAccountStatus::Unverified);
});

it('refuses to verify a row that is already verified', function () {
    ($this->verify)();

    expect(fn () => ($this->verify)())->toThrow(AuthorizationException::class)
        ->and(AuditLog::query()->count())->toBe(1);
});

it('lets the later of two verifications win and keeps one verified owner (specs/13 §9)', function () {
    $other = User::factory()->create();
    $otherUlid = app(AttachAccountService::class)->attach($other, $this->tag)->accountUlid;
    $this->fakeCoc()->acceptToken($this->tag, 'other-token');

    ($this->verify)();
    ($this->verify)(as: $other, token: 'other-token', ulid: $otherUlid);

    expect(CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->where('status', 'verified')->sole()->user_id)->toBe($other->id)
        ->and($this->user->refresh()->verified_accounts_count)->toBe(0)
        ->and($other->refresh()->verified_accounts_count)->toBe(1);
});

it('holds the events until the transaction commits', function () {
    Event::fake([CocAccountVerified::class]);

    DB::beginTransaction();
    ($this->verify)();
    Event::assertNotDispatched(CocAccountVerified::class);
    DB::commit();

    Event::assertDispatched(CocAccountVerified::class);
});

it('counts every attempt against coc-verify (specs/09 §9)', function () {
    $limit = (int) config('coc.accounts.verify_per_hour');

    foreach (range(1, $limit) as $i) {
        ($this->verify)(token: 'wrong');
    }
    $over = ($this->verify)();

    expect($over->outcome)->toBe(VerifyOutcome::RateLimited)
        ->and($over->retryAfter)->toBeGreaterThan(0)
        ->and(CocAccountClaim::query()->latest('id')->first()->failure_reason)->toBe(ClaimFailureReason::RateLimited)
        ->and(CocAccount::query()->where('ulid', $this->account)->value('status'))->toBe(CocAccountStatus::Unverified);
    expect(collect($this->fakeCoc()->calls())->where('endpoint', 'players.verifytoken'))->toHaveCount($limit);
});

it('locks every affected user before it reads the featured flag or recounts (specs/13 §9)', function () {
    $holder = User::factory()->create();
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->mock(UserStatusService::class, function ($mock) use ($holder) {
        $mock->shouldReceive('lockAccounts')->once()->with([$this->user->id, $holder->id])->ordered();
        $mock->shouldReceive('syncVerifiedAccounts')->twice()->ordered();
    });

    expect(($this->verify)()->superseded)->toBeTrue();
});

it('features only one account and counts both when one user verifies two tags', function () {
    $second = PlayerTag::from('#LQ2RJ9P0');
    $secondUlid = (string) app(AttachAccountService::class)->attach($this->user, $second)->accountUlid;
    $this->fakeCoc()->acceptToken($second, 'second-token');

    $first = ($this->verify)();
    $other = ($this->verify)(token: 'second-token', ulid: $secondUlid);

    expect($first->featured)->toBeTrue()
        ->and($other->featured)->toBeFalse()
        ->and(CocAccount::query()->where('user_id', $this->user->id)->where('is_featured', true)->count())->toBe(1)
        ->and($this->user->refresh()->verified_accounts_count)->toBe(2);
});

it('writes one claim row for a run of throttled attempts (specs/11 §3)', function () {
    foreach (range(1, (int) config('coc.accounts.verify_per_hour') + 5) as $i) {
        ($this->verify)(token: 'wrong');
    }

    expect(CocAccountClaim::query()->where('failure_reason', ClaimFailureReason::RateLimited)->count())->toBe(1)
        ->and(collect($this->securityEvents())->where('context.reason', 'rate_limited'))->toHaveCount(1);
});

it("closes only the verifier's own pending claims on a reused row", function () {
    $earlier = User::factory()->create();
    CocAccountClaim::factory()->create([
        'coc_account_id' => CocAccount::query()->where('ulid', $this->account)->value('id'),
        'user_id' => $earlier->id,
        'tag_normalized' => '2PQ8GRJC',
    ]);

    ($this->verify)();

    expect(CocAccountClaim::query()->where('user_id', $earlier->id)->sole()->status)->toBe(ClaimStatus::Pending);
});
