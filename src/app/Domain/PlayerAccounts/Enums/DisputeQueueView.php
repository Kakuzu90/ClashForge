<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The slices of the admin dispute queue (P2-17). `active` is every running dispute.
 */
enum DisputeQueueView: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Active = 'active';
    case AwaitingAdmin = 'awaiting_admin';
    case WaitingOnHolder = 'waiting_on_holder';
    case WaitingOnClaimant = 'waiting_on_claimant';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'All running',
            self::AwaitingAdmin => 'Waiting for an admin',
            self::WaitingOnHolder => 'Waiting for the holder',
            self::WaitingOnClaimant => 'Waiting for the claimant',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingAdmin => 'state-warning',
            self::Closed => 'text-muted',
            default => 'state-info',
        };
    }

    /**
     * @return list<DisputeStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::Active => DisputeStatus::ACTIVE,
            self::AwaitingAdmin => [DisputeStatus::AwaitingAdmin],
            self::WaitingOnHolder => [DisputeStatus::Open, DisputeStatus::AwaitingHolder],
            self::WaitingOnClaimant => [DisputeStatus::AwaitingClaimant],
            self::Closed => array_values(array_filter(DisputeStatus::cases(), fn (DisputeStatus $status): bool => ! $status->isActive())),
        };
    }
}
