<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Models\User;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §9: the token lives only in the request that verifies it. Not in the session, old
// input, props or logs, whatever the outcome.

uses(InteractsWithCoc::class);

it('keeps the token out of the session, the page and the logs', function (string $token, bool $valid, ?string $invalidField) {
    $this->captureAppLog();
    $user = User::factory()->create();
    $account = CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->create();
    if ($valid) {
        $this->fakeCoc()->acceptToken(PlayerTag::from('#2PQ8GRJC'), $token);
    }

    $body = ['api_token' => $token];
    if ($invalidField === 'conflict') {
        CocAccount::query()->delete();
        CocAccount::factory()->forTag('#2PQ8GRJC')->verified()->create();
        $this->actingAs($user)->post('/accounts/attach/preview', ['tag' => '#2PQ8GRJC']);
        $this->post('/accounts/attach/verify-tag', [...$body, 'tag' => '#2PQ8GRJC'])->assertRedirect();
        $account = CocAccount::query()->where('user_id', $user->id)->first() ?? CocAccount::factory()->for($user)->forTag('#GRJ0P8UV')->create();
    } elseif ($invalidField === 'tag') {
        $this->actingAs($user)->from('/accounts/attach')->post('/accounts/attach/verify-tag', [...$body, 'tag' => '#bad'])->assertSessionHasErrors('tag');
    } else {
        $this->actingAs($user)->post("/accounts/{$account->ulid}/verify", $body)->assertRedirect();
    }
    $page = $this->get("/accounts/{$account->ulid}/verify")->getContent().$this->get('/accounts/attach?tag=%232PQ8GRJC')->getContent();

    $needle = trim($token);
    expect(json_encode(session()->all()))->not->toContain($needle)
        ->and(session()->getOldInput())->not->toHaveKey('api_token')
        ->and((string) $page)->not->toContain($needle)
        ->and($this->appLogText())->not->toContain($needle);
})->with([
    'valid token' => ['tok-valid-'.bin2hex(random_bytes(4)), true, null],
    'refused token' => ['tok-refused-'.bin2hex(random_bytes(4)), false, null],
    'validation failure elsewhere' => ['tok-flash-'.bin2hex(random_bytes(4)), false, 'tag'],
    'conflict card, valid token' => ['tok-claim-'.bin2hex(random_bytes(4)), true, 'conflict'],
    'conflict card, refused token' => ['tok-claim-bad-'.bin2hex(random_bytes(4)), false, 'conflict'],
]);
