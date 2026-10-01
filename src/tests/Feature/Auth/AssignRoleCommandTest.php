<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\Auth\CapturesSecurityLog;

// specs/04 §1: roles change from the console only, and a change ends the account's sessions (§4)
// and writes an audit entry (specs/11 "Broken authorization").

uses(CapturesSecurityLog::class);

beforeEach(fn () => $this->captureSecurityLog());

function sessionRowFor(User $user): void
{
    DB::table('sessions')->insert(['id' => 'sess-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
}

it('sets the role, ends the sessions, cycles the remember token and logs the change', function () {
    $user = User::factory()->create(['username' => 'chief', 'remember_token' => 'old-token']);
    sessionRowFor($user);

    $this->artisan('platform:assign-role', ['username' => 'chief', 'role' => 'admin'])->assertSuccessful();

    $user->refresh();
    expect($user->role)->toBe(Role::Admin)
        ->and($user->remember_token)->not->toBe('old-token')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($this->securityEvents())->toContain([
            'message' => 'auth.role_changed',
            'context' => ['user' => $user->ulid, 'from' => 'user', 'to' => 'admin', 'actor' => 'console'],
        ]);
});

it('writes an audit entry with the role before and after', function () {
    $user = User::factory()->moderator()->create(['username' => 'chief']);

    $this->artisan('platform:assign-role', ['username' => 'chief', 'role' => 'admin'])->assertSuccessful();

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::RoleChanged)
        ->and($entry->auditable_type)->toBe(AuditSubject::User)
        ->and($entry->auditable_id)->toBe($user->id)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->before)->toBe(['role' => 'moderator'])
        ->and($entry->after)->toBe(['role' => 'admin'])
        ->and($entry->context)->toBe(['via' => 'console'])
        ->and($entry->ip_hash)->toBeNull();
});

it('grants super admin', function () {
    User::factory()->create(['username' => 'founder']);

    $this->artisan('platform:assign-role', ['username' => 'founder', 'role' => 'super_admin'])->assertSuccessful();

    expect(User::query()->where('username', 'founder')->firstOrFail()->role)->toBe(Role::SuperAdmin);
});

it('changes nothing when the role is already set', function () {
    $user = User::factory()->moderator()->create(['username' => 'mod']);
    sessionRowFor($user);

    $this->artisan('platform:assign-role', ['username' => 'mod', 'role' => 'moderator'])->assertSuccessful();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1)
        ->and($this->securityEvents())->toBe([])
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects an unknown role and an unknown username', function () {
    User::factory()->create(['username' => 'chief']);

    $this->artisan('platform:assign-role', ['username' => 'chief', 'role' => 'owner'])->assertExitCode(2);
    $this->artisan('platform:assign-role', ['username' => 'nobody', 'role' => 'admin'])->assertFailed();

    expect(User::query()->where('username', 'chief')->firstOrFail()->role)->toBe(Role::User);
});
