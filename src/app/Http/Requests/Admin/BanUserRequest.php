<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use Illuminate\Support\Facades\Gate;

/**
 * A permanent ban. The ability is checked first; SanctionService checks the rank rule.
 */
class BanUserRequest extends ApplySanctionRequest
{
    public function authorize(): bool
    {
        return Gate::allows(StaffAbility::BanUser->value);
    }
}
