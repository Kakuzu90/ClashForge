<?php

namespace App\Domain\Media\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Lifecycle from FR-MEDIA-7: pending → uploaded → processing → ready / failed / quarantined.
 */
enum MediaStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Quarantined = 'quarantined';
    case Deleting = 'deleting';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for upload',
            self::Uploaded => 'Uploaded',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
            self::Quarantined => 'Held for review',
            self::Deleting => 'Deleting',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Uploaded, self::Processing => 'state-info',
            self::Ready => 'state-success',
            self::Failed => 'state-danger',
            self::Quarantined => 'state-warning',
            self::Deleting => 'text-muted',
        };
    }

    /**
     * Processing has finished one way or the other; the client stops polling.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed, self::Quarantined, self::Deleting], true);
    }
}
