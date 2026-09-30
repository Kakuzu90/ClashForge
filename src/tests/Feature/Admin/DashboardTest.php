<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// FR-ADMIN-1: /admin is for staff only.

it('sends a guest to sign in', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('refuses a plain user', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('still opens for a restricted moderator, who keeps read access', function () {
    $this->actingAs(User::factory()->moderator()->restricted()->create())->get('/admin')->assertOk();
});

it('sends a suspended moderator to the notice', function () {
    $this->actingAs(User::factory()->moderator()->suspended()->create())->get('/admin')->assertRedirect('/account/suspended');
});

it('renders the dashboard for staff', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('meta.title', 'Admin')
            ->where('auth.can.accessAdmin', true)
        );
})->with(['moderator', 'admin', 'superAdmin']);
