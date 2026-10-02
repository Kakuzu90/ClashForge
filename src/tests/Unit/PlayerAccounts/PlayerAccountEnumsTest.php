<?php

use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimMethod;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;

// The values are the specs/07 CHECK lists; a new case needs a migration.

it('matches the specs/07 value lists', function (string $enum, array $values) {
    expect($enum::values())->toBe($values);
})->with([
    [CocAccountStatus::class, ['unverified', 'verified', 'disputed', 'suspended', 'released']],
    [VerificationMethod::class, ['api_token', 'admin']],
    [ClaimMethod::class, ['api_token', 'dispute', 'admin']],
    [ClaimStatus::class, ['pending', 'succeeded', 'failed', 'rejected', 'superseded']],
    [ClaimFailureReason::class, ['invalid_token', 'already_claimed', 'api_error', 'rate_limited', 'not_found']],
]);
