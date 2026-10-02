<?php

namespace App\Http\Requests\Accounts;

use App\Domain\CocIntegration\Data\CocTagFieldRules;
use App\Domain\CocIntegration\Data\PlayerTag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A player tag for a lookup or an attach (FR-COC-2): refused before any API call when the value
 * object would refuse it.
 */
class PlayerTagRequest extends FormRequest
{
    /**
     * Authorization runs in AttachAccountService through CocAccountPolicy.
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
        return ['tag' => CocTagFieldRules::player()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tag' => 'player tag'];
    }

    public function tag(): PlayerTag
    {
        return PlayerTag::from($this->string('tag')->trim()->toString());
    }
}
