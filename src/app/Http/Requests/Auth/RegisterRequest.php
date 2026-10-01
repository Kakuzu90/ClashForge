<?php

namespace App\Http\Requests\Auth;

use App\Domain\Auth\Contracts\TurnstileVerifier;
use App\Domain\Auth\Data\RegistrationData;
use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Services\DisposableEmailDomains;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Registration (FR-AUTH-1/2/11). The email is never checked against existing accounts here: that
 * answer only goes to the inbox (specs/11 "Account enumeration"). Turnstile runs last, and only
 * when everything else passed, so a typo does not spend the widget's single-use token.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Only strings are tidied; anything else reaches the `string` rules as sent.
        $email = $this->input('email');
        $username = $this->input('username');
        $this->merge(array_filter([
            'email' => is_string($email) ? trim($email) : null,
            'username' => is_string($username) ? strtolower(trim($username)) : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && app(DisposableEmailDomains::class)->isDisposable($value)) {
                    $fail('Use an email address you will keep. Throwaway inboxes are not accepted.');
                }
            }],
            'username' => UsernameFieldRules::forRegistration(),
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'turnstile_token' => ['nullable', 'string', 'max:'.(int) config('services.turnstile.max_token_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return UsernameFieldRules::messages();
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! app(TurnstileVerifier::class)->passes($this->input('turnstile_token'), $this->ip())) {
                $validator->errors()->add('turnstile_token', 'We could not confirm you are not a bot. Try again.');
            }
        }];
    }

    public function registration(): RegistrationData
    {
        /** @var array{email: string, username: string, password: string} $valid */
        $valid = $this->validated();

        return new RegistrationData(email: $valid['email'], username: $valid['username'], password: $valid['password']);
    }
}
