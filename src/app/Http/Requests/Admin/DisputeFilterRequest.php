<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\StaffAbility;
use App\Domain\PlayerAccounts\Enums\DisputeQueueView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The admin dispute queue's filters (P2-17). A cursor that does not decode starts at page one.
 */
class DisputeFilterRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'admin.disputes.index';

    public function authorize(): bool
    {
        return Gate::allows(StaffAbility::ResolveDisputes->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::enum(DisputeQueueView::class)],
            'mine' => ['nullable', 'boolean'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }

    public function view(): DisputeQueueView
    {
        return $this->enum('view', DisputeQueueView::class) ?? DisputeQueueView::Active;
    }

    public function mine(): bool
    {
        return $this->boolean('mine');
    }

    public function cursor(): ?string
    {
        $cursor = $this->string('cursor')->toString();

        return $cursor === '' ? null : $cursor;
    }
}
