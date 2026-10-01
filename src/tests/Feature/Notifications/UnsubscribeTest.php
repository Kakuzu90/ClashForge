<?php

use App\Domain\Auth\Services\SessionService;
use App\Domain\Notifications\Enums\UnsubscribeOutcome;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\UnsubscribeService;
use App\Domain\Notifications\Support\UnsubscribeCapability;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    $this->recipient = User::factory()->create();
    $this->link = UnsubscribeCapability::urlFor($this->recipient);
});

it('shows a scanner-safe unsubscribe page with no private props', function () {
    $this->get($this->link)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Notifications/Unsubscribe')
        ->where('outcome', 'pending')->where('confirmUrl', $this->link)
        ->where('meta.title', 'Email preferences')->missing('email')->missing('username')->missing('settings'));
    expect(NotificationPreference::query()->count())->toBe(0);
});

it('unsubscribes a guest once and returns a success page', function () {
    $this->post($this->link)->assertRedirect('/notifications/unsubscribe/done');
    $this->get('/notifications/unsubscribe/done')->assertInertia(fn (Assert $page) => $page->component('Notifications/Unsubscribe')
        ->where('outcome', 'unsubscribed')->where('confirmUrl', null));
    $this->assertGuest();

    $this->post($this->link)->assertRedirect('/notifications/unsubscribe/done');
    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('outcome', 'unsubscribed'));
    expect(NotificationPreference::query()->count())->toBe(1)
        ->and(NotificationPreference::query()->findOrFail($this->recipient->id)->non_security_email_enabled)->toBeFalse();
});

it('changes only the signed recipient when another account is signed in', function () {
    $other = User::factory()->create();
    $this->actingAs($other)->post($this->link)->assertRedirect('/notifications/unsubscribe/done');
    expect(NotificationPreference::query()->findOrFail($this->recipient->id)->non_security_email_enabled)->toBeFalse()
        ->and(NotificationPreference::query()->whereKey($other->id)->exists())->toBeFalse();
    $this->assertAuthenticatedAs($other);
});

it('lets a banned recipient unsubscribe without an account session', function () {
    $this->recipient->forceFill(['status' => 'banned'])->save();
    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('outcome', 'pending'));
    $this->post($this->link)->assertRedirect('/notifications/unsubscribe/done');
    expect(NotificationPreference::query()->findOrFail($this->recipient->id)->non_security_email_enabled)->toBeFalse();
});

it('ends a banned browser session and still lets its signed link open', function () {
    $this->recipient->forceFill(['status' => 'banned'])->save();
    $this->actingAs($this->recipient)->get($this->link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('outcome', 'pending'));
    $this->assertGuest();
});

it('invalidates expired links and links for a previous email', function (string $change) {
    if ($change === 'expired') {
        $this->travel((int) config('platform.notifications.unsubscribe_link_days') + 1)->days();
    } else {
        $this->recipient->forceFill(['email' => 'new-inbox@example.test'])->save();
    }
    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('outcome', 'invalid')->where('confirmUrl', null));
    $this->post($this->link)->assertRedirect('/notifications/unsubscribe/done');
    $this->get('/notifications/unsubscribe/done')->assertInertia(fn (Assert $page) => $page->where('outcome', 'invalid'));
    expect(NotificationPreference::query()->count())->toBe(0);
})->with(['expired', 'email changed']);

it('validates signatures in the service as well as at the HTTP boundary', function () {
    expect(app(UnsubscribeService::class)->unsubscribe($this->recipient->ulid, UnsubscribeCapability::emailHash($this->recipient), preg_replace('/signature=[^&]+/', 'signature=forged', $this->link)))
        ->toBe(UnsubscribeOutcome::Invalid);
    expect(NotificationPreference::query()->count())->toBe(0);
});

it('keeps existing category and in-app choices when unsubscribing', function () {
    $channels = ['bases' => ['in_app' => false, 'email' => true], 'social' => ['in_app' => true, 'email' => false]];
    $settings = NotificationPreference::factory()->create(['user_id' => $this->recipient->id, 'channel_prefs' => $channels, 'digest_frequency' => 'daily']);
    $this->post($this->link);
    expect($settings->refresh()->channel_prefs)->toEqual($channels)->and($settings->digest_frequency)->toBe('daily');
});

it('expires an old session without making sign-in a prerequisite for unsubscribe', function () {
    $this->actingAs($this->recipient)->withSession([
        SessionService::SIGNED_IN_AT => now()->subDays((int) config('platform.auth.absolute_session_days') + 1)->getTimestamp(),
    ])->get($this->link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('outcome', 'pending'));
    $this->assertGuest();
});

it('redirects an unprompted result page to home', function () {
    $this->get('/notifications/unsubscribe/done')->assertRedirect('/');
});
