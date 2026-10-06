<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\PlayerAccounts\Enums\AttachOutcome;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Media\InteractsWithMedia;

// P2-25: releasing a tag a dispute suspended (specs/13 §2) and deleting an evidence image that shows
// an identity document (specs/13 §9).

uses(InteractsWithMedia::class);

beforeEach(function () {
    Date::setTestNow('2026-10-07 12:00:00');
    $this->fakeMediaStorage();
    Queue::fake([DeleteMediaObjectsJob::class]);
    $this->admin = User::factory()->admin()->create();
    $this->holder = User::factory()->create();
    $this->claimant = User::factory()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->disputes = app(DisputeService::class);
    $this->ulid = (string) $this->disputes->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'I lost the phone with this account.')->disputeUlid;
    $this->disputes->respond($this->holder, $this->ulid, 'It is mine.');
});

function suspendTheTag(): void
{
    test()->disputes->decide(test()->admin, test()->ulid, DisputeDecision::Suspend, 'Both stories look made up.');
}

function sentEvidence(User $owner, string $disputeUlid, string $party): Media
{
    $dispute = CocAccountDispute::query()->where('ulid', $disputeUlid)->sole();
    $media = Media::factory()->collection(MediaCollection::Evidence)->ready()->create([
        'user_id' => $owner->id, 'attachable_type' => $dispute->getMorphClass(), 'attachable_id' => $dispute->id,
    ]);
    foreach ([VariantName::Full, VariantName::Thumb] as $variant) {
        MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => $variant->value, 'path' => "private/evidence/{$media->ulid}/{$variant->value}.webp"]);
    }
    $dispute->forceFill(['evidence' => [...$dispute->evidence, ['party' => $party, 'note' => 'See this.', 'media' => [$media->ulid], 'at' => now()->toIso8601String()]]])->save();

    return $media;
}

it('releases a suspended tag: unowned, claimable, audited, logged and the holder told', function () {
    suspendTheTag();

    $this->actingAs($this->admin)->get("/admin/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page
        ->where('dispute.canReleaseTag', true)->where('dispute.releaseBlockedReason', null));

    $this->actingAs($this->admin)->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Fraud review closed, no owner proven.'])
        ->assertRedirect()->assertSessionHas('success', 'The tag is released. Anyone can claim it now.');

    $row = $this->held->refresh();
    expect($row->status)->toBe(CocAccountStatus::Released)
        ->and($row->user_id)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::CocAccountReleased)->sole()->context)->toMatchArray(['reason' => 'admin'])
        ->and(ModerationAction::query()->where('action', ModerationActionType::ReleaseTag)->sole()->note)->toBe('Fraud review closed, no owner proven.')
        ->and(Notification::query()->where('notifiable_id', $this->holder->id)->where('type', NotificationType::CocAccountReleased->value)->exists())->toBeTrue();

    expect(app(AttachAccountService::class)->attach($this->claimant, PlayerTag::from('#2PQ8GRJC'))->outcome)->not->toBe(AttachOutcome::TagSuspended);
});

it('refuses a release once the tag is no longer suspended, and on a dispute that did not suspend it', function () {
    suspendTheTag();
    $this->actingAs($this->admin)->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'First.']);

    $this->actingAs($this->admin)->from("/admin/disputes/{$this->ulid}")
        ->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Again.'])
        ->assertSessionHasErrors(['note' => 'This tag is no longer suspended.']);

    $other = CocAccount::factory()->for(User::factory()->create())->forTag('#GRJ0P8UV')->verified()->create();
    $running = (string) $this->disputes->open($this->claimant, PlayerTag::from('#GRJ0P8UV'), 'Mine too.')->disputeUlid;
    $this->actingAs($this->admin)->post("/admin/disputes/{$running}/release-tag", ['note' => 'No.'])->assertForbidden();
    expect($other->refresh()->status)->toBe(CocAccountStatus::Disputed);
});

it('needs a note to release', function () {
    suspendTheTag();

    $this->actingAs($this->admin)->from("/admin/disputes/{$this->ulid}")
        ->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => '  '])
        ->assertSessionHasErrors('note');

    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Suspended);
});

it('leaves an admin holder\'s tag to a super admin', function () {
    $this->holder->forceFill(['role' => 'admin'])->save();
    suspendTheTagAsSuperAdmin();

    $this->actingAs($this->admin)->get("/admin/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page
        ->where('dispute.canReleaseTag', false)
        ->where('dispute.releaseBlockedReason', 'The holder is an admin, so only a super admin can release this tag.'));
    $this->actingAs($this->admin)->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Release.'])->assertForbidden();

    $this->actingAs(User::factory()->superAdmin()->create())->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Release.'])->assertRedirect();
    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Released);
});

