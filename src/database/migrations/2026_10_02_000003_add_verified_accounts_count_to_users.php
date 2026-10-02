<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// specs/07 `users.verified_accounts_count`: denormalised, drives the verified badge. Written only by
// Auth's UserStatusService, recounted from `coc_accounts` (P2-02). No `featured_coc_account_id`:
// `coc_accounts.is_featured` is the only featured flag (owner decision 2026-10-02).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('verified_accounts_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('verified_accounts_count');
        });
    }
};
