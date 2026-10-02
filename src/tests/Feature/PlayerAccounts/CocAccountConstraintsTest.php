<?php

use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

// specs/07 `coc_accounts` constraints: the database itself holds the core rule (specs/13 §1).

it('allows one holding row per tag, verified or disputed (FR-COC-4)', function (string $first, string $second) {
    CocAccount::factory()->forTag('#2PQ8GRJC')->{$first}()->create();

    expect(fn () => CocAccount::factory()->forTag('#2PQ8GRJC')->{$second}()->create())
        ->toThrow(UniqueConstraintViolationException::class);
})->with([['verified', 'verified'], ['verified', 'disputed'], ['disputed', 'verified']]);

it('allows any number of unverified and released rows per tag', function () {
    CocAccount::factory()->forTag('#2PQ8GRJC')->verified()->create();
    CocAccount::factory()->count(3)->forTag('#2PQ8GRJC')->create();
    CocAccount::factory()->count(2)->forTag('#2PQ8GRJC')->released()->create();

    expect(CocAccount::query()->count())->toBe(6);
});

it('lets a user attach a tag only once', function () {
    $user = User::factory()->create();
    CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->create();

    expect(fn () => CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows one featured account per user', function () {
    $user = User::factory()->create();
    CocAccount::factory()->for($user)->featured()->create();

    expect(fn () => CocAccount::factory()->for($user)->featured()->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps the account rows when the user is hard-deleted (specs/08 §2)', function () {
    $user = User::factory()->create();
    $account = CocAccount::factory()->for($user)->create();

    $user->forceDelete();

    expect($account->refresh()->user_id)->toBeNull();
});
