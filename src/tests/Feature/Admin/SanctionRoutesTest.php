<?php

use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Moderation\Models\UserSanction;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

// FR-ADMIN-3 through the admin user detail: suspend, ban and lift, with field errors and refusals.

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create(['username' => 'warden']);
    $this->target = User::factory()->create(['username' => 'chief']);
});

function suspendPayload(array $overrides = []): array
{
    return [
        'reason_code' => 'harassment',
        'days' => 7,
        'public_reason' => 'Harassment in comments',
        'internal_note' => 'Three reports, two warnings.',
        ...$overrides,
    ];
}

it('suspends, bans and lifts from the detail page', function () {
    $detail = '/admin/users/'.$this->target->ulid;

    $this->actingAs($this->admin)->from($detail)->post($detail.'/suspension', suspendPayload())
        ->assertRedirect($detail)->assertSessionHasNoErrors();
    expect($this->target->fresh()->status)->toBe(UserStatus::Suspended);

    $this->actingAs($this->admin)->from($detail)->delete($detail.'/sanction', ['note' => 'Appeal accepted.'])
        ->assertRedirect($detail)->assertSessionHasNoErrors();
    expect($this->target->fresh()->status)->toBe(UserStatus::Active);

    $this->actingAs($this->admin)->from($detail)->post($detail.'/ban', suspendPayload(['days' => null]))
        ->assertRedirect($detail)->assertSessionHasNoErrors();
    expect($this->target->fresh()->status)->toBe(UserStatus::Banned);
});

it('shows the sanctions, abilities and form options on the detail', function () {
    $this->actingAs($this->admin)->post('/admin/users/'.$this->target->ulid.'/suspension', suspendPayload());

    $this->actingAs($this->admin)->get('/admin/users/'.$this->target->ulid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('sanctions', ['suspend' => true, 'ban' => true, 'lift' => true, 'activeType' => 'suspension'])
            ->where('sanctionHistory.0.typeLabel', 'Suspension')
            ->where('sanctionHistory.0.publicReason', 'Harassment in comments')
            ->where('sanctionHistory.0.internalNote', 'Three reports, two warnings.')
            ->where('sanctionHistory.0.issuedBy', 'warden')
            ->where('sanctionHistory.0.state', 'active')
            ->where('sanctionForm.maxDays', 90)
            ->where('sanctionForm.reasons.0', ['value' => 'account_trading', 'label' => 'Account trading'])
            ->where('user.statusLabel', 'Suspended'));
});

it('rejects invalid input with field errors', function (string $route, array $payload, string $field) {
    $detail = '/admin/users/'.$this->target->ulid;

    $this->actingAs($this->admin)->from($detail)->{$route === 'sanction' ? 'delete' : 'post'}($detail.'/'.$route, $payload)
        ->assertRedirect($detail)
        ->assertSessionHasErrors($field);

    expect(UserSanction::query()->count())->toBe(0);
})->with([
    'missing reason' => ['suspension', suspendPayload(['reason_code' => null]), 'reason_code'],
    'unknown reason' => ['suspension', suspendPayload(['reason_code' => 'rude']), 'reason_code'],
    'zero days' => ['suspension', suspendPayload(['days' => 0]), 'days'],
    'too many days' => ['suspension', suspendPayload(['days' => 91]), 'days'],
    'missing days' => ['suspension', suspendPayload(['days' => null]), 'days'],
    'missing message' => ['ban', suspendPayload(['public_reason' => '']), 'public_reason'],
    'long message' => ['ban', suspendPayload(['public_reason' => str_repeat('a', 256)]), 'public_reason'],
    'missing note' => ['ban', suspendPayload(['internal_note' => '']), 'internal_note'],
    'invalid UTF-8 note' => ['ban', suspendPayload(['internal_note' => "a\xC3\x28"]), 'internal_note'],
    'missing lift note' => ['sanction', [], 'note'],
]);

it('returns a refusal as an error, not a 500', function () {
    $banned = User::factory()->banned()->create();
    $detail = '/admin/users/'.$banned->ulid;

    $this->actingAs($this->admin)->from($detail)->post($detail.'/suspension', suspendPayload())
        ->assertRedirect($detail)
        ->assertSessionHasErrors(['sanction' => 'This account is already banned. Lift the ban first.']);

    $this->actingAs($this->admin)->from('/admin/users/'.$this->target->ulid)->delete('/admin/users/'.$this->target->ulid.'/sanction', ['note' => 'Nothing.'])
        ->assertSessionHasErrors(['sanction' => 'This account has no active suspension or ban to lift.']);
});

it('404s accounts the viewer cannot see: unknown, their own, super admins', function (string $who) {
    $ulid = match ($who) {
        'unknown' => '01hzzzzzzzzzzzzzzzzzzzzzzz',
        'self' => $this->admin->ulid,
        'super admin' => User::factory()->superAdmin()->create()->ulid,
    };

    $this->actingAs($this->admin)->post('/admin/users/'.$ulid.'/suspension', suspendPayload())->assertNotFound();
    $this->actingAs($this->admin)->post('/admin/users/'.$ulid.'/ban', suspendPayload())->assertNotFound();
    $this->actingAs($this->admin)->delete('/admin/users/'.$ulid.'/sanction', ['note' => 'x'])->assertNotFound();
})->with(['unknown', 'self', 'super admin']);
