<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class RequestAccountDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'confirmation' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password.',
            'confirmation.accepted' => 'Confirm that you understand what account deletion removes.',
        ];
    }
}
