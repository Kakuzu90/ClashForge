<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\ClaimMethod;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Enums\VerifyOutcome;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeClosed;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeInfoRequested;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeOpened;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Coc\InteractsWithCoc;

// specs/13 §5 and §9; FR-COC-6, FR-COC-7, FR-COC-8.

uses(InteractsWithCoc::class, CapturesSecurityLog::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->captureSecurityLog();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->holder = User::factory()->create();
    $this->claimant = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->featured()->create();
    $this->disputes = fn () => app(DisputeService::class);
    $this->open = fn (?User $as = null, array $evidence = []) => ($this->disputes)()->open($as ?? $this->claimant, $this->tag, 'I lost the phone with this account.', $evidence);
    // Opened and answered by the holder: with the admins, where the tag may move.
    $this->withAdmins = function (): string {
        $ulid = ($this->open)()->disputeUlid;
        ($this->disputes)()->respond($this->holder, $ulid, 'It is mine.');

        return $ulid;
    };
});

function evidenceFor(User $user, int $count = 1): array
{
    return Media::factory()->count($count)->collection(MediaCollection::Evidence)->ready()->create(['user_id' => $user->id])->pluck('ulid')->all();
}

it('opens a dispute and puts the holder row under review (specs/13 §5 step 2)', function () {
    Event::fake([CocAccountDisputeOpened::class]);
    $media = evidenceFor($this->claimant, 2);

    $result = ($this->open)(evidence: $media);

    $dispute = CocAccountDispute::query()->sole();
    expect($result->status)->toBe(DisputeStatus::Open)
        ->and($result->disputeUlid)->toBe($dispute->ulid)
        ->and($dispute->current_holder_id)->toBe($this->holder->id)
        ->and($dispute->coc_account_id)->toBe($this->held->id)
        ->and($dispute->evidence)->toHaveCount(1)
        ->and($dispute->evidence[0]['party'])->toBe('claimant')
        ->and($dispute->evidence[0]['media'])->toBe($media)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed)
        ->and($this->held->is_featured)->toBeTrue()
        ->and(Media::query()->whereIn('ulid', $media)->pluck('attachable_id')->unique()->all())->toBe([$dispute->id])
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::CocDisputeOpened)
        ->and(collect($this->securityEvents())->pluck('message'))->toContain('coc.dispute_opened');
    Event::assertDispatched(CocAccountDisputeOpened::class, fn ($e) => $e->claimantId === $this->claimant->id && $e->holderId === $this->holder->id);
});

it('refuses to open what the guardrails forbid', function (string $case, DisputeRefusal $refusal) {
    match ($case) {
        'not held' => $this->held->forceFill(['status' => CocAccountStatus::Unverified])->save(),
        'already disputed' => ($this->open)(User::factory()->create()),
        'suspended' => $this->held->forceFill(['status' => CocAccountStatus::Suspended])->save(),
        'too many' => config(['coc.disputes.max_open_per_user' => 0]),
        'barred' => CocAccountDispute::factory()->count(2)->status(DisputeStatus::ResolvedDenied)->create(['claimant_id' => $this->claimant->id, 'decided_at' => now()->subDays(10)]),
        default => null,
    };

    $as = $case === 'own account' ? $this->holder : $this->claimant;

    expect(($this->open)($as)->refusal)->toBe($refusal)
        ->and(CocAccountDispute::query()->where('claimant_id', $as->id)->active()->count())->toBe(0);
})->with([
    'not held' => ['not held', DisputeRefusal::NotHeld],
    'own account' => ['own account', DisputeRefusal::OwnAccount],
    'already disputed' => ['already disputed', DisputeRefusal::AlreadyDisputed],
    'suspended tag' => ['suspended', DisputeRefusal::TagSuspended],
    'too many open' => ['too many', DisputeRefusal::TooManyOpen],
    'barred after two denials' => ['barred', DisputeRefusal::Barred],
]);

it('lifts the bar once the denials are older than bar_days', function () {
    CocAccountDispute::factory()->count(2)->status(DisputeStatus::ResolvedDenied)->create(['claimant_id' => $this->claimant->id, 'decided_at' => now()->subDays(91)]);

    expect(($this->open)()->status)->toBe(DisputeStatus::Open);
});

it('needs a reason and limits the evidence', function () {
    expect(fn () => ($this->disputes)()->open($this->claimant, $this->tag, '   '))->toThrow(ValidationException::class)
        ->and(fn () => ($this->disputes)()->open($this->claimant, $this->tag, str_repeat('a', 1001)))->toThrow(ValidationException::class)
        ->and(fn () => ($this->open)(evidence: evidenceFor($this->claimant, 4)))->toThrow(ValidationException::class);
});

