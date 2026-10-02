<?php

use App\Domain\CocIntegration\Data\CocTagFieldRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

it('accepts a tag the client accepts', function (string $input) {
    expect(Validator::make(['tag' => $input], ['tag' => CocTagFieldRules::player()])->passes())->toBeTrue()
        ->and(Validator::make(['tag' => $input], ['tag' => CocTagFieldRules::clan()])->passes())->toBeTrue();
})->with(['#2PQ8GRJC', '2pq8grjo', '  #PYL  ']);

it('rejects an invalid tag before any API call (FR-COC-2)', function (mixed $input, string $message) {
    Http::fake();

    $validator = Validator::make(['tag' => $input], ['tag' => CocTagFieldRules::player()]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('tag'))->toContain($message);
    Http::assertNothingSent();
})->with([
    'missing' => ['', 'required'],
    'not a string' => [[1], 'must be a string'],
    'bad characters' => ['#ABCD', 'A player tag uses only'],
    'too long' => [str_repeat('2', CocTagFieldRules::MAX_INPUT + 1), 'must not be greater than'],
]);
