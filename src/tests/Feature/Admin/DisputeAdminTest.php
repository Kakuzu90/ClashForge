<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Media\InteractsWithMedia;

// P2-17: the admin dispute queue, review page and decision (specs/13 §5 step 4, FR-ADMIN-2,
// FR-ADMIN-5).

uses(InteractsWithMedia::class);

beforeEach(function () {
    Date::setTestNow('2026-10-05 12:00:00');
    $this->fakeMediaStorage();
    $this->admin = User::factory()->admin()->create(['username' => 'warden']);
    $this->holder = User::factory()->create(['username' => 'holder']);
    $this->claimant = User::factory()->create(['username' => 'claimant']);
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create(['ign' => 'Fixture Chief']);
});

function disputeAgainst(User $holder, User $claimant, string $tag = '#2PQ8GRJC', bool $answered = true): string
{
    $disputes = app(DisputeService::class);
    $ulid = (string) $disputes->open($claimant, PlayerTag::from($tag), 'I lost the phone with this account.')->disputeUlid;
    if ($answered) {
        $disputes->respond($holder, $ulid, 'It is mine, I play it every day.');
    }

    return $ulid;
}

function loadQueue(User $viewer, array $query = []): TestResponse
{
    return test()->actingAs($viewer)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Disputes/Index',
        'X-Inertia-Partial-Data' => 'disputes',
    ])->get('/admin/disputes'.($query === [] ? '' : '?'.http_build_query($query)));
}

function evidenceImage(User $owner, CocAccountDispute $dispute): Media
{
    $media = Media::factory()->collection(MediaCollection::Evidence)->ready()->create([
        'user_id' => $owner->id, 'attachable_type' => $dispute->getMorphClass(), 'attachable_id' => $dispute->id,
    ]);
    foreach ([VariantName::Full, VariantName::Thumb] as $variant) {
        MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => $variant->value, 'path' => "private/evidence/{$media->ulid}/{$variant->value}.webp"]);
    }
    $dispute->forceFill(['evidence' => [...$dispute->evidence, ['party' => 'holder', 'note' => 'A screenshot of my settings.', 'media' => [$media->ulid], 'at' => now()->toIso8601String()]]])->save();

    return $media;
}

it('renders the queue shell and loads the rows deferred, oldest wait first', function () {
    $first = disputeAgainst($this->holder, $this->claimant);
    Date::setTestNow(now()->addHour());
    $other = User::factory()->create(['username' => 'other']);
    CocAccount::factory()->for($other)->forTag('#8LQ9JPY2')->verified()->create();
    $second = disputeAgainst($other, User::factory()->create(['username' => 'second']), '#8LQ9JPY2', answered: false);

    $this->actingAs($this->admin)->get('/admin/disputes')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Disputes/Index')
            ->where('meta.title', 'Disputes')
            ->where('view', 'active')
            ->where('mine', false)
            ->where('views.0', ['value' => 'active', 'label' => 'All running'])
            ->where('auth.can.resolveDisputes', true)
            ->missing('disputes'));

    $entries = loadQueue($this->admin)->assertOk()->json('props.disputes.entries');
    expect(array_column($entries, 'ulid'))->toBe([$first, $second])
        ->and($entries[0])->toMatchArray(['tag' => '#2PQ8GRJC', 'claimant' => 'claimant', 'holder' => 'holder', 'status' => 'awaiting_admin', 'blockedReason' => null])
        ->and($entries[0])->not->toHaveKeys(['reason', 'evidence', 'decisionNote']);
});

it('filters the queue by view and by assignment', function () {
    $awaiting = disputeAgainst($this->holder, $this->claimant);
    $other = User::factory()->create();
    CocAccount::factory()->for($other)->forTag('#8LQ9JPY2')->verified()->create();
    $open = disputeAgainst($other, User::factory()->create(), '#8LQ9JPY2', answered: false);
    CocAccountDispute::query()->where('ulid', $awaiting)->update(['assigned_admin_id' => $this->admin->id]);

    expect(array_column(loadQueue($this->admin, ['view' => 'awaiting_admin'])->json('props.disputes.entries'), 'ulid'))->toBe([$awaiting])
        ->and(array_column(loadQueue($this->admin, ['view' => 'waiting_on_holder'])->json('props.disputes.entries'), 'ulid'))->toBe([$open])
        ->and(array_column(loadQueue($this->admin, ['mine' => '1'])->json('props.disputes.entries'), 'ulid'))->toBe([$awaiting])
        ->and(loadQueue($this->admin, ['view' => 'closed'])->json('props.disputes.entries'))->toBe([]);
});

