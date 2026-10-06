<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Releasing the tag a dispute suspended (specs/13 §2, P2-25). The note is required (specs/04 §2
 * rule 2); DisputeService authorizes and releases.
 */
class ReleaseDisputedTagRequest extends FormRequest
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
        return [
            'note' => ['required', 'string', 'max:'.config('coc.disputes.text_max')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Write an internal note: why the tag can go back into play.',
        ];
    }

    public function note(): string
    {
        return trim($this->string('note')->toString());
    }
}
