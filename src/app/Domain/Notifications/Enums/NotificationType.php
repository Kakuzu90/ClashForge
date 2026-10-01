<?php

namespace App\Domain\Notifications\Enums;

use App\Domain\Notifications\Data\RenderedNotificationData;
use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Every in-app notification the platform writes (specs/16 §2). A row stores the type and its
 * parameters; the words and the link are rendered when it is read, so copy fixes reach old rows
 * and no markup is ever stored. Parameters may be missing on old rows: rendering never fails.
 */
enum NotificationType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case PasswordChanged = 'password_changed';
    case NewDeviceSignIn = 'new_device_sign_in';
    case AccountSuspended = 'account_suspended';
    case AccountBanned = 'account_banned';
    case SanctionEnded = 'sanction_ended';
    case MediaProcessingFailed = 'media_processing_failed';

    public function label(): string
    {
        return match ($this) {
            self::PasswordChanged => 'Password changed',
            self::NewDeviceSignIn => 'New sign-in',
            self::AccountSuspended => 'Account suspended',
            self::AccountBanned => 'Account banned',
            self::SanctionEnded => 'Sanction over',
            self::MediaProcessingFailed => 'Upload failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AccountSuspended, self::AccountBanned, self::MediaProcessingFailed => 'state-danger',
            self::PasswordChanged, self::NewDeviceSignIn => 'state-warning',
            self::SanctionEnded => 'state-success',
        };
    }

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::MediaProcessingFailed => NotificationCategory::Bases,
            default => NotificationCategory::Security,
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function render(array $params): RenderedNotificationData
    {
        return match ($this) {
            self::PasswordChanged => new RenderedNotificationData(
                title: 'Your password was changed',
                body: 'Every other device was signed out. If you did not change it, reset your password now.',
                url: route('settings.security.edit', absolute: false),
            ),
            self::NewDeviceSignIn => new RenderedNotificationData(
                title: 'New sign-in to your account',
                body: 'From a device we have not seen before: '.self::where($params).'. If it was not you, sign that device out and change your password.',
                url: route('settings.security.edit', absolute: false),
            ),
            self::AccountSuspended => new RenderedNotificationData(
                title: 'Your account is suspended',
                body: trim(self::moment($params['ends_at'] ?? null, 'Until ').' '.self::reason($params)) ?: 'You can still read your settings and notifications.',
                url: route('account.suspended', absolute: false),
            ),
            self::AccountBanned => new RenderedNotificationData(
                title: 'Your account is banned',
                body: self::reason($params) ?: 'You can no longer use Clash Commons.',
                url: null,
            ),
            self::SanctionEnded => new RenderedNotificationData(
                title: ($params['sanction'] ?? null) === 'ban' ? 'Your ban is over' : 'Your suspension is over',
                body: (($params['expired'] ?? false) === true ? 'It has ended.' : 'It has been lifted.').' Your account works as normal again.',
                url: null,
            ),
            self::MediaProcessingFailed => new RenderedNotificationData(
                title: 'An upload could not be processed',
                body: 'Your '.self::upload($params).' failed to process after several tries. Upload it again.',
                url: ($params['collection'] ?? null) === 'avatar' ? route('settings.profile.edit', absolute: false) : null,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function where(array $params): string
    {
        $device = is_string($params['device'] ?? null) && $params['device'] !== '' ? $params['device'] : 'an unknown device';
        $country = is_string($params['country'] ?? null) && $params['country'] !== '' ? $params['country'] : null;

        return $country === null ? $device : "{$device}, {$country}";
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function reason(array $params): string
    {
        $reason = $params['reason'] ?? null;

        return is_string($reason) && trim($reason) !== '' ? 'Reason: '.trim($reason) : '';
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function upload(array $params): string
    {
        return match ($params['collection'] ?? null) {
            'avatar' => 'avatar',
            'account_image' => 'account image',
            'base_screenshot' => 'base screenshot',
            'base_video' => 'base video',
            'evidence' => 'report evidence',
            'portfolio' => 'portfolio image',
            default => 'upload',
        };
    }

    /**
     * "Until 8 October 2026 at 14:00 UTC.", or nothing for a missing or unreadable time.
     */
    private static function moment(mixed $iso, string $prefix): string
    {
        if (! is_string($iso)) {
            return '';
        }

        try {
            return $prefix.CarbonImmutable::parse($iso)->utc()->format('j F Y \a\t H:i').' UTC.';
        } catch (Throwable) {
            return '';
        }
    }
}
