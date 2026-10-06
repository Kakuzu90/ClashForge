<?php

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationType;

// Every type renders, with or without its parameters, and belongs to a catalogue category. A Feature
// test because the links come from the router.

it('renders every type with no parameters at all', function (NotificationType $type) {
    $rendered = $type->render([]);

    expect($rendered->title)->not->toBe('')
        ->and($rendered->url === null || str_starts_with($rendered->url, '/'))->toBeTrue();
})->with(NotificationType::cases());

it('tolerates parameters of the wrong type', function (NotificationType $type) {
    $rendered = $type->render(['device' => 42, 'country' => [], 'reason' => false, 'ends_at' => 'not a date', 'collection' => 7, 'sanction' => null, 'expired' => 'yes']);

    expect($rendered->title)->not->toBe('');
})->with(NotificationType::cases());

it('falls back to readable words when parameters are missing', function () {
    expect(NotificationType::NewDeviceSignIn->render([])->body)->toContain('an unknown device')
        ->and(NotificationType::AccountSuspended->render([])->body)->toBe('You can still read your settings and notifications.')
        ->and(NotificationType::AccountBanned->render([])->body)->toBe('You can no longer use Clash Commons.')
        ->and(NotificationType::MediaProcessingFailed->render([])->body)->toContain('Your upload failed');
});

it('says lifted or ended, ban or suspension', function () {
    expect(NotificationType::SanctionEnded->render(['sanction' => 'ban', 'expired' => false])->title)->toBe('Your ban is over')
        ->and(NotificationType::SanctionEnded->render(['sanction' => 'ban', 'expired' => false])->body)->toStartWith('It has been lifted.')
        ->and(NotificationType::SanctionEnded->render(['sanction' => 'suspension', 'expired' => true])->body)->toStartWith('It has ended.');
});

it('names the account, or falls back when the row predates its parameters (specs/13 §8)', function () {
    $params = ['tag' => '#2PQ8GRJC', 'name' => 'Chief Pat', 'method' => 'api_token'];

    expect(NotificationType::CocAccountVerified->render($params)->body)->toBe('#2PQ8GRJC (Chief Pat) is now verified on your Clash Commons account.')
        ->and(NotificationType::CocAccountVerified->render([])->body)->toBe('Your account is now verified on your Clash Commons account.')
        ->and(NotificationType::CocAccountVerified->render(['tag' => '#2PQ8GRJC', 'name' => '  '])->body)->toStartWith('#2PQ8GRJC is now verified')
        ->and(NotificationType::CocAccountTakenOver->render($params)->body)->toStartWith('Someone verified #2PQ8GRJC (Chief Pat) with an in-game API token')
        ->and(NotificationType::CocAccountTakenOver->render($params)->body)->toContain('secure it in game, then verify it again with a new token.')
        ->and(NotificationType::CocAccountTakenOver->render([])->body)->toStartWith('Someone verified one of your Clash of Clans accounts')
        ->and(NotificationType::CocAccountTakenOver->render($params)->url)->toBe('/accounts/attach?tag=%232PQ8GRJC');
});

it('files both ownership notices under Accounts', function () {
    expect(NotificationType::CocAccountVerified->category())->toBe(NotificationCategory::Ownership)
        ->and(NotificationType::CocAccountTakenOver->category())->toBe(NotificationCategory::Ownership)
        ->and(NotificationType::CocAccountVerified->color())->toBe('state-success')
        ->and(NotificationType::CocAccountTakenOver->color())->toBe('state-danger')
        ->and(NotificationCategory::Ownership->emailHint())->toBe('Takeover alerts are always sent.');
});

it('lists only the categories with a type as in use', function () {
    expect(NotificationCategory::inUse())->toBe([NotificationCategory::Security, NotificationCategory::Ownership, NotificationCategory::Bases]);
});

it('words the account-not-found notice as still verified, under Accounts (specs/09 §6, specs/16 §2)', function () {
    $notice = NotificationType::CocAccountNotFound->render(['tag' => '#2PQ8GRJC', 'name' => 'Chief Pat']);

    expect($notice->title)->toBe("We can't find one of your accounts")
        ->and($notice->body)->toStartWith('Clash of Clans no longer finds #2PQ8GRJC (Chief Pat).')
        ->and($notice->body)->toContain('It stays verified')
        ->and(NotificationType::CocAccountNotFound->render([])->body)->toStartWith('Clash of Clans no longer finds one of your accounts.')
        ->and(NotificationType::CocAccountNotFound->category())->toBe(NotificationCategory::Ownership)
        ->and(NotificationType::CocAccountNotFound->color())->toBe('state-warning');
});

it('renders every dispute notice in the Ownership category, even without params (P2-18)', function (NotificationType $type) {
    $rendered = $type->render([]);

    expect($type->category())->toBe(NotificationCategory::Ownership)
        ->and($rendered->title)->not->toBe('')
        ->and($rendered->body)->not->toBe('')
        ->and($rendered->url)->toBeNull();
})->with([NotificationType::CocDisputeOpened, NotificationType::CocDisputeReminder, NotificationType::CocDisputeInfoRequested, NotificationType::CocDisputeClosed]);

it('renders each dispute outcome with its own words and link', function (string $outcome, string $title, ?string $url) {
    $rendered = NotificationType::CocDisputeClosed->render(['tag' => '#2PQ8GRJC', 'dispute' => '01J0000000000000000000DISP', 'outcome' => $outcome, 'account' => '01J00000000000000000000ACC']);

    expect($rendered->title)->toBe($title)->and($rendered->url)->toBe($url);
})->with([
    ['transferred_to_you', '#2PQ8GRJC is now yours', '/accounts/01J00000000000000000000ACC'],
    ['released_to_you', '#2PQ8GRJC is now yours', '/accounts/01J00000000000000000000ACC'],
    ['transferred_away', '#2PQ8GRJC was moved to another player', '/accounts/attach?tag=%232PQ8GRJC'],
    ['kept', '#2PQ8GRJC stays yours', '/accounts/01J00000000000000000000ACC'],
    ['denied', 'Your claim to #2PQ8GRJC was not accepted', '/accounts/attach?tag=%232PQ8GRJC'],
    ['denied_token', 'Your claim to #2PQ8GRJC was closed', '/accounts/attach?tag=%232PQ8GRJC'],
    ['suspended', '#2PQ8GRJC is suspended', null],
    ['withdrawn', 'The review of #2PQ8GRJC is over', '/accounts/01J00000000000000000000ACC'],
    ['withdrawn_inactive', 'Your claim to #2PQ8GRJC was closed', null],
    ['verified_by_other', 'The review of #2PQ8GRJC is over', null],
    ['unknown', 'The review of #2PQ8GRJC is over', null],
]);

it('says how many days are left to answer, and nothing when it does not know', function () {
    expect(NotificationType::CocDisputeReminder->render(['tag' => '#2PQ8GRJC', 'days' => 1])->body)->toStartWith('You have 1 day to answer.')
        ->and(NotificationType::CocDisputeReminder->render(['tag' => '#2PQ8GRJC', 'days' => 4])->body)->toStartWith('You have 4 days to answer.')
        ->and(NotificationType::CocDisputeReminder->render(['tag' => '#2PQ8GRJC'])->body)->toStartWith('Verify #2PQ8GRJC again');
});
