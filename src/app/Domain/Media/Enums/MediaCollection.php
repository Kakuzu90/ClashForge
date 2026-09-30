<?php

namespace App\Domain\Media\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * Where an upload is headed. Limits and variants live in config('media.collections') (specs/10 §4–5, §8).
 */
enum MediaCollection: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Avatar = 'avatar';
    case AccountImage = 'account_image';
    case BaseScreenshot = 'base_screenshot';
    case BaseVideo = 'base_video';
    case Evidence = 'evidence';
    case Portfolio = 'portfolio';

    public function label(): string
    {
        return match ($this) {
            self::Avatar => 'Avatar',
            self::AccountImage => 'Account image',
            self::BaseScreenshot => 'Base screenshot',
            self::BaseVideo => 'Base video',
            self::Evidence => 'Report evidence',
            self::Portfolio => 'Portfolio image',
        };
    }

    public function color(): string
    {
        return 'text-muted';
    }

    public function kind(): MediaKind
    {
        return MediaKind::from($this->setting('kind'));
    }

    public function visibility(): MediaVisibility
    {
        return MediaVisibility::from($this->setting('visibility'));
    }

    public function maxBytes(): int
    {
        return (int) $this->setting('max_bytes');
    }

    public function acceptsUploads(): bool
    {
        return (bool) $this->setting('accepts_uploads');
    }

    /**
     * @return array<string, array{width: int, square?: bool}>
     */
    public function variants(): array
    {
        /** @var array<string, array{width: int, square?: bool}> */
        return $this->setting('variants');
    }

    /**
     * Collections the intent endpoint currently accepts.
     *
     * @return list<self>
     */
    public static function uploadable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->acceptsUploads()));
    }

    private function setting(string $key): mixed
    {
        return config("media.collections.{$this->value}.{$key}");
    }
}
