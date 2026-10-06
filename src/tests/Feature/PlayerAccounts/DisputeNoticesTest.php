<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Notifications\Data\UpdateEmailPreferencesData;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\EmailDelivery;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Notifications\NonSecurityEmail;
use App\Domain\Notifications\Services\EmailPreferenceService;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Events\CocAccountDisputeClosed;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Coc\InteractsWithCoc;

// P2-18: dispute notices (specs/16 §2, specs/13 §8; owner decisions 2026-10-06).

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    Mail::fake();
    $this->holder = User::factory()->create(['username' => 'holder_name']);
    $this->claimant = User::factory()->create(['username' => 'claimant_name']);
    $this->admin = User::factory()->admin()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->disputes = app(DisputeService::class);
    $this->open = fn (): string => (string) $this->disputes->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'I lost the phone with this account.')->disputeUlid;
});

/**
 * @return Collection<int, Notification>
 */
function disputeNotices(User $user, ?NotificationType $type = null): Collection
{
    return Notification::query()->where('notifiable_id', $user->id)
        ->when($type !== null, fn ($q) => $q->where('type', $type->value))
        ->orderBy('created_at')->get();
}

it('tells the holder when a dispute is opened, in-app and by email, without naming the claimant', function () {
    $ulid = ($this->open)();

    $notice = disputeNotices($this->holder, NotificationType::CocDisputeOpened)->sole();
    $rendered = NotificationType::CocDisputeOpened->render($notice->data['params']);
    expect($notice->data['params'])->toHaveCount(5)->toMatchArray(['tag' => '#2PQ8GRJC', 'dispute' => $ulid, 'account' => $this->held->ulid, 'days' => 7, 'key' => "{$ulid}:opened"])
        ->and($rendered->title)->toBe('Someone disputes your ownership of #2PQ8GRJC')
        ->and($rendered->body)->toContain('You have 7 days to answer.')
        ->and($rendered->url)->toBe("/accounts/{$this->held->ulid}")
        ->and(disputeNotices($this->claimant))->toHaveCount(0)
        ->and(EmailDelivery::query()->where('user_id', $this->holder->id)->sole()->event_key)->toBe("{$ulid}:opened");
    Mail::assertSent(NonSecurityEmail::class, fn (NonSecurityEmail $mail) => $mail->hasTo($this->holder->email)
        && ! str_contains($mail->notice->body, 'claimant_name'));
});

it('reminds the holder on day 3 and day 6, once each (specs/16 §2)', function () {
    ($this->open)();

    Date::setTestNow('2026-10-09 11:59:59');
    $this->disputes->sweep();
    expect(disputeNotices($this->holder, NotificationType::CocDisputeReminder))->toHaveCount(0);

    Date::setTestNow('2026-10-09 12:00:00');
    $this->disputes->sweep();
    $this->disputes->sweep();
    $first = disputeNotices($this->holder, NotificationType::CocDisputeReminder);
    expect($first)->toHaveCount(1)->and($first->sole()->data['params']['days'])->toBe(4);

    Date::setTestNow('2026-10-12 12:00:00');
    $this->disputes->sweep();
    $both = disputeNotices($this->holder, NotificationType::CocDisputeReminder);
    expect($both)->toHaveCount(2)->and($both->last()->data['params']['days'])->toBe(1)
        ->and(EmailDelivery::query()->where('user_id', $this->holder->id)->where('type', 'coc_dispute_reminder')->count())->toBe(2);
});

it('sends only the latest reminder when the sweep missed a day', function () {
    ($this->open)();

    Date::setTestNow('2026-10-12 13:00:00');
    expect($this->disputes->sweep()['reminded'])->toBe(1);

    expect(disputeNotices($this->holder, NotificationType::CocDisputeReminder))->toHaveCount(1)
        ->and(CocAccountDispute::query()->sole()->holder_reminders_sent)->toBe(2);
});

it('sends no reminder once the dispute is with the admins or closed', function () {
    $ulid = ($this->open)();
    $this->disputes->respond($this->holder, $ulid, 'It is mine.');

    Date::setTestNow('2026-10-12 12:00:00');
    $this->disputes->sweep();

    expect(disputeNotices($this->holder, NotificationType::CocDisputeReminder))->toHaveCount(0);
});

