<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Http\Controllers\Accounts\AttachController;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Media\InteractsWithMedia;

// P2-16: the parties' dispute screens (specs/13 §4 B, §5, §8; FR-COC-6, FR-COC-7, FR-COC-8).

uses(InteractsWithMedia::class);

beforeEach(function () {
    Date::setTestNow('2026-10-06 12:00:00');
    $this->fakeMediaStorage();
    $this->holder = User::factory()->create(['username' => 'holderpat']);
    $this->claimant = User::factory()->create(['username' => 'claimantsam']);
    $this->admin = User::factory()->admin()->create();
    $this->held = CocAccount::factory()->for($this->holder)->forTag('#2PQ8GRJC')->verified()->create();
    $this->disputes = fn () => app(DisputeService::class);
    $this->open = fn (?User $as = null, string $tag = '#2PQ8GRJC') => (string) ($this->disputes)()->open($as ?? $this->claimant, PlayerTag::from($tag), 'I lost the phone with this account.')->disputeUlid;
});

/**
 * @return list<string>
 */
function screenEvidence(User $user, int $count = 1, MediaCollection $collection = MediaCollection::Evidence): array
{
    return Media::factory()->count($count)->collection($collection)->ready()->create(['user_id' => $user->id])->pluck('ulid')->all();
}

function withThumb(string $ulid): void
{
    $media = Media::query()->where('ulid', $ulid)->sole();
    MediaVariant::factory()->create(['media_id' => $media->id, 'variant' => VariantName::Thumb->value, 'path' => "private/evidence/{$ulid}/thumb.webp"]);
}

describe('the conflict card (specs/13 §4 B)', function () {
    $conflict = ['outcome' => 'verified_elsewhere', 'tag' => '#2PQ8GRJC', 'player' => null, 'accountUlid' => null, 'holderUsername' => null, 'retryAfter' => null];

    it('offers the dispute path, then links the running dispute instead', function () use ($conflict) {
        $this->actingAs($this->claimant)->withSession([AttachController::PREVIEW => $conflict])->get('/accounts/attach?tag=%232PQ8GRJC')
            ->assertInertia(fn (Assert $page) => $page->component('Accounts/Attach')->where('disputeUlid', null));

        $ulid = ($this->open)();

        $this->actingAs($this->claimant)->withSession([AttachController::PREVIEW => $conflict])->get('/accounts/attach?tag=%232PQ8GRJC')
            ->assertInertia(fn (Assert $page) => $page->where('disputeUlid', $ulid));
    });
});

