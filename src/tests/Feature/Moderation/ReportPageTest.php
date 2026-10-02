<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// The moderators' report queue lives outside /admin (owner decision, 2026-10-02).

it('opens the reports page for moderators and admins', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())->get('/moderation/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Moderation/Reports')
            ->where('meta.title', 'Reports')
            ->where('auth.can.viewReportQueue', true));
})->with(['moderator', 'admin', 'superAdmin']);

it('gives moderators the reports link, not the admin one', function () {
    $this->actingAs(User::factory()->moderator()->create())->get('/moderation/reports')
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.accessAdmin', false));
});

it('refuses members and guests', function () {
    $this->actingAs(User::factory()->create())->get('/moderation/reports')->assertForbidden();
    auth()->logout();
    $this->get('/moderation/reports')->assertRedirect('/login');
});

it('keeps read access for a restricted moderator and sends a suspended one to the notice', function () {
    $this->actingAs(User::factory()->moderator()->restricted()->create())->get('/moderation/reports')->assertOk();
    $this->actingAs(User::factory()->moderator()->suspended()->create())->get('/moderation/reports')->assertRedirect('/account/suspended');
});
