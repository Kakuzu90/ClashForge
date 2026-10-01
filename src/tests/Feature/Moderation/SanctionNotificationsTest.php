<?php

use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Events\SanctionApplied;
use App\Domain\Moderation\Events\SanctionLifted;
use App\Domain\Moderation\Listeners\SendSanctionNotice;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Moderation\Notifications\AccountBannedNotification;
use App\Domain\Moderation\Notifications\AccountSuspendedNotification;
use App\Domain\Moderation\Notifications\SanctionEndedNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

// FR-MOD-8 and specs/16 §2: the account holder is told the reason (and the end date) of a
// sanction, and when it is over. Security emails go on `high`.

function mailText(object $notification, User $to): string
{
    $mail = $notification->toMail($to);

    return implode("\n", [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines, $mail->actionText ?? '']);
}

beforeEach(fn () => Date::setTestNow('2026-10-01 12:00:00'));

it('tells a suspended account the reason and the end in UTC', function () {
    $user = User::factory()->create(['username' => 'chief']);
    $notification = new AccountSuspendedNotification('Spam links in comments', CarbonImmutable::parse('2026-10-08 12:00:00', 'UTC'));

    $text = mailText($notification, $user);

    expect($text)->toContain('Your Clash Commons account is suspended')
        ->and($text)->toContain('Hi chief,')
        ->and($text)->toContain('suspended until 8 October 2026 at 12:00 UTC')
        ->and($text)->toContain('Reason: Spam links in comments')
        ->and($notification->queue)->toBe('high')
        ->and($notification->via($user))->toBe(['mail']);
});

it('tells a banned account the reason and that it is signed out', function () {
    $user = User::factory()->create();
    $notification = new AccountBannedNotification('Account trading');

    $text = mailText($notification, $user);

    expect($text)->toContain('Your Clash Commons account is banned')
        ->and($text)->toContain('can no longer sign in')
        ->and($text)->toContain('Reason: Account trading')
        ->and($notification->queue)->toBe('high');
});

it('says whether a sanction was lifted or ran out', function (SanctionType $type, bool $expired, string $subject, string $line) {
    $text = mailText($notification = new SanctionEndedNotification($type, $expired), User::factory()->create());

    expect($text)->toContain($subject)
        ->and($text)->toContain($line)
        ->and($notification->queue)->toBe('high');
})->with([
    'suspension lifted' => [SanctionType::Suspension, false, 'Your Clash Commons suspension is over', 'Your suspension has been lifted.'],
    'suspension ended' => [SanctionType::Suspension, true, 'Your Clash Commons suspension is over', 'Your suspension has ended.'],
    'ban lifted' => [SanctionType::Ban, false, 'Your Clash Commons ban is over', 'Your ban has been lifted.'],
]);

it('renders Markdown in an admin\'s message as literal text, not a link or an image', function () {
    $user = User::factory()->create();
    $reason = '[Appeal here](https://phish.example) ![](https://tracker.example/p.gif) **now**';

    $html = (string) (new AccountBannedNotification($reason))->toMail($user)->render();

    expect($html)->not->toContain('href="https://phish.example"')
        ->and($html)->not->toContain('src="https://tracker.example/p.gif"')
        ->and($html)->not->toContain('<strong>now</strong>')
        ->and($html)->toContain('phish.example');
});

it('holds the sanction events until the transaction commits', function () {
    expect(new SanctionApplied(1, 1))->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and(new SanctionLifted(1, 1, false))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('sends no stale notice: not for a sanction lifted before the listener ran, not "over" while another is active', function () {
    Notification::fake();
    $user = User::factory()->create();
    $lifted = UserSanction::factory()->create(['user_id' => $user->id]);
    $lifted->forceFill(['lifted_at' => now(), 'lifted_by' => $lifted->issued_by])->save();
    UserSanction::factory()->ban()->create(['user_id' => $user->id]);

    $listener = new SendSanctionNotice;
    $listener->handleApplied(new SanctionApplied($user->id, $lifted->id));
    $listener->handleLifted(new SanctionLifted($user->id, $lifted->id, false));

    Notification::assertNothingSent();
});
