<?php

namespace App\Http\Requests\Admin;

use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Support\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields of a suspension or ban (FR-ADMIN-3): reason code, the message the account holder
 * sees and the internal note. SanctionService checks them again, with the policy.
 */
abstract class ApplySanctionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::enum(ReasonCode::class)],
            'public_reason' => ['required', 'string', 'max:'.(int) config('moderation.sanctions.public_reason_max'), new Utf8Text],
            'internal_note' => ['required', 'string', 'max:'.(int) config('moderation.sanctions.note_max'), new Utf8Text],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason_code' => 'reason', 'public_reason' => 'message to the account holder', 'internal_note' => 'internal note', 'days' => 'length'];
    }

    public function sanction(): ApplySanctionData
    {
        return new ApplySanctionData(
            reasonCode: $this->enum('reason_code', ReasonCode::class) ?? ReasonCode::Other,
            publicReason: trim($this->string('public_reason')->toString()),
            internalNote: trim($this->string('internal_note')->toString()),
            days: $this->has('days') ? $this->integer('days') : null,
        );
    }
}
