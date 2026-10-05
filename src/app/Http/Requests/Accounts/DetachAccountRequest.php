<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Detaching a CoC account takes the current password inline (specs/13 §6, specs/11), like the
 * other sensitive settings forms; guesses share the `password-confirm` limiter on the route.
 */
class DetachAccountRequest extends FormRequest
{
    /**
     * Authorization runs in AccountOwnershipService through CocAccountPolicy.
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
}
