<?php

namespace App\Http\Requests\Disputes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The holder gives the account up (specs/13 §5 3c). An ownership transfer, so it takes the current
 * password inline (specs/11); guesses share the `password-confirm` limiter on the route.
 */
class ReleaseDisputeRequest extends FormRequest
{
    /**
     * Authorization runs in DisputeService through CocAccountDisputePolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['current_password' => ['required', 'string']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['current_password.required' => 'Enter your current password.'];
    }

    public function currentPassword(): string
    {
        return (string) $this->validated('current_password');
    }
}
