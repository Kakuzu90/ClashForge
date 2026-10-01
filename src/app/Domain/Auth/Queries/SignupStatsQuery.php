<?php

namespace App\Domain\Auth\Queries;

use App\Domain\Auth\Data\SignupCountData;
use App\Domain\Auth\Data\SignupStatsData;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * New sign-ups for the admin dashboard (FR-ADMIN-5). Deleted accounts still count: the sign-up
 * happened. One query over the `users (created_at)` index.
 */
class SignupStatsQuery
{
    public function stats(): SignupStatsData
    {
        $now = CarbonImmutable::instance(Date::now());
        $since = [
            'day' => $now->subDay(),
            'week' => $now->subDays(7),
            'month' => $now->subDays(30),
        ];

        $select = [];
        $bindings = [];
        foreach ($since as $key => $from) {
            $select[] = "COUNT(CASE WHEN created_at >= ? THEN 1 END) AS {$key}_total";
            $select[] = "COUNT(CASE WHEN created_at >= ? AND email_verified_at IS NOT NULL THEN 1 END) AS {$key}_verified";
            array_push($bindings, $from, $from);
        }

        $row = User::withTrashed()
            ->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->where('created_at', '>=', $since['month'])
            ->first();

        $count = fn (string $key): SignupCountData => new SignupCountData(
            total: (int) ($row->{"{$key}_total"} ?? 0),
            verified: (int) ($row->{"{$key}_verified"} ?? 0),
        );

        return new SignupStatsData($count('day'), $count('week'), $count('month'));
    }
}
