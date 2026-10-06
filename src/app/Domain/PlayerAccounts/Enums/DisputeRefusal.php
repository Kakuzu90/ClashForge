<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Why a dispute action was refused (specs/13 §5 guardrails), for the screens to explain.
 */
enum DisputeRefusal: string implements HasLabelAndColor
{
    use EnumHelpers;

    case NotHeld = 'not_held';
    case OwnAccount = 'own_account';
    case AlreadyDisputed = 'already_disputed';
    case TagSuspended = 'tag_suspended';
    case TooManyOpen = 'too_many_open';
    case TooManyToday = 'too_many_today';
    case TooManyTags = 'too_many_tags';
    case Barred = 'barred';
    case NotYourTurn = 'not_your_turn';
    case Closed = 'closed';
    case HolderCannotKeep = 'holder_cannot_keep';
    case ClaimantUnavailable = 'claimant_unavailable';
    case RecentlyWithdrawn = 'recently_withdrawn';
    case NotSuspended = 'not_suspended';

    public function label(): string
    {
        return match ($this) {
            self::NotHeld => 'Nobody holds this account as verified; verify it with your token instead',
            self::OwnAccount => 'This account is already verified as yours',
            self::AlreadyDisputed => 'This account is already under review',
            self::TagSuspended => 'This account is suspended while staff review it',
            self::TooManyOpen => 'You have too many disputes running',
            self::TooManyToday => 'You opened as many disputes as one day allows. Try again tomorrow',
            self::TooManyTags => 'You looked up too many new tags this hour. Try again later',
            self::Barred => 'You cannot open disputes for a while after earlier ones were denied',
            self::NotYourTurn => 'This dispute is not waiting on you yet',
            self::Closed => 'This dispute is closed',
            self::HolderCannotKeep => 'A banned holder cannot keep the account',
            self::RecentlyWithdrawn => 'You withdrew a dispute for this account recently',
            self::NotSuspended => 'This tag is no longer suspended',
            self::ClaimantUnavailable => 'The claimant cannot receive the account: banned, suspended or leaving',
        };
    }

    public function color(): string
    {
        return 'state-warning';
    }
}
