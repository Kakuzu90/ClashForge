<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sending the email-change link again takes the current password too, so a copied cookie cannot
 * finish a stale change later (specs/11 re-confirmation). EmailChangeService checks it.
 */
class ResendEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['current_password' => ['required', 'string']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => 'current password'];
    }
}
