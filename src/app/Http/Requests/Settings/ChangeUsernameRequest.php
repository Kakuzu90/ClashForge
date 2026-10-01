<?php

namespace App\Http\Requests\Settings;

use App\Domain\Auth\Data\UsernameFieldRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new username (FR-PROFILE-7): the registration rules, lowercased and trimmed first, with the
 * account's own released names allowed back; and the current password, which
 * UsernameChangeService checks along with the policy and the 30-day wait.
 */
class ChangeUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');

        if (is_string($username)) {
            $this->merge(['username' => strtolower(trim($username))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => UsernameFieldRules::forChange((int) $this->user()?->getAuthIdentifier()),
            'current_password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UsernameFieldRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['username' => 'new username', 'current_password' => 'current password'];
    }
}