it('pages the queue with cursors', function () {
    config(['coc.disputes.queue_per_page' => 1]);
    disputeAgainst($this->holder, $this->claimant);
    $other = User::factory()->create();
    CocAccount::factory()->for($other)->forTag('#8LQ9JPY2')->verified()->create();
    disputeAgainst($other, User::factory()->create(), '#8LQ9JPY2');

    $first = loadQueue($this->admin)->json('props.disputes');
    $second = loadQueue($this->admin, ['cursor' => $first['nextCursor']])->json('props.disputes');

    expect($first['entries'])->toHaveCount(1)->and($second['entries'])->toHaveCount(1)
        ->and($second['entries'][0]['ulid'])->not->toBe($first['entries'][0]['ulid'])
        ->and($second['previousCursor'])->not->toBeNull();
});

it('shows everything a decision needs on the review page (specs/13 §5 step 4)', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);
    $dispute = CocAccountDispute::query()->where('ulid', $ulid)->sole();
    $image = evidenceImage($this->holder, $dispute);
    UserSanction::factory()->create(['user_id' => $this->claimant->id, 'starts_at' => now()->subYear(), 'expires_at' => now()->subYear()->addWeek()]);
    CocAccountClaim::factory()->create(['tag_normalized' => '2PQ8GRJC', 'coc_account_id' => $this->held->id, 'user_id' => $this->holder->id]);
    CocAccountSnapshot::factory()->create(['coc_account_id' => $this->held->id, 'captured_at' => now()->subDays(20), 'clan_tag' => '#AAA', 'th_level' => 15]);
    CocAccountSnapshot::factory()->create(['coc_account_id' => $this->held->id, 'captured_at' => now()->subDays(2), 'clan_tag' => '#BBB', 'th_level' => 15]);

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Disputes/Show')
            ->where('meta.title', 'Dispute #2PQ8GRJC')
            ->where('dispute.tag', '#2PQ8GRJC')
            ->where('dispute.status', 'awaiting_admin')
            ->where('dispute.accountName', 'Fixture Chief')
            ->where('dispute.accountStatus', 'disputed')
            ->where('dispute.claimant.username', 'claimant')
            ->where('dispute.holder.username', 'holder')
            ->where('dispute.evidence.0', fn ($entry) => $entry['opening'] === true && $entry['party'] === 'claimant' && $entry['note'] === 'I lost the phone with this account.')
            ->where('dispute.evidence.1.note', 'It is mine, I play it every day.')
            ->where('dispute.evidence.2.images.0.ulid', $image->ulid)
            ->where('dispute.evidence.2.images.0.url', fn (string $url) => str_starts_with($url, 'https://storage.test/private/evidence/'))
            ->where('dispute.claims.0.username', 'holder')
            ->where('dispute.snapshots.0.clanTag', '#BBB')
            ->where('dispute.snapshots.0.changes', ['clan'])
            ->where('dispute.snapshots.1.changes', [])
            ->where('dispute.blockedReason', null)
            ->where('dispute.decisions', fn ($options) => collect($options)->where('available', true)->pluck('value')->all() === ['transfer', 'deny', 'suspend', 'ask_claimant', 'ask_holder'])
            ->has('claimantSanctions', 1)
            ->has('holderSanctions', 0));
});

it('audits each view that shows evidence, before the URLs leave', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);
    $image = evidenceImage($this->holder, CocAccountDispute::query()->where('ulid', $ulid)->sole());

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")->assertOk();
    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")->assertOk();

    $views = AuditLog::query()->where('action', AuditAction::CocDisputeEvidenceViewed)->get();
    expect($views)->toHaveCount(2)
        ->and($views->first()->actor_id)->toBe($this->admin->id)
        ->and($views->first()->context['media'])->toBe([$image->ulid]);
});

