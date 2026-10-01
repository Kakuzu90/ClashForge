<?php

namespace App\Http\Requests\Settings;

use App\Domain\Auth\Data\EmailFieldRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new address (FR-AUTH-8): the registration rules, disposable blocklist included, and the
 * current password, which EmailChangeService checks (specs/11 re-confirmation). Whether
 * another account uses it is never a field error (specs/11 "Account enumeration");
 * EmailChangeService authorizes and handles that.
 */
class ChangeEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => trim($email)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => EmailFieldRules::rules(),
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'new email', 'current_password' => 'current password'];
    }
}
