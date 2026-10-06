<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

// specs/04 §2–3 and specs/11 for the P2-25 staff writes: who may release a suspended tag or delete
// an evidence image, and that nothing reaches another dispute's media.

beforeEach(function () {
    Queue::fake([DeleteMediaObjectsJob::class]);
    $this->admin = User::factory()->admin()->create();
    $this->holder = User::factory()->create();
    $this->claimant = User::factory()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $disputes = app(DisputeService::class);
    $this->ulid = (string) $disputes->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'Mine.')->disputeUlid;
    $disputes->respond($this->holder, $this->ulid, 'No, mine.');
    $this->dispute = CocAccountDispute::query()->where('ulid', $this->ulid)->sole();
    $this->media = Media::factory()->collection(MediaCollection::Evidence)->ready()->create([
        'user_id' => $this->claimant->id, 'attachable_type' => $this->dispute->getMorphClass(), 'attachable_id' => $this->dispute->id,
    ]);
    $this->dispute->forceFill(['evidence' => [...$this->dispute->evidence, ['party' => 'claimant', 'note' => null, 'media' => [$this->media->ulid], 'at' => now()->toIso8601String()]]])->save();
    $disputes->decide($this->admin, $this->ulid, DisputeDecision::Suspend, 'Both look made up.');
});

it('keeps users and moderators out of both actions', function (string $role) {
    $user = $role === 'moderator' ? User::factory()->moderator()->create() : User::factory()->create();

    $this->actingAs($user)->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Release.'])->assertForbidden();
    $this->actingAs($user)->delete("/admin/disputes/{$this->ulid}/evidence/{$this->media->ulid}")->assertForbidden();

    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Suspended)
        ->and($this->media->refresh()->status)->toBe(MediaStatus::Ready);
})->with(['user', 'moderator']);

it('answers staff with a stake in the tag with a 404 on both', function () {
    $staked = User::factory()->admin()->create();
    CocAccount::factory()->for($staked)->forTag('#2PQ8GRJC')->create();

    $this->actingAs($staked)->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Release.'])->assertNotFound();
    $this->actingAs($staked)->delete("/admin/disputes/{$this->ulid}/evidence/{$this->media->ulid}")->assertNotFound();

    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Suspended);
});

it('refuses an admin deleting an admin party\'s evidence', function () {
    $this->claimant->forceFill(['role' => 'admin'])->save();

    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$this->media->ulid}")->assertForbidden();
    expect($this->media->refresh()->status)->toBe(MediaStatus::Ready);
});

it("answers another dispute's or an unattached image with a 404", function () {
    $loose = Media::factory()->collection(MediaCollection::Evidence)->ready()->create(['user_id' => $this->claimant->id]);
    $other = CocAccountDispute::factory()->create();
    $theirs = Media::factory()->collection(MediaCollection::Evidence)->ready()->create([
        'user_id' => $other->claimant_id, 'attachable_type' => $other->getMorphClass(), 'attachable_id' => $other->id,
    ]);

    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$loose->ulid}")->assertNotFound();
    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$theirs->ulid}")->assertNotFound();

    expect($loose->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($theirs->refresh()->status)->toBe(MediaStatus::Ready);
});

it('ignores extra fields posted with a release', function () {
    $this->actingAs($this->admin)->post("/admin/disputes/{$this->ulid}/release-tag", [
        'note' => 'Release.',
        'status' => 'verified',
        'user_id' => $this->admin->id,
    ])->assertRedirect();

    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Released)
        ->and($this->held->user_id)->toBeNull();
});

it('stops listing a deleted image on the review page', function () {
    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$this->media->ulid}")->assertRedirect();

    $html = $this->actingAs($this->admin)->get("/admin/disputes/{$this->ulid}")->assertOk()->getContent();
    expect($html)->not->toContain($this->media->ulid);
});
