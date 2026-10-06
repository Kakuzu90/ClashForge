<?php

namespace App\Http\Requests\Accounts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Adds a finished upload to an account (P2-23). Only the media ULID is read; who may add, the
 * collection and the count are the service's to check.
 */
class StoreAccountImageRequest extends FormRequest
{
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
            'media' => ['required', 'string', 'size:26', 'alpha_num:ascii'],
        ];
    }
}