it('takes the holder answer and sends the dispute to the admins (specs/13 §5 3b)', function () {
    $ulid = ($this->open)()->disputeUlid;

    $result = ($this->disputes)()->respond($this->holder, $ulid, 'This has been my account since 2014.', evidenceFor($this->holder));

    $dispute = CocAccountDispute::query()->sole();
    expect($result->status)->toBe(DisputeStatus::AwaitingAdmin)
        ->and($dispute->status)->toBe(DisputeStatus::AwaitingAdmin)
        ->and(collect($dispute->evidence)->pluck('party')->all())->toBe(['holder'])
        ->and(($this->disputes)()->respond($this->claimant, $ulid, 'More from me')->refusal)->toBe(DisputeRefusal::NotYourTurn);
});

it('lets the claimant answer only when an admin asked them', function () {
    Event::fake([CocAccountDisputeInfoRequested::class]);
    $ulid = ($this->open)()->disputeUlid;

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::AskClaimant, 'Need the Supercell ID receipt.')->status)->toBe(DisputeStatus::AwaitingClaimant)
        ->and(($this->disputes)()->respond($this->holder, $ulid, 'Nothing to add')->refusal)->toBe(DisputeRefusal::NotYourTurn)
        ->and(($this->disputes)()->respond($this->claimant, $ulid, 'Receipt attached', evidenceFor($this->claimant))->status)->toBe(DisputeStatus::AwaitingAdmin)
        ->and(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::AskHolder, 'Explain the name change.')->status)->toBe(DisputeStatus::AwaitingHolder);
    Event::assertDispatched(CocAccountDisputeInfoRequested::class, 2);
});

it('lets the claimant withdraw only before the holder answers, so a denial cannot be dodged', function () {
    $ulid = ($this->withAdmins)();

    expect(($this->disputes)()->withdraw($this->claimant, $ulid)->refusal)->toBe(DisputeRefusal::NotYourTurn)
        ->and(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::AwaitingAdmin);
});

it('refuses to reopen the same tag soon after withdrawing', function () {
    $ulid = ($this->open)()->disputeUlid;
    ($this->disputes)()->withdraw($this->claimant, $ulid);

    expect(($this->open)()->refusal)->toBe(DisputeRefusal::RecentlyWithdrawn);
    Date::setTestNow('2026-11-02 12:00:00');
    expect(($this->open)()->status)->toBe(DisputeStatus::Open);
});

it('counts a withdrawal for not answering an admin toward the bar', function () {
    CocAccountDispute::factory()->status(DisputeStatus::ResolvedDenied)->create(['claimant_id' => $this->claimant->id, 'decided_at' => now()->subDay()]);
    CocAccountDispute::factory()->status(DisputeStatus::Withdrawn)->create(['claimant_id' => $this->claimant->id, 'decided_at' => now()->subDay(), 'closed_by' => 'sweep']);

    expect(($this->open)()->refusal)->toBe(DisputeRefusal::Barred);
});

it('lets the claimant withdraw, and the holder is verified again', function () {
    $ulid = ($this->open)()->disputeUlid;

    expect(($this->disputes)()->withdraw($this->claimant, $ulid)->status)->toBe(DisputeStatus::Withdrawn)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified)
        ->and(($this->disputes)()->withdraw($this->claimant, $ulid)->refusal)->toBe(DisputeRefusal::Closed);
});

it('hands the tag to the claimant when the holder releases it (specs/13 §5 3c, owner decision 2026-10-02)', function () {
    Event::fake([CocAccountVerified::class, CocAccountOwnershipTransferred::class, CocAccountDisputeClosed::class]);
    $ulid = ($this->open)()->disputeUlid;

    expect(($this->disputes)()->release($this->holder, $ulid)->status)->toBe(DisputeStatus::ResolvedTransfer);

    $granted = CocAccount::query()->where('user_id', $this->claimant->id)->sole();
    expect($granted->id)->toBe($this->held->id)
        ->and($granted->status)->toBe(CocAccountStatus::Verified)
        ->and($granted->verification_method)->toBe(VerificationMethod::Admin)
        ->and($granted->is_featured)->toBeTrue()
        ->and(CocAccount::query()->where('user_id', $this->holder->id)->exists())->toBeFalse()
        ->and($this->claimant->refresh()->verified_accounts_count)->toBe(1)
        ->and($this->holder->refresh()->verified_accounts_count)->toBe(0)
        ->and(CocAccountClaim::query()->where('user_id', $this->claimant->id)->sole()->method)->toBe(ClaimMethod::Dispute)
        ->and(CocAccountDispute::query()->sole()->decided_by)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::CocDisputeClosed)->sole()->context)->toMatchArray(['by' => 'holder_release', 'from_user_id' => $this->holder->id, 'to_user_id' => $this->claimant->id]);
    Event::assertDispatched(CocAccountVerified::class, fn ($e) => $e->userId === $this->claimant->id);
    Event::assertNotDispatched(CocAccountOwnershipTransferred::class);
    Event::assertDispatched(CocAccountDisputeClosed::class, fn ($e) => $e->status === DisputeStatus::ResolvedTransfer);
});