it('starts the reminders over when an admin asks the holder for more, and tells them', function () {
    $ulid = ($this->open)();
    Date::setTestNow('2026-10-12 12:00:00');
    $this->disputes->sweep();
    $this->disputes->respond($this->holder, $ulid, 'It is mine.');
    $this->disputes->decide($this->admin, $ulid, DisputeDecision::AskHolder, 'Send a settings screenshot.');

    $asked = disputeNotices($this->holder, NotificationType::CocDisputeInfoRequested)->sole();
    expect($asked->data['params'])->toMatchArray(['tag' => '#2PQ8GRJC', 'account' => $this->held->ulid, 'days' => 7])
        ->and(CocAccountDispute::query()->sole()->holder_reminders_sent)->toBe(0);

    Date::setTestNow('2026-10-15 12:00:00');
    $this->disputes->sweep();
    expect(disputeNotices($this->holder, NotificationType::CocDisputeReminder))->toHaveCount(2);
});

it('tells the claimant when an admin asks them, with their own deadline and no account link', function () {
    $ulid = ($this->open)();
    $this->disputes->decide($this->admin, $ulid, DisputeDecision::AskClaimant, 'Send a receipt.');

    $asked = disputeNotices($this->claimant, NotificationType::CocDisputeInfoRequested)->sole();
    expect($asked->data['params'])->toHaveCount(4)->toMatchArray(['tag' => '#2PQ8GRJC', 'dispute' => $ulid, 'days' => 30])
        ->and(NotificationType::CocDisputeInfoRequested->render($asked->data['params'])->url)->toBeNull();
});

it('tells both parties of an admin decision, each their own outcome', function (DisputeDecision $decision, string $claimantOutcome, string $holderOutcome) {
    $ulid = ($this->open)();
    $this->disputes->respond($this->holder, $ulid, 'It is mine.');
    $this->disputes->decide($this->admin, $ulid, $decision, 'Weighed both sides.');

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe($claimantOutcome)
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe($holderOutcome)
        ->and(disputeNotices($this->holder, NotificationType::CocAccountTakenOver))->toHaveCount(0);
})->with([
    'transfer' => [DisputeDecision::Transfer, 'transferred_to_you', 'transferred_away'],
    'deny' => [DisputeDecision::Deny, 'denied', 'kept'],
    'suspend' => [DisputeDecision::Suspend, 'suspended', 'suspended'],
]);

it('links the claimant to their new account after a transfer', function () {
    $ulid = ($this->open)();
    $this->disputes->respond($this->holder, $ulid, 'It is mine.');
    $this->disputes->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Receipt matches.');

    $own = CocAccount::query()->where('user_id', $this->claimant->id)->sole();
    $params = disputeNotices($this->claimant, NotificationType::CocDisputeClosed)->sole()->data['params'];
    expect(NotificationType::CocDisputeClosed->render($params)->url)->toBe("/accounts/{$own->ulid}");
});

it('tells only the other side when one side ends it', function () {
    $ulid = ($this->open)();
    $this->disputes->release($this->holder, $ulid);

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('released_to_you')
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeClosed))->toHaveCount(0);
});

it('tells the holder when the claimant withdraws', function () {
    $ulid = ($this->open)();
    $this->disputes->withdraw($this->claimant, $ulid);

    expect(disputeNotices($this->holder, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('withdrawn')
        ->and(disputeNotices($this->claimant, NotificationType::CocDisputeClosed))->toHaveCount(0);
});

it('tells both when the sweep withdraws a claim left unanswered', function () {
    $ulid = ($this->open)();
    $this->disputes->decide($this->admin, $ulid, DisputeDecision::AskClaimant, 'Send a receipt.');

    Date::setTestNow(now()->addDays((int) config('coc.disputes.claimant_inactive_days')));
    $this->disputes->sweep();

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('withdrawn_inactive')
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('withdrawn');
});

it('tells the claimant when the holder ends it with their token, and nobody when the claimant does', function () {
    $ulid = ($this->open)();
    $this->fakeCoc()->acceptToken(PlayerTag::from('#2PQ8GRJC'), 'holder-token');
    app(VerifyOwnershipService::class)->verify($this->holder, $this->held->ulid, 'holder-token');

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('denied_token');

    Notification::query()->delete();
    $second = (string) $this->disputes->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'Still mine.')->disputeUlid;
    expect($second)->not->toBe($ulid);
    $this->fakeCoc()->acceptToken(PlayerTag::from('#2PQ8GRJC'), 'claimant-token');
    app(VerifyOwnershipService::class)->verifyTag($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'claimant-token');

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed))->toHaveCount(0)
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeClosed))->toHaveCount(0)
        ->and(disputeNotices($this->holder, NotificationType::CocAccountTakenOver))->toHaveCount(1);
});

