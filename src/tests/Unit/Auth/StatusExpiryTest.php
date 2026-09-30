<?php

use App\Domain\Auth\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

// specs/23 §7: a timed restriction or suspension stops applying once its end passes. Bans and
// pending deletions have no end date, so a stray one never lifts them.

uses(TestCase::class);

beforeEach(fn () => Date::setTestNow('2026-10-01 12:00:00'));

it('lifts a timed status whose end has passed', function (UserStatus $status) {
    expect($status->effective(CarbonImmutable::parse('2026-10-01 11:59:59')))->toBe(UserStatus::Active)
        ->and($status->effective(CarbonImmutable::parse('2026-10-01 12:00:00')))->toBe(UserStatus::Active);
})->with([UserStatus::Restricted, UserStatus::Suspended]);

it('keeps a timed status until its end, or forever without one', function (UserStatus $status) {
    expect($status->effective(CarbonImmutable::parse('2026-10-01 12:00:01')))->toBe($status)
        ->and($status->effective(null))->toBe($status);
})->with([UserStatus::Restricted, UserStatus::Suspended]);

it('never lifts a ban or a pending deletion from an end date', function (UserStatus $status) {
    expect($status->effective(CarbonImmutable::parse('2026-09-01 00:00:00')))->toBe($status);
})->with([UserStatus::Banned, UserStatus::PendingDeletion]);

it('leaves active alone', function () {
    expect(UserStatus::Active->effective(CarbonImmutable::parse('2026-09-01 00:00:00')))->toBe(UserStatus::Active);
});
