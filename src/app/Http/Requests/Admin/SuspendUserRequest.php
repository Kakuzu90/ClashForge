<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use Illuminate\Support\Facades\Gate;

/**
 * A suspension of 1 to `moderation.sanctions.suspension_max_days` days. The ability is checked
 * first; the rank rule needs the account, so SanctionService checks it.
 */
class SuspendUserRequest extends ApplySanctionRequest
{
    public function authorize(): bool
    {
        return Gate::allows(StaffAbility::SuspendUser->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'days' => ['required', 'integer', 'min:1', 'max:'.(int) config('moderation.sanctions.suspension_max_days')],
        ];
    }
}
