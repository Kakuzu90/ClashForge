<?php

use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Models\User;

// specs/11 "Props exposure" and "Account enumeration" for /accounts/{ulid} (P2-04).

beforeEach(function () {
    $this->owner = User::factory()->create(['username' => 'hiddenowner', 'email' => 'owner@example.test']);
    $this->account = CocAccount::factory()->for($this->owner)->verified()->create([
        'ign' => '<script>alert(1)</script>',
        'raw_payload' => ['marker' => 'raw-payload-marker'],
        'api_sync_failures' => 1,
    ]);
    CocAccountClaim::factory()->create(['coc_account_id' => $this->account->id, 'user_id' => $this->owner->id, 'tag_normalized' => $this->account->tag_normalized, 'ip_hash' => 'ip-hash-marker']);
});

it('sends nothing about the owner, the claims or the raw payload', function () {
    $html = $this->get("/accounts/{$this->account->ulid}")->assertOk()->getContent();

    expect($html)->not->toContain('owner@example.test')
        ->not->toContain('hiddenowner')
        ->not->toContain('raw-payload-marker')
        ->not->toContain('ip-hash-marker')
        ->not->toContain('apiSyncFailures')
        ->not->toContain('"userId"');
});

it('escapes an in-game name in the head and the page data', function () {
    $html = $this->get("/accounts/{$this->account->ulid}")->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('answers a malformed ulid with a 404, not an error', function (string $ulid) {
    $this->get("/accounts/{$ulid}")->assertNotFound();
})->with(['short', str_repeat('Z', 27), '01J0000000000000000000000%27']);
