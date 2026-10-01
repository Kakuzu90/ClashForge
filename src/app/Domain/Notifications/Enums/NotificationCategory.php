<?php

namespace App\Domain\Notifications\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The catalogue's categories (specs/16 §2). A category appears as a filter on the notification
 * centre once at least one notification type belongs to it.
 */
enum NotificationCategory: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Security = 'security';
    case Ownership = 'ownership';
    case Bases = 'bases';
    case Moderation = 'moderation';
    case Recruitment = 'recruitment';
    case Marketplace = 'marketplace';
    case Social = 'social';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Security => 'Security',
            self::Ownership => 'Accounts',
            self::Bases => 'Bases',
            self::Moderation => 'Moderation',
            self::Recruitment => 'Recruitment',
            self::Marketplace => 'Marketplace',
            self::Social => 'Social',
            self::Staff => 'Staff',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    /**
     * @return list<NotificationType>
     */
    public function types(): array
    {
        return array_values(array_filter(NotificationType::cases(), fn (NotificationType $type): bool => $type->category() === $this));
    }

    /**
     * Categories that have at least one notification type, in catalogue order.
     *
     * @return list<self>
     */
    public static function inUse(): array
    {
        return array_values(array_filter(self::cases(), fn (self $category): bool => $category->types() !== []));
    }
}
