<?php

namespace App\Http\Requests\Disputes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The evidence field shared by opening and answering: a short list of upload ULIDs.
 */
final class DisputeEvidenceRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'evidence' => ['nullable', 'array', 'max:'.(int) config('coc.disputes.evidence_max')],
            'evidence.*' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'evidence.max' => 'Attach at most '.(int) config('coc.disputes.evidence_max').' images.',
            'evidence.*' => 'One of the images did not upload properly. Remove it and upload it again.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function ulids(FormRequest $request): array
    {
        /** @var list<string> $ulids */
        $ulids = array_values(array_map(strval(...), (array) $request->validated('evidence', [])));

        return $ulids;
    }
}