describe('opening a dispute', function () {
    it('renders the form with the evidence limits and the attach lookup of the same tag', function () {
        $preview = ['outcome' => 'verified_elsewhere', 'tag' => '#2PQ8GRJC', 'player' => [
            'tag' => '#2PQ8GRJC', 'name' => 'Fixture Chief', 'townHallLevel' => 16, 'trophies' => 4800, 'expLevel' => 200,
            'clanName' => null, 'leagueName' => null, 'stale' => false, 'fetchedAt' => null,
        ], 'accountUlid' => null, 'holderUsername' => null, 'retryAfter' => null];

        $this->actingAs($this->claimant)->withSession([AttachController::PREVIEW => $preview])->get('/disputes/create?tag=%232PQ8GRJC')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Disputes/Create')
                ->where('tag', '#2PQ8GRJC')
                ->where('player.name', 'Fixture Chief')
                ->where('refusal', null)
                ->where('evidenceUpload.value', 'evidence')
                ->where('evidenceMax', config('coc.disputes.evidence_max'))
                ->where('textMax', config('coc.disputes.text_max'))
                ->where('responseDays', config('coc.disputes.holder_response_days')));
    });

    it('says up front why a dispute cannot be opened, in words only', function (Closure $setup, string $text) {
        $tag = $setup->call($this);

        $this->actingAs($this->claimant)->get('/disputes/create?tag='.urlencode($tag))
            ->assertInertia(fn (Assert $page) => $page->where('refusal', $text)->missing('refusalLabel'));
    })->with([
        'not held' => [fn () => '#8LQ9JPY2', 'A dispute cannot be opened for this account now. If it is yours, verify it with your in-game API token.'],
        'own account' => [function () {
            CocAccount::factory()->for($this->claimant)->forTag('#8LQ9JPY2')->verified()->create();

            return '#8LQ9JPY2';
        }, DisputeRefusal::OwnAccount->label().'.'],
        // Under review reads like an unheld tag: not the asker's to know (P2-16 security review).
        'disputed by someone else' => [function () {
            ($this->open)(User::factory()->create());

            return '#2PQ8GRJC';
        }, 'A dispute cannot be opened for this account now. If it is yours, verify it with your in-game API token.'],
        'too many running' => [function () {
            config(['coc.disputes.max_open_per_user' => 1]);
            CocAccount::factory()->for(User::factory()->create())->forTag('#8LQ9JPY2')->verified()->create();
            ($this->open)(null, '#8LQ9JPY2');

            return '#2PQ8GRJC';
        }, DisputeRefusal::TooManyOpen->label().'.'],
    ]);

    it('sends a claimant with a running dispute over the tag to it', function () {
        $ulid = ($this->open)();

        $this->actingAs($this->claimant)->get('/disputes/create?tag=%232PQ8GRJC')->assertRedirect("/disputes/{$ulid}");
    });

    it('answers a malformed tag with a 404', function () {
        $this->actingAs($this->claimant)->get('/disputes/create?tag=nope')->assertNotFound();
        $this->actingAs($this->claimant)->get('/disputes/create')->assertNotFound();
    });

    it('opens the dispute with the claimant own images and goes to its page', function () {
        $media = screenEvidence($this->claimant, 2);

        $response = $this->actingAs($this->claimant)->post('/disputes', ['tag' => '#2PQ8GRJC', 'reason' => 'I lost my phone.', 'evidence' => $media]);

        $dispute = CocAccountDispute::query()->sole();
        $response->assertRedirect("/disputes/{$dispute->ulid}")->assertSessionHas('success');
        expect($dispute->status)->toBe(DisputeStatus::Open)
            ->and($dispute->reason)->toBe('I lost my phone.')
            ->and($dispute->evidence[0]['media'])->toBe($media)
            ->and($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed)
            ->and(Media::query()->whereIn('ulid', $media)->pluck('attachable_id')->unique()->all())->toBe([$dispute->id]);
    });

    it('validates the statement and the images', function (array $input, string $field) {
        $this->actingAs($this->claimant)->from('/disputes/create?tag=%232PQ8GRJC')
            ->post('/disputes', [...['tag' => '#2PQ8GRJC', 'reason' => 'Mine.'], ...$input])
            ->assertSessionHasErrors($field);

        expect(CocAccountDispute::query()->count())->toBe(0);
    })->with([
        'no reason' => [['reason' => ''], 'reason'],
        'reason too long' => [['reason' => str_repeat('a', 1001)], 'reason'],
        'bad tag' => [['tag' => 'nope'], 'tag'],
        'too many images' => [['evidence' => ['01J00000000000000000000001', '01J00000000000000000000002', '01J00000000000000000000003', '01J00000000000000000000004']], 'evidence'],
        'not an upload id' => [['evidence' => ['../../etc']], 'evidence.0'],
        'the same image twice' => [['evidence' => ['01J00000000000000000000001', '01J00000000000000000000001']], 'evidence.0'],
    ]);

    it('turns a refusal into a form error in the dispute words', function () {
        $this->actingAs($this->claimant)->from('/disputes/create?tag=%238LQ9JPY2')
            ->post('/disputes', ['tag' => '#8LQ9JPY2', 'reason' => 'Mine.'])
            ->assertRedirect('/disputes/create?tag=%238LQ9JPY2')
            ->assertSessionHasErrors(['reason' => 'A dispute cannot be opened for this account now. If it is yours, verify it with your in-game API token.']);
        $this->actingAs($this->claimant)->from('/disputes/create?tag=%232PQ8GRJC')
            ->post('/disputes', ['tag' => '#2PQ8GRJC', 'reason' => str_repeat('a', 10), 'evidence' => ['01J00000000000000000000001']])
            ->assertSessionHasErrors(['evidence' => 'One of the images did not upload properly. Remove it and upload it again.']);
        expect(CocAccountDispute::query()->count())->toBe(0);
    });

    it('spends a coc-attach lookup on each new tag, so the form cannot read tags in bulk', function () {
        config(['coc.accounts.attach_per_hour' => 1]);
        $conflict = ['outcome' => 'verified_elsewhere', 'tag' => '#2PQ8GRJC', 'player' => null, 'accountUlid' => null, 'holderUsername' => null, 'retryAfter' => null];

        $this->actingAs($this->claimant)->get('/disputes/create?tag=%232PQ8GRJC')->assertInertia(fn (Assert $page) => $page->where('refusal', null));
        // The same tag again is free, as in the attach flow.
        $this->actingAs($this->claimant)->withSession([AttachController::PREVIEW => $conflict])->get('/disputes/create?tag=%232PQ8GRJC')
            ->assertInertia(fn (Assert $page) => $page->where('refusal', null));
        $this->actingAs($this->claimant)->get('/disputes/create?tag=%238LQ9JPY2')
            ->assertInertia(fn (Assert $page) => $page->where('refusal', DisputeRefusal::TooManyTags->label().'.'));
    });

    it('caps accepted disputes per rolling day, not refused attempts (coc-dispute-open)', function () {
        config(['coc.disputes.open_per_day' => 2, 'coc.disputes.max_open_per_user' => 5]);
        foreach (['#8LQ9JPY2', '#Q0LVCRRY'] as $tag) {
            CocAccount::factory()->for(User::factory()->create())->forTag($tag)->verified()->create();
        }
        // A refused attempt does not use one up.
        expect(($this->disputes)()->open($this->claimant, PlayerTag::from('#2YV0JR2Q'), 'Mine.')->refusal)->toBe(DisputeRefusal::NotHeld);
        ($this->open)(null, '#8LQ9JPY2');
        ($this->open)(null, '#Q0LVCRRY');

        expect(($this->disputes)()->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'Mine.')->refusal)->toBe(DisputeRefusal::TooManyToday);

        Date::setTestNow(now()->addDay()->addMinute());
        expect(($this->disputes)()->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'Mine.')->status)->toBe(DisputeStatus::Open);
    });

    it('counts a withdrawal soon after opening toward the denials bar, and a later one not', function () {
        config(['coc.disputes.bar_after_denials' => 1]);
        $ulid = ($this->open)();
        Date::setTestNow(now()->addHours((int) config('coc.disputes.early_withdraw_hours'))->subMinute());
        ($this->disputes)()->withdraw($this->claimant, $ulid);

        CocAccount::factory()->for(User::factory()->create())->forTag('#8LQ9JPY2')->verified()->create();
        expect(($this->disputes)()->open($this->claimant, PlayerTag::from('#8LQ9JPY2'), 'Mine.')->refusal)->toBe(DisputeRefusal::Barred);

        $other = User::factory()->create();
        $late = ($this->open)($other, '#8LQ9JPY2');
        Date::setTestNow(now()->addHours((int) config('coc.disputes.early_withdraw_hours'))->addMinute());
        ($this->disputes)()->withdraw($other, $late);
        CocAccount::factory()->for(User::factory()->create())->forTag('#Q0LVCRRY')->verified()->create();
        expect(($this->disputes)()->open($other, PlayerTag::from('#Q0LVCRRY'), 'Mine.')->status)->toBe(DisputeStatus::Open);
    });
});

