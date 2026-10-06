<?php

namespace App\Http\Requests\Disputes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A party's answer in their dispute (specs/13 §5 3b, or what an admin asked for): a statement and
 * up to the images the party has left.
 */
class RespondDisputeRequest extends FormRequest
{
    /**
     * Authorization runs in DisputeService through CocAccountDisputePolicy.
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
            'statement' => ['required', 'string', 'max:'.(int) config('coc.disputes.text_max')],
            ...DisputeEvidenceRules::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statement.required' => 'Write your answer for the admins.',
            ...DisputeEvidenceRules::messages(),
        ];
    }

    public function statement(): string
    {
        return $this->string('statement')->toString();
    }

    /**
     * @return list<string>
     */
    public function evidence(): array
    {
        return DisputeEvidenceRules::ulids($this);
    }
}
