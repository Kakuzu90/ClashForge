<?php

namespace App\Http\Requests\Settings;

use App\Domain\Notifications\Data\UpdateEmailPreferencesData;
use App\Domain\Notifications\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $categories = array_filter(NotificationCategory::cases(), fn (NotificationCategory $category): bool => $category !== NotificationCategory::Security);
        $rules = ['email_enabled' => ['required', 'boolean'], 'email_categories' => ['required', 'array:'.implode(',', array_map(fn (NotificationCategory $category): string => $category->value, $categories))]];
        foreach ($categories as $category) {
            $rules['email_categories.'.$category->value] = ['required', 'boolean'];
        }

        return $rules;
    }

    public function toData(): UpdateEmailPreferencesData
    {
        $categories = [];
        foreach (NotificationCategory::cases() as $category) {
            if ($category !== NotificationCategory::Security) {
                $categories[$category->value] = $this->boolean('email_categories.'.$category->value);
            }
        }

        return new UpdateEmailPreferencesData($this->boolean('email_enabled'), $categories);
    }
}