describe('the dispute page', function () {
    it('shows the claimant their own claim and images, the wait on the holder, and withdraw', function () {
        $media = screenEvidence($this->claimant);
        withThumb($media[0]);
        $ulid = (string) ($this->disputes)()->open($this->claimant, PlayerTag::from('#2PQ8GRJC'), 'I lost the phone.', $media)->disputeUlid;

        $this->actingAs($this->claimant)->get("/disputes/{$ulid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Disputes/Show')
                ->where('dispute.ulid', $ulid)
                ->where('dispute.tag', '#2PQ8GRJC')
                ->where('dispute.role', 'claimant')
                ->where('dispute.status', 'open')
                ->where('dispute.statusLabel', DisputeStatus::Open->label())
                ->where('dispute.waitingOn', 'holder')
                ->where('dispute.deadline', null)
                ->where('dispute.outcome', null)
                ->where('dispute.accountUlid', null)
                ->where('dispute.canWithdraw', true)
                ->where('dispute.canRespond', false)
                ->where('dispute.canRelease', false)
                ->where('dispute.canVerify', false)
                ->where('dispute.withdrawCountsTowardBar', true)
                ->where('dispute.evidenceLeft', config('coc.disputes.evidence_max') - 1)
                ->has('dispute.submissions', 2)
                ->where('dispute.submissions.0.opening', true)
                ->where('dispute.submissions.0.note', 'I lost the phone.')
                ->where('dispute.submissions.1.images.0.ulid', $media[0])
                ->where('dispute.submissions.1.images.0.url', null)
                ->where('dispute.submissions.1.images.0.thumbUrl', fn (?string $url) => is_string($url) && str_contains($url, 'thumb.webp')));
    });

    it('shows the holder the token path, their answer form, giving up and their deadline', function () {
        $ulid = ($this->open)();

        $this->actingAs($this->holder)->get("/disputes/{$ulid}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('dispute.role', 'holder')
                ->where('dispute.waitingOn', 'holder')
                ->where('dispute.deadline', now()->addDays((int) config('coc.disputes.holder_response_days'))->toIso8601String())
                ->where('dispute.accountUlid', $this->held->ulid)
                ->where('dispute.canRespond', true)
                ->where('dispute.canRelease', true)
                ->where('dispute.canVerify', true)
                ->where('dispute.canWithdraw', false)
                ->where('dispute.withdrawCountsTowardBar', false)
                ->has('dispute.submissions', 0));
    });

    it('lets the claimant answer only when the admins ask them, with their own deadline', function () {
        $ulid = ($this->open)();
        ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::AskClaimant, 'Send a receipt.');

        $this->actingAs($this->claimant)->get("/disputes/{$ulid}")
            ->assertInertia(fn (Assert $page) => $page->where('dispute.waitingOn', 'claimant')
                ->where('dispute.canRespond', true)
                ->where('dispute.canWithdraw', false)
                ->where('dispute.deadline', now()->addDays((int) config('coc.disputes.claimant_inactive_days'))->toIso8601String()));
    });

    it('shows each party how it ended', function (Closure $end, string $claimantOutcome, string $holderOutcome) {
        $ulid = ($this->open)();
        $end->call($this, $ulid);

        $this->actingAs($this->claimant)->get("/disputes/{$ulid}")->assertInertia(fn (Assert $page) => $page->where('dispute.outcome', $claimantOutcome)
            ->where('dispute.outcomeLabel', fn (string $label) => $label !== '')
            ->where('dispute.canRespond', false)->where('dispute.canWithdraw', false)->where('dispute.closedAt', now()->toIso8601String()));
        $this->actingAs($this->holder)->get("/disputes/{$ulid}")->assertInertia(fn (Assert $page) => $page->where('dispute.outcome', $holderOutcome)
            ->where('dispute.canRelease', false)->where('dispute.canVerify', false));
    })->with([
        'admin transfer' => [function (string $ulid) {
            ($this->disputes)()->respond($this->holder, $ulid, 'Mine.');
            ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Transfer, 'Receipt checks out.');
        }, 'transferred_to_you', 'transferred_away'],
        'release' => [fn (string $ulid) => ($this->disputes)()->release($this->holder, $ulid, 'password'), 'released_to_you', 'released_by_you'],
        'admin deny' => [fn (string $ulid) => ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'No proof.'), 'denied', 'kept'],
        'withdrawn' => [fn (string $ulid) => ($this->disputes)()->withdraw($this->claimant, $ulid), 'withdrawn_by_you', 'withdrawn'],
    ]);

    it('gives the winner a link to their account', function () {
        $ulid = ($this->open)();
        ($this->disputes)()->release($this->holder, $ulid, 'password');
        $own = CocAccount::query()->where('user_id', $this->claimant->id)->sole();

        $this->actingAs($this->claimant)->get("/disputes/{$ulid}")->assertInertia(fn (Assert $page) => $page->where('dispute.accountUlid', $own->ulid));
    });

    it('stays within the query budget', function () {
        $ulid = ($this->open)();
        $this->actingAs($this->holder);
        DB::enableQueryLog();

        $this->get("/disputes/{$ulid}")->assertOk();

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
    });
});

