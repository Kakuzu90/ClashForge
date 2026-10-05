<?php

namespace App\Http\Requests\Admin;

use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin's decision on a dispute (specs/13 §5 step 4). The internal note is required (specs/04
 * §2 rule 2); DisputeService authorizes and decides.
 */
class DecideDisputeRequest extends FormRequest
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
            'decision' => ['required', Rule::enum(DisputeDecision::class)],
            'note' => ['required', 'string', 'max:'.config('coc.disputes.text_max')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Choose a decision.',
            'note.required' => 'Write an internal note: what you weighed and why.',
        ];
    }

    public function decision(): DisputeDecision
    {
        return $this->enum('decision', DisputeDecision::class) ?? DisputeDecision::Deny;
    }

    public function note(): string
    {
        return trim($this->string('note')->toString());
    }
}