function suspendTheTagAsSuperAdmin(): void
{
    test()->disputes->decide(User::factory()->superAdmin()->create(), test()->ulid, DisputeDecision::Suspend, 'Both stories look made up.');
}

it('deletes an evidence image: media deleting, entry marked, audited, logged and the sender told', function () {
    $media = sentEvidence($this->holder, $this->ulid, 'holder');

    $this->actingAs($this->admin)->get("/admin/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page
        // The holder's text-only answer has nothing to delete; the image entry does.
        ->where('dispute.evidence.1.removable', false)->where('dispute.evidence.2.removable', true));

    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$media->ulid}")
        ->assertRedirect()->assertSessionHas('success', 'Image deleted. The sender was told why.');

    $dispute = CocAccountDispute::query()->where('ulid', $this->ulid)->sole();
    $entry = collect($dispute->evidence)->last();
    expect($media->refresh()->status)->toBe(MediaStatus::Deleting)
        ->and($entry['media'])->toBe([])
        ->and($entry['removed'])->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::CocDisputeEvidenceRemoved)->sole()->context)->toMatchArray(['media' => $media->ulid, 'party' => 'holder'])
        ->and(ModerationAction::query()->where('action', ModerationActionType::Remove)->sole()->target_user_id)->toBe($this->holder->id);
    Queue::assertPushed(DeleteMediaObjectsJob::class);

    $notice = Notification::query()->where('notifiable_id', $this->holder->id)->where('type', NotificationType::CocDisputeEvidenceRemoved->value)->sole();
    expect(NotificationType::CocDisputeEvidenceRemoved->render($notice->data['params'])->body)->toContain('showed an identity document')
        ->and(NotificationType::CocDisputeEvidenceRemoved->render($notice->data['params'])->url)->toBe("/disputes/{$this->ulid}");

    $this->actingAs($this->holder)->get("/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page
        ->where('dispute.submissions.1.removed', 1)->where('dispute.submissions.1.images', []));
});

it('gives the party the evidence slot back', function () {
    $max = (int) config('coc.disputes.evidence_max');
    $sent = [];
    foreach (range(1, $max) as $i) {
        $sent[] = sentEvidence($this->claimant, $this->ulid, 'claimant');
    }
    $this->actingAs($this->claimant)->get("/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page->where('dispute.evidenceLeft', 0));

    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$sent[0]->ulid}");

    $this->actingAs($this->claimant)->get("/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page->where('dispute.evidenceLeft', 1));
});

it('deletes evidence on a closed dispute too, and only once', function () {
    $media = sentEvidence($this->holder, $this->ulid, 'holder');
    suspendTheTag();

    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$media->ulid}")->assertRedirect();
    $this->actingAs($this->admin)->delete("/admin/disputes/{$this->ulid}/evidence/{$media->ulid}")->assertNotFound();

    expect(ModerationAction::query()->where('action', ModerationActionType::Remove)->count())->toBe(1);
});

it('offers the release only on the dispute that suspended the tag as it stands', function () {
    suspendTheTag();
    $this->actingAs($this->admin)->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'Released after review.']);

    // The tag is verified again and a second dispute suspends it.
    $newHolder = User::factory()->create();
    $this->held->refresh()->forceFill(['user_id' => $newHolder->id, 'status' => CocAccountStatus::Verified, 'verified_at' => now()])->save();
    $second = (string) $this->disputes->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'Mine again.')->disputeUlid;
    $this->disputes->respond($newHolder, $second, 'No, mine.');
    $this->disputes->decide($this->admin, $second, DisputeDecision::Suspend, 'Still unclear.');

    $this->actingAs($this->admin)->get("/admin/disputes/{$this->ulid}")->assertInertia(fn (Assert $page) => $page
        ->where('dispute.canReleaseTag', false)->where('dispute.releaseBlockedReason', null));
    $this->actingAs($this->admin)->from("/admin/disputes/{$this->ulid}")
        ->post("/admin/disputes/{$this->ulid}/release-tag", ['note' => 'From the old one.'])
        ->assertSessionHasErrors(['note' => 'This tag is no longer suspended.']);

    $this->actingAs($this->admin)->get("/admin/disputes/{$second}")->assertInertia(fn (Assert $page) => $page->where('dispute.canReleaseTag', true));
    expect($this->held->refresh()->status)->toBe(CocAccountStatus::Suspended);
});
