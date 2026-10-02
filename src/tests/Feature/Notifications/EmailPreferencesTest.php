<?php

use App\Domain\Notifications\Data\UpdateEmailPreferencesData;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\EmailPreferenceService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Notifications\EmailPreferenceInput;

it('requires sign-in for email preferences and updates', function () {
    $this->get('/settings/notifications')->assertRedirect('/login');
    $this->patch('/settings/notifications', EmailPreferenceInput::form())->assertRedirect('/login');
});

it('renders defaults without creating a preference row on GET', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/settings/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Notifications')
            ->where('settings.emailEnabled', true)->where('settings.canUpdate', true)
            ->has('settings.categories', count(NotificationCategory::cases()))
            ->where('settings.categories.0', ['key' => 'security', 'label' => 'Security', 'enabled' => true, 'locked' => true, 'hint' => null])
            ->where('settings.categories.1', ['key' => 'ownership', 'label' => 'Accounts', 'enabled' => true, 'locked' => false, 'hint' => 'Takeover alerts are always sent.'])
            ->where('settings.categories.2.hint', null)
            ->where('settings.categories.2.enabled', true)
            ->where('settings.categories.6.enabled', false)
            ->missing('settings.digestFrequency')->missing('settings.userId')->missing('settings.email'));

    expect(NotificationPreference::query()->count())->toBe(0);
});

it('keeps the email settings page within the query budget', function () {
    $user = User::factory()->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($user)->get('/settings/notifications')->assertOk();
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(25);
    DB::disableQueryLog();
});

it('saves email controls while preserving in-app and digest choices', function () {
    $settings = NotificationPreference::factory()->create([
        'channel_prefs' => ['bases' => ['in_app' => false, 'email' => true]], 'digest_frequency' => 'weekly',
    ]);
    $user = User::query()->findOrFail($settings->user_id);
    $form = EmailPreferenceInput::form(false);
    $form['email_categories']['bases'] = false;

    $this->actingAs($user)->from('/settings/notifications')->patch('/settings/notifications', $form)
        ->assertRedirect('/settings/notifications')->assertSessionHas('success')->assertSessionHasNoErrors();

    expect($settings->refresh()->non_security_email_enabled)->toBeFalse()
        ->and($settings->channel_prefs['bases'])->toEqual(['in_app' => false, 'email' => false])
        ->and($settings->channel_prefs['security'])->toEqual(['in_app' => true, 'email' => true])
        ->and($settings->digest_frequency)->toBe('weekly');

    $this->patch('/settings/notifications', EmailPreferenceInput::form())->assertSessionHasNoErrors();
    expect($settings->refresh()->non_security_email_enabled)->toBeTrue();
});

it('allows housekeeping across readable account statuses', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $this->actingAs($user)->get('/settings/notifications')->assertOk();
    $this->from('/settings/notifications')->patch('/settings/notifications', EmailPreferenceInput::form(false))->assertSessionHasNoErrors();

    expect(NotificationPreference::query()->findOrFail($user->id)->non_security_email_enabled)->toBeFalse();
})->with(['unverified', 'restricted', 'suspended', 'pendingDeletion']);

it('validates email category allowlists and booleans', function (array $changes, string $field) {
    $this->actingAs(User::factory()->create())->from('/settings/notifications')->patch('/settings/notifications', array_replace_recursive(EmailPreferenceInput::form(), $changes))
        ->assertSessionHasErrors($field);
})->with([
    'non boolean global' => [['email_enabled' => 'sometimes'], 'email_enabled'],
    'non boolean category' => [['email_categories' => ['bases' => 'sometimes']], 'email_categories.bases'],
    'missing category' => [['email_categories' => ['bases' => null]], 'email_categories.bases'],
    'unknown category' => [['email_categories' => ['unexpected' => true]], 'email_categories'],
    'security category' => [['email_categories' => ['security' => false]], 'email_categories'],
]);

it('keeps the security category enabled regardless of the global opt-out', function () {
    $settings = NotificationPreference::factory()->unsubscribed()->create();
    $user = User::query()->findOrFail($settings->user_id);
    expect(app(EmailPreferenceService::class)->allowsEmail($user, NotificationCategory::Security))->toBeTrue();
});

it('rechecks the owner status in direct preference writes', function () {
    $user = User::factory()->create();
    User::query()->whereKey($user->id)->update(['status' => 'banned']);
    expect(fn () => app(EmailPreferenceService::class)->update($user, new UpdateEmailPreferencesData(false, [])))
        ->toThrow(AuthorizationException::class);
});
