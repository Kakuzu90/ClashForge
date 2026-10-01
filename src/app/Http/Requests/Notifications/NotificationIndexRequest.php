<?php

namespace App\Http\Requests\Notifications;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Queries\NotificationReadModel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The notification centre's tab and page (FR-NOTIF-1). Only the account's own rows are ever
 * read, so there is nothing to authorize beyond being signed in.
 */
class NotificationIndexRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $redirectRoute = 'notifications.index';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in(array_map(fn (NotificationCategory $category) => $category->value, NotificationCategory::inUse()))],
            'cursor' => ['nullable', 'string', 'max:512', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! NotificationReadModel::acceptsCursor($value)) {
                    $fail('That page link is not valid. Start from the first page.');
                }
            }],
        ];
    }

    public function category(): ?NotificationCategory
    {
        return $this->enum('category', NotificationCategory::class);
    }

    public function cursor(): ?string
    {
        $cursor = trim($this->string('cursor')->toString());

        return $cursor === '' ? null : $cursor;
    }
}
