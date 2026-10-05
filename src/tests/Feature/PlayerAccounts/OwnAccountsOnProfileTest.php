<?php

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Notifications\CocAccountTakenOverNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// specs/18 §6 "Player profile": the own Accounts tab lists the owner's rows until PlayerCards
// (P2-04), with the attach CTA when there are none (owner decision 2026-10-02, P2-11).

beforeEach(function () {
    $this->owner = User::factory()->create(['username' => 'chief']);
});

it('gives the owner an empty list, so the tab shows the attach CTA', function () {
    $this->actingAs($this->owner)->get('/u/chief')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profile/Show')->where('ownAccounts', []));
});

it("lists the owner's rows, featured first, without released ones", function () {
    $older = CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->create(['ign' => 'Old Chief']);
    $featured = CocAccount::factory()->for($this->owner)->forTag('#GRJ0P8UV')->verified()->featured()->create(['ign' => 'Main']);
    CocAccount::factory()->for($this->owner)->forTag('#LQ2RJ9P0')->create(['status' => CocAccountStatus::Released]);

    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->has('ownAccounts', 2)
        ->where('ownAccounts.0', ['ulid' => $featured->ulid, 'tag' => '#GRJ0P8UV', 'name' => 'Main', 'status' => 'verified', 'statusLabel' => 'Verified', 'townHallLevel' => $featured->th_level, 'featured' => true, 'canFeature' => false])
        ->where('ownAccounts.1.ulid', $older->ulid)
        ->where('ownAccounts.1.status', 'unverified'));
});

it('labels a suspended row as suspended, not unverified', function () {
    CocAccount::factory()->for($this->owner)->forTag('#2PQ8GRJC')->create(['status' => CocAccountStatus::Suspended]);

    $this->actingAs($this->owner)->get('/u/chief')->assertInertia(fn (Assert $page) => $page
        ->where('ownAccounts.0.status', 'suspended')->where('ownAccounts.0.statusLabel', 'Suspended'));
});

it('keeps the own profile within the query budget', function () {
    CocAccount::factory()->for($this->owner)->count(3)->sequence(['tag' => '#2PQ8GRJC', 'tag_normalized' => '2PQ8GRJC'], ['tag' => '#GRJ0P8UV', 'tag_normalized' => 'GRJ0P8UV'], ['tag' => '#LQ2RJ9P0', 'tag_normalized' => 'LQ2RJ9P0'])->create();
    $this->actingAs($this->owner)->get('/u/chief');

    DB::enableQueryLog();
    $this->get('/u/chief')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
});

it('links the takeover notice to the attach page with the tag filled in', function () {
    expect(NotificationType::CocAccountTakenOver->render(['tag' => '#2PQ8GRJC'])->url)->toBe('/accounts/attach?tag=%232PQ8GRJC')
        ->and(NotificationType::CocAccountTakenOver->render([])->url)->toBeNull();

    $mail = (new CocAccountTakenOverNotification(['tag' => '#2PQ8GRJC', 'method' => 'api_token']))->toMail($this->owner);
    expect($mail->actionText)->toBe('Verify it again')
        ->and($mail->actionUrl)->toBe(url('/accounts/attach?tag=%232PQ8GRJC'));
});
