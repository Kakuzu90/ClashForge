<?php

namespace App\Http\Requests\Settings;

use App\Domain\Users\Data\ProfileFieldRules;
use App\Domain\Users\Data\UpdateProfileData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The profile fields of FR-PROFILE-2. The Users value objects hold the rules; only the validated
 * keys reach the service, so `user_id`, `avatar_media_id`, `role` or `status` in the body do nothing.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Authorization runs in ProfileService through ProfilePolicy.
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
        $socials = [];

        foreach (ProfileFieldRules::socialNetworks() as $network) {
            $socials["socials.{$network}"] = ['nullable', 'string', 'max:100', self::check(fn (mixed $value): ?string => ProfileFieldRules::socialError($network, $value))];
        }

        return [
            'display_name' => ['nullable', 'string', 'max:'.(int) config('platform.profile.display_name_max')],
            'bio' => ['nullable', 'string', 'max:'.(int) config('platform.profile.bio_max')],
            'country_code' => ['nullable', 'string', self::check(fn (mixed $value): ?string => ProfileFieldRules::countryError((string) $value))],
            'languages' => ['present', 'array', self::check(fn (mixed $value): ?string => ProfileFieldRules::languagesError((array) $value))],
            'timezone' => ['nullable', 'string', self::check(fn (mixed $value): ?string => ProfileFieldRules::timezoneError((string) $value))],
            'socials' => ['present', 'array'],
            ...$socials,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display_name' => 'display name',
            'country_code' => 'country',
        ];
    }

    public function toData(): UpdateProfileData
    {
        $country = $this->validated('country_code');
        $timezone = $this->validated('timezone');
        $displayName = $this->validated('display_name');
        $bio = $this->validated('bio');

        return UpdateProfileData::fromValidated(
            displayName: is_string($displayName) ? $displayName : null,
            bio: is_string($bio) ? $bio : null,
            countryCode: is_string($country) ? $country : null,
            languages: (array) $this->validated('languages', []),
            timezone: is_string($timezone) ? $timezone : null,
            socials: (array) $this->validated('socials', []),
        );
    }

    /**
     * A rule from a check that returns its error message, or null when the value passes.
     *
     * @param  Closure(mixed): ?string  $error
     * @return Closure(string, mixed, Closure): void
     */
    private static function check(Closure $error): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($error): void {
            if (($message = $error($value)) !== null) {
                $fail($message);
            }
        };
    }
}
