<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Models\Media;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\Support\Auth\CapturesSecurityLog;

// P2-16: the parties' dispute pages are a trust boundary. Only the parties see a dispute (IDOR 404),
// neither sees the other (owner decision 2026-10-06), uploads must be the submitter's own evidence,
// giving the account up takes the password every time, and every write is rate limited
// (specs/04 §3–4, specs/11, specs/13 §5 guardrails).

uses(CapturesSecurityLog::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->captureSecurityLog();
    $this->holder = User::factory()->create(['username' => 'holderpat', 'email' => 'holder@example.test']);
    $this->claimant = User::factory()->create(['username' => 'claimantsam', 'email' => 'claimant@example.test']);
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->ulid = (string) app(DisputeService::class)->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'CLAIMANT-SECRET-REASON')->disputeUlid;
});

it("answers another user's dispute with a 404 on every route, like an unknown one (IDOR)", function () {
    $stranger = User::factory()->create();
    $unknown = '01J00000000000000000000000';

    foreach ([$this->ulid, $unknown] as $ulid) {
        $this->actingAs($stranger)->get("/disputes/{$ulid}")->assertNotFound();
        $this->actingAs($stranger)->post("/disputes/{$ulid}/respond", ['statement' => 'Mine.'])->assertNotFound();
        $this->actingAs($stranger)->post("/disputes/{$ulid}/withdraw")->assertNotFound();
        $this->actingAs($stranger)->post("/disputes/{$ulid}/release", ['current_password' => 'password'])->assertNotFound();
    }

    expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Open);
});

it('keeps guests out and blocks accounts that may not write', function () {
    $this->get("/disputes/{$this->ulid}")->assertRedirect('/login');
    $this->post('/disputes', ['tag' => '#2PQ8GRJC', 'reason' => 'Mine.'])->assertRedirect('/login');

    $this->actingAs(User::factory()->suspended()->create())->post('/disputes', ['tag' => '#2PQ8GRJC', 'reason' => 'Mine.'])->assertRedirect('/account/suspended');
    $this->actingAs(User::factory()->pendingDeletion()->create())->post('/disputes', ['tag' => '#2PQ8GRJC', 'reason' => 'Mine.'])->assertForbidden();
    expect(CocAccountDispute::query()->count())->toBe(1);

    // Restricted accounts keep account writes, disputes included (specs/04 §3).
    CocAccount::factory()->for(User::factory()->create())->forTag('#8LQ9JPY2')->verified()->create();
    $this->actingAs(User::factory()->restricted()->create())->post('/disputes', ['tag' => '#8LQ9JPY2', 'reason' => 'Mine.'])->assertSessionHasNoErrors();
    expect(CocAccountDispute::query()->count())->toBe(2);
});

it('lets only the claimant withdraw and only the holder give the account up', function () {
    $this->actingAs($this->holder)->post("/disputes/{$this->ulid}/withdraw")->assertForbidden();
    $this->actingAs($this->claimant)->post("/disputes/{$this->ulid}/release", ['current_password' => 'password'])->assertForbidden();

    expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Open)
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed);
});

it('does not tell a stranger that a holder is leaving or that a hidden account is under review', function () {
    $leaving = User::factory()->pendingDeletion()->create();
    CocAccount::factory()->for($leaving)->forTag('#8LQ9JPY2')->verified()->create();
    $asker = User::factory()->create();
    $generic = 'A dispute cannot be opened for this account now. If it is yours, verify it with your in-game API token.';

    foreach (['%238LQ9JPY2', '%232PQ8GRJC', '%23Q0LVCRRY'] as $tag) {
        $props = $this->actingAs($asker)->get("/disputes/create?tag={$tag}")->viewData('page')['props'];
        expect($props['refusal'])->toBe($generic)
            ->and(json_encode($props))->not->toContain('not_held')->not->toContain('already_disputed')->not->toContain('pending');
    }
});

it('never shows a party the other party, their statements or their evidence', function () {
    $holderMedia = Media::factory()->collection(MediaCollection::Evidence)->ready()->create(['user_id' => $this->holder->id]);
    app(DisputeService::class)->respond($this->holder, $this->ulid, 'HOLDER-SECRET-STATEMENT', [$holderMedia->ulid]);

    $asHolder = json_encode($this->actingAs($this->holder)->get("/disputes/{$this->ulid}")->viewData('page')['props']);
    $asClaimant = json_encode($this->actingAs($this->claimant)->get("/disputes/{$this->ulid}")->viewData('page')['props']['dispute']);

    expect($asHolder)->not->toContain('CLAIMANT-SECRET-REASON')->not->toContain('claimantsam')->not->toContain('claimant@example.test')
        ->and($asHolder)->toContain('HOLDER-SECRET-STATEMENT')
        ->and($asClaimant)->not->toContain('HOLDER-SECRET-STATEMENT')->not->toContain('holderpat')->not->toContain('holder@example.test')
        ->not->toContain($holderMedia->ulid);
});

