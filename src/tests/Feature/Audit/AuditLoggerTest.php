<?php

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Exceptions\AuditLogIsImmutable;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

// specs/12 §9, specs/07 `audit_logs`: who, what, on which record, before/after, hashed IP; never edited.

function entryOn(User $target, AuditActorData $actor): AuditEntryData
{
    return new AuditEntryData(
        actor: $actor,
        action: AuditAction::RoleChanged,
        subject: AuditSubject::User,
        subjectId: $target->id,
        before: ['role' => 'user'],
        after: ['role' => 'moderator'],
        context: ['reason' => 'Trusted helper'],
    );
}

it('records a console action without request data', function () {
    Date::setTestNow('2026-10-01 12:00:00');
    $target = User::factory()->create();

    app(AuditLogger::class)->record(entryOn($target, AuditActorData::console()));

    $entry = AuditLog::query()->sole();
    expect($entry->actor_id)->toBeNull()
        ->and($entry->actor_role)->toBeNull()
        ->and($entry->action)->toBe(AuditAction::RoleChanged)
        ->and($entry->auditable_type)->toBe(AuditSubject::User)
        ->and($entry->auditable_id)->toBe($target->id)
        ->and($entry->before)->toBe(['role' => 'user'])
        ->and($entry->after)->toBe(['role' => 'moderator'])
        ->and($entry->context)->toBe(['via' => 'console', 'reason' => 'Trusted helper'])
        ->and($entry->ip_hash)->toBeNull()
        ->and($entry->user_agent)->toBeNull()
        ->and($entry->request_id)->toBeNull()
        ->and($entry->created_at->toIso8601String())->toBe('2026-10-01T12:00:00+00:00');
});

it('takes the hashed IP, user agent and request id from the current request', function () {
    $actor = User::factory()->admin()->create();
    $target = User::factory()->create();

    $request = Request::create('/admin/users', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => str_repeat('A', 300)]);
    $request->setRouteResolver(fn () => new Route('POST', '/admin/users', []));
    app()->instance('request', $request);
    Context::add('request_id', 'req-0123456789');

    app(AuditLogger::class)->record(entryOn($target, new AuditActorData(id: $actor->id, role: $actor->role->value)));

    $entry = AuditLog::query()->sole();
    $raw = (array) DB::table('audit_logs')->first();

    expect($entry->actor_id)->toBe($actor->id)
        ->and($entry->actor_role)->toBe('admin')
        ->and($entry->context)->toBe(['reason' => 'Trusted helper'])
        ->and($entry->ip_hash)->toBe(IpHash::of('203.0.113.7'))
        ->and($entry->user_agent)->toBe(str_repeat('A', 255))
        ->and($entry->request_id)->toBe('req-0123456789')
        ->and(json_encode($raw))->not->toContain('203.0.113.7');
});

it('refuses to edit or delete an entry through the model', function () {
    $entry = AuditLog::factory()->create();

    expect(fn () => $entry->forceFill(['action' => 'role.changed', 'context' => ['edited' => true]])->save())->toThrow(AuditLogIsImmutable::class)
        ->and(fn () => $entry->delete())->toThrow(AuditLogIsImmutable::class)
        ->and(AuditLog::query()->sole()->context)->toBe(['via' => 'console']);
});

// Each attempt runs in a savepoint: on Postgres a failed statement aborts the test's transaction.
it('refuses to edit or delete an entry in the database itself', function () {
    AuditLog::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['action' => 'tampered'])))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))->toThrow(QueryException::class, 'append-only')
        ->and(DB::table('audit_logs')->value('action'))->toBe('role.changed');
});

it('refuses to truncate the table on Postgres', function () {
    AuditLog::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::statement('TRUNCATE audit_logs')))->toThrow(QueryException::class, 'append-only')
        ->and(DB::table('audit_logs')->count())->toBe(1);
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'TRUNCATE is Postgres only');

it('keeps an actor account that has audit entries', function () {
    $actor = User::factory()->admin()->create();
    AuditLog::factory()->by($actor)->create();

    expect(fn () => DB::table('users')->where('id', $actor->id)->delete())->toThrow(QueryException::class);
});
