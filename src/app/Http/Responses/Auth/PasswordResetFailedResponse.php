<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;

/**
 * A used, expired or mismatched reset link all read the same (specs/23 §1), so the form never
 * confirms that an email and token belong together.
 */
class PasswordResetFailedResponse implements FailedPasswordResetResponse
{
    public const MESSAGE = 'This link has already been used or has expired. Ask for a new one.';

    public function __construct(public readonly string $status = '') {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): never
    {
        throw ValidationException::withMessages(['email' => self::MESSAGE]);
    }
}
