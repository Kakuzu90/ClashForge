<?php

namespace App\Http\Requests\Settings;

use App\Domain\Users\Data\UpdatePrivacyData;
use App\Domain\Users\Enums\ProfileVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The five privacy fields the form shows (FR-PROFILE-4). Only validated keys reach the service, so
 * `user_id`, `role`, `status` or the hidden settings in the body do nothing.
 */
class UpdatePrivacyRequest extends FormRequest
{
    /**
     * Authorization runs in PrivacySettingsService through PrivacySettingsPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile_visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'show_coc_accounts' => ['required', 'boolean'],
            'show_clan' => ['required', 'boolean'],
            'allow_recruitment_contact' => ['required', 'boolean'],
            'searchable' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'profile_visibility' => 'who can see your profile',
            'show_coc_accounts' => 'show accounts',
            'allow_recruitment_contact' => 'recruitment contact',
        ];
    }

    public function toData(): UpdatePrivacyData
    {
        return new UpdatePrivacyData(
            visibility: ProfileVisibility::from($this->string('profile_visibility')->toString()),
            showCocAccounts: $this->boolean('show_coc_accounts'),
            showClan: $this->boolean('show_clan'),
            allowRecruitmentContact: $this->boolean('allow_recruitment_contact'),
            searchable: $this->boolean('searchable'),
        );
    }
}
