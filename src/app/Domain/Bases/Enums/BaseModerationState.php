<?php

namespace App\Domain\Bases\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * A base's standing with moderation (specs/07 `base_layouts.moderation_state`). Publishing sets
 * `Flagged` for another user's layout (FR-BASE-11); the review states are Moderation v1's (P3-06).
 */
enum BaseModerationState: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Clean = 'clean';
    case Flagged = 'flagged';
    case UnderReview = 'under_review';
    case Actioned = 'actioned';

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::Flagged => 'Flagged',
            self::UnderReview => 'Under review',
            self::Actioned => 'Actioned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Clean => 'text-muted',
            self::Flagged, self::UnderReview => 'state-warning',
            self::Actioned => 'state-danger',
        };
    }
}
