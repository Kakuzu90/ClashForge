<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SetAvatarRequest extends FormRequest
{
    /**
     * Authorization runs in ProfileService (ProfilePolicy) and MediaAttachmentService (owner scope).
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
        return [
            'media' => ['required', 'string', 'ulid'],
        ];
    }
}
