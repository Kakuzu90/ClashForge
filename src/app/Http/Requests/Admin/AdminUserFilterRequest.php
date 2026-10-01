<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Data\AdminUserFilterData;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Queries\AdminUserQuery;
use App\Support\Rules\Utf8Text;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin user list filters (FR-ADMIN-2). Authorizes first, so someone without the ability gets a
 * 403 rather than validation feedback. Invalid filters return to the unfiltered list.
 */
class AdminUserFilterRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'admin.users.index';

    public function authorize(): bool
    {
        return Gate::allows(StaffAbility::ViewUsers->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // An email is at most 254 characters (RFC 5321).
            'search' => ['nullable', 'string', 'max:254', new Utf8Text],
            'role' => ['nullable', Rule::in(array_map(fn (Role $role): string => $role->value, self::listedRoles()))],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'cursor' => ['nullable', 'string', 'max:512', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! AdminUserQuery::acceptsCursor($value)) {
                    $fail('That page link is not valid. Start from the first page.');
                }
            }],
        ];
    }

    /**
     * Super admins are never listed, so they are not a filter either.
     *
     * @return list<Role>
     */
    public static function listedRoles(): array
    {
        $roles = [];
        foreach (Role::cases() as $role) {
            if ($role !== Role::SuperAdmin) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    public function filters(): AdminUserFilterData
    {
        $search = trim($this->string('search')->toString());

        return new AdminUserFilterData(
            search: $search === '' ? null : $search,
            role: $this->enum('role', Role::class),
            status: $this->enum('status', UserStatus::class),
        );
    }

    public function cursor(): ?string
    {
        $cursor = $this->string('cursor')->toString();

        return $cursor === '' ? null : $cursor;
    }
}
