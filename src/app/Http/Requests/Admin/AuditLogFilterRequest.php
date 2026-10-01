<?php

namespace App\Http\Requests\Admin;

use App\Domain\Audit\Data\AuditLogFilterData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Queries\AuditLogQuery;
use App\Domain\Auth\Enums\StaffAbility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Audit log filters (FR-ADMIN-4). Authorizes first, so someone without the ability gets a 403
 * rather than validation feedback. Invalid filters return to the unfiltered log with the errors.
 */
class AuditLogFilterRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'admin.audit';

    public function authorize(): bool
    {
        return Gate::allows(StaffAbility::ViewAuditLog->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'string', 'max:20'],
            'target' => ['nullable', 'string', 'max:20'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'cursor' => ['nullable', 'string', 'max:512', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ! AuditLogQuery::acceptsCursor($value)) {
                    $fail('That page link is not valid. Start from the first page.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['actor' => 'acted by', 'target' => 'account', 'from' => 'from date', 'to' => 'to date'];
    }

    public function filters(): AuditLogFilterData
    {
        return new AuditLogFilterData(
            actor: $this->text('actor'),
            target: $this->text('target'),
            action: $this->enum('action', AuditAction::class),
            from: $this->day('from'),
            to: $this->day('to'),
        );
    }

    public function cursor(): ?string
    {
        return $this->text('cursor');
    }

    private function text(string $key): ?string
    {
        $value = trim($this->string($key)->toString());

        return $value === '' ? null : $value;
    }

    private function day(string $key): ?CarbonImmutable
    {
        $value = $this->text($key);

        if ($value === null) {
            return null;
        }

        // Validated as Y-m-d already, so this always parses.
        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC') ?: null;
    }
}
