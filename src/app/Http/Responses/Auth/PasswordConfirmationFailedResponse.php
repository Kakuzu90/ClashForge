<?php

namespace App\Http\Responses\Auth;

use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordConfirmationResponse;

/**
 * A wrong password on the confirm page: our copy, and a security log line, since a hijacked
 * session guessing the password is what this page exists to stop (specs/11).
 */
class PasswordConfirmationFailedResponse implements FailedPasswordConfirmationResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): never
    {
        $user = $request->user();

        Log::channel('security')->warning('auth.password_confirm_failed', [
            'user' => $user instanceof User ? $user->ulid : null,
            'ip_hash' => IpHash::of($request->ip()),
        ]);

        throw ValidationException::withMessages(['password' => 'That is not your password.']);
    }
}
