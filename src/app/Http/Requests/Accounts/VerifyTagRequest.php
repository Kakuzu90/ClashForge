<?php

namespace App\Http\Requests\Accounts;

use App\Domain\CocIntegration\Data\CocTagFieldRules;
use App\Domain\CocIntegration\Data\PlayerTag;

/**
 * A token sent from the conflict card, for a tag the user has not attached (specs/13 §4 A).
 */
class VerifyTagRequest extends ApiTokenRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['tag' => CocTagFieldRules::player(), ...parent::rules()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tag' => 'player tag', ...parent::attributes()];
    }

    public function tag(): PlayerTag
    {
        return PlayerTag::from($this->string('tag')->trim()->toString());
    }
}