it('transfers on an admin decision, the holder keeping an unverified row (specs/13 §5 step 5)', function () {
    $ulid = ($this->withAdmins)();

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Receipt matches the Supercell ID.')->status)->toBe(DisputeStatus::ResolvedTransfer);

    $granted = CocAccount::query()->where('user_id', $this->claimant->id)->sole();
    $action = ModerationAction::query()->sole();
    $dispute = CocAccountDispute::query()->sole();
    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Unverified)
        ->and($this->held->user_id)->toBe($this->holder->id)
        ->and($this->held->is_featured)->toBeFalse()
        ->and($granted->status)->toBe(CocAccountStatus::Verified)
        ->and($granted->verification_method)->toBe(VerificationMethod::Admin)
        ->and($granted->ign)->toBe($this->held->ign)
        ->and(CocAccountClaim::query()->where('user_id', $this->claimant->id)->sole()->method)->toBe(ClaimMethod::Admin)
        ->and($action->action)->toBe(ModerationActionType::TransferOwnership)
        ->and($action->actor_id)->toBe($this->admin->id)
        ->and($action->metadata)->toMatchArray(['from_user_id' => $this->holder->id, 'to_user_id' => $this->claimant->id])
        ->and($dispute->decided_by)->toBe($this->admin->id)
        ->and($dispute->assigned_admin_id)->toBe($this->admin->id)
        ->and($dispute->decision_note)->toBe('Receipt matches the Supercell ID.')
        ->and(CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->whereIn('status', ['verified', 'disputed'])->count())->toBe(1)
        ->and($this->claimant->refresh()->verified_accounts_count)->toBe(1)
        ->and($this->holder->refresh()->verified_accounts_count)->toBe(0);
});

it('sends no token-takeover notice for an admin transfer; the decision notice is P2-18', function () {
    $ulid = ($this->withAdmins)();

    ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Clear evidence.');

    expect(Notification::query()->where('type', NotificationType::CocAccountTakenOver->value)->count())->toBe(0)
        ->and(Notification::query()->where('notifiable_id', $this->claimant->id)->where('type', NotificationType::CocAccountVerified->value)->count())->toBe(1);
});

it('denies and puts the holder back, the default under doubt', function () {
    $ulid = ($this->open)()->disputeUlid;

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'Screenshots only, no receipt.')->status)->toBe(DisputeStatus::ResolvedDenied)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified)
        ->and(ModerationAction::query()->sole()->action)->toBe(ModerationActionType::Dismiss)
        ->and(ModerationAction::query()->sole()->target_user_id)->toBe($this->claimant->id);
});

it('will not let a banned holder keep the tag (specs/13 §9)', function () {
    $ulid = ($this->open)()->disputeUlid;
    $this->holder->forceFill(['status' => 'banned'])->save();

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'Deny')->refusal)->toBe(DisputeRefusal::HolderCannotKeep)
        ->and(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Open);
});

it('suspends a tag neither party should get, and then nobody can attach or verify it', function () {
    $ulid = ($this->withAdmins)();

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Suspend, 'Both accounts look fake.')->status)->toBe(DisputeStatus::ResolvedSuspended)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Suspended)
        ->and($this->holder->refresh()->verified_accounts_count)->toBe(0)
        ->and(ModerationAction::query()->sole()->action)->toBe(ModerationActionType::Suspend);

    $someone = User::factory()->create();
    $this->fakeCoc()->acceptToken($this->tag, 'good');
    expect(app(AttachAccountService::class)->preview($someone, $this->tag)->outcome)->toBe(AttachOutcome::TagSuspended)
        ->and(app(AttachAccountService::class)->attach($someone, $this->tag)->outcome)->toBe(AttachOutcome::TagSuspended)
        ->and(app(VerifyOwnershipService::class)->verifyTag($someone, $this->tag, 'good')->outcome)->toBe(VerifyOutcome::TagSuspended)
        ->and($this->fakeCoc()->calls())->toBe([]);
});

