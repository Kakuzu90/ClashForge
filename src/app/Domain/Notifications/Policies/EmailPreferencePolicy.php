<?php

namespace App\Domain\Notifications\Policies;

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Support\UnsubscribeCapability;
use App\Models\User;

class EmailPreferencePolicy
{
    public function view(User $user, NotificationPreference $preferences): bool
    {
        return $user->id === $preferences->user_id && $user->deleted_at === null && $user->effectiveStatus() !== UserStatus::Banned;
    }

    public function update(User $user, NotificationPreference $preferences): bool
    {
        return $this->view($user, $preferences);
    }

    public function unsubscribe(?User $viewer, User $recipient, UnsubscribeCapability $capability): bool
    {
        return $capability->allows($recipient);
    }
}
