<?php

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Tests\Support\Auth\FakesHibp;
use Tests\Support\Media\InteractsWithMedia;

// specs/11 "Mass assignment": `role=admin` and `status=active` posted into every endpoint that
// writes to an account change nothing. Later tasks add their endpoints here.

uses(FakesHibp::class, InteractsWithMedia::class);

it('ignores role and status on a password reset', function () {
    $this->fakeHibp();
    $user = User::factory()->restricted()->create(['email' => 'chief@example.com']);

    $this->post('/reset-password', [
        'token' => Password::createToken($user),
        'email' => 'chief@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
        'role' => 'admin',
        'status' => 'active',
    ])->assertRedirect('/login');

    $user->refresh();
    expect($user->role)->toBe(Role::User)
        ->and($user->status)->toBe(UserStatus::Restricted);
});

it('ignores role and status on sign-in', function () {
    $user = User::factory()->create(['email' => 'chief@example.com', 'password' => 'a-long-password']);

    $this->post('/login', ['email' => 'chief@example.com', 'password' => 'a-long-password', 'role' => 'admin', 'status' => 'banned']);

    expect($user->refresh()->role)->toBe(Role::User)
        ->and($user->status)->toBe(UserStatus::Active);
});

it('ignores role and status on an upload intent', function () {
    $this->fakeMediaStorage();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/uploads/intent', [
        'collection' => 'base_screenshot',
        'filename' => 'a.jpg',
        'size' => 1000,
        'mime' => 'image/jpeg',
        'role' => 'admin',
        'status' => 'active',
    ])->assertCreated();

    expect($user->refresh()->role)->toBe(Role::User);
});
