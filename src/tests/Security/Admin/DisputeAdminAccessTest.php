<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia as Assert;

// P2-17: who reaches the dispute queue and review (specs/04 §2–3), the party rule and the rank
// rule (owner decisions 2026-10-05), and what never leaves in the queue.

beforeEach(function () {
    $this->holder = User::factory()->create();
    $this->claimant = User::factory()->create();
    CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $disputes = app(DisputeService::class);
    $this->ulid = (string) $disputes->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'I lost the phone with this account.')->disputeUlid;
    $disputes->respond($this->holder, $this->ulid, 'It is mine.');
});

it('stops moderators and users at the /admin gate', function (string $state) {
    $viewer = User::factory()->{$state}()->create();

    $this->actingAs($viewer)->get('/admin/disputes')->assertForbidden();
    $this->actingAs($viewer)->get("/admin/disputes/{$this->ulid}")->assertForbidden();
    $this->actingAs($viewer)->post("/admin/disputes/{$this->ulid}/decision", ['decision' => 'deny', 'note' => 'x'])->assertForbidden();

    expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::AwaitingAdmin);
})->with(['moderator', 'restricted']);

it('answers an admin without resolve-disputes with a 403 queue and a 404 dispute (specs/04 §3)', function () {
    $admin = User::factory()->admin()->restricted()->create();

    $this->actingAs($admin)->get('/admin/disputes')->assertForbidden();
    $this->actingAs($admin)->get("/admin/disputes/{$this->ulid}")->assertNotFound();
    $this->actingAs($admin)->post("/admin/disputes/{$this->ulid}/decision", ['decision' => 'deny', 'note' => 'x'])->assertNotFound();
});

it('refuses queue cursors this view cannot read, instead of failing', function (Closure $cursor) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from('/admin/disputes')->get('/admin/disputes?cursor='.urlencode($cursor()))
        ->assertRedirect('/admin/disputes')
        ->assertSessionHasErrors(['cursor' => 'That page link is not valid. Start from the first page.']);
})->with([
    'not a cursor' => [fn () => 'not-a-cursor'],
    'no direction' => [fn () => rtrim(strtr(base64_encode('{}'), '+/', '-_'), '=')],
    'from the closed view' => [fn () => rtrim(strtr(base64_encode(json_encode(['decided_at' => '2026-10-01 00:00:00', 'id' => 1, '_pointsToNextItems' => true])), '+/', '-_'), '=')],
    'a bad timestamp' => [fn () => rtrim(strtr(base64_encode(json_encode(['awaiting_since' => 'x', 'id' => 1, '_pointsToNextItems' => true])), '+/', '-_'), '=')],
]);

it('keeps guests out', function () {
    $this->get('/admin/disputes')->assertRedirect('/login');
    $this->get("/admin/disputes/{$this->ulid}")->assertRedirect('/login');
});

it('hides a dispute from an admin who is part of it (owner decision 2026-10-05)', function () {
    $this->holder->forceFill(['role' => 'admin'])->save();

    $this->actingAs($this->holder)->get("/admin/disputes/{$this->ulid}")->assertNotFound();
    $entries = $this->actingAs($this->holder)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Disputes/Index',
        'X-Inertia-Partial-Data' => 'disputes',
    ])->get('/admin/disputes')->json('props.disputes.entries');

    expect($entries)->toBe([]);
});

it('lets an admin review but not decide when a party is an admin (specs/04 §2 rule 1)', function () {
    $this->claimant->forceFill(['role' => 'admin'])->save();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get("/admin/disputes/{$this->ulid}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('dispute.blockedReason', 'An admin is a party to this dispute, so only a super admin can decide it.'));
    $this->actingAs($admin)->post("/admin/disputes/{$this->ulid}/decision", ['decision' => 'deny', 'note' => 'Not enough.'])->assertForbidden();

    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    $this->actingAs($superAdmin)->get("/admin/disputes/{$this->ulid}")
        ->assertInertia(fn (Assert $page) => $page->where('dispute.blockedReason', null));
    $this->actingAs($superAdmin)->post("/admin/disputes/{$this->ulid}/decision", ['decision' => 'deny', 'note' => 'Not enough.'])->assertRedirect();

    expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::ResolvedDenied);
});

it('lets nobody decide in the app when a super admin is a party', function () {
    $this->holder->forceFill(['role' => 'super_admin'])->save();
    $superAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($superAdmin)->get("/admin/disputes/{$this->ulid}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('dispute.blockedReason', 'A super admin is part of this dispute, so it cannot be decided here.'));
    $this->actingAs($superAdmin)->post("/admin/disputes/{$this->ulid}/decision", ['decision' => 'deny', 'note' => 'x'])->assertForbidden();
});

it('treats an admin with a stake in the tag as a party (owner decision 2026-10-06)', function (string $stake) {
    $admin = User::factory()->admin()->create();
    match ($stake) {
        'row' => CocAccount::factory()->for($admin)->forTag('#2PQ8GRJC')->create(),
        'claim' => CocAccountClaim::factory()->create(['user_id' => $admin->id, 'tag_normalized' => '2PQ8GRJC']),
        'dispute' => CocAccountDispute::factory()->create([
            'claimant_id' => $admin->id, 'tag_normalized' => '2PQ8GRJC', 'status' => DisputeStatus::ResolvedDenied,
            'coc_account_id' => CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->value('id'),
        ]),
    };
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Admin/Disputes/Index',
        'X-Inertia-Partial-Data' => 'disputes',
    ];

    $this->actingAs($admin)->get("/admin/disputes/{$this->ulid}")->assertNotFound();
    expect($this->actingAs($admin)->withHeaders($headers)->get('/admin/disputes')->json('props.disputes.entries'))->toBe([]);
    expect(fn () => app(DisputeService::class)->decide($admin, $this->ulid, DisputeDecision::Deny, 'x'))
        ->toThrow(AuthorizationException::class);
})->with([
    'an unverified row on the tag' => ['row'],
    'an earlier claim attempt' => ['claim'],
    'an earlier dispute over the tag' => ['dispute'],
]);

it('answers an unknown dispute like a hidden one', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/disputes/01J0000000000000000000NONE')->assertNotFound();
});

it('keeps statements, notes and evidence out of the queue and the panel', function () {
    $admin = User::factory()->admin()->create();
    CocAccountDispute::query()->update(['decision_note' => 'internal-secret-note']);
    $headers = fn (string $component, string $prop) => [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $prop,
    ];

    $queue = $this->actingAs($admin)->withHeaders($headers('Admin/Disputes/Index', 'disputes'))->get('/admin/disputes')->getContent();
    $panel = $this->actingAs($admin)->withHeaders($headers('Admin/Dashboard', 'pendingDisputes'))->get('/admin')->getContent();

    foreach ([$queue, $panel] as $body) {
        expect($body)->not->toContain('internal-secret-note')
            ->not->toContain('I lost the phone')
            ->not->toContain('It is mine.');
    }
});