it('needs a note for every decision', function () {
    $ulid = ($this->open)()->disputeUlid;

    expect(fn () => ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, ' '))->toThrow(ValidationException::class);
});

it('keeps decisions to admins who are not a party (specs/04 §2, specs/13 §5)', function (string $who) {
    $ulid = ($this->open)()->disputeUlid;
    $actor = match ($who) {
        'moderator' => User::factory()->moderator()->create(),
        'user' => User::factory()->create(),
        'claimant admin' => tap($this->claimant)->forceFill(['role' => 'admin'])->save() ? $this->claimant : null,
    };

    expect(fn () => ($this->disputes)()->decide($actor, $ulid, DisputeDecision::Transfer, 'Mine now'))
        ->toThrow($who === 'claimant admin' ? AuthorizationException::class : ModelNotFoundException::class)
        ->and(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Open);
})->with(['moderator', 'user', 'claimant admin']);

it('moves the tag only once the dispute is with the admins (specs/13 §5 steps 3–4)', function (DisputeDecision $decision) {
    $ulid = ($this->open)()->disputeUlid;

    expect(($this->disputes)()->decide($this->admin, $ulid, $decision, 'Too early')->refusal)->toBe(DisputeRefusal::NotYourTurn)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed);
})->with([DisputeDecision::Transfer, DisputeDecision::Suspend]);

it('gives the tag only to a claimant in good standing (specs/13 §9)', function (string $state) {
    $ulid = ($this->withAdmins)();
    $this->claimant->forceFill(['status' => $state])->save();

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Evidence holds')->refusal)->toBe(DisputeRefusal::ClaimantUnavailable)
        ->and(($this->disputes)()->release($this->holder, $ulid)->refusal)->toBe(DisputeRefusal::ClaimantUnavailable)
        ->and(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'Claimant left')->status)->toBe(DisputeStatus::ResolvedDenied);
})->with(['banned', 'suspended', 'pending_deletion']);

it('transfers away from a banned holder when the evidence holds (specs/13 §9)', function () {
    $ulid = ($this->withAdmins)();
    $this->holder->forceFill(['status' => 'banned'])->save();

    expect(($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Holder banned; receipt holds.')->status)->toBe(DisputeStatus::ResolvedTransfer)
        ->and(CocAccount::query()->where('user_id', $this->claimant->id)->sole()->status)->toBe(CocAccountStatus::Verified);
});

it('caps evidence per party over the whole dispute, not per round', function () {
    $ulid = ($this->open)(evidence: evidenceFor($this->claimant, 2))->disputeUlid;
    ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::AskClaimant, 'More please.');

    expect(fn () => ($this->disputes)()->respond($this->claimant, $ulid, 'Two more', evidenceFor($this->claimant, 2)))->toThrow(ValidationException::class)
        ->and(($this->disputes)()->respond($this->claimant, $ulid, 'One more', evidenceFor($this->claimant))->status)->toBe(DisputeStatus::AwaitingAdmin);
});

it('logs a refused dispute from a barred claimant', function () {
    CocAccountDispute::factory()->count(2)->status(DisputeStatus::ResolvedDenied)->create(['claimant_id' => $this->claimant->id, 'decided_at' => now()->subDay()]);

    ($this->open)();

    expect(collect($this->securityEvents())->pluck('message'))->toContain('coc.dispute_denied_bar');
});

it('refuses a token for a tag suspended after the pre-check, inside the lock', function () {
    $mine = CocAccount::factory()->for($this->claimant)->forTag('#2PQ8GRJC')->create();
    $this->fakeCoc()->acceptToken($this->tag, 'claimant-token');
    // Suspended between the pre-check and the transaction: the fake's token check runs in between.
    $this->fakeCoc()->onVerify(fn () => $this->held->forceFill(['status' => CocAccountStatus::Suspended])->save());

    $result = app(VerifyOwnershipService::class)->verify($this->claimant, $mine->ulid, 'claimant-token');

    expect($result->outcome)->toBe(VerifyOutcome::TagSuspended)
        ->and($mine->refresh()->status)->toBe(CocAccountStatus::Unverified)
        ->and(CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->where('status', 'verified')->count())->toBe(0);
});

it('holds the closing and asking events until the transaction commits', function () {
    Event::fake([CocAccountDisputeClosed::class, CocAccountDisputeInfoRequested::class]);
    $ulid = ($this->open)()->disputeUlid;

    DB::beginTransaction();
    ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::AskHolder, 'Explain.');
    ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'No case.');
    Event::assertNothingDispatched();
    DB::commit();

    Event::assertDispatched(CocAccountDisputeInfoRequested::class);
    Event::assertDispatched(CocAccountDisputeClosed::class, fn ($e) => $e->status === DisputeStatus::ResolvedDenied);
});