it('takes only the submitter own evidence uploads', function () {
    CocAccount::factory()->for(User::factory()->create())->forTag('#8LQ9JPY2')->verified()->create();
    $foreign = Media::factory()->collection(MediaCollection::Evidence)->ready()->create(['user_id' => $this->holder->id]);
    $avatar = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $this->claimant->id]);

    // Someone else's upload reads like a lost one: a field error, no hint it exists.
    $this->actingAs($this->claimant)->from('/disputes/create?tag=%238LQ9JPY2')
        ->post('/disputes', ['tag' => '#8LQ9JPY2', 'reason' => 'Mine.', 'evidence' => [$foreign->ulid]])
        ->assertSessionHasErrors(['evidence' => 'One of the images did not upload properly. Remove it and upload it again.']);
    $this->actingAs($this->claimant)->from('/disputes/create?tag=%238LQ9JPY2')
        ->post('/disputes', ['tag' => '#8LQ9JPY2', 'reason' => 'Mine.', 'evidence' => [$avatar->ulid]])
        ->assertSessionHasErrors('evidence');

    expect(CocAccountDispute::query()->count())->toBe(1)
        ->and($foreign->refresh()->attachable_id)->toBeNull()
        ->and($avatar->refresh()->attachable_id)->toBeNull();
});

it('lets restricted accounts upload evidence (an account write), not suspended ones', function () {
    $payload = ['collection' => 'evidence', 'filename' => 'proof.png', 'size' => 1000, 'mime' => 'image/png'];

    $this->actingAs(User::factory()->restricted()->create())->postJson('/uploads/intent', $payload)->assertCreated();
    $this->actingAs(User::factory()->suspended()->create())->postJson('/uploads/intent', $payload)->assertForbidden();
});

it('asks for the password on every release and shares the password-confirm limiter', function () {
    config(['platform.auth.password_confirm_per_minute' => 2]);

    $this->actingAs($this->holder)->withSession(['auth.password_confirmed_at' => time()])->from("/disputes/{$this->ulid}")
        ->post("/disputes/{$this->ulid}/release", [])->assertSessionHasErrors('current_password');
    $this->actingAs($this->holder)->from("/disputes/{$this->ulid}")->post("/disputes/{$this->ulid}/release", ['current_password' => 'wrong']);
    $this->actingAs($this->holder)->from("/disputes/{$this->ulid}")->post("/disputes/{$this->ulid}/release", ['current_password' => 'password'])
        ->assertSessionHasErrors('current_password');

    expect(session('errors')->first('current_password'))->toStartWith('Too many attempts')
        ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed)
        ->and(collect($this->securityEvents())->contains(fn (array $event) => $event['message'] === 'auth.rate_limited' && $event['context']['limiter'] === 'password-confirm'))->toBeTrue();
});

it('limits dispute writes per account per hour (coc-dispute-write)', function () {
    config(['coc.disputes.write_per_hour' => 1]);

    $this->actingAs($this->holder)->from("/disputes/{$this->ulid}")->post("/disputes/{$this->ulid}/respond", ['statement' => ''])->assertSessionHasErrors('statement');
    $this->actingAs($this->holder)->from("/disputes/{$this->ulid}")->post("/disputes/{$this->ulid}/respond", ['statement' => 'Mine.'])
        ->assertRedirect("/disputes/{$this->ulid}")->assertSessionHasErrors('statement');
    expect(session('errors')->first('statement'))->toStartWith('You changed your disputes a lot this hour. Try again in');

    // Keyed per account: the claimant still has theirs.
    $this->actingAs($this->claimant)->from("/disputes/{$this->ulid}")->post("/disputes/{$this->ulid}/withdraw")->assertSessionHas('success');
    expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Withdrawn)
        ->and(collect($this->securityEvents())->contains(fn (array $event) => $event['message'] === 'auth.rate_limited' && $event['context']['limiter'] === 'coc-dispute-write'))->toBeTrue();
});