it('writes no evidence audit when there is no image', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")->assertOk();

    expect(AuditLog::query()->where('action', AuditAction::CocDisputeEvidenceViewed)->exists())->toBeFalse();
});

it('explains which decisions wait, and keeps a banned holder from keeping the tag', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant, answered: false);
    $this->holder->forceFill(['status' => 'banned'])->save();

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('dispute.decisions.0', fn ($option) => $option['value'] === 'transfer' && $option['available'] === false && str_contains($option['unavailableReason'], 'holder has answered'))
            ->where('dispute.decisions.1', fn ($option) => $option['value'] === 'deny' && $option['available'] === false && str_contains($option['unavailableReason'], 'banned holder'))
            ->where('dispute.decisions.3.available', true));
});

it('records a decision through the service and says so', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);

    $this->actingAs($this->admin)->from("/admin/disputes/{$ulid}")
        ->post("/admin/disputes/{$ulid}/decision", ['decision' => 'deny', 'note' => 'No evidence beyond the statement.'])
        ->assertRedirect("/admin/disputes/{$ulid}")
        ->assertSessionHas('success', 'Claim denied. The holder keeps the account.');

    expect(CocAccountDispute::query()->where('ulid', $ulid)->sole()->status)->toBe(DisputeStatus::ResolvedDenied)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('shows the service refusal inline', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant, answered: false);

    $this->actingAs($this->admin)->from("/admin/disputes/{$ulid}")
        ->post("/admin/disputes/{$ulid}/decision", ['decision' => 'transfer', 'note' => 'Receipt matches.'])
        ->assertSessionHasErrors(['decision' => 'This dispute is not waiting on you yet.']);

    expect(CocAccountDispute::query()->where('ulid', $ulid)->sole()->status)->toBe(DisputeStatus::Open);
});

it('refuses a decision on a dispute a token closed meanwhile', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);
    CocAccountDispute::query()->where('ulid', $ulid)->update(['status' => DisputeStatus::AutoResolved, 'decided_at' => now()]);

    $this->actingAs($this->admin)->from("/admin/disputes/{$ulid}")
        ->post("/admin/disputes/{$ulid}/decision", ['decision' => 'deny', 'note' => 'Too late.'])
        ->assertSessionHasErrors(['decision' => 'This dispute is closed.']);

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('dispute.blockedReason', 'This dispute is closed.')->where('dispute.active', false));
});

it('needs a decision and a note', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);

    $this->actingAs($this->admin)->post("/admin/disputes/{$ulid}/decision", [])
        ->assertSessionHasErrors(['decision' => 'Choose a decision.', 'note' => 'Write an internal note: what you weighed and why.']);
    $this->actingAs($this->admin)->post("/admin/disputes/{$ulid}/decision", ['decision' => 'deny', 'note' => str_repeat('x', (int) config('coc.disputes.text_max') + 1)])
        ->assertSessionHasErrors('note');
});

it('counts pending disputes on the dashboard panel (FR-ADMIN-5)', function () {
    disputeAgainst($this->holder, $this->claimant);
    $other = User::factory()->create();
    CocAccount::factory()->for($other)->forTag('#8LQ9JPY2')->verified()->create();
    Date::setTestNow(now()->subDays((int) config('coc.disputes.holder_response_days') + 1));
    disputeAgainst($other, User::factory()->create(), '#8LQ9JPY2', answered: false);
    Date::setTestNow('2026-10-05 12:00:00');

    $panel = test()->actingAs($this->admin)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Dashboard',
        'X-Inertia-Partial-Data' => 'pendingDisputes',
    ])->get('/admin')->assertOk()->json('props.pendingDisputes');

    expect($panel)->toMatchArray(['awaitingAdmin' => 1, 'pastHolderWindow' => 1, 'running' => 2])
        ->and($panel['oldestWaitingSince'])->not->toBeNull();
});

