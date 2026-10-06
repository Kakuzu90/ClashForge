<?php

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Queries\AccountReadModel;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\PrivacyPolicyResolver;
use App\Models\User;

// AccountReadModel::forProfile (P2-22): which rows each viewer gets, and in what order.

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->rows = [];
    foreach ([
        'verified' => ['#GRJ0P8UV', CocAccountStatus::Verified, false],
        'disputedFeatured' => ['#2PQ8GRJC', CocAccountStatus::Disputed, true],
        'unverified' => ['#Q0RJ9P2L', CocAccountStatus::Unverified, false],
        'suspended' => ['#LQ2RJ9P0', CocAccountStatus::Suspended, false],
        'released' => ['#8QU0PLR2', CocAccountStatus::Released, false],
    ] as $key => [$tag, $status, $featured]) {
        $this->rows[$key] = CocAccount::factory()->for($this->owner)->forTag($tag)
            ->create(['status' => $status, 'is_featured' => $featured, 'verified_at' => now()])->ulid;
    }
});

function profileUlids(?User $viewer, int $ownerId): array
{
    return array_map(fn ($card) => $card->ulid, app(AccountReadModel::class)->forProfile($viewer, $ownerId)->cards);
}

it('gives the owner every row but released, featured first, then newest', function () {
    expect(profileUlids($this->owner, $this->owner->id))->toBe([
        $this->rows['disputedFeatured'], $this->rows['suspended'], $this->rows['unverified'], $this->rows['verified'],
    ]);
});

it('gives anyone else the verified and disputed rows, staff included', function (string $who) {
    $viewer = match ($who) {
        'guest' => null,
        'member' => User::factory()->create(),
        'moderator' => User::factory()->moderator()->create(),
        'admin' => User::factory()->admin()->create(),
    };

    expect(profileUlids($viewer, $this->owner->id))->toBe([$this->rows['disputedFeatured'], $this->rows['verified']]);

    PrivacySettings::query()->whereKey($this->owner->id)->update(['show_coc_accounts' => false]);
    app(PrivacyPolicyResolver::class)->refresh($this->owner->id);

    expect(app(AccountReadModel::class)->forProfile($viewer, $this->owner->id))
        ->cards->toBe([])
        ->featured->toBeNull()
        ->warStars->toBeNull()
        ->verified->toBeFalse();
})->with(['guest', 'member', 'moderator', 'admin']);
