<?php

namespace App\Http\Requests\Disputes;

use App\Domain\CocIntegration\Data\CocTagFieldRules;
use App\Domain\CocIntegration\Data\PlayerTag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening an ownership dispute (specs/13 §5 step 1): the tag, why it is the claimant's, and up to
 * `coc.disputes.evidence_max` of their own `evidence` uploads. DisputeService checks ownership and
 * collection of every upload again.
 */
class OpenDisputeRequest extends FormRequest
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
            'tag' => CocTagFieldRules::player(),
            'reason' => ['required', 'string', 'max:'.(int) config('coc.disputes.text_max')],
            ...DisputeEvidenceRules::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Tell the admins why this account is yours.',
            ...DisputeEvidenceRules::messages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tag' => 'player tag'];
    }

    public function tag(): PlayerTag
    {
        return PlayerTag::from($this->string('tag')->trim()->toString());
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }

    /**
     * @return list<string>
     */
    public function evidence(): array
    {
        return DisputeEvidenceRules::ulids($this);
    }
}
