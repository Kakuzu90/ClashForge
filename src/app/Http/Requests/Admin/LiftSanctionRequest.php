<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Support\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Lifting needs a reason (specs/12 §6), kept as the note of the lift action.
 */
class LiftSanctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(StaffAbility::LiftSanction->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:'.(int) config('moderation.sanctions.note_max'), new Utf8Text],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => 'reason for lifting'];
    }
}
