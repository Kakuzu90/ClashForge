<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * Fortify's sign-in request with an email format check, so a typo gets "Enter a valid email
 * address" instead of the wrong-password message. The check never looks up an account, so it
 * says nothing about which emails exist (specs/11 "Account enumeration").
 */
class LoginRequest extends FortifyLoginRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
