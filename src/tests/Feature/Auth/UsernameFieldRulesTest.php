<?php

use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Models\UsernameHistory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

// FR-AUTH-1: 3 to 20 lowercase letters, numbers or underscores, not reserved, unique ignoring
// case. A Feature test: the rules read config and the users table.

function usernameErrors(mixed $username): array
{
    return Validator::make(['username' => $username], ['username' => UsernameFieldRules::forRegistration()], UsernameFieldRules::messages())
        ->errors()
        ->get('username');
}

it('accepts names at both length bounds', function (string $name) {
    expect(usernameErrors($name))->toBe([]);
})->with(['abc', str_repeat('a', 20), 'clan_chief_99']);

it('refuses names outside the bounds or the character set', function (mixed $name) {
    expect(usernameErrors($name))->not->toBe([]);
})->with(['short' => 'ab', 'long' => str_repeat('a', 21), 'capital' => 'Chief', 'dash' => 'chief-1', 'dot' => 'chief.1', 'space' => 'chief 1', 'accent' => 'chïef', 'newline' => "chief\n", 'empty' => '', 'null' => null, 'array' => [['chief']], 'number' => 42]);

it('refuses reserved names, ignoring case', function () {
    expect(usernameErrors('support'))->toBe(['That username is reserved. Pick another.'])
        ->and(UsernameFieldRules::isReserved('Admin'))->toBeTrue()
        ->and(UsernameFieldRules::isReserved('administrators'))->toBeFalse();
});

it('refuses a taken name, deleted accounts included', function () {
    User::factory()->create(['username' => 'chief'])->delete();

    expect(usernameErrors('chief'))->toBe(['That username is taken. Pick another.']);
});

it('holds a changed-away name for other accounts until the hold ends, never for its owner', function () {
    $days = (int) config('platform.auth.username_reservation_days');
    $owner = User::factory()->create();
    UsernameHistory::factory()->create(['user_id' => $owner->id, 'username' => 'old_chief', 'released_at' => now()->subDays($days)->addMinute()]);

    expect(usernameErrors('old_chief'))->toBe(['That username is taken. Pick another.'])
        ->and(UsernameFieldRules::isHeld('OLD_CHIEF'))->toBeTrue()
        ->and(UsernameFieldRules::isHeld('old_chief', $owner->id))->toBeFalse();

    $this->travel(2)->minutes();
    expect(UsernameFieldRules::isHeld('old_chief'))->toBeFalse();
});

it('holds a deleted account\'s name forever, for everyone', function () {
    $owner = User::factory()->create();
    UsernameHistory::factory()->permanent()->create(['user_id' => $owner->id, 'username' => 'gone_chief', 'released_at' => now()->subYears(5)]);

    expect(UsernameFieldRules::isHeld('gone_chief'))->toBeTrue()
        ->and(UsernameFieldRules::isHeld('gone_chief', $owner->id))->toBeTrue();
});

it('reads the username change windows from config', function () {
    expect(config('platform.auth.username_change_days'))->toBe(30)
        ->and(config('platform.auth.username_reservation_days'))->toBe(90);
});
