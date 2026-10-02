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
