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

    case EmailVerified = 'email_verified';
    case PasswordChanged = 'password_changed';
    case NewDeviceSignIn = 'new_device_sign_in';
    case AccountSuspended = 'account_suspended';
    case AccountBanned = 'account_banned';
    case SanctionEnded = 'sanction_ended';
    case MediaProcessingFailed = 'media_processing_failed';
    case CocAccountVerified = 'coc_account_verified';
    case CocAccountTakenOver = 'coc_account_taken_over';
    case CocAccountNotFound = 'coc_account_not_found';
    case CocAccountReleased = 'coc_account_released';
    case CocDisputeOpened = 'coc_dispute_opened';
    case CocDisputeReminder = 'coc_dispute_reminder';
    case CocDisputeInfoRequested = 'coc_dispute_info_requested';
    case CocDisputeClosed = 'coc_dispute_closed';

    public function label(): string
    {
        return match ($this) {
            self::EmailVerified => 'Email confirmed',
            self::PasswordChanged => 'Password changed',
            self::NewDeviceSignIn => 'New sign-in',
            self::AccountSuspended => 'Account suspended',
            self::AccountBanned => 'Account banned',
            self::SanctionEnded => 'Sanction over',
            self::MediaProcessingFailed => 'Upload failed',
            self::CocAccountVerified => 'Account verified',
            self::CocAccountTakenOver => 'Account taken over',
            self::CocAccountNotFound => 'Account not found',
            self::CocAccountReleased => 'Account removed',
            self::CocDisputeOpened => 'Ownership disputed',
            self::CocDisputeReminder => 'Dispute reminder',
            self::CocDisputeInfoRequested => 'More info needed',
            self::CocDisputeClosed => 'Dispute closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AccountSuspended, self::AccountBanned, self::MediaProcessingFailed, self::CocAccountTakenOver => 'state-danger',
            self::PasswordChanged, self::NewDeviceSignIn, self::CocAccountNotFound => 'state-warning',
            self::EmailVerified, self::SanctionEnded, self::CocAccountVerified => 'state-success',
            self::CocAccountReleased, self::CocDisputeInfoRequested, self::CocDisputeClosed => 'state-info',
            self::CocDisputeOpened, self::CocDisputeReminder => 'state-warning',
        };
    }

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::MediaProcessingFailed => NotificationCategory::Bases,
            self::CocAccountVerified, self::CocAccountTakenOver, self::CocAccountNotFound, self::CocAccountReleased,
            self::CocDisputeOpened, self::CocDisputeReminder, self::CocDisputeInfoRequested, self::CocDisputeClosed => NotificationCategory::Ownership,
            default => NotificationCategory::Security,
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function render(array $params): RenderedNotificationData
    {
        return match ($this) {
            self::EmailVerified => new RenderedNotificationData(
                title: 'Your email is confirmed',
                body: 'Thanks for confirming. Your account is all set.',
                url: null,
            ),
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
                actionLabel: 'View upload settings',
            ),
            self::CocAccountVerified => new RenderedNotificationData(
                title: 'Your Clash of Clans account is verified',
                body: ucfirst(self::cocAccount($params, 'your account')).' is now verified on your Clash Commons account.',
                // Notices written before the account page (P2-04) carry no `account` and keep no link.
                url: self::accountUrl($params),
                actionLabel: 'View your account',
            ),
            // `method` is stored for the dispute decision's wording (P2-03); a token is the only path now.
            self::CocAccountTakenOver => new RenderedNotificationData(
                title: 'Someone else verified one of your accounts',
                body: 'Someone verified '.self::cocAccount($params, 'one of your Clash of Clans accounts').' with an in-game API token, so it is no longer verified on your Clash Commons account. If that was not you, someone else can get into your game account: secure it in game, then verify it again with a new token.',
                url: is_string($params['tag'] ?? null) && $params['tag'] !== '' ? route('accounts.attach', ['tag' => $params['tag']], absolute: false) : null,
            ),
            // specs/09 §6: after three 404s in a row. The account stays verified (specs/13 §9).
            self::CocAccountNotFound => new RenderedNotificationData(
                title: "We can't find one of your accounts",
                body: 'Clash of Clans no longer finds '.self::cocAccount($params, 'one of your accounts').'. It may have been renamed or deleted in game. It stays verified on your Clash Commons account, and we keep checking.',
                url: null,
            ),
            // specs/16 §2 "Tag released". The removed account has no page for its former owner any more.
            self::CocAccountReleased => new RenderedNotificationData(
                title: 'An account was removed from your profile',
                body: ucfirst(self::cocAccount($params, 'one of your Clash of Clans accounts')).' is no longer on your Clash Commons account. Anyone with its in-game API token can verify it now.',
                url: null,
            ),
            // Dispute notices (P2-18) name the tag only, never the other party (owner decision
            // 2026-10-06). The holder's link is their account page; the claimant's comes with P2-16.
            self::CocDisputeOpened => new RenderedNotificationData(
                title: 'Someone disputes your ownership of '.self::tag($params),
                body: 'Another player says '.self::tag($params).' is theirs and asked the admins to review it. '
                    .self::within($params).'Verify it again with a new in-game API token to end the review at once.',
                url: self::accountUrl($params),
                actionLabel: 'Review your account',
            ),
            self::CocDisputeReminder => new RenderedNotificationData(
                title: 'Answer the review of '.self::tag($params),
                body: self::within($params).'Verify '.self::tag($params).' again with a new in-game API token to end the review. Otherwise the admins decide without your answer.',
                url: self::accountUrl($params),
                actionLabel: 'Review your account',
            ),
            self::CocDisputeInfoRequested => new RenderedNotificationData(
                title: 'The admins need more about '.self::tag($params),
                body: trim('The admins reviewing the ownership of '.self::tag($params).' asked you for more information. '.self::within($params)),
                url: self::accountUrl($params),
                actionLabel: 'Review your account',
            ),
            self::CocDisputeClosed => self::disputeOutcome($params),
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
     * "#2PP0LJQ (Chief Pat)", the tag alone (the takeover notice stores no name), or the fallback when the row has neither.
     *
     * @param  array<string, mixed>  $params
     */
    private static function cocAccount(array $params, string $fallback): string
    {
        $tag = is_string($params['tag'] ?? null) && $params['tag'] !== '' ? $params['tag'] : null;
        // Control and direction-override characters could reorder the sentence around the name.
        $name = is_string($params['name'] ?? null) ? trim((string) preg_replace('/[\p{Cc}\p{Cf}]/u', '', $params['name'])) : '';
        $name = $name === '' ? null : $name;

        return match (true) {
            $tag === null => $fallback,
            $name === null => $tag,
            default => "{$tag} ({$name})",
        };
    }

    /**
     * The account page for the `account` ulid param, only for a well-formed ulid.
     *
     * @param  array<string, mixed>  $params
     */
    private static function accountUrl(array $params): ?string
    {
        $ulid = $params['account'] ?? null;

        return is_string($ulid) && preg_match('/^[0-9A-Za-z]{26}$/D', $ulid) === 1 ? route('accounts.show', ['ulid' => $ulid], absolute: false) : null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function tag(array $params): string
    {
        return self::cocAccount(['tag' => $params['tag'] ?? null], 'your Clash of Clans account');
    }

    /**
     * "You have 4 days to answer. ", or nothing without a usable count.
     *
     * @param  array<string, mixed>  $params
     */
    private static function within(array $params): string
    {
        $days = $params['days'] ?? null;

        return is_int($days) && $days > 0 ? 'You have '.($days === 1 ? '1 day' : "{$days} days").' to answer. ' : '';
    }

    /**
     * How a dispute ended for this recipient (`outcome`, P2-18).
     *
     * @param  array<string, mixed>  $params
     */
    private static function disputeOutcome(array $params): RenderedNotificationData
    {
        $tag = self::tag($params);
        $verifyAgain = is_string($params['tag'] ?? null) && $params['tag'] !== '' ? route('accounts.attach', ['tag' => $params['tag']], absolute: false) : null;

        return match ($params['outcome'] ?? null) {
            'transferred_to_you' => new RenderedNotificationData("{$tag} is now yours", "The admins reviewed your claim and moved {$tag} to your Clash Commons account.", self::accountUrl($params), 'View your account'),
            'released_to_you' => new RenderedNotificationData("{$tag} is now yours", "The holder gave {$tag} up, so it is now verified on your Clash Commons account.", self::accountUrl($params), 'View your account'),
            'transferred_away' => new RenderedNotificationData("{$tag} was moved to another player", "The admins reviewed the claim to {$tag} and moved it to the other player's account. If it is yours, verify it again with a new in-game API token.", $verifyAgain, 'Verify it again'),
            'kept' => new RenderedNotificationData("{$tag} stays yours", "The admins reviewed the claim to {$tag}. It stays on your Clash Commons account.", self::accountUrl($params), 'View your account'),
            'denied' => new RenderedNotificationData("Your claim to {$tag} was not accepted", "The admins reviewed your claim and {$tag} stays with its current holder. If it is yours, you can still verify it with a new in-game API token.", $verifyAgain, 'Verify with a token'),
            'denied_token' => new RenderedNotificationData("Your claim to {$tag} was closed", "The holder verified {$tag} again with an in-game API token, which ends a dispute. If it is yours, secure the game account, then verify it with a new token.", $verifyAgain, 'Verify with a token'),
            'suspended' => new RenderedNotificationData("{$tag} is suspended", "After reviewing the dispute, the admins suspended {$tag} on Clash Commons. Nobody can verify it for now.", null),
            'withdrawn' => new RenderedNotificationData("The review of {$tag} is over", "The claim to {$tag} was withdrawn. It stays on your Clash Commons account.", self::accountUrl($params), 'View your account'),
            'withdrawn_inactive' => new RenderedNotificationData("Your claim to {$tag} was closed", 'The admins asked you for more and got no answer in time, so the claim was closed.', null),
            'verified_by_other' => new RenderedNotificationData("The review of {$tag} is over", "Someone verified {$tag} with an in-game API token, which ends the dispute.", null),
            default => new RenderedNotificationData("The review of {$tag} is over", 'The ownership dispute is closed.', null),
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
