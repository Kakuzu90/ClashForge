<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Services\AccountImageService;
use App\Domain\PlayerAccounts\Services\AccountOwnershipService;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Coc\InteractsWithCoc;

// FR-COC-11, specs/10 §3 "Attachment" and §8 (P2-23): the owner's custom images on an account.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    Queue::fake([DeleteMediaObjectsJob::class]);
    $this->owner = User::factory()->create();
    $this->account = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->verified()->featured()->create(['ign' => 'Fixture Chief']);
});

function accountImageUpload(User $user, MediaCollection $collection = MediaCollection::AccountImage, MediaStatus $status = MediaStatus::Ready): Media
{
    $media = Media::factory()->collection($collection)->ready()->create(['user_id' => $user->id, 'status' => $status]);
    foreach ([VariantName::Card->value => 800, VariantName::Full->value => 1600] as $name => $width) {
        MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => $name, 'path' => "public/account_image/{$media->ulid}/{$name}.webp", 'width' => $width, 'height' => (int) ($width * 0.75)]);
    }

    return $media;
}

function addAccountImages(User $user, CocAccount $account, int $count): array
{
    $ulids = [];
    foreach (range(1, $count) as $i) {
        $media = accountImageUpload($user);
        app(AccountImageService::class)->add($user, $account->ulid, $media->ulid);
        $ulids[] = $media->ulid;
    }

    return $ulids;
}

it('adds an image, counts it and shows it on the account page', function () {
    $media = accountImageUpload($this->owner);

    $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/images", ['media' => $media->ulid])
        ->assertRedirect()->assertSessionHas('success', 'Image added.');

    expect($this->account->refresh()->images_count)->toBe(1)
        ->and($media->refresh()->attachable_id)->toBe($this->account->id)
        ->and($media->expires_at)->toBeNull();

    $this->get("/accounts/{$this->account->ulid}")->assertInertia(fn (Assert $page) => $page
        ->component('Accounts/Show')
        ->has('account.images', 1)
        ->where('account.images.0.ulid', $media->ulid)
        ->where('account.images.0.card.width', 800)
        ->where('account.images.0.full.width', 1600)
        ->where('account.canManageImages', true)
        ->where('account.imagesMax', config('coc.images.max'))
        ->where('imageUpload.value', 'account_image'));
});

it('attaches an upload still waiting in the processing queue', function () {
    // The gallery attaches as soon as storage accepted the file, often before a worker took the job.
    $queued = accountImageUpload($this->owner, status: MediaStatus::Uploaded);

    $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/images", ['media' => $queued->ulid])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Image added.');

    expect($queued->refresh()->attachable_id)->toBe($this->account->id)
        ->and($this->account->refresh()->images_count)->toBe(1);
    $this->get("/accounts/{$this->account->ulid}")->assertInertia(fn (Assert $page) => $page->where('account.images.0.processing', true));
});

it('refuses an image past the limit', function () {
    addAccountImages($this->owner, $this->account, (int) config('coc.images.max'));
    $extra = accountImageUpload($this->owner);

    expect(fn () => app(AccountImageService::class)->add($this->owner, $this->account->ulid, $extra->ulid))->toThrow(ValidationException::class)
        ->and($this->account->refresh()->images_count)->toBe((int) config('coc.images.max'))
        ->and($extra->refresh()->attachable_id)->toBeNull();
});

