<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * The current password typed inline on a sensitive form (specs/11 "CSRF"), for other modules'
 * sensitive actions. The route shares the `password-confirm` limiter; a wrong guess is logged as on
 * the confirm page.
 */
class PasswordConfirmationService
{
    /**
     * @throws ValidationException
     */
    public function confirm(User $account, #[SensitiveParameter] string $currentPassword, ?string $ip): void
    {
        if ($account->password === null || ! Hash::check($currentPassword, $account->password)) {
            Log::channel('security')->warning('auth.password_confirm_failed', ['user' => $account->ulid, 'ip_hash' => IpHash::of($ip)]);

            throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
        }
    }
}
