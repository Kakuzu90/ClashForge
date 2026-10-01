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

it('lists only the categories with a type as in use', function () {
    expect(NotificationCategory::inUse())->toBe([NotificationCategory::Security, NotificationCategory::Bases]);
});
