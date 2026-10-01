<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\UnsubscribeOutcome;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Support\UnsubscribeCapability;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnsubscribeService
{
    public function __construct(private readonly EmailPreferenceService $preferences) {}

    public function inspect(string $ulid, string $hash, string $url): UnsubscribeOutcome
    {
        $owner = User::query()->whereUlid(strtolower($ulid))->first();
        if ($owner === null || ! Gate::forUser(null)->allows('unsubscribe', [NotificationPreference::class, $owner, new UnsubscribeCapability($ulid, $hash, $url)])) {
            return UnsubscribeOutcome::Invalid;
        }

        return $this->preferences->settings($owner)->non_security_email_enabled ? UnsubscribeOutcome::Pending : UnsubscribeOutcome::Unsubscribed;
    }

    public function unsubscribe(string $ulid, string $hash, string $url): UnsubscribeOutcome
    {
        return DB::transaction(function () use ($ulid, $hash, $url): UnsubscribeOutcome {
            $owner = User::query()->whereUlid(strtolower($ulid))->lockForUpdate()->first();
            if ($owner === null || ! Gate::forUser(null)->allows('unsubscribe', [NotificationPreference::class, $owner, new UnsubscribeCapability($ulid, $hash, $url)])) {
                return UnsubscribeOutcome::Invalid;
            }

            $settings = $this->preferences->settings($owner);
            $settings->non_security_email_enabled = false;
            $settings->save();

            return UnsubscribeOutcome::Unsubscribed;
        });
    }
}
