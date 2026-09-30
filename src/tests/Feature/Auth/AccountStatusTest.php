<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

// specs/04 §1 status effects, enforced on every request (specs/23 §7).

describe('banned', function () {
    it('refuses sign-in with the right password and says why', function () {
        User::factory()->banned('Account trading')->create(['email' => 'banned@example.com', 'password' => 'a-long-password']);

        $this->from('/login')->post('/login', ['email' => 'banned@example.com', 'password' => 'a-long-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.banned_reason', ['reason' => 'Account trading'])]);

        $this->assertGuest();
    });

    it('gives a wrong password the usual answer, so the status stays private', function () {
        User::factory()->banned()->create(['email' => 'banned@example.com', 'password' => 'a-long-password']);

        $this->from('/login')->post('/login', ['email' => 'banned@example.com', 'password' => 'not-the-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    });

    it('signs out a banned account on its next request and cycles the remember token', function () {
        $user = User::factory()->banned()->create(['remember_token' => 'before-the-ban']);

        $this->actingAs($user)->get('/')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.banned')]);

        $this->assertGuest();
        expect($user->refresh()->remember_token)->not->toBe('before-the-ban');
    });

    it('signs out a banned account that comes back on its remember cookie', function () {
        $user = User::factory()->banned()->create(['remember_token' => 'before-the-ban']);
        $recaller = Auth::guard('web')->getRecallerName();

        $this->withCookie($recaller, $user->id.'|before-the-ban|'.$user->password)
            ->get('/')
            ->assertRedirect('/login')
            ->assertCookieExpired($recaller);

        $this->assertGuest();
        expect($user->refresh()->remember_token)->not->toBe('before-the-ban');
    });

    it('answers JSON requests with 401', function () {
        $this->actingAs(User::factory()->banned()->create())->postJson('/uploads/intent')->assertUnauthorized();
    });
});

describe('suspended', function () {
    it('sends every page to the notice', function () {
        $this->actingAs(User::factory()->suspended()->create())->get('/')->assertRedirect('/account/suspended');
    });

    it('shows the reason and end date on the notice', function () {
        $until = now()->addDays(5)->startOfSecond();
        $user = User::factory()->suspended($until, 'Harassment')->create();

        $this->actingAs($user)->get('/account/suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Suspended')
                ->where('status', 'suspended')
                ->where('reason', 'Harassment')
                ->where('endsAt', $until->toIso8601String())
            );
    });

    it('says so when the suspension has no end date', function () {
        $user = User::factory()->create(['status' => 'suspended', 'status_reason' => 'Harassment', 'status_expires_at' => null]);

        $this->actingAs($user)->get('/account/suspended')
            ->assertInertia(fn (Assert $page) => $page->component('Account/Suspended')->where('endsAt', null));
    });

    it('can still reach settings and notifications', function () {
        Route::middleware(['web', 'auth'])->get('/_test/settings', fn () => response('settings'))->name('settings.probe');
        Route::middleware(['web', 'auth'])->get('/_test/notifications', fn () => response('notifications'))->name('notifications.probe');
        Route::middleware(['web', 'auth'])->get('/_test/other', fn () => response('other'))->name('bases.probe');
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)->get('/_test/settings')->assertOk();
        $this->actingAs($user)->get('/_test/notifications')->assertOk();
        $this->actingAs($user)->get('/_test/other')->assertRedirect('/account/suspended');
    });

    it('can still sign out', function () {
        $this->actingAs(User::factory()->suspended()->create())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    });

    it('lifts on the next request once the end date passes', function () {
        $user = User::factory()->suspended(now()->addHour())->create();

        $this->actingAs($user)->get('/')->assertRedirect('/account/suspended');

        $this->travel(2)->hours();

        $this->get('/')->assertOk();
        $this->get('/account/suspended')->assertRedirect('/');
    });

    it('sends everyone else away from the notice', function () {
        $this->actingAs(User::factory()->create())->get('/account/suspended')->assertRedirect('/');
    });

    it('answers JSON requests with 403', function () {
        $this->actingAs(User::factory()->suspended()->create())->postJson('/uploads/intent')->assertForbidden();
    });
});

describe('write gate', function () {
    beforeEach(function () {
        Route::middleware(['web', 'auth', 'account.active'])->post('/_test/account-write', fn () => response('ok'));
        Route::middleware(['web', 'auth', 'account.active:content'])->post('/_test/content-write', fn () => response('ok'));
    });

    it('lets a restricted account make account writes but not content writes', function () {
        $user = User::factory()->restricted(now()->addDays(2)->startOfSecond(), 'Spam in comments')->create();

        $this->actingAs($user)->post('/_test/account-write')->assertOk();
        $this->actingAs($user)->post('/_test/content-write')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/WriteBlocked')
                ->where('status', 'restricted')
                ->where('reason', 'Spam in comments')
                ->has('endsAt')
            );
    });

    it('blocks every write for a pending deletion', function () {
        $user = User::factory()->pendingDeletion()->create();

        $this->actingAs($user)->post('/_test/account-write')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Account/WriteBlocked')->where('status', 'pending_deletion')->where('reason', null));
    });

    it('lets reads through for every status that is signed in', function () {
        Route::middleware(['web', 'auth', 'account.active:content'])->get('/_test/content-read', fn () => response('ok'));

        $this->actingAs(User::factory()->pendingDeletion()->create())->get('/_test/content-read')->assertOk();
    });

    it('lets an active account through both gates', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/_test/account-write')->assertOk();
        $this->actingAs($user)->post('/_test/content-write')->assertOk();
    });

    it('lets a restriction whose end has passed through', function () {
        $user = User::factory()->restricted(now()->subMinute())->create();

        $this->actingAs($user)->post('/_test/content-write')->assertOk();
    });

    it('blocks a restricted account from starting an upload', function () {
        $this->actingAs(User::factory()->restricted()->create())
            ->postJson('/uploads/intent', ['collection' => 'base_screenshot', 'filename' => 'a.jpg', 'size' => 1000, 'mime' => 'image/jpeg'])
            ->assertForbidden()
            ->assertJson(['message' => __('account.write_blocked')]);
    });
});
