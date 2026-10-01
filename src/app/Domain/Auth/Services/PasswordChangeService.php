<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Events\PasswordChanged;
use App\Domain\Auth\Notifications\PasswordChangedNotification;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Changing the password from settings (specs/04 §4, specs/11). The current password is the
 * re-confirmation; the new one has already passed the policy in the form request. Every other
 * session ends and their remember cookies stop working; this one stays signed in.
 */
class PasswordChangeService
{
    public function __construct(private readonly SessionService $sessions) {}

    public function change(User $user, string $currentPassword, string $newPassword, ?string $currentSessionId): void
    {
        $account = DB::transaction(function () use ($user, $currentPassword, $newPassword, $currentSessionId): User {
            $current = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($current)->authorize('changePassword', $current);
            if ($current->password === null || ! Hash::check($currentPassword, $current->password)) {
                Log::channel('security')->warning('auth.password_change_failed', ['user' => $current->ulid, 'ip_hash' => IpHash::of(request()->ip())]);
                throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
            }
            $current->forceFill(['password' => $newPassword])->save();
            $this->sessions->endOthers($current, $currentSessionId);

            return $current;
        });

        Log::channel('security')->info('auth.password_changed', ['user' => $user->ulid, 'ip_hash' => IpHash::of(request()->ip())]);
        $account->notify(new PasswordChangedNotification);
        PasswordChanged::dispatch($user->id);
    }
}
