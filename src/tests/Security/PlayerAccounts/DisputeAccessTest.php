<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

// specs/04 §2–3 (owner-scoped, 404 first; resolve-disputes), specs/10 (evidence is the uploader's
// own private media), specs/11 "Mass assignment".

beforeEach(function () {
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->holder = User::factory()->create();
    $this->claimant = User::factory()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->service = fn () => app(DisputeService::class);
    $this->ulid = fn () => ($this->service)()->open($this->claimant, $this->tag, 'Mine.')->disputeUlid;
});

it("hides another user's dispute behind a 404 (IDOR)", function (string $action) {
    $ulid = ($this->ulid)();
    $stranger = User::factory()->create();

    expect(fn () => match ($action) {
        'respond' => ($this->service)()->respond($stranger, $ulid, 'Hi'),
        'withdraw' => ($this->service)()->withdraw($stranger, $ulid),
        'release' => ($this->service)()->release($stranger, $ulid, 'password'),
    })->toThrow(ModelNotFoundException::class)
        ->and(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Open);
})->with(['respond', 'withdraw', 'release']);

it('keeps each party to its own actions', function () {
    $ulid = ($this->ulid)();

    expect(fn () => ($this->service)()->withdraw($this->holder, $ulid))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->service)()->release($this->claimant, $ulid, 'password'))->toThrow(AuthorizationException::class)
        ->and(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Open);
});

it('refuses accounts that may not open a dispute', function (string $state) {
    $user = match ($state) {
        'unconfirmed email' => User::factory()->unverified()->create(),
        'suspended' => User::factory()->suspended()->create(),
        'banned' => User::factory()->banned()->create(),
        'pending deletion' => User::factory()->create(['status' => 'pending_deletion']),
    };

    expect(fn () => ($this->service)()->open($user, $this->tag, 'Mine.'))->toThrow(AuthorizationException::class)
        ->and(CocAccountDispute::query()->count())->toBe(0);
})->with(['unconfirmed email', 'suspended', 'banned', 'pending deletion']);

it('answers a non-admin with the same 404 for a real dispute and an unknown one', function () {
    $ulid = ($this->ulid)();
    $moderator = User::factory()->moderator()->create();

    expect(fn () => ($this->service)()->decide($moderator, $ulid, DisputeDecision::Deny, 'x'))->toThrow(ModelNotFoundException::class)
        ->and(fn () => ($this->service)()->decide($moderator, '01J0000000000000000000000A', DisputeDecision::Deny, 'x'))->toThrow(ModelNotFoundException::class);
});

it('stops a pending-deletion party from answering, withdrawing or releasing', function () {
    $ulid = ($this->ulid)();
    $this->holder->forceFill(['status' => 'pending_deletion'])->save();
    $this->claimant->forceFill(['status' => 'pending_deletion'])->save();

    expect(fn () => ($this->service)()->respond($this->holder, $ulid, 'Mine'))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->service)()->release($this->holder, $ulid, 'password'))->toThrow(AuthorizationException::class)
        ->and(fn () => ($this->service)()->withdraw($this->claimant, $ulid))->toThrow(AuthorizationException::class);
});

it('stops a suspended party from answering', function () {
    $ulid = ($this->ulid)();
    $this->holder->forceFill(['status' => 'suspended'])->save();

    expect(fn () => ($this->service)()->respond($this->holder, $ulid, 'Still mine'))->toThrow(AuthorizationException::class);
});

it("accepts only the submitter's own evidence uploads", function () {
    $theirs = Media::factory()->collection(MediaCollection::Evidence)->ready()->create();
    $wrongKind = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $this->claimant->id]);

    // Someone else's upload reads like a lost one (P2-16): a field error that does not say it exists.
    expect(fn () => ($this->service)()->open($this->claimant, $this->tag, 'Mine.', [$theirs->ulid]))->toThrow(ValidationException::class, 'did not upload properly')
        ->and(fn () => ($this->service)()->open($this->claimant, $this->tag, 'Mine.', [$wrongKind->ulid]))->toThrow(ValidationException::class)
        ->and(CocAccountDispute::query()->count())->toBe(0)
        ->and($theirs->refresh()->attachable_id)->toBeNull();
});

it('refuses evidence already attached to another dispute, which stays as decided', function () {
    $ulid = ($this->ulid)();
    $media = Media::factory()->collection(MediaCollection::Evidence)->ready()->create(['user_id' => $this->holder->id]);
    ($this->service)()->respond($this->holder, $ulid, 'Proof', [$media->ulid]);
    $first = CocAccountDispute::query()->sole();
    ($this->service)()->decide(User::factory()->admin()->create(), $ulid, DisputeDecision::Deny, 'Holder keeps it.');

    // The holder, now disputing someone else's tag, reuses the same upload.
    $other = CocAccount::factory()->forTag('#GRJ0P8UV')->verified()->create();
    expect(fn () => ($this->service)()->open($this->holder, PlayerTag::from('#GRJ0P8UV'), 'Also mine.', [$media->ulid]))->toThrow(ValidationException::class)
        ->and($media->refresh()->attachable_id)->toBe($first->id)
        ->and($other->refresh()->status->value)->toBe('verified');
});

it('has no mass-assignable columns', function () {
    expect(fn () => CocAccountDispute::query()->create(['status' => 'resolved_transfer', 'claimant_id' => $this->claimant->id]))
        ->toThrow(MassAssignmentException::class);
});