it('ends the dispute when the claimant verifies with a token (specs/13 §3.1 step 8)', function () {
    $ulid = ($this->open)()->disputeUlid;
    $this->fakeCoc()->acceptToken($this->tag, 'claimant-token');

    app(VerifyOwnershipService::class)->verifyTag($this->claimant, $this->tag, 'claimant-token');

    expect(CocAccountDispute::query()->where('ulid', $ulid)->value('status'))->toBe(DisputeStatus::AutoResolved)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Unverified)
        ->and(AuditLog::query()->where('action', AuditAction::CocDisputeClosed)->sole()->context)->toMatchArray(['by' => 'claimant_token']);
});

it('ends the dispute when the holder verifies again with a token (specs/13 §5 3a)', function () {
    $ulid = ($this->open)()->disputeUlid;
    $this->fakeCoc()->acceptToken($this->tag, 'holder-token');

    $result = app(VerifyOwnershipService::class)->verify($this->holder, $this->held->ulid, 'holder-token');

    expect($result->outcome)->toBe(VerifyOutcome::Verified)
        ->and($result->featured)->toBeTrue()
        ->and(CocAccountDispute::query()->where('ulid', $ulid)->value('status'))->toBe(DisputeStatus::ResolvedDenied)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified)
        ->and($this->held->is_featured)->toBeTrue()
        ->and($this->holder->refresh()->verified_accounts_count)->toBe(1);
});

it('escalates an unanswered dispute after holder_response_days, not before (specs/13 §5 3d)', function () {
    ($this->open)();

    Date::setTestNow('2026-10-09 11:59:59');
    expect(($this->disputes)()->sweep())->toBe(['escalated' => 0, 'withdrawn' => 0]);

    Date::setTestNow('2026-10-09 12:00:00');
    expect(($this->disputes)()->sweep())->toBe(['escalated' => 1, 'withdrawn' => 0]);

    $dispute = CocAccountDispute::query()->sole();
    expect($dispute->status)->toBe(DisputeStatus::AwaitingAdmin)
        ->and($dispute->escalated_at?->equalTo(now()))->toBeTrue()
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed)
        ->and(AuditLog::query()->where('action', AuditAction::CocDisputeEscalated)->sole()->actor_id)->toBeNull();
});

it('withdraws a dispute left waiting on the claimant for claimant_inactive_days', function () {
    $ulid = ($this->open)()->disputeUlid;
    ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::AskClaimant, 'Send the receipt.');

    Date::setTestNow('2026-11-01 11:59:59');
    expect(($this->disputes)()->sweep()['withdrawn'])->toBe(0);
    Date::setTestNow('2026-11-01 12:00:00');
    $this->artisan('coc:process-disputes')->expectsOutputToContain('0 escalated, 1 withdrawn.')->assertSuccessful();

    expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Withdrawn)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('logs a third denied dispute as a false claim for review', function () {
    CocAccountDispute::factory()->count(2)->status(DisputeStatus::ResolvedDenied)->create(['claimant_id' => $this->claimant->id, 'decided_at' => now()->subDays(200)]);
    $ulid = ($this->open)()->disputeUlid;

    ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'No evidence.');

    expect(collect($this->securityEvents())->pluck('message'))->toContain('coc.dispute_false_claim');
});

it('holds events until the transaction commits', function () {
    Event::fake([CocAccountDisputeOpened::class]);

    DB::beginTransaction();
    ($this->open)();
    Event::assertNotDispatched(CocAccountDisputeOpened::class);
    DB::commit();

    Event::assertDispatched(CocAccountDisputeOpened::class);
});

it('schedules the hourly sweep and reads its limits from config', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'coc:process-disputes'));

    expect($event?->expression)->toBe('25 * * * *')
        ->and(config('coc.disputes'))->toMatchArray([
            'max_open_per_user' => 2, 'bar_after_denials' => 2, 'bar_days' => 90,
            'holder_response_days' => 7, 'claimant_inactive_days' => 30, 'reopen_cooldown_days' => 30, 'evidence_max' => 3, 'text_max' => 1000,
        ]);
});