it('tells both when someone else ends it with a token', function () {
    ($this->open)();
    $other = User::factory()->create();
    $this->fakeCoc()->acceptToken(PlayerTag::from('#2PQ8GRJC'), 'other-token');
    app(VerifyOwnershipService::class)->verifyTag($other, PlayerTag::from('#2PQ8GRJC'), 'other-token');

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('verified_by_other')
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeClosed)->sole()->data['params']['outcome'])->toBe('verified_by_other');
});

it('skips a deleted recipient', function () {
    $ulid = ($this->open)();
    $this->holder->delete();
    $this->disputes->withdraw($this->claimant, $ulid);

    expect(Notification::query()->where('notifiable_id', $this->holder->id)->where('type', 'coc_dispute_closed')->exists())->toBeFalse();
});

it('sends no reminder for a dispute closed before the day comes', function () {
    $ulid = ($this->open)();
    $this->disputes->withdraw($this->claimant, $ulid);

    Date::setTestNow('2026-10-12 12:00:00');

    expect($this->disputes->sweep()['reminded'])->toBe(0)
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeReminder))->toHaveCount(0);
});

it('keys each email by dispute, notice and wait', function () {
    $ulid = ($this->open)();
    $opened = CocAccountDispute::query()->sole()->awaiting_since->getTimestamp();
    Date::setTestNow('2026-10-09 12:00:00');
    $this->disputes->sweep();
    $this->disputes->decide($this->admin, $ulid, DisputeDecision::AskHolder, 'Send a settings screenshot.');
    $asked = CocAccountDispute::query()->sole()->awaiting_since->getTimestamp();
    $this->disputes->respond($this->holder, $ulid, 'Here it is.');
    $this->disputes->decide($this->admin, $ulid, DisputeDecision::Deny, 'Not enough.');

    expect(EmailDelivery::query()->where('user_id', $this->holder->id)->orderBy('id')->pluck('event_key')->all())->toBe([
        "{$ulid}:opened",
        "{$ulid}:reminder:{$opened}:1",
        "{$ulid}:info:{$asked}",
        "{$ulid}:closed",
    ])->and(EmailDelivery::query()->where('user_id', $this->claimant->id)->pluck('event_key')->all())->toBe(["{$ulid}:closed"]);
});

it('writes one notice and one email when the same event is handled twice', function () {
    $ulid = ($this->open)();
    $this->disputes->withdraw($this->claimant, $ulid);
    $dispute = CocAccountDispute::query()->sole();

    event(new CocAccountDisputeClosed($dispute->id, $dispute->status, 'claimant'));

    expect(EmailDelivery::query()->where('user_id', $this->holder->id)->where('event_key', "{$ulid}:closed")->count())->toBe(1)
        ->and(disputeNotices($this->holder, NotificationType::CocDisputeClosed))->toHaveCount(1);
});

it('keeps the in-app notice but sends no email when the holder turned Ownership email off', function () {
    app(EmailPreferenceService::class)->update($this->holder, new UpdateEmailPreferencesData(true, [NotificationCategory::Ownership->value => false]));

    ($this->open)();

    expect(disputeNotices($this->holder, NotificationType::CocDisputeOpened))->toHaveCount(1)
        ->and(EmailDelivery::query()->where('user_id', $this->holder->id)->exists())->toBeFalse();
});

it('sends the claimant only the outcome, not a second "verified" notice, on a transfer or release', function (string $how) {
    $ulid = ($this->open)();
    if ($how === 'release') {
        $this->disputes->release($this->holder, $ulid);
    } else {
        $this->disputes->respond($this->holder, $ulid, 'It is mine.');
        $this->disputes->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Receipt matches.');
    }

    expect(disputeNotices($this->claimant, NotificationType::CocDisputeClosed))->toHaveCount(1)
        ->and(disputeNotices($this->claimant, NotificationType::CocAccountVerified))->toHaveCount(0);
})->with(['release', 'transfer']);

it('reads the reminder days from config', function () {
    expect(config('coc.disputes.reminder_days'))->toBe([3, 6]);
});