describe('acting on the dispute page', function () {
    it('takes the holder answer with images and sends it to the admins', function () {
        $ulid = ($this->open)();
        $media = screenEvidence($this->holder);

        $this->actingAs($this->holder)->from("/disputes/{$ulid}")
            ->post("/disputes/{$ulid}/respond", ['statement' => 'I play it every day.', 'evidence' => $media])
            ->assertRedirect("/disputes/{$ulid}")->assertSessionHas('success');

        $dispute = CocAccountDispute::query()->sole();
        expect($dispute->status)->toBe(DisputeStatus::AwaitingAdmin)
            ->and($dispute->evidence[0])->toMatchArray(['party' => 'holder', 'note' => 'I play it every day.', 'media' => $media]);
    });

    it('refuses an answer out of turn as a form error', function () {
        $ulid = ($this->open)();

        $this->actingAs($this->claimant)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/respond", ['statement' => 'More.'])
            ->assertSessionHasErrors(['statement' => DisputeRefusal::NotYourTurn->label().'.']);
        $this->actingAs($this->holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/respond", ['statement' => ''])
            ->assertSessionHasErrors('statement');
    });

    it('lets the claimant withdraw while the holder has not answered', function () {
        $ulid = ($this->open)();

        $this->actingAs($this->claimant)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/withdraw")
            ->assertRedirect("/disputes/{$ulid}")->assertSessionHas('success');

        expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::Withdrawn);
    });

    it('explains a late withdrawal instead of doing it', function () {
        $ulid = ($this->open)();
        ($this->disputes)()->respond($this->holder, $ulid, 'Mine.');

        $this->actingAs($this->claimant)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/withdraw")
            ->assertSessionHas('error', DisputeRefusal::NotYourTurn->label().'.');
    });

    it('lets a restricted holder answer: an account write (specs/04 §3)', function () {
        $holder = User::factory()->restricted()->create();
        CocAccount::factory()->for($holder)->forTag('#8LQ9JPY2')->verified()->create();
        $ulid = ($this->open)(null, '#8LQ9JPY2');

        $this->actingAs($holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/respond", ['statement' => 'Mine.'])->assertSessionHasNoErrors();

        expect(CocAccountDispute::query()->where('ulid', $ulid)->sole()->status)->toBe(DisputeStatus::AwaitingAdmin);
    });

    it('answers a stale page acting on a closed dispute with "closed"', function () {
        $ulid = ($this->open)();
        ($this->disputes)()->decide($this->admin, $ulid, DisputeDecision::Deny, 'No proof.');
        $closed = DisputeRefusal::Closed->label().'.';

        $this->actingAs($this->holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/respond", ['statement' => 'Mine.'])
            ->assertSessionHasErrors(['statement' => $closed]);
        $this->actingAs($this->holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/release", ['current_password' => 'password'])
            ->assertSessionHasErrors(['current_password' => $closed]);
        $this->actingAs($this->claimant)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/withdraw")->assertSessionHas('error', $closed);

        expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::ResolvedDenied)
            ->and($this->held->refresh()->user_id)->toBe($this->holder->id);
    });

    it('gives the account up only with the current password', function () {
        $ulid = ($this->open)();

        $this->actingAs($this->holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/release", ['current_password' => 'wrong'])
            ->assertSessionHasErrors(['current_password' => 'That is not your current password.']);
        $this->actingAs($this->holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/release", [])
            ->assertSessionHasErrors('current_password');
        expect($this->held->refresh()->status)->toBe(CocAccountStatus::Disputed);

        $this->actingAs($this->holder)->from("/disputes/{$ulid}")->post("/disputes/{$ulid}/release", ['current_password' => 'password'])
            ->assertRedirect("/disputes/{$ulid}")->assertSessionHas('success');

        expect(CocAccountDispute::query()->sole()->status)->toBe(DisputeStatus::ResolvedTransfer)
            ->and(CocAccount::query()->where('user_id', $this->claimant->id)->sole()->status)->toBe(CocAccountStatus::Verified);
    });
});

describe('links to the dispute', function () {
    it('links the holder account page to the running dispute, for the holder only', function () {
        $ulid = ($this->open)();

        $this->actingAs($this->holder)->get("/accounts/{$this->held->ulid}")->assertInertia(fn (Assert $page) => $page->where('account.disputeUlid', $ulid));
        $this->actingAs($this->claimant)->get("/accounts/{$this->held->ulid}")->assertInertia(fn (Assert $page) => $page->where('account.disputeUlid', null));
    });

    it('opens the token page for the holder of a disputed row (specs/13 §5 3a)', function () {
        ($this->open)();

        $this->actingAs($this->holder)->get("/accounts/{$this->held->ulid}/verify")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Accounts/Verify')->where('account.status', 'disputed'));
    });
});

it('reads the dispute screen limits from config (specs/04 §4)', function () {
    expect(config('coc.disputes'))->toMatchArray(['open_per_day' => 3, 'write_per_hour' => 10, 'early_withdraw_hours' => 24]);
});
