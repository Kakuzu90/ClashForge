<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The in-game API token (specs/09 §9). It lives only in this request: `api_token` is in the
 * exception handler's `dontFlash`, so a failed validation never puts it in the session.
 */
class ApiTokenRequest extends FormRequest
{
    public const MAX_LENGTH = 64;

    /**
     * Authorization runs in VerifyOwnershipService through CocAccountPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['api_token' => ['bail', 'required', 'string', 'max:'.self::MAX_LENGTH]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['api_token' => 'API token'];
    }

    public function token(): string
    {
        return $this->string('api_token')->trim()->toString();
    }
}
