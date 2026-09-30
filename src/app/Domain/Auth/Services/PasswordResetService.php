<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Jobs\SendPasswordResetLinkJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Reset links and the reset itself (FR-AUTH-6). A valid token sets the new password and ends every
 * session the user had: whoever asked for the reset may be locking someone else out. Fortify then
 * cycles the remember token and consumes the token.
 */
class PasswordResetService implements ResetsUserPasswords
{
    /**
     * Queues the lookup and the email; the caller always answers the same way.
     */
    public function requestLink(string $email): void
    {
        SendPasswordResetLinkJob::dispatch(Str::lower(trim($email)));
    }

    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ])->validate();

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill(['password' => (string) $input['password']])->save();

            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        });
    }
}
