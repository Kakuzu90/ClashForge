<?php

namespace Database\Factories\PlayerAccounts;

use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CocAccountDispute>
 */
class CocAccountDisputeFactory extends Factory
{
    protected $model = CocAccountDispute::class;

    /**
     * An open dispute against a disputed holder row.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $account = CocAccount::factory()->disputed()->create();

        return [
            'coc_account_id' => $account->id,
            'tag_normalized' => $account->tag_normalized,
            'claimant_id' => User::factory(),
            'current_holder_id' => $account->user_id,
            'reason' => 'I lost my phone, this is my main account.',
            'evidence' => [],
            'status' => DisputeStatus::Open,
            'awaiting_since' => now(),
        ];
    }

    public function against(CocAccount $account): static
    {
        return $this->state(fn () => [
            'coc_account_id' => $account->id,
            'tag_normalized' => $account->tag_normalized,
            'current_holder_id' => $account->user_id,
        ])->afterCreating(function () use ($account): void {
            if ($account->status === CocAccountStatus::Verified) {
                $account->forceFill(['status' => CocAccountStatus::Disputed])->save();
            }
        });
    }

    public function status(DisputeStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