it('holds the limit in the database too, should two adds ever race past the row lock', function () {
    expect(fn () => CocAccount::query()->whereKey($this->account->id)->update(['images_count' => (int) config('coc.images.max') + 1]))
        ->toThrow(QueryException::class);
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'The CHECK exists on PostgreSQL only.');

it('reads the count under the row lock, so a racing add sees the other one', function () {
    addAccountImages($this->owner, $this->account, (int) config('coc.images.max') - 1);
    [$first, $second] = [accountImageUpload($this->owner), accountImageUpload($this->owner)];

    // The second add starts after the first committed, as the lock makes it wait.
    app(AccountImageService::class)->add($this->owner, $this->account->ulid, $first->ulid);
    expect(fn () => app(AccountImageService::class)->add($this->owner, $this->account->ulid, $second->ulid))->toThrow(ValidationException::class)
        ->and($this->account->refresh()->images_count)->toBe((int) config('coc.images.max'));
});

it('refuses an upload made for another collection or already attached elsewhere', function () {
    $avatar = accountImageUpload($this->owner, MediaCollection::Avatar);
    expect(fn () => app(AccountImageService::class)->add($this->owner, $this->account->ulid, $avatar->ulid))->toThrow(ValidationException::class);

    $other = CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->create();
    [$used] = addAccountImages($this->owner, $other, 1);
    expect(fn () => app(AccountImageService::class)->add($this->owner, $this->account->ulid, $used))->toThrow(ValidationException::class)
        ->and($this->account->refresh()->images_count)->toBe(0);
});

it('removes an image: unlinked, deleted after commit, uncounted', function () {
    [$ulid] = addAccountImages($this->owner, $this->account, 1);

    $this->actingAs($this->owner)->delete("/accounts/{$this->account->ulid}/images/{$ulid}")
        ->assertRedirect()->assertSessionHas('success', 'Image removed.');

    $media = Media::query()->where('ulid', $ulid)->sole();
    expect($media->status)->toBe(MediaStatus::Deleting)
        ->and($media->attachable_id)->toBeNull()
        ->and($this->account->refresh()->images_count)->toBe(0);
    Queue::assertPushed(DeleteMediaObjectsJob::class);
});

it('unlinks a quarantined image without deleting it, so it stays for review', function () {
    [$ulid] = addAccountImages($this->owner, $this->account, 1);
    Media::query()->where('ulid', $ulid)->update(['status' => MediaStatus::Quarantined]);

    app(AccountImageService::class)->remove($this->owner, $this->account->ulid, $ulid);

    $media = Media::query()->where('ulid', $ulid)->sole();
    expect($media->status)->toBe(MediaStatus::Quarantined)
        ->and($media->attachable_id)->toBeNull()
        ->and($this->account->refresh()->images_count)->toBe(0);
});

it('shows others only ready images, and the owner their processing and failed ones too', function () {
    addAccountImages($this->owner, $this->account, 1);
    $processing = accountImageUpload($this->owner, status: MediaStatus::Processing);
    app(AccountImageService::class)->add($this->owner, $this->account->ulid, $processing->ulid);
    [$failed] = addAccountImages($this->owner, $this->account, 1);
    Media::query()->where('ulid', $failed)->update(['status' => MediaStatus::Failed]);

    $this->actingAs($this->owner)->get("/accounts/{$this->account->ulid}")->assertInertia(fn (Assert $page) => $page
        ->has('account.images', 3)
        ->where('account.images.1.processing', true)
        ->where('account.images.1.card', null)
        ->where('account.images.2.failed', true));

    $this->actingAs(User::factory()->create())->get("/accounts/{$this->account->ulid}")->assertInertia(fn (Assert $page) => $page
        ->has('account.images', 1)
        ->where('account.canManageImages', false)
        ->where('imageUpload', null));
});

it('lets the owner manage images on verified and disputed rows only', function (CocAccountStatus $status, bool $allowed) {
    $this->account->forceFill(['status' => $status])->save();
    $media = accountImageUpload($this->owner);

    $response = $this->actingAs($this->owner)->post("/accounts/{$this->account->ulid}/images", ['media' => $media->ulid]);

    $allowed ? $response->assertRedirect() : $response->assertForbidden();
    expect($this->account->refresh()->images_count)->toBe($allowed ? 1 : 0);
})->with([
    'verified' => [CocAccountStatus::Verified, true],
    'disputed' => [CocAccountStatus::Disputed, true],
    'unverified' => [CocAccountStatus::Unverified, false],
    'suspended' => [CocAccountStatus::Suspended, false],
]);

it('deletes the images when the owner detaches the account', function () {
    addAccountImages($this->owner, $this->account, 2);

    app(AccountOwnershipService::class)->detach($this->owner, $this->account->ulid, 'password', null);

    expect($this->account->refresh()->images_count)->toBe(0)
        ->and(Media::query()->where('attachable_id', $this->account->id)->count())->toBe(0)
        ->and(Media::query()->where('status', MediaStatus::Deleting)->count())->toBe(2);
});

it('deletes the holder\'s images when another user takes the tag with a token', function () {
    addAccountImages($this->owner, $this->account, 2);
    $verifier = User::factory()->create();
    $this->fakeCoc()->acceptToken(PlayerTag::from('#2PQ8GRJC'), 'in-game-token');

    app(VerifyOwnershipService::class)->verifyTag($verifier, PlayerTag::from('#2PQ8GRJC'), 'in-game-token');

    expect($this->account->refresh()->images_count)->toBe(0)
        ->and(Media::query()->where('status', MediaStatus::Deleting)->count())->toBe(2);
});

it('deletes the holder\'s images before a dispute hands the row to the claimant', function (string $how) {
    addAccountImages($this->owner, $this->account, 2);
    $claimant = User::factory()->create();
    $disputes = app(DisputeService::class);
    $ulid = $disputes->open($claimant, PlayerTag::from('#2PQ8GRJC'), 'I lost the phone with this account.', [])->disputeUlid;

    if ($how === 'release') {
        $disputes->release($this->owner, $ulid, 'password');
    } else {
        $disputes->respond($this->owner, $ulid, 'It is mine.');
        $disputes->decide(User::factory()->admin()->create(), $ulid, DisputeDecision::Transfer, 'Receipt matches.');
    }

    expect($this->account->refresh()->images_count)->toBe(0)
        ->and(Media::query()->where('collection', MediaCollection::AccountImage)->whereNotNull('attachable_id')->count())->toBe(0);
})->with(['release', 'admin transfer']);

it('keeps the page within the query budget with five images', function () {
    addAccountImages($this->owner, $this->account, 5);
    $this->get("/accounts/{$this->account->ulid}");

    DB::enableQueryLog();
    $this->get("/accounts/{$this->account->ulid}")->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('reads the limits from config', function () {
    expect(config('coc.images.max'))->toBe(5)
        ->and(config('coc.images.writes_per_hour'))->toBeInt();
});
