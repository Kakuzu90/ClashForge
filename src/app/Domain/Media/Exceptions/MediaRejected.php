<?php

namespace App\Domain\Media\Exceptions;

use App\Domain\Media\Enums\MediaFailureReason;
use RuntimeException;

/**
 * A file failed validation. Suspicious rejections quarantine the file instead of failing it
 * (specs/10 §4 "Malware posture"); `detail` is for logs only, never shown to the uploader.
 */
final class MediaRejected extends RuntimeException
{
    private function __construct(
        public readonly MediaFailureReason $reason,
        public readonly bool $suspicious,
        public readonly string $detail,
    ) {
        parent::__construct($reason->value.': '.$detail);
    }

    public static function because(MediaFailureReason $reason, string $detail = ''): self
    {
        return new self($reason, false, $detail);
    }

    public static function suspicious(string $detail): self
    {
        return new self(MediaFailureReason::Suspicious, true, $detail);
    }
}
