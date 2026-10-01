<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\EmailPreferenceService;
use App\Domain\Notifications\Support\UnsubscribeCapability;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Notifications\EmailPreferenceInput;

it('denies reading or changing another recipients preferences for every role', function (Role $role) {
    $settings = NotificationPreference::factory()->create();
    $other = User::factory()->create(['role' => $role]);
    expect(Gate::forUser($other)->allows('view', $settings))->toBeFalse()
        ->and(Gate::forUser($other)->allows('update', $settings))->toBeFalse();
})->with(Role::cases());

it('ignores privileged fields and another account id in a preference submission', function () {
    $owner = User::factory()->create();
    $other = NotificationPreference::factory()->create();
    $this->actingAs($owner)->patch('/settings/notifications', [
        ...EmailPreferenceInput::form(false), 'user_id' => $other->user_id, 'role' => 'super_admin', 'status' => 'banned',
        'digest_frequency' => 'weekly', 'channel_prefs' => ['security' => ['in_app' => false, 'email' => false]],
    ])->assertSessionHasNoErrors();
    expect($owner->refresh()->role)->toBe(Role::User)->and($owner->status->value)->toBe('active')
        ->and($other->refresh()->non_security_email_enabled)->toBeTrue()
        ->and(NotificationPreference::query()->findOrFail($owner->id)->digest_frequency)->toBe('none');
});

it('rejects recipient and hash substitutions even with a signature from a real link', function () {
    $recipient = User::factory()->create();
    $other = User::factory()->create();
    $link = UnsubscribeCapability::urlFor($recipient);
    $foreign = str_replace($recipient->ulid, $other->ulid, $link);
    $changedHash = str_replace(UnsubscribeCapability::emailHash($recipient), str_repeat('0', 64), $link);
    foreach ([$foreign, $changedHash, preg_replace('/signature=[^&]+/', 'signature=forged', $link)] as $invalid) {
        $this->post($invalid)->assertRedirect('/notifications/unsubscribe/done');
    }
    expect(NotificationPreference::query()->count())->toBe(0);
});

it('cannot turn a signed unsubscribe capability into a preference editor', function () {
    $recipient = User::factory()->create();
    $this->patch('/settings/notifications?'.parse_url(UnsubscribeCapability::urlFor($recipient), PHP_URL_QUERY), EmailPreferenceInput::form())
        ->assertRedirect('/login');
    expect(NotificationPreference::query()->count())->toBe(0);
});

it('keeps browser CSRF on signed unsubscribe and settings writes', function () {
    $user = User::factory()->create();
    $url = UnsubscribeCapability::urlFor($user);
    $this->app['env'] = 'production';
    $this->post($url)->assertStatus(419);
    $this->actingAs($user)->patch('/settings/notifications', EmailPreferenceInput::form(false))->assertStatus(419);
    expect(NotificationPreference::query()->count())->toBe(0);
});

it('applies the global write limiter to guest unsubscribe and authenticated preferences', function () {
    config(['platform.rate_limits.global_write_per_minute' => 1]);
    $user = User::factory()->create();
    $link = UnsubscribeCapability::urlFor($user);
    $this->from($link)->post($link)->assertRedirect('/notifications/unsubscribe/done');
    $this->post($link)->assertSessionHas('error');
    $this->actingAs($user)->from('/settings/notifications')->patch('/settings/notifications', EmailPreferenceInput::form())->assertSessionHasNoErrors();
    $this->patch('/settings/notifications', EmailPreferenceInput::form(false))->assertSessionHas('error');
    expect(NotificationPreference::query()->findOrFail($user->id)->non_security_email_enabled)->toBeTrue();
});

it('invalidates unsubscribe after anonymisation without recreating cleared preferences', function () {
    $user = User::factory()->create();
    $url = UnsubscribeCapability::urlFor($user);
    $user->delete();
    $this->post($url)->assertRedirect('/notifications/unsubscribe/done');
    expect(NotificationPreference::query()->count())->toBe(0);
});

it('never exposes an address or hidden preference fields in the page object', function () {
    $user = User::factory()->create(['email' => 'private@example.test']);
    $response = $this->actingAs($user)->get('/settings/notifications');
    $response->assertOk()->assertDontSee('private@example.test')->assertDontSee('digest_frequency')->assertDontSee('in_app');
    expect(app(EmailPreferenceService::class)->read($user)->canUpdate)->toBeTrue();
});
