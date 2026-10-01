<?php

namespace App\Http\Requests\Auth;

use App\Domain\Auth\Contracts\TurnstileVerifier;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The reset-link form. Turnstile guards it against scripted link requests (specs/11 "API abuse"),
 * checked only once the email is well formed so a typo keeps the widget's token.
 */
class RequestPasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'turnstile_token' => ['nullable', 'string', 'max:'.(int) config('services.turnstile.max_token_length')],
        ];
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
}