it('keeps the queue, the review page and the panel within the query budget', function () {
    foreach (['#8LQ9JPYC', '#8LQ9JPYG', '#8LQ9JPYL', '#8LQ9JPYR', '#8LQ9JPYU'] as $tag) {
        $holder = User::factory()->create();
        CocAccount::factory()->for($holder)->forTag($tag)->verified()->create();
        disputeAgainst($holder, User::factory()->create(), $tag);
    }
    $ulid = disputeAgainst($this->holder, $this->claimant);
    CocAccountClaim::factory()->count(5)->create(['tag_normalized' => '2PQ8GRJC']);

    foreach ([fn () => loadQueue($this->admin), fn () => $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")] as $request) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request()->assertOk();
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
        DB::disableQueryLog();
    }
});

it('records every decision through the controller', function (string $decision, DisputeStatus $after) {
    $ulid = disputeAgainst($this->holder, $this->claimant);

    $this->actingAs($this->admin)->from("/admin/disputes/{$ulid}")
        ->post("/admin/disputes/{$ulid}/decision", ['decision' => $decision, 'note' => 'Weighed both sides.'])
        ->assertRedirect("/admin/disputes/{$ulid}")
        ->assertSessionHasNoErrors();

    expect(CocAccountDispute::query()->where('ulid', $ulid)->sole()->status)->toBe($after);
})->with([
    'transfer' => ['transfer', DisputeStatus::ResolvedTransfer],
    'deny' => ['deny', DisputeStatus::ResolvedDenied],
    'suspend' => ['suspend', DisputeStatus::ResolvedSuspended],
    'ask the claimant' => ['ask_claimant', DisputeStatus::AwaitingClaimant],
    'ask the holder' => ['ask_holder', DisputeStatus::AwaitingHolder],
]);

it('shows each refusal of the service inline', function (string $decision, Closure $setup, string $message) {
    $ulid = disputeAgainst($this->holder, $this->claimant);
    $setup($this);

    $this->actingAs($this->admin)->from("/admin/disputes/{$ulid}")
        ->post("/admin/disputes/{$ulid}/decision", ['decision' => $decision, 'note' => 'Weighed both sides.'])
        ->assertSessionHasErrors(['decision' => $message]);

    expect(CocAccountDispute::query()->where('ulid', $ulid)->sole()->status)->toBe(DisputeStatus::AwaitingAdmin);
})->with([
    'a banned holder cannot keep it' => ['deny', fn ($test) => $test->holder->forceFill(['status' => 'banned'])->save(), 'A banned holder cannot keep the account.'],
    'the claimant cannot receive it' => ['transfer', fn ($test) => $test->claimant->forceFill(['status' => 'suspended', 'status_expires_at' => now()->addDay()])->save(), 'The claimant cannot receive the account: banned, suspended or leaving.'],
]);

it('leaves the admin\'s own disputes out of the panel and unlinked on the review page', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);
    CocAccount::factory()->for($this->admin)->forTag('#8LQ9JPYC')->verified()->create();
    $own = disputeAgainst($this->admin, $this->claimant, '#8LQ9JPYC');

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('dispute.claimant.priorDisputes.0.ulid', $own)
            ->where('dispute.claimant.priorDisputes.0.reviewable', false));

    $panel = test()->actingAs($this->admin)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Dashboard',
        'X-Inertia-Partial-Data' => 'pendingDisputes',
    ])->get('/admin')->json('props.pendingDisputes');
    expect($panel['awaitingAdmin'])->toBe(1)->and($panel['running'])->toBe(1);
});

it('shows the dispute\'s own audit trail on the review page', function () {
    $ulid = disputeAgainst($this->holder, $this->claimant);

    $this->actingAs($this->admin)->get("/admin/disputes/{$ulid}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('auditTrail', fn ($entries) => collect($entries)->pluck('actionLabel')->contains('Ownership dispute opened'))
            ->where('moreAuditEntries', false));
});

it('reads its limits from config', function () {
    expect(config('coc.disputes.queue_per_page'))->toBe(25)
        ->and(config('coc.disputes.review_history_limit'))->toBe(20);
});
