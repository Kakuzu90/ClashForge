<?php

use App\Models\User;
use Tests\Support\Auth\CapturesSecurityLog;

// specs/11 §2: a named limiter on search. The admin user list and audit log share
// `admin-search`, per staff member; a breach is a bare 429 (the page shows it inline) and a
// security event.

uses(CapturesSecurityLog::class);

beforeEach(function () {
    $this->captureSecurityLog();
    $this->freezeTime();
});

it('limits admin list loads per staff member', function (string $url) {
    $limit = (int) config('platform.rate_limits.admin_search_per_minute');
    $admin = User::factory()->admin()->create();

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($admin)->get($url)->assertOk();
    }

    $this->actingAs($admin)->get($url)->assertStatus(429);

    expect($this->securityEvents())->toContain(['message' => 'auth.rate_limited', 'context' => ['limiter' => 'admin-search', 'ip_hash' => $this->securityEvents()[0]['context']['ip_hash']]]);

    // Another admin has their own bucket.
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
})->with(['/admin/users', '/admin/audit']);

it('shares one bucket across the two lists', function () {
    $limit = (int) config('platform.rate_limits.admin_search_per_minute');
    $admin = User::factory()->admin()->create();

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($admin)->get($i % 2 === 0 ? '/admin/users' : '/admin/audit');
    }

    $this->actingAs($admin)->get('/admin/users')->assertStatus(429);
});

it('reads its limit from config', function () {
    expect(config('platform.rate_limits.admin_search_per_minute'))->toBe(60);
});
