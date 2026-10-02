<?php

use App\Domain\Auth\Services\UserStatusService;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('locks accounts in ascending id order, once each', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    DB::transaction(fn () => app(UserStatusService::class)->lockAccounts([$b->id, $a->id, $b->id]));

    // Integer keys are inlined, not bound.
    $lock = collect($queries)->first(fn ($q) => str_contains($q['sql'], 'from "users"'));
    expect($lock['sql'])->toContain("in ({$a->id}, {$b->id})")
        ->and($lock['sql'])->toContain('order by "id" asc');

    if (DB::getDriverName() === 'pgsql') {
        expect($lock['sql'])->toContain('for update');
    }
});
