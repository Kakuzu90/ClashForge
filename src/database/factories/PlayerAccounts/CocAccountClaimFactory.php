<?php

namespace Database\Factories\PlayerAccounts;

use App\Domain\PlayerAccounts\Enums\ClaimMethod;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CocAccountClaim>
 */
class CocAccountClaimFactory extends Factory
{
    protected $model = CocAccountClaim::class;

    /**
     * The pending claim an attach writes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $account = CocAccount::factory()->create();

        return [
            'coc_account_id' => $account->id,
            'tag_normalized' => $account->tag_normalized,
            'user_id' => $account->user_id,
            'method' => ClaimMethod::ApiToken,
            'status' => ClaimStatus::Pending,
        ];
    }
}
