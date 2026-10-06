<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Services\AccountImageService;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// specs/11 uploads, IDOR, mass assignment and props exposure for account images (P2-23).

beforeEach(function () {
    Queue::fake([DeleteMediaObjectsJob::class]);
    $this->owner = User::factory()->create(['email' => 'owner@example.test']);
    $this->other = User::factory()->create();
    $this->account = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->verified()->create();
    $this->upload = fn (User $user) => Media::factory()->collection(MediaCollection::AccountImage)->ready()->create(['user_id' => $user->id]);
});

it("answers another user's account with a 404 on add and remove", function () {
    $media = ($this->upload)($this->other);

    $this->actingAs($this->other)->post("/accounts/{$this->account->ulid}/images", ['media' => $media->ulid])->assertNotFound();

    $mine = ($this->upload)($this->owner);
    app(AccountImageService::class)->add($this->owner, $this->account->ulid, $mine->ulid);
    $this->actingAs($this->other)->delete("/accounts/{$this->account->ulid}/images/{$mine->ulid}")->assertNotFound();

    expect($this->account->refresh()->images_count)->toBe(1)
        ->and($mine->refresh()->attachable_id)->toBe($this->account->id);
});

it("answers another user's upload with a 404, leaving it untouched", function () {
    $theirs = ($this->upload)($this->other);

    $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/images", ['media' => $theirs->ulid])->assertNotFound();

    expect($theirs->refresh()->attachable_id)->toBeNull()
        ->and($this->account->refresh()->images_count)->toBe(0);
});

it('cannot remove an image of another account through this one', function () {
    $second = CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->create();
    $media = ($this->upload)($this->owner);
    app(AccountImageService::class)->add($this->owner, $second->ulid, $media->ulid);

    $this->actingAs($this->owner)->delete("/accounts/{$this->account->ulid}/images/{$media->ulid}")->assertNotFound();

    expect($second->refresh()->images_count)->toBe(1);
});

it('ignores posted counts, owners and positions', function () {
    $media = ($this->upload)($this->owner);

    $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/images", [
        'media' => $media->ulid,
        'images_count' => 0,
        'user_id' => $this->other->id,
        'position' => 99,
    ])->assertRedirect();

    expect($this->account->refresh()->images_count)->toBe(1)
        ->and($this->account->user_id)->toBe($this->owner->id)
        ->and($media->refresh()->position)->toBe(0);
});

it('rejects a malformed media id', function (mixed $value) {
    $this->actingAs($this->owner)->from("/accounts/{$this->account->ulid}")
        ->post("/accounts/{$this->account->ulid}/images", ['media' => $value])
        ->assertSessionHasErrors('media');
})->with(['missing' => [null], 'short' => ['abc'], 'injection' => ["01J0000000000000000000000'"]]);

it('keeps restricted, suspended and unverified-email accounts from adding', function (string $state) {
    $user = match ($state) {
        'restricted' => User::factory()->restricted()->create(),
        'suspended' => User::factory()->suspended()->create(),
        'unverified email' => User::factory()->unverified()->create(),
    };
    $account = CocAccount::factory()->for($user)->forTag('#LQ2RJ9P0')->verified()->create();
    $media = ($this->upload)($user);

    // Suspended accounts are sent to their notice before the write gate; the others get its 403.
    $response = $this->actingAs($user)->post("/accounts/{$account->ulid}/images", ['media' => $media->ulid])->assertSessionMissing('success');
    $state === 'suspended' ? $response->assertRedirect() : $response->assertForbidden();

    expect($account->refresh()->images_count)->toBe(0)
        ->and($media->refresh()->attachable_id)->toBeNull();
})->with(['restricted', 'suspended', 'unverified email']);

it('sends image URLs only: no storage paths, user ids or owner details', function () {
    $media = ($this->upload)($this->owner);
    MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => VariantName::Card, 'path' => "public/account_image/{$media->ulid}/card.webp"]);
    app(AccountImageService::class)->add($this->owner, $this->account->ulid, $media->ulid);

    $html = $this->get("/accounts/{$this->account->ulid}")->assertOk()->getContent();

    expect($html)->not->toContain('owner@example.test')
        ->not->toContain('&quot;path&quot;')
        ->not->toContain('&quot;userId&quot;')
        ->not->toContain('original_filename')
        ->not->toContain('attachable');
});

it('counts an upload posted twice only once', function () {
    $media = ($this->upload)($this->owner);

    $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/images", ['media' => $media->ulid])->assertSessionHas('success');
    $this->actingAs($this->owner)->from("/accounts/{$this->account->ulid}")
        ->post("/accounts/{$this->account->ulid}/images", ['media' => $media->ulid])
        ->assertSessionHasErrors(['media' => 'This image is already on this account.']);

    expect($this->account->refresh()->images_count)->toBe(1);
});
